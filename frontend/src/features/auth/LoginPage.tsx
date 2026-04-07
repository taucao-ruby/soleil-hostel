import React, { useEffect, useRef, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from './AuthContext'

const FALLBACK_AUTH_ERROR = 'Đăng nhập thất bại. Vui lòng thử lại.'

// Atmospheric background for the dark left panel
const PANEL_BG_URL =
  'https://lh3.googleusercontent.com/aida-public/AB6AXuDrrZ-_kbUOiPIjqKGhwKUmHvexBu3MtwoxvXYtyfsenN141FowEmaDKmR6ddeqFZgfdQEzLQ_BTyJRThd95YQcBC5Qz-ZGAOPC8JSqpfVuOZUGp9AUqoN17xRMoyO-m7XpABSh29muTel0bub5gMzmZlq1Sqab2hdvkJDTSqM7xHyCY96lmsbaRFRC_uZdVCa8RxeLGt0FdDVSxV4KxY7IeM7QOeHgnDMjypCScDLcv-VgH4j-jYWeX2B6Qkm-i6Ka7EY3TvDfDDw'

const TRUST_ITEMS = [
  'Xác nhận đặt phòng tức thì',
  'Lịch sử booking rõ ràng',
  'Hủy phòng dễ dàng từ tài khoản',
]

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

    if (!validate()) return

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
    <section className="flex flex-col md:flex-row min-h-[calc(100vh-3.5rem)]">
      {/* ── LEFT PANEL: dark editorial identity (desktop only) ─────────────── */}
      <div className="hidden md:flex md:w-1/2 bg-[#1A1612] relative overflow-hidden flex-col justify-center px-16 xl:px-24">
        {/* Atmospheric background */}
        <div
          className="absolute inset-0 opacity-20 pointer-events-none"
          aria-hidden="true"
          style={{
            backgroundImage: `url('${PANEL_BG_URL}')`,
            backgroundSize: 'cover',
            backgroundPosition: 'center',
          }}
        />

        <div className="relative z-10 space-y-12">
          {/* Brand logotype */}
          <div>
            <p className="flex items-baseline gap-2 leading-none">
              <span className="font-serif italic text-[#C9920A] text-4xl tracking-tight">
                Soleil
              </span>
              <span
                className="text-white text-2xl font-light tracking-[0.25em] opacity-90"
                style={{ fontVariant: 'small-caps' }}
              >
                HOSTEL
              </span>
            </p>
          </div>

          {/* Headline */}
          <div className="space-y-4">
            <h2 className="font-serif italic text-5xl leading-tight text-white">
              Chào mừng trở lại
            </h2>
            <p className="text-white/70 text-lg max-w-md leading-relaxed">
              Đặt phòng, theo dõi lịch ở, quản lý tài khoản chỉ trong vài click.
            </p>
          </div>

          {/* Trust items */}
          <ul className="space-y-6 pt-4">
            {TRUST_ITEMS.map(item => (
              <li
                key={item}
                className="flex items-center gap-4 text-white/60 text-sm tracking-wide"
              >
                <svg
                  viewBox="0 0 20 20"
                  fill="currentColor"
                  className="w-5 h-5 shrink-0 text-[#C9920A]"
                  aria-hidden="true"
                >
                  <path
                    fillRule="evenodd"
                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z"
                    clipRule="evenodd"
                  />
                </svg>
                <span>{item}</span>
              </li>
            ))}
          </ul>
        </div>

        {/* Copyright */}
        <p className="absolute bottom-12 left-16 xl:left-24 text-[10px] uppercase tracking-[0.3em] text-white/30 font-medium">
          The Modern Archivist © 2024
        </p>
      </div>

      {/* ── RIGHT PANEL: auth form ─────────────────────────────────────────── */}
      <div className="flex-1 md:w-1/2 bg-[#F5EFE3] flex flex-col justify-center items-center px-6 py-12">
        {/* Mobile-only logo */}
        <div className="md:hidden mb-8 text-center">
          <p className="font-serif italic text-[#C9920A] text-3xl leading-none">Soleil</p>
          <span
            className="text-[#1C1A17] text-[11px] font-bold tracking-[0.25em] mt-1 block"
            style={{ fontVariant: 'small-caps' }}
          >
            HOSTEL
          </span>
        </div>

        {/* Auth card */}
        <div className="w-full max-w-md bg-white rounded-2xl p-8 md:p-10 shadow-[0_20px_40px_rgba(26,22,18,0.06)] border border-[#D4C4AE]/10">
          {/* Back link */}
          <Link
            to="/"
            className="inline-flex items-center gap-1.5 text-[#7C5800] text-[11px] font-bold tracking-widest uppercase mb-8 hover:opacity-70 transition-opacity focus-visible:outline-none focus-visible:underline"
          >
            ← Quay về trang chủ
          </Link>

          <header className="mb-8">
            <h1 className="font-serif italic text-3xl text-[#1C1A17] mb-2">Đăng nhập tài khoản</h1>
            <p className="text-[#504534] text-sm">
              Chưa có tài khoản?{' '}
              <Link
                to="/register"
                className="text-[#7C5800] font-bold hover:underline decoration-2 underline-offset-4 focus-visible:outline-none"
              >
                Đăng ký ngay
              </Link>
            </p>
          </header>

          {/* Error alert */}
          {errorMessage && (
            <div role="alert" className="mb-6 px-4 py-3 rounded-lg border border-red-200 bg-red-50">
              <p className="text-sm font-medium text-red-800">{errorMessage}</p>
            </div>
          )}

          <form className="space-y-6" noValidate onSubmit={handleSubmit}>
            {/* Email */}
            <div className="space-y-2">
              <label
                htmlFor="email"
                className="block text-[11px] font-bold uppercase tracking-widest text-[#504534]/80"
              >
                Địa chỉ email
              </label>
              <input
                id="email"
                name="email"
                type="email"
                autoComplete="email"
                placeholder="example@gmail.com"
                value={formData.email}
                onChange={handleChange}
                disabled={isBusy}
                aria-invalid={errors.email ? 'true' : 'false'}
                aria-describedby={errors.email ? 'login-email-error' : undefined}
                className={`w-full px-4 py-3.5 bg-white border rounded-lg text-sm text-[#1C1A17] placeholder:text-[#D4C4AE] focus:outline-none focus:ring-1 focus:ring-[#7C5800] focus:border-[#7C5800] transition-all disabled:opacity-60 ${errors.email ? 'border-red-400' : 'border-[#D4C4AE]'}`}
              />
              {errors.email && (
                <p
                  id="login-email-error"
                  className="text-[11px] font-medium text-red-700 flex items-center gap-1"
                >
                  {errors.email}
                </p>
              )}
            </div>

            {/* Password */}
            <div className="space-y-2">
              <div className="flex justify-between items-end">
                <label
                  htmlFor="password"
                  className="block text-[11px] font-bold uppercase tracking-widest text-[#504534]/80"
                >
                  Mật khẩu
                </label>
              </div>
              <div className="relative">
                <input
                  id="password"
                  name="password"
                  type={showPassword ? 'text' : 'password'}
                  autoComplete="current-password"
                  placeholder="••••••••"
                  value={formData.password}
                  onChange={handleChange}
                  disabled={isBusy}
                  aria-invalid={errors.password ? 'true' : 'false'}
                  aria-describedby={errors.password ? 'login-password-error' : undefined}
                  className={`w-full px-4 pr-12 py-3.5 bg-white border rounded-lg text-sm text-[#1C1A17] placeholder:text-[#D4C4AE] focus:outline-none focus:ring-1 focus:ring-[#7C5800] focus:border-[#7C5800] transition-all disabled:opacity-60 ${errors.password ? 'border-red-400' : 'border-[#D4C4AE]'}`}
                />
                <button
                  type="button"
                  aria-label={showPassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'}
                  disabled={isBusy}
                  onClick={() => setShowPassword(c => !c)}
                  className="absolute right-4 top-1/2 -translate-y-1/2 text-[#827562] hover:text-[#7C5800] transition-colors focus-visible:outline-none"
                >
                  {showPassword ? (
                    <svg
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      strokeWidth="1.8"
                      className="w-5 h-5"
                      aria-hidden="true"
                    >
                      <path strokeLinecap="round" strokeLinejoin="round" d="M3 3l18 18" />
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        d="M10.58 10.58a2 2 0 102.83 2.83"
                      />
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        d="M9.88 5.09A10.94 10.94 0 0112 4.91c4.85 0 8.93 3.04 10.5 7.09a11.82 11.82 0 01-4.04 5.27"
                      />
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        d="M6.61 6.61A11.86 11.86 0 001.5 12c1.57 4.05 5.65 7.09 10.5 7.09 1.77 0 3.44-.4 4.89-1.1"
                      />
                    </svg>
                  ) : (
                    <svg
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      strokeWidth="1.8"
                      className="w-5 h-5"
                      aria-hidden="true"
                    >
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        d="M1.5 12C3.07 7.95 7.15 4.91 12 4.91S20.93 7.95 22.5 12C20.93 16.05 16.85 19.09 12 19.09S3.07 16.05 1.5 12z"
                      />
                      <circle cx="12" cy="12" r="3.25" />
                    </svg>
                  )}
                </button>
              </div>
              {errors.password && (
                <p id="login-password-error" className="text-[11px] font-medium text-red-700">
                  {errors.password}
                </p>
              )}
            </div>

            {/* Remember me */}
            <div className="flex items-center gap-3">
              <input
                id="rememberMe"
                name="rememberMe"
                type="checkbox"
                checked={formData.rememberMe}
                onChange={handleChange}
                disabled={isBusy}
                className="w-4 h-4 rounded border-[#D4C4AE] text-[#7C5800] focus:ring-[#7C5800]/20"
              />
              <label
                htmlFor="rememberMe"
                className="text-xs text-[#504534] font-medium select-none cursor-pointer"
              >
                Ghi nhớ đăng nhập trong 30 ngày
              </label>
            </div>

            {/* CTA */}
            <button
              type="submit"
              disabled={isBusy}
              aria-busy={isBusy}
              className="w-full py-4 rounded-lg font-bold text-xs tracking-[0.2em] uppercase text-white shadow-lg transition-all hover:scale-[1.02] active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60"
              style={{ background: 'linear-gradient(135deg, #C9920A 0%, #A87808 100%)' }}
            >
              {isBusy ? (
                <span className="flex items-center justify-center gap-2">
                  <svg
                    aria-hidden="true"
                    className="w-4 h-4 animate-spin"
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

          {/* Divider */}
          <div className="relative my-8">
            <div className="absolute inset-0 flex items-center">
              <div className="w-full border-t border-[#D4C4AE]/30" />
            </div>
            <div className="relative flex justify-center">
              <span className="px-4 bg-white text-[10px] font-bold uppercase tracking-[0.2em] text-[#827562]">
                hoặc
              </span>
            </div>
          </div>
        </div>

        {/* Footer links */}
        <footer className="mt-10 flex gap-6">
          <Link
            to="/privacy"
            className="text-[10px] font-bold uppercase tracking-widest text-[#504534]/50 hover:text-[#7C5800] transition-colors focus-visible:outline-none"
          >
            Chính sách bảo mật
          </Link>
          <Link
            to="/terms"
            className="text-[10px] font-bold uppercase tracking-widest text-[#504534]/50 hover:text-[#7C5800] transition-colors focus-visible:outline-none"
          >
            Điều khoản
          </Link>
          <Link
            to="/support"
            className="text-[10px] font-bold uppercase tracking-widest text-[#504534]/50 hover:text-[#7C5800] transition-colors focus-visible:outline-none"
          >
            Hỗ trợ
          </Link>
        </footer>
      </div>
    </section>
  )
}

export default LoginPage
