import api from '@/shared/lib/api'
import { setCsrfToken } from '@/shared/utils/csrf'

/**
 * Auth API Service
 *
 * All authentication-related API calls using the shared api instance.
 * Handles httpOnly cookie authentication with CSRF protection.
 */

export interface User {
  id: number
  name: string
  email: string
}

export interface LoginRequest {
  email: string
  password: string
  remember_me?: boolean
}

export interface RegisterRequest {
  name: string
  email: string
  password: string
  password_confirmation: string
}

export interface AuthResponse {
  user: User
  csrf_token: string
}

/**
 * Login with httpOnly cookie
 *
 * POST /auth/login-httponly
 * Returns user data + csrf_token
 * Browser automatically stores httpOnly cookie
 */
export async function loginHttpOnly(data: LoginRequest): Promise<AuthResponse> {
  const response = await api.post<AuthResponse>('/auth/login-httponly', data)

  // Save CSRF token to sessionStorage
  if (response.data.csrf_token) {
    setCsrfToken(response.data.csrf_token)
  }

  return response.data
}

/**
 * Register new user with httpOnly cookie
 *
 * POST /auth/register-httponly
 * Creates account + sets httpOnly cookie
 */
export async function registerHttpOnly(data: RegisterRequest): Promise<AuthResponse> {
  const response = await api.post<AuthResponse>('/auth/register-httponly', data)

  // Save CSRF token to sessionStorage
  if (response.data.csrf_token) {
    setCsrfToken(response.data.csrf_token)
  }

  return response.data
}

/**
 * Logout and clear httpOnly cookie
 *
 * POST /auth/logout-httponly
 * Backend clears cookie + revokes token
 */
export async function logoutHttpOnly(): Promise<void> {
  await api.post('/auth/logout-httponly')
}

/**
 * Check current authentication status
 *
 * GET /auth/me-httponly
 * Returns current user if token is valid
 */
export async function checkAuth(): Promise<User> {
  const response = await api.get<{ user: User }>('/auth/me-httponly')
  return response.data.user
}
