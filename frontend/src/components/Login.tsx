import React, { useState } from 'react'
import { useAuth } from '../contexts/AuthContext'

interface LoginProps {
  onSuccess?: () => void
  onSwitchToRegister?: () => void
}

/**
 * Login Component - httpOnly Cookie Authentication
 *
 * ========== Flow ==========
 * 1. User fills email + password
 * 2. POST /api/auth/login-httponly
 * 3. Backend returns user + csrf_token
 * 4. Browser auto-stores token in httpOnly cookie
 * 5. CSRF token saved to sessionStorage
 * 6. Axios interceptor adds X-XSRF-TOKEN header
 * 7. XSS cannot steal token (it's in httpOnly cookie)
 */
const Login: React.FC<LoginProps> = ({ onSuccess, onSwitchToRegister }) => {
  const { loginHttpOnly, loading: authLoading, error: authError, clearError } = useAuth()

  const [form, setForm] = useState({ email: '', password: '', rememberMe: false })
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, type, checked, value } = e.target

    setForm({
      ...form,
      [name]: type === 'checkbox' ? checked : value,
    })

    if (error) setError(null)
    if (authError) clearError()
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError(null)
    setLoading(true)

    try {
      // ========== LOGIN WITH httpOnly COOKIE ==========
      await loginHttpOnly(form.email, form.password, form.rememberMe)

      // ✅ Token stored in httpOnly cookie (browser managed)
      // ✅ CSRF token saved to sessionStorage
      // ✅ Axios interceptor will auto-add X-XSRF-TOKEN header
      // ✅ XSS cannot access token

      setForm({ email: '', password: '', rememberMe: false })
      onSuccess?.()
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } }; message?: string }
      const errorMsg = error?.response?.data?.message || error?.message || 'Login failed'
      setError(errorMsg)
    } finally {
      setLoading(false)
    }
  }

  const isLoading = loading || authLoading
  const displayError = error || authError

  return (
    <section
      className="max-w-md p-6 mx-auto bg-white rounded-lg shadow-lg"
      aria-labelledby="login-heading"
    >
      <h2 id="login-heading" className="mb-6 text-2xl font-bold text-blue-600">
        Login
      </h2>

      {displayError && (
        <div
          className="p-3 mb-4 text-sm text-red-700 bg-red-100 border border-red-500 rounded-lg"
          role="alert"
          aria-live="assertive"
          id="login-error"
        >
          {displayError}
        </div>
      )}

      <form
        onSubmit={handleSubmit}
        className="space-y-4"
        aria-labelledby="login-heading"
        aria-describedby={displayError ? 'login-error' : undefined}
      >
        <div>
          <label htmlFor="email" className="block mb-1 text-sm font-medium">
            Email
          </label>
          <input
            type="email"
            id="email"
            name="email"
            value={form.email}
            onChange={handleChange}
            disabled={isLoading}
            className="w-full p-3 border-2 border-blue-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 disabled:opacity-50"
            placeholder="your@email.com"
            required
            aria-required="true"
            aria-invalid={displayError ? 'true' : 'false'}
            autoComplete="email"
          />
        </div>

        <div>
          <label htmlFor="password" className="block mb-1 text-sm font-medium">
            Password
          </label>
          <input
            type="password"
            id="password"
            name="password"
            value={form.password}
            onChange={handleChange}
            disabled={isLoading}
            className="w-full p-3 border-2 border-blue-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 disabled:opacity-50"
            placeholder="••••••••"
            required
            aria-required="true"
            aria-invalid={displayError ? 'true' : 'false'}
            autoComplete="current-password"
          />
        </div>

        <div className="flex items-center">
          <input
            type="checkbox"
            id="rememberMe"
            name="rememberMe"
            checked={form.rememberMe}
            onChange={handleChange}
            disabled={isLoading}
            className="w-4 h-4 rounded"
            aria-label="Remember me for 30 days"
            aria-describedby="rememberMe-label"
          />
          <label htmlFor="rememberMe" id="rememberMe-label" className="ml-2 text-sm font-medium">
            Remember me (30 days)
          </label>
        </div>

        <button
          type="submit"
          disabled={isLoading}
          className="w-full py-3 font-bold text-white transition bg-blue-500 rounded-lg hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed"
          aria-label={isLoading ? 'Logging in, please wait' : 'Login to your account'}
          aria-busy={isLoading}
          aria-disabled={isLoading}
        >
          {isLoading && (
            <span className="inline-block mr-2" aria-hidden="true">
              <svg
                className="inline w-5 h-5 animate-spin"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
              >
                <circle
                  className="opacity-25"
                  cx="12"
                  cy="12"
                  r="10"
                  stroke="currentColor"
                  strokeWidth="4"
                ></circle>
                <path
                  className="opacity-75"
                  fill="currentColor"
                  d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                ></path>
              </svg>
            </span>
          )}
          {isLoading ? 'Logging in...' : 'Login'}
        </button>
      </form>

      <div className="mt-4 text-sm text-center">
        <span id="register-prompt">Don't have an account? </span>
        <button
          onClick={onSwitchToRegister}
          className="font-semibold text-blue-500 hover:text-blue-700"
          aria-label="Switch to registration form"
          aria-describedby="register-prompt"
        >
          Register here
        </button>
      </div>
    </section>
  )
}

export default Login
