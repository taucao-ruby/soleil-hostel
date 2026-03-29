import axios, { AxiosError, InternalAxiosRequestConfig } from 'axios'
import { getCsrfToken, setCsrfToken } from '@/shared/utils/csrf'
import { appNavigate } from '@/shared/lib/navigation'

/**
 * Shared API Client - Single Axios Instance
 *
 * Features:
 * - HttpOnly cookie authentication (withCredentials: true)
 * - CSRF token protection (X-XSRF-TOKEN header)
 * - Automatic token refresh on 401 with retry
 * - Environment-based base URL
 */

// Read API base URL from Vite environment variable
// Default to localhost for development
if (import.meta.env.PROD && !import.meta.env.VITE_API_URL) {
  throw new Error('VITE_API_URL environment variable is required in production')
}
const BASE_URL = (import.meta.env.VITE_API_URL as string) || '/api'

// Refresh mutex - prevents multiple concurrent refresh requests
let isRefreshing = false
let failedQueue: Array<{ resolve: (v?: unknown) => void; reject: (e?: unknown) => void }> = []

function processQueue(error: unknown = null) {
  failedQueue.forEach(({ resolve, reject }) => (error ? reject(error) : resolve()))
  failedQueue = []
}

/**
 * Main API client instance
 *
 * withCredentials: true enables:
 * - Browser sends httpOnly cookies automatically
 * - CORS credentials sharing
 * - Secure token transmission
 */
const api = axios.create({
  baseURL: BASE_URL,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
  withCredentials: true, // ⚡ CRITICAL: Enable httpOnly cookie sending
})

/**
 * Request Interceptor
 *
 * Adds X-XSRF-TOKEN header on state-changing requests.
 *
 * CSRF protection model: The httpOnly soleil_token cookie is scoped
 * SameSite=Strict, which prevents cross-origin state-changing requests from
 * carrying the authentication cookie. That is the active CSRF defence.
 *
 * The X-XSRF-TOKEN header (sourced from sessionStorage) is sent for
 * defence-in-depth but is NOT currently validated server-side. It does
 * provide a supplementary XSS barrier: a cross-origin attacker cannot read
 * sessionStorage and therefore cannot forge the header value.
 *
 * Flow:
 * 1. Login returns csrf_token in response body
 * 2. Save to sessionStorage via setCsrfToken()
 * 3. This interceptor adds it as X-XSRF-TOKEN on non-GET requests
 * 4. Backend CSRF protection is provided by SameSite=Strict on the cookie
 */
api.interceptors.request.use(
  (config: InternalAxiosRequestConfig) => {
    // Only add CSRF token for state-changing requests
    if (config.method && ['post', 'put', 'patch', 'delete'].includes(config.method.toLowerCase())) {
      const csrfToken = getCsrfToken()
      if (csrfToken && config.headers) {
        config.headers['X-XSRF-TOKEN'] = csrfToken
      }
    }

    return config
  },
  error => {
    return Promise.reject(error)
  }
)

/**
 * Response Interceptor
 *
 * Handles 401 Unauthorized with automatic token refresh.
 *
 * Token Refresh Flow:
 * 1. Protected endpoint returns 401 (token expired)
 * 2. Check if already retried (prevent infinite loop)
 * 3. Call POST /auth/refresh-httponly
 * 4. Browser sends httpOnly cookie with old token
 * 5. Backend validates + issues new token in httpOnly cookie
 * 6. Backend returns new csrf_token in response body
 * 7. Update sessionStorage with new csrf_token
 * 8. Retry original request with new token
 * 9. If refresh fails → clear storage + redirect to login
 */
api.interceptors.response.use(
  response => {
    // Success - return response as-is
    return response
  },
  async (error: AxiosError) => {
    const originalRequest = error.config as InternalAxiosRequestConfig & { _retry?: boolean }

    // Only handle 401 errors once per request
    // AND only if user has csrf_token (meaning they were logged in)
    if (error.response?.status === 401 && originalRequest && !originalRequest._retry) {
      // Check if user has csrf token (indicates previous login)
      const hasCsrfToken = !!getCsrfToken()

      // If no csrf token, user was never logged in - don't try refresh
      if (!hasCsrfToken) {
        return Promise.reject(error)
      }

      // If already refreshing, queue this request
      if (isRefreshing) {
        return new Promise((resolve, reject) => {
          failedQueue.push({ resolve, reject })
        }).then(() => api(originalRequest))
      }

      originalRequest._retry = true
      isRefreshing = true

      try {
        // ========== REFRESH TOKEN ==========
        // Browser automatically sends httpOnly cookie with old token
        // Backend returns new token in httpOnly cookie + new csrf_token
        const refreshResponse = await api.post<{ csrf_token: string }>('/auth/refresh-httponly')

        // Update CSRF token from refresh response
        if (refreshResponse.data?.csrf_token) {
          setCsrfToken(refreshResponse.data.csrf_token)
        }

        // Process all queued requests
        processQueue()

        // ========== RETRY ORIGINAL REQUEST ==========
        // Browser now has new token in httpOnly cookie
        return api(originalRequest)
      } catch (refreshError) {
        // ========== REFRESH FAILED ==========
        // Token is invalid/expired/revoked - force logout
        // Note: 401 on refresh is expected when user is not logged in
        processQueue(refreshError)

        const isAxiosError = (error: unknown): error is AxiosError => {
          return (error as AxiosError).isAxiosError === true
        }

        // Only log if it's not a 401 (401 means not logged in - expected)
        if (
          import.meta.env.DEV &&
          (!isAxiosError(refreshError) || refreshError.response?.status !== 401)
        ) {
          // eslint-disable-next-line no-console
          console.error('Token refresh failed:', refreshError)
        }

        // Clear auth-related storage only (preserve user preferences, UI state, etc.)
        const AUTH_STORAGE_KEYS = ['csrf_token', 'auth_token', 'user', 'refresh_token']
        AUTH_STORAGE_KEYS.forEach(key => {
          sessionStorage.removeItem(key)
          localStorage.removeItem(key)
        })

        // Only redirect if user was trying to access protected route
        // Don't redirect on public pages (home, rooms list, etc.)
        const isPublicRoute = /^\/(rooms)?$/.test(originalRequest.url || '')
        if (typeof window !== 'undefined' && !isPublicRoute) {
          appNavigate('/login')
        }

        throw refreshError
      } finally {
        isRefreshing = false
      }
    }

    // 403 Forbidden — user authenticated but lacks permission (UX feedback only;
    // backend middleware is the actual enforcement layer)
    if (error.response?.status === 403) {
      // Lazy-import toast to avoid circular dependency
      void import('@/shared/utils/toast').then(({ showToast }) => {
        showToast.error('Bạn không có quyền thực hiện thao tác này.')
      })
      return Promise.reject(error)
    }

    // Not a 401/403 or already retried - reject error
    return Promise.reject(error)
  }
)

export { isAxiosError } from 'axios'

export default api
