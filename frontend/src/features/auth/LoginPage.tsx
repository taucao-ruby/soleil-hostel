import React, { useEffect, useRef, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from './AuthContext'

const FALLBACK_AUTH_ERROR = 'Đăng nhập thất bại. Vui lòng thử lại.'

const LoginPage: React.FC = () => {
  const navigate = useNavigate()
  const { loginHttpOnly, error: authError, clearError } = useAuth()
  const redirectTimeoutRef = useRef<number | null>(null)

  const [formData, setFormData] = useState({
    email: '',
    password: '',
    rememberMe: false,
  })
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [loading, setLoading] = useState(false)
  const [redirecting, setRedirecting] = useState(false)
  const [showPassword, setShowPassword] = useState(false)
  const [showFallbackError, setShowFallbackError] = useState(false)

  const isBusy = loading || redirecting
  const errorMessage = authError ?? (showFallbackError ? FALLBACK_AUTH_ERROR : null)

  useEffect(() => {
    return () => {
      if (redirectTimeoutRef.current !== null) {
        window.clearTimeout(redirectTimeoutRef.current)
      }
    }
  }, [])

  const validate = (): boolean => {
    const nextErrors: Record<string, string> = {}

    if (!formData.email.trim()) {
      nextErrors.email = 'Vui lòng nhập email'
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email.trim())) {
      nextErrors.email = 'Email không hợp lệ'
    }

    if (!formData.password) {
      nextErrors.password = 'Vui lòng nhập mật khẩu'
    }

    setErrors(nextErrors)
    return Object.keys(nextErrors).length === 0
  }

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault()

    clearError()
    setShowFallbackError(false)
    setRedirecting(false)

    if (redirectTimeoutRef.current !== null) {
      window.clearTimeout(redirectTimeoutRef.current)
      redirectTimeoutRef.current = null
    }

    if (!validate()) {
      return
    }

    setLoading(true)

    try {
      await loginHttpOnly(formData.email.trim(), formData.password, formData.rememberMe)
      setRedirecting(true)
      redirectTimeoutRef.current = window.setTimeout(() => {
        navigate('/dashboard')
      }, 500)
    } catch {
      setShowFallbackError(true)
    } finally {
      setLoading(false)
    }
  }

  const handleChange = (event: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value, type, checked } = event.target

    setFormData(current => ({
      ...current,
      [name]: type === 'checkbox' ? checked : value,
    }))

    if (errors[name]) {
      setErrors(current => {
        const nextErrors = { ...current }
        delete nextErrors[name]
        return nextErrors
      })
    }

    if (authError || showFallbackError) {
      clearError()
      setShowFallbackError(false)
    }
  }

  return (
    <section className="bg-hueSurface px-4 py-14 sm:px-6 sm:py-16">
      <div className="mx-auto flex min-h-[70vh] w-full max-w-sm flex-col justify-center">
        <div className="rounded-lg border border-hueBorder bg-white p-6 shadow-[0_20px_45px_rgba(28,26,23,0.08)] sm:p-8">
          <div className="mb-8">
            <p className="text-sm font-medium uppercase tracking-[0.2em] text-brandAmber">
              Soleil Hostel
            </p>
            <h1 className="mt-3 text-3xl font-medium text-hueBlack">Đăng nhập tài khoản</h1>
            <p className="mt-3 text-sm leading-6 text-hueMuted">
              Tiếp tục quản lý đặt phòng và theo dõi chuyến đi của bạn tại Soleil Hostel.
            </p>
          </div>

          {errorMessage && (
            <div className="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3" role="alert">
              <p className="text-sm font-medium text-red-800">{errorMessage}</p>
            </div>
          )}

          <form className="space-y-5" noValidate onSubmit={handleSubmit}>
            <div>
              <label htmlFor="email" className="mb-2 block text-sm font-medium text-hueBlack">
                Địa chỉ email
              </label>
              <input
                autoComplete="email"
                className={`w-full rounded-lg border bg-white px-4 py-3 text-sm text-hueBlack outline-none transition focus:border-brandAmber focus:ring-2 focus:ring-brandAmber/20 ${
                  errors.email ? 'border-red-300' : 'border-hueBorder'
                } ${isBusy ? 'cursor-not-allowed bg-stone-50 text-hueMuted' : ''}`}
                disabled={isBusy}
                id="email"
                name="email"
                onChange={handleChange}
                placeholder="Nhập email của bạn"
                type="email"
                value={formData.email}
                aria-describedby={errors.email ? 'login-email-error' : undefined}
                aria-invalid={errors.email ? 'true' : 'false'}
              />
              {errors.email && (
                <p id="login-email-error" className="mt-2 text-sm font-medium text-red-700">
                  {errors.email}
                </p>
              )}
            </div>

            <div>
              <label htmlFor="password" className="mb-2 block text-sm font-medium text-hueBlack">
                Mật khẩu
              </label>
              <div className="relative">
                <input
                  autoComplete="current-password"
                  className={`w-full rounded-lg border bg-white px-4 py-3 pr-12 text-sm text-hueBlack outline-none transition focus:border-brandAmber focus:ring-2 focus:ring-brandAmber/20 ${
                    errors.password ? 'border-red-300' : 'border-hueBorder'
                  } ${isBusy ? 'cursor-not-allowed bg-stone-50 text-hueMuted' : ''}`}
                  disabled={isBusy}
                  id="password"
                  name="password"
                  onChange={handleChange}
                  placeholder="Nhập mật khẩu"
                  type={showPassword ? 'text' : 'password'}
                  value={formData.password}
                  aria-describedby={errors.password ? 'login-password-error' : undefined}
                  aria-invalid={errors.password ? 'true' : 'false'}
                />
                <button
                  aria-label={showPassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'}
                  className="absolute right-3 top-1/2 -translate-y-1/2 text-hueMuted transition hover:text-hueBlack focus:outline-none focus:ring-2 focus:ring-brandAmber/30"
                  disabled={isBusy}
                  type="button"
                  onClick={() => setShowPassword(current => !current)}
                >
                  {showPassword ? (
                    <svg
                      aria-hidden="true"
                      className="h-5 w-5"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        d="M3 3l18 18"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth="1.8"
                      />
                      <path
                        d="M10.58 10.58a2 2 0 102.83 2.83"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth="1.8"
                      />
                      <path
                        d="M9.88 5.09A10.94 10.94 0 0112 4.91c4.85 0 8.93 3.04 10.5 7.09a11.82 11.82 0 01-4.04 5.27"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth="1.8"
                      />
                      <path
                        d="M6.61 6.61A11.86 11.86 0 001.5 12c1.57 4.05 5.65 7.09 10.5 7.09 1.77 0 3.44-.4 4.89-1.1"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth="1.8"
                      />
                    </svg>
                  ) : (
                    <svg
                      aria-hidden="true"
                      className="h-5 w-5"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        d="M1.5 12C3.07 7.95 7.15 4.91 12 4.91S20.93 7.95 22.5 12C20.93 16.05 16.85 19.09 12 19.09S3.07 16.05 1.5 12z"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth="1.8"
                      />
                      <circle cx="12" cy="12" r="3.25" strokeWidth="1.8" />
                    </svg>
                  )}
                </button>
              </div>
              {errors.password && (
                <p id="login-password-error" className="mt-2 text-sm font-medium text-red-700">
                  {errors.password}
                </p>
              )}
            </div>

            <label className="flex items-start gap-3 text-sm text-hueMuted" htmlFor="rememberMe">
              <input
                checked={formData.rememberMe}
                className="mt-0.5 h-4 w-4 rounded border-hueBorder text-brandAmber focus:ring-brandAmber"
                disabled={isBusy}
                id="rememberMe"
                name="rememberMe"
                onChange={handleChange}
                type="checkbox"
              />
              <span>Ghi nhớ đăng nhập trong 30 ngày</span>
            </label>

            <button
              aria-busy={isBusy}
              className="flex w-full items-center justify-center rounded-lg bg-brandAmber px-4 py-3 text-sm font-medium text-hueBlack transition hover:bg-[#b88933] focus:outline-none focus:ring-2 focus:ring-brandAmber/30 disabled:cursor-not-allowed disabled:bg-[#d6b173] disabled:text-hueBlack/75"
              disabled={isBusy}
              type="submit"
            >
              {isBusy ? (
                <span className="flex items-center gap-2">
                  <svg
                    aria-hidden="true"
                    className="h-4 w-4 animate-spin"
                    fill="none"
                    viewBox="0 0 24 24"
                  >
                    <circle
                      className="opacity-30"
                      cx="12"
                      cy="12"
                      r="10"
                      stroke="currentColor"
                      strokeWidth="4"
                    />
                    <path
                      className="opacity-80"
                      d="M22 12a10 10 0 00-10-10"
                      stroke="currentColor"
                      strokeLinecap="round"
                      strokeWidth="4"
                    />
                  </svg>
                  Đang đăng nhập...
                </span>
              ) : (
                'Đăng nhập'
              )}
            </button>
          </form>

          <div className="mt-6 space-y-3 text-sm">
            <p className="text-center text-hueMuted">
              Chưa có tài khoản?{' '}
              <Link
                className="font-medium text-brandAmber transition hover:text-hueBlack"
                to="/register"
              >
                Đăng ký ngay
              </Link>
            </p>
            <p className="text-center">
              <Link className="font-medium text-hueMuted transition hover:text-hueBlack" to="/">
                ← Quay về trang chủ
              </Link>
            </p>
          </div>
        </div>
      </div>
    </section>
  )
}

export default LoginPage
