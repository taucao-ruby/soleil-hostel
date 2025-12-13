import axios, { AxiosError, InternalAxiosRequestConfig } from 'axios'
import { getCsrfToken, setCsrfToken } from '@/shared/utils/csrf'

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
const BASE_URL = (import.meta.env.VITE_API_URL as string) || 'http://localhost:8000/api'

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
 * Adds X-XSRF-TOKEN header for CSRF protection on state-changing requests.
 *
 * Flow:
 * 1. Login returns csrf_token in response body
 * 2. Save to sessionStorage via setCsrfToken()
 * 3. This interceptor adds it to non-GET requests
 * 4. Backend validates CSRF token + httpOnly cookie
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

      originalRequest._retry = true

      try {
        // ========== REFRESH TOKEN ==========
        // Browser automatically sends httpOnly cookie with old token
        // Backend returns new token in httpOnly cookie + new csrf_token
        const refreshResponse = await api.post<{ csrf_token: string }>('/auth/refresh-httponly')

        // Update CSRF token from refresh response
        if (refreshResponse.data?.csrf_token) {
          setCsrfToken(refreshResponse.data.csrf_token)
        }

        // ========== RETRY ORIGINAL REQUEST ==========
        // Browser now has new token in httpOnly cookie
        return api(originalRequest)
      } catch (refreshError) {
        // ========== REFRESH FAILED ==========
        // Token is invalid/expired/revoked - force logout
        // Note: 401 on refresh is expected when user is not logged in
        const isAxiosError = (error: unknown): error is AxiosError => {
          return (error as AxiosError).isAxiosError === true
        }

        // Only log if it's not a 401 (401 means not logged in - expected)
        if (!isAxiosError(refreshError) || refreshError.response?.status !== 401) {
          console.error('Token refresh failed:', refreshError)
        }

        // Clear all auth data
        sessionStorage.clear()
        localStorage.clear()

        // Only redirect if user was trying to access protected route
        // Don't redirect on public pages (home, rooms list, etc.)
        const isPublicRoute = originalRequest.url?.match(/\/(rooms|$)/)
        if (typeof window !== 'undefined' && !isPublicRoute) {
          window.location.href = '/login'
        }

        return Promise.reject(refreshError)
      }
    }

    // Not a 401 or already retried - reject error
    return Promise.reject(error)
  }
)

export default api
