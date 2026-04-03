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
  if (!password) {
    return 0
  }

  let score = 0

  if (password.length >= 8) {
    score += 1
  }

  if (/[A-Z]/.test(password) && /[a-z]/.test(password)) {
    score += 1
  }

  if (/\d/.test(password)) {
    score += 1
  }

  if (/[!@#$%^&*()_+\-=[\]{};':"\\|,.<>/?`~]/.test(password)) {
    score += 1
  }

  return Math.max(1, score)
}

const getStrengthLabel = (strength: number) => {
  if (strength >= 4) {
    return 'Mạnh'
  }

  if (strength === 3) {
    return 'Trung bình'
  }

  if (strength <= 2) {
    return 'Yếu'
  }

  return 'Chưa nhập mật khẩu'
}

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
    if (!authError || !redirecting) {
      return
    }

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

    if (!validate()) {
      return
    }

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

    setFormData(current => ({
      ...current,
      [name]: value,
    }))

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
    <section className="px-4 bg-hueSurface py-14 sm:px-6 sm:py-16">
      <div className="mx-auto flex min-h-[70vh] w-full max-w-sm flex-col justify-center">
        <div className="rounded-lg border border-hueBorder bg-white p-6 shadow-[0_20px_45px_rgba(28,26,23,0.08)] sm:p-8">
          <div className="mb-8">
            <p className="text-sm font-medium uppercase tracking-[0.2em] text-brandAmber">
              Soleil Hostel
            </p>
            <h1 className="mt-3 text-3xl font-medium text-hueBlack">Tạo tài khoản</h1>
            <p className="mt-3 text-sm leading-6 text-hueMuted">
              Đặt phòng nhanh hơn với tài khoản Soleil
            </p>
          </div>

          {redirecting && !errorMessage && (
            <div
              className="px-4 py-3 mb-6 border rounded-lg border-emerald-200 bg-emerald-50"
              role="status"
            >
              <p className="text-sm font-medium text-emerald-800">
                Tài khoản đã được tạo! Đang chuyển hướng...
              </p>
            </div>
          )}

          {errorMessage && (
            <div className="px-4 py-3 mb-6 border border-red-200 rounded-lg bg-red-50" role="alert">
              <p className="text-sm font-medium text-red-800">{errorMessage}</p>
            </div>
          )}

          <form className="space-y-5" noValidate onSubmit={handleSubmit}>
            <div>
              <label htmlFor="name" className="block mb-2 text-sm font-medium text-hueBlack">
                Họ và tên
              </label>
              <input
                autoComplete="name"
                className={`w-full rounded-lg border bg-white px-4 py-3 text-sm text-hueBlack outline-none transition focus:border-brandAmber focus:ring-2 focus:ring-brandAmber/20 ${
                  errors.name ? 'border-red-400' : 'border-hueBorder'
                } ${isBusy ? 'cursor-not-allowed bg-stone-50 text-hueMuted' : ''}`}
                disabled={isBusy}
                id="name"
                name="name"
                onChange={handleChange}
                placeholder="Nguyễn Văn A"
                type="text"
                value={formData.name}
                aria-describedby={errors.name ? 'register-name-error' : undefined}
                aria-invalid={errors.name ? 'true' : 'false'}
              />
              {errors.name && (
                <p id="register-name-error" className="mt-2 text-sm font-medium text-red-700">
                  {errors.name}
                </p>
              )}
            </div>

            <div>
              <label htmlFor="email" className="block mb-2 text-sm font-medium text-hueBlack">
                Email
              </label>
              <input
                autoComplete="email"
                className={`w-full rounded-lg border bg-white px-4 py-3 text-sm text-hueBlack outline-none transition focus:border-brandAmber focus:ring-2 focus:ring-brandAmber/20 ${
                  errors.email ? 'border-red-400' : 'border-hueBorder'
                } ${isBusy ? 'cursor-not-allowed bg-stone-50 text-hueMuted' : ''}`}
                disabled={isBusy}
                id="email"
                name="email"
                onChange={handleChange}
                placeholder="user@example.com"
                type="email"
                value={formData.email}
                aria-describedby={errors.email ? 'register-email-error' : undefined}
                aria-invalid={errors.email ? 'true' : 'false'}
              />
              {errors.email && (
                <p id="register-email-error" className="mt-2 text-sm font-medium text-red-700">
                  {errors.email}
                </p>
              )}
            </div>

            <div>
              <label htmlFor="password" className="block mb-2 text-sm font-medium text-hueBlack">
                Mật khẩu
              </label>
              <input
                autoComplete="new-password"
                className={`w-full rounded-lg border bg-white px-4 py-3 text-sm text-hueBlack outline-none transition focus:border-brandAmber focus:ring-2 focus:ring-brandAmber/20 ${
                  errors.password ? 'border-red-400' : 'border-hueBorder'
                } ${isBusy ? 'cursor-not-allowed bg-stone-50 text-hueMuted' : ''}`}
                disabled={isBusy}
                id="password"
                name="password"
                onChange={handleChange}
                placeholder="Tối thiểu 8 ký tự"
                type="password"
                value={formData.password}
                aria-describedby={
                  errors.password
                    ? 'register-password-error register-password-strength'
                    : 'register-password-strength'
                }
                aria-invalid={errors.password ? 'true' : 'false'}
              />
              <div
                id="register-password-strength"
                className="mt-3"
                aria-live="polite"
                aria-label={`Độ mạnh mật khẩu: ${getStrengthLabel(passwordStrength)}`}
              >
                <div className="grid grid-cols-4 gap-2">
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
                        className={`block h-1 rounded-full transition ${
                          active ? activeClass : 'bg-stone-200'
                        }`}
                      />
                    )
                  })}
                </div>
                <p className="mt-2 text-xs font-medium text-hueMuted">
                  {passwordStrength ? getStrengthLabel(passwordStrength) : 'Mật khẩu chưa đủ mạnh'}
                </p>
              </div>
              {errors.password && (
                <p id="register-password-error" className="mt-2 text-sm font-medium text-red-700">
                  {errors.password}
                </p>
              )}
            </div>

            <div>
              <label
                htmlFor="passwordConfirmation"
                className="block mb-2 text-sm font-medium text-hueBlack"
              >
                Xác nhận mật khẩu
              </label>
              <input
                autoComplete="new-password"
                className={`w-full rounded-lg border bg-white px-4 py-3 text-sm text-hueBlack outline-none transition focus:border-brandAmber focus:ring-2 focus:ring-brandAmber/20 ${
                  errors.passwordConfirmation ? 'border-red-400' : 'border-hueBorder'
                } ${isBusy ? 'cursor-not-allowed bg-stone-50 text-hueMuted' : ''}`}
                disabled={isBusy}
                id="passwordConfirmation"
                name="passwordConfirmation"
                onChange={handleChange}
                placeholder="Nhập lại mật khẩu"
                type="password"
                value={formData.passwordConfirmation}
                aria-describedby={
                  errors.passwordConfirmation ? 'register-password-confirmation-error' : undefined
                }
                aria-invalid={errors.passwordConfirmation ? 'true' : 'false'}
              />
              {errors.passwordConfirmation && (
                <p
                  id="register-password-confirmation-error"
                  className="mt-2 text-sm font-medium text-red-700"
                >
                  {errors.passwordConfirmation}
                </p>
              )}
            </div>

            <button
              aria-busy={isBusy}
              className="flex w-full items-center justify-center rounded-lg bg-brandAmber px-4 py-3 text-sm font-medium text-hueBlack transition hover:bg-[#b88933] focus:outline-none focus:ring-2 focus:ring-brandAmber/30 disabled:cursor-not-allowed disabled:bg-[#d6b173] disabled:text-hueBlack/75"
              disabled={isBusy}
              type="submit"
            >
              {loading ? (
                <span className="flex items-center gap-2">
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
          </form>

          <div className="mt-6 text-sm">
            <p className="text-center text-hueMuted">
              Đã có tài khoản?{' '}
              <Link
                className="font-medium transition text-brandAmber hover:text-hueBlack"
                to="/login"
              >
                Đăng nhập
              </Link>
            </p>
          </div>
        </div>
      </div>
    </section>
  )
}

export default RegisterPage
