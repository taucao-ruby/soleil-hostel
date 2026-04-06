import React, { useEffect, useRef, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from './AuthContext'

const FALLBACK_AUTH_ERROR = 'Đăng ký thất bại. Vui lòng thử lại.'
const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
const PASSWORD_REGEX =
  /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=[\]{};':"\\|,.<>/?`~]).{8,}$/

type FormState = {
  name: string
  email: string
  password: string
  passwordConfirmation: string
}

type FormErrors = Partial<Record<keyof FormState, string>>

const validateName = (name: string) => (name.trim().length >= 2 ? '' : 'Tên cần ít nhất 2 ký tự')

const validateEmail = (email: string) =>
  EMAIL_REGEX.test(email.trim()) ? '' : 'Địa chỉ email không hợp lệ'

const validatePassword = (password: string) =>
  PASSWORD_REGEX.test(password)
    ? ''
    : 'Mật khẩu cần ít nhất 8 ký tự, 1 chữ hoa, 1 chữ thường, 1 số và 1 ký tự đặc biệt'

const validatePasswordConfirmation = (password: string, passwordConfirmation: string) =>
  passwordConfirmation && password === passwordConfirmation ? '' : 'Mật khẩu xác nhận không khớp'

const getPasswordStrength = (password: string) => {
  if (!password) return 0
  let score = 0
  if (password.length >= 8) score += 1
  if (/[A-Z]/.test(password) && /[a-z]/.test(password)) score += 1
  if (/\d/.test(password)) score += 1
  if (/[!@#$%^&*()_+\-=[\]{};':"\\|,.<>/?`~]/.test(password)) score += 1
  return Math.max(1, score)
}

const getStrengthLabel = (strength: number) => {
  if (strength >= 4) return 'Mạnh'
  if (strength === 3) return 'Trung bình'
  if (strength <= 2) return 'Yếu'
  return 'Chưa nhập mật khẩu'
}

const BENEFITS = [
  'Đặt phòng không cần thẻ tín dụng',
  'Lịch sử booking luôn được lưu',
  'Nhận ưu đãi thành viên',
]

// Atmospheric hostel background for left panel
const PANEL_IMAGE_URL =
  'https://lh3.googleusercontent.com/aida-public/AB6AXuBkfCeHo1_eLa1MGSKX7rjBTzAPdZ5PyTDDtZ52FOFl8mdKuzpBRwdvEGiw-G1mYAiJxXJ9aMHG3JggO_1AF8bzB91tLPsFVDuQEBX4Aoy-JgjllBUoHmagrhD77yxTZVANm2NdsdM-VaSE3zwqeGkR8U7Bw2VdhueUGmogp7jRvn8jy85vYN5z_xDd14zvSzYQXL08hLkf-VHLlvJosovwsO6LzTV4JDo1bW7Qh5QzUwlMOLQC4tXwU_64reUsFW9l--ejIVBLLUM'

const RegisterPage: React.FC = () => {
  const navigate = useNavigate()
  const { registerHttpOnly, error: authError, clearError } = useAuth()
  const redirectTimeoutRef = useRef<number | null>(null)

  const [formData, setFormData] = useState<FormState>({
    name: '',
    email: '',
    password: '',
    passwordConfirmation: '',
  })
  const [errors, setErrors] = useState<FormErrors>({})
  const [loading, setLoading] = useState(false)
  const [redirecting, setRedirecting] = useState(false)
  const [showFallbackError, setShowFallbackError] = useState(false)
  const [showPassword, setShowPassword] = useState(false)
  const [showPasswordConfirmation, setShowPasswordConfirmation] = useState(false)

  const passwordStrength = getPasswordStrength(formData.password)
  const isBusy = loading || redirecting
  const errorMessage = authError ?? (showFallbackError ? FALLBACK_AUTH_ERROR : null)

  useEffect(() => {
    return () => {
      if (redirectTimeoutRef.current !== null) {
        window.clearTimeout(redirectTimeoutRef.current)
      }
    }
  }, [])

  useEffect(() => {
    if (!authError || !redirecting) return
    if (redirectTimeoutRef.current !== null) {
      window.clearTimeout(redirectTimeoutRef.current)
      redirectTimeoutRef.current = null
    }
    setRedirecting(false)
  }, [authError, redirecting])

  const validate = () => {
    const nextErrors: FormErrors = {
      name: validateName(formData.name),
      email: validateEmail(formData.email),
      password: validatePassword(formData.password),
      passwordConfirmation: validatePasswordConfirmation(
        formData.password,
        formData.passwordConfirmation
      ),
    }
    const filteredErrors = Object.fromEntries(
      Object.entries(nextErrors).filter(([, value]) => Boolean(value))
    ) as FormErrors
    setErrors(filteredErrors)
    return Object.keys(filteredErrors).length === 0
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
      await registerHttpOnly(
        formData.name.trim(),
        formData.email.trim(),
        formData.password,
        formData.passwordConfirmation
      )
      setRedirecting(true)
      redirectTimeoutRef.current = window.setTimeout(() => {
        navigate('/email/verify')
      }, 1000)
    } catch {
      setShowFallbackError(true)
    } finally {
      setLoading(false)
    }
  }

  const handleChange = (event: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value } = event.target
    setFormData(current => ({ ...current, [name]: value }))
    if (errors[name as keyof FormErrors]) {
      setErrors(current => {
        const nextErrors = { ...current }
        delete nextErrors[name as keyof FormErrors]
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
      <div className="hidden md:flex md:w-1/2 bg-[#1A1612] p-16 flex-col justify-between relative overflow-hidden">
        {/* Atmospheric background texture */}
        <div className="absolute inset-0 opacity-20" aria-hidden="true">
          <img src={PANEL_IMAGE_URL} alt="" className="w-full h-full object-cover" />
        </div>

        {/* Brand logotype */}
        <div className="relative z-10">
          <p className="flex flex-col leading-none">
            <span className="font-serif italic text-[#C9920A] text-5xl tracking-tight">Soleil</span>
            <span
              className="text-white text-sm font-light tracking-[0.25em] opacity-90 mt-1"
              style={{ fontVariant: 'small-caps' }}
            >
              HOSTEL
            </span>
          </p>
        </div>

        {/* Value proposition */}
        <div className="relative z-10 max-w-md">
          <h2 className="text-white font-serif italic text-4xl leading-tight mb-4">
            Tạo tài khoản ngay
          </h2>
          <p className="text-white/70 text-[15px] leading-relaxed mb-10">
            Đặt phòng nhanh hơn với tài khoản Soleil
          </p>
          <ul className="space-y-4">
            {BENEFITS.map(benefit => (
              <li key={benefit} className="flex items-start gap-3 text-white/60 text-sm">
                <svg
                  viewBox="0 0 20 20"
                  fill="currentColor"
                  className="w-[18px] h-[18px] shrink-0 mt-0.5 text-[#C9920A]"
                  aria-hidden="true"
                >
                  <path
                    fillRule="evenodd"
                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z"
                    clipRule="evenodd"
                  />
                </svg>
                <span>{benefit}</span>
              </li>
            ))}
          </ul>
        </div>

        {/* Copyright */}
        <p className="relative z-10 text-[10px] text-white/50 tracking-widest uppercase">
          The Modern Archivist Collection © 2024
        </p>
      </div>

      {/* ── RIGHT PANEL: form ──────────────────────────────────────────────── */}
      <div className="flex-1 md:w-1/2 bg-[#FFF8F4] flex items-center justify-center p-6 md:p-12 relative">
        {/* Subtle ambient glows */}
        <div
          className="absolute top-0 right-0 w-64 h-64 rounded-full blur-[100px] -mr-32 -mt-32 pointer-events-none"
          style={{ background: 'rgba(124,88,0,0.05)' }}
          aria-hidden="true"
        />
        <div
          className="absolute bottom-0 left-0 w-64 h-64 rounded-full blur-[100px] -ml-32 -mb-32 pointer-events-none"
          style={{ background: 'rgba(124,88,0,0.05)' }}
          aria-hidden="true"
        />

        <div className="w-full max-w-md bg-white rounded-2xl p-8 shadow-[0_20px_40px_rgba(26,22,18,0.06)] relative z-10">
          {/* Nav row */}
          <div className="flex justify-between items-center mb-8">
            <Link
              to="/"
              className="text-[#C9920A] text-[13px] font-medium flex items-center gap-1 hover:gap-2 transition-all focus-visible:outline-none focus-visible:underline"
            >
              ← Trang chủ
            </Link>
            <Link
              to="/login"
              className="text-[#C9920A] text-[13px] font-medium underline underline-offset-4 focus-visible:outline-none"
            >
              Đã có tài khoản? Đăng nhập
            </Link>
          </div>

          <h1 className="font-serif italic text-[#1C1A17] text-2xl mb-8">Tạo tài khoản</h1>

          {/* Success banner */}
          {redirecting && !errorMessage && (
            <div
              role="status"
              className="mb-6 px-4 py-3 rounded-lg border border-emerald-200 bg-emerald-50"
            >
              <p className="text-sm font-medium text-emerald-800">
                Tài khoản đã được tạo! Đang chuyển hướng...
              </p>
            </div>
          )}

          {/* Error banner */}
          {errorMessage && (
            <div role="alert" className="mb-6 px-4 py-3 rounded-lg border border-red-200 bg-red-50">
              <p className="text-sm font-medium text-red-800">{errorMessage}</p>
            </div>
          )}

          <form className="space-y-4" noValidate onSubmit={handleSubmit}>
            {/* Name */}
            <div className="space-y-1.5">
              <label
                htmlFor="name"
                className="block text-[11px] font-bold text-[#827562] uppercase tracking-wider"
              >
                Họ và tên
              </label>
              <input
                id="name"
                name="name"
                type="text"
                autoComplete="name"
                placeholder="Nguyễn Văn A"
                value={formData.name}
                onChange={handleChange}
                disabled={isBusy}
                aria-invalid={errors.name ? 'true' : 'false'}
                aria-describedby={errors.name ? 'register-name-error' : undefined}
                className={`w-full h-12 px-4 bg-[#FCF2EB] border-none rounded-lg text-sm text-[#1C1A17] placeholder:text-[#827562]/50 focus:outline-none focus:ring-1 focus:ring-[#C9920A] transition disabled:opacity-60 ${errors.name ? 'ring-1 ring-red-400' : ''}`}
              />
              {errors.name && (
                <p id="register-name-error" className="text-sm font-medium text-red-700">
                  {errors.name}
                </p>
              )}
            </div>

            {/* Email */}
            <div className="space-y-1.5">
              <label
                htmlFor="email"
                className="block text-[11px] font-bold text-[#827562] uppercase tracking-wider"
              >
                Email
              </label>
              <input
                id="email"
                name="email"
                type="email"
                autoComplete="email"
                placeholder="bạn@email.com"
                value={formData.email}
                onChange={handleChange}
                disabled={isBusy}
                aria-invalid={errors.email ? 'true' : 'false'}
                aria-describedby={errors.email ? 'register-email-error' : undefined}
                className={`w-full h-12 px-4 bg-[#FCF2EB] border-none rounded-lg text-sm text-[#1C1A17] placeholder:text-[#827562]/50 focus:outline-none focus:ring-1 focus:ring-[#C9920A] transition disabled:opacity-60 ${errors.email ? 'ring-1 ring-red-400' : ''}`}
              />
              {errors.email && (
                <p id="register-email-error" className="text-sm font-medium text-red-700">
                  {errors.email}
                </p>
              )}
            </div>

            {/* Password */}
            <div className="space-y-1.5">
              <label
                htmlFor="password"
                className="block text-[11px] font-bold text-[#827562] uppercase tracking-wider"
              >
                Mật khẩu
              </label>
              <div className="relative">
                <input
                  id="password"
                  name="password"
                  type={showPassword ? 'text' : 'password'}
                  autoComplete="new-password"
                  placeholder="Tối thiểu 8 ký tự"
                  value={formData.password}
                  onChange={handleChange}
                  disabled={isBusy}
                  aria-invalid={errors.password ? 'true' : 'false'}
                  aria-describedby={
                    errors.password
                      ? 'register-password-error register-password-strength'
                      : 'register-password-strength'
                  }
                  className={`w-full h-12 pl-4 pr-10 bg-[#FCF2EB] border-none rounded-lg text-sm text-[#1C1A17] focus:outline-none focus:ring-1 focus:ring-[#C9920A] transition disabled:opacity-60 ${errors.password ? 'ring-1 ring-red-400' : ''}`}
                />
                <button
                  type="button"
                  onClick={() => setShowPassword(v => !v)}
                  aria-label={showPassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'}
                  className="absolute right-3 top-1/2 -translate-y-1/2 text-[#827562] hover:text-[#C9920A] transition-colors focus-visible:outline-none"
                >
                  {showPassword ? (
                    <svg
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      strokeWidth="2"
                      className="w-5 h-5"
                      aria-hidden="true"
                    >
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"
                      />
                    </svg>
                  ) : (
                    <svg
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      strokeWidth="2"
                      className="w-5 h-5"
                      aria-hidden="true"
                    >
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"
                      />
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                      />
                    </svg>
                  )}
                </button>
              </div>

              {/* Strength meter */}
              <div
                id="register-password-strength"
                className="pt-1"
                aria-live="polite"
                aria-label={`Độ mạnh mật khẩu: ${getStrengthLabel(passwordStrength)}`}
              >
                <div className="grid grid-cols-4 gap-1.5">
                  {[0, 1, 2, 3].map(index => {
                    const active = index < passwordStrength
                    const activeClass =
                      passwordStrength >= 4
                        ? 'bg-emerald-500'
                        : passwordStrength === 3
                          ? 'bg-amber-400'
                          : 'bg-red-500'
                    return (
                      <span
                        key={index}
                        data-testid={`password-strength-segment-${index}`}
                        className={`block h-1 rounded-full transition ${active ? activeClass : 'bg-stone-200'}`}
                      />
                    )
                  })}
                </div>
                <p className="mt-1.5 text-[10px] font-bold text-[#006c49] uppercase tracking-tighter">
                  {passwordStrength ? getStrengthLabel(passwordStrength) : ''}
                </p>
              </div>

              {errors.password && (
                <p id="register-password-error" className="text-sm font-medium text-red-700">
                  {errors.password}
                </p>
              )}
            </div>

            {/* Password confirmation */}
            <div className="space-y-1.5">
              <label
                htmlFor="passwordConfirmation"
                className="block text-[11px] font-bold text-[#827562] uppercase tracking-wider"
              >
                Xác nhận mật khẩu
              </label>
              <div className="relative">
                <input
                  id="passwordConfirmation"
                  name="passwordConfirmation"
                  type={showPasswordConfirmation ? 'text' : 'password'}
                  autoComplete="new-password"
                  placeholder="Nhập lại mật khẩu"
                  value={formData.passwordConfirmation}
                  onChange={handleChange}
                  disabled={isBusy}
                  aria-invalid={errors.passwordConfirmation ? 'true' : 'false'}
                  aria-describedby={
                    errors.passwordConfirmation ? 'register-password-confirmation-error' : undefined
                  }
                  className={`w-full h-12 pl-4 pr-10 bg-[#FCF2EB] border-none rounded-lg text-sm text-[#1C1A17] focus:outline-none focus:ring-1 focus:ring-[#C9920A] transition disabled:opacity-60 ${errors.passwordConfirmation ? 'ring-1 ring-red-400' : ''}`}
                />
                <button
                  type="button"
                  onClick={() => setShowPasswordConfirmation(v => !v)}
                  aria-label={showPasswordConfirmation ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'}
                  className="absolute right-3 top-1/2 -translate-y-1/2 text-[#827562] hover:text-[#C9920A] transition-colors focus-visible:outline-none"
                >
                  {showPasswordConfirmation ? (
                    <svg
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      strokeWidth="2"
                      className="w-5 h-5"
                      aria-hidden="true"
                    >
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"
                      />
                    </svg>
                  ) : (
                    <svg
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      strokeWidth="2"
                      className="w-5 h-5"
                      aria-hidden="true"
                    >
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"
                      />
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                      />
                    </svg>
                  )}
                </button>
              </div>
              {errors.passwordConfirmation && (
                <p
                  id="register-password-confirmation-error"
                  className="text-sm font-medium text-red-700"
                >
                  {errors.passwordConfirmation}
                </p>
              )}
            </div>

            {/* Terms (UI-only, not validated) */}
            <div className="flex items-start gap-3 py-1">
              <input
                type="checkbox"
                id="terms"
                className="mt-0.5 w-4 h-4 rounded border-[#D4C4AE] text-[#C9920A] focus:ring-[#C9920A] cursor-pointer"
              />
              <label
                htmlFor="terms"
                className="text-[13px] text-[#827562] leading-snug cursor-pointer"
              >
                Tôi đồng ý với{' '}
                <Link to="/terms" className="text-[#C9920A] font-medium hover:underline">
                  Điều khoản sử dụng
                </Link>{' '}
                và{' '}
                <Link to="/privacy" className="text-[#C9920A] font-medium hover:underline">
                  Chính sách bảo mật
                </Link>
              </label>
            </div>

            {/* CTA */}
            <button
              type="submit"
              disabled={isBusy}
              aria-busy={isBusy}
              className="w-full h-12 rounded-lg font-semibold text-[15px] tracking-wide text-white shadow-lg transition-all duration-200 hover:scale-[1.01] active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-60"
              style={{ background: 'linear-gradient(135deg, #c9920a 0%, #a87808 100%)' }}
            >
              {loading ? (
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
                  Đang tạo tài khoản...
                </span>
              ) : (
                'Tạo tài khoản'
              )}
            </button>

            {/* Divider */}
            <div className="relative flex items-center py-1">
              <div className="flex-grow border-t border-[#D4C4AE]/30" />
              <span className="flex-shrink mx-4 text-[12px] text-[#827562]/60 uppercase tracking-widest">
                hoặc
              </span>
              <div className="flex-grow border-t border-[#D4C4AE]/30" />
            </div>

            {/* Login link (mobile — duplicates the top nav row on small screens) */}
            <p className="text-center text-sm text-[#827562] md:hidden">
              Đã có tài khoản?{' '}
              <Link to="/login" className="font-medium text-[#C9920A] hover:underline">
                Đăng nhập
              </Link>
            </p>
          </form>
        </div>
      </div>
    </section>
  )
}

export default RegisterPage
