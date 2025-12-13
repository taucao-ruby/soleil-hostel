import React, { createContext, useContext, useState, useEffect, useCallback } from 'react'
import api from '@/shared/lib/api'
import { setCsrfToken, clearCsrfToken } from '@/shared/utils/csrf'

/**
 * User Interface
 */
export interface User {
  id: number
  name: string
  email: string
}

/**
 * Auth Context Type
 */
interface AuthContextType {
  user: User | null
  isAuthenticated: boolean
  loading: boolean
  error: string | null
  loginHttpOnly: (email: string, password: string, rememberMe?: boolean) => Promise<void>
  registerHttpOnly: (
    name: string,
    email: string,
    password: string,
    passwordConfirmation: string
  ) => Promise<void>
  logoutHttpOnly: () => Promise<void>
  me: () => Promise<User | null>
  clearError: () => void
}

/**
 * Auth Context
 */
const AuthContext = createContext<AuthContextType | undefined>(undefined)

/**
 * AuthProvider - Manages authentication state with httpOnly cookies
 *
 * ========== httpOnly Cookie Authentication ==========
 * - Token stored in httpOnly cookie (XSS-safe, JavaScript cannot access)
 * - CSRF token in sessionStorage (temporary, for X-XSRF-TOKEN header)
 * - Browser automatically sends httpOnly cookie with every request
 * - Axios interceptor handles 401 with automatic token refresh + retry
 * - Logout clears both cookie + sessionStorage
 *
 * Security Benefits:
 * - XSS attacks cannot steal token (httpOnly = no JavaScript access)
 * - CSRF protection via X-XSRF-TOKEN header validation
 * - Token refresh handled transparently
 * - Secure by default
 */
export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [user, setUser] = useState<User | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  /**
   * Clear error state
   */
  const clearError = useCallback(() => {
    setError(null)
  }, [])

  /**
   * Validate token on mount by calling /me endpoint
   *
   * Flow:
   * 1. Component mounts
   * 2. Check if csrf_token exists (indicates previous login)
   * 3. If yes: Call GET /auth/me-httponly
   * 4. Browser automatically sends httpOnly cookie
   * 5. Backend validates token → returns user data
   * 6. If 401: Token expired/invalid → user stays null
   */
  useEffect(() => {
    const validateToken = async () => {
      // Only validate if we have a csrf token (indicates previous login session)
      const csrfToken = sessionStorage.getItem('csrf_token')

      if (!csrfToken) {
        // No csrf token = user never logged in
        setLoading(false)
        return
      }

      try {
        const response = await api.get<{ user: User }>('/auth/me-httponly')
        setUser(response.data.user)
        setError(null)
      } catch (err: unknown) {
        // No valid token - user not authenticated
        setUser(null)
        const error = err as { response?: { status?: number } }
        // Only log if it's not a 401 (401 is expected when token expired)
        if (error?.response?.status !== 401) {
          console.warn('Token validation failed:', error?.response?.status)
        }
        // Clear invalid csrf token
        sessionStorage.removeItem('csrf_token')
      } finally {
        setLoading(false)
      }
    }

    validateToken()
  }, [])

  /**
   * LOGIN with httpOnly Cookie
   *
   * ========== Flow ==========
   * 1. POST /api/auth/login-httponly with credentials
   * 2. Backend validates credentials
   * 3. Backend generates JWT token
   * 4. Backend sets token in httpOnly cookie (Set-Cookie header)
   * 5. Backend returns user data + csrf_token in response body
   * 6. Frontend saves csrf_token to sessionStorage
   * 7. Frontend updates user state
   *
   * Important: Browser automatically stores httpOnly cookie
   * JavaScript CANNOT access it (that's the security feature!)
   */
  const loginHttpOnly = useCallback(
    async (email: string, password: string, rememberMe: boolean = false) => {
      setLoading(true)
      setError(null)

      try {
        const response = await api.post<{ user: User; csrf_token: string }>(
          '/auth/login-httponly',
          {
            email,
            password,
            remember_me: rememberMe,
          }
        )

        // Save user to state
        setUser(response.data.user)

        // Save CSRF token to sessionStorage
        if (response.data.csrf_token) {
          setCsrfToken(response.data.csrf_token)
        }

        setError(null)
      } catch (err: unknown) {
        const error = err as { response?: { data?: { message?: string } } }
        const errorMessage = error?.response?.data?.message || 'Login failed'
        setError(errorMessage)
        throw err
      } finally {
        setLoading(false)
      }
    },
    []
  )

  /**
   * REGISTER with httpOnly Cookie
   *
   * Similar flow to login - creates account + sets httpOnly cookie
   */
  const registerHttpOnly = useCallback(
    async (name: string, email: string, password: string, passwordConfirmation: string) => {
      setLoading(true)
      setError(null)

      try {
        const response = await api.post<{ user: User; csrf_token: string }>(
          '/auth/register-httponly',
          {
            name,
            email,
            password,
            password_confirmation: passwordConfirmation,
          }
        )

        // Save user to state
        setUser(response.data.user)

        // Save CSRF token to sessionStorage
        if (response.data.csrf_token) {
          setCsrfToken(response.data.csrf_token)
        }

        setError(null)
      } catch (err: unknown) {
        const error = err as { response?: { data?: { message?: string } } }
        const errorMessage = error?.response?.data?.message || 'Registration failed'
        setError(errorMessage)
        throw err
      } finally {
        setLoading(false)
      }
    },
    []
  )

  /**
   * LOGOUT with httpOnly Cookie
   *
   * ========== Flow ==========
   * 1. POST /api/auth/logout-httponly
   * 2. Browser sends httpOnly cookie automatically
   * 3. Backend marks token as revoked/blacklisted
   * 4. Backend clears httpOnly cookie (Set-Cookie with Max-Age=0)
   * 5. Frontend clears sessionStorage (CSRF token)
   * 6. Frontend clears user state
   */
  const logoutHttpOnly = useCallback(async () => {
    setLoading(true)

    try {
      // Backend revokes token + clears cookie
      await api.post('/auth/logout-httponly')

      // Frontend cleanup
      setUser(null)
      clearCsrfToken()
      setError(null)
    } catch (err) {
      console.error('Logout error:', err)
      // Even if API call fails, clear local state
      setUser(null)
      clearCsrfToken()
    } finally {
      setLoading(false)
    }
  }, [])

  /**
   * ME - Get current user from token
   *
   * ========== Flow ==========
   * 1. GET /api/auth/me-httponly
   * 2. Browser sends httpOnly cookie automatically
   * 3. Backend validates token + returns user data
   */
  const me = useCallback(async (): Promise<User | null> => {
    try {
      const response = await api.get<{ user: User }>('/auth/me-httponly')
      setUser(response.data.user)
      return response.data.user
    } catch (err) {
      setUser(null)
      throw err
    }
  }, [])

  return (
    <AuthContext.Provider
      value={{
        user,
        isAuthenticated: !!user,
        loading,
        error,
        loginHttpOnly,
        registerHttpOnly,
        logoutHttpOnly,
        me,
        clearError,
      }}
    >
      {children}
    </AuthContext.Provider>
  )
}

/**
 * useAuth Hook
 *
 * Access auth context in any component.
 * Throws error if used outside AuthProvider.
 */
export const useAuth = () => {
  const context = useContext(AuthContext)
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider')
  }
  return context
}
