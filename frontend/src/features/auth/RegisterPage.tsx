import React, { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from './AuthContext'
/**
 * RegisterPage Component
 *
 * Full-featured registration form with:
 * - Name, email, password, password confirmation
 * - Client-side validation
 * - Loading/error/success states
 * - Redirect after successful registration
 * - Link to login
 */

const RegisterPage: React.FC = () => {
  const navigate = useNavigate()
  const { registerHttpOnly, error: authError, clearError } = useAuth()

  const [formData, setFormData] = useState({
    name: '',
    email: '',
    password: '',
    passwordConfirmation: '',
  })
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [loading, setLoading] = useState(false)
  const [success, setSuccess] = useState(false)

  /**
   * Validate form data
   */
  const validate = (): boolean => {
    const newErrors: Record<string, string> = {}

    // Name validation
    if (!formData.name.trim()) {
      newErrors.name = 'Vui lòng nhập họ tên'
    } else if (formData.name.trim().length < 2) {
      newErrors.name = 'Họ tên phải có ít nhất 2 ký tự'
    }

    // Email validation
    if (!formData.email.trim()) {
      newErrors.email = 'Vui lòng nhập email'
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
      newErrors.email = 'Email không hợp lệ'
    }

    // Password validation
    if (!formData.password) {
      newErrors.password = 'Vui lòng nhập mật khẩu'
    } else if (formData.password.length < 8) {
      newErrors.password = 'Mật khẩu phải có ít nhất 8 ký tự'
    } else if (!/(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/.test(formData.password)) {
      newErrors.password = 'Mật khẩu phải chứa chữ hoa, chữ thường và số'
    }

    // Password confirmation validation
    if (!formData.passwordConfirmation) {
      newErrors.passwordConfirmation = 'Vui lòng xác nhận mật khẩu'
    } else if (formData.password !== formData.passwordConfirmation) {
      newErrors.passwordConfirmation = 'Mật khẩu không khớp'
    }

    setErrors(newErrors)
    return Object.keys(newErrors).length === 0
  }

  /**
   * Handle form submission
   */
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    clearError()

    if (!validate()) return

    setLoading(true)
    setSuccess(false)

    try {
      await registerHttpOnly(
        formData.name.trim(),
        formData.email.trim(),
        formData.password,
        formData.passwordConfirmation
      )

      setSuccess(true)

      // Redirect to dashboard after successful registration
      setTimeout(() => {
        navigate('/dashboard')
      }, 1000)
    } catch {
      // Error is handled by AuthContext and displayed via authError
    } finally {
      setLoading(false)
    }
  }

  /**
   * Handle input changes
   */
  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value } = e.target
    setFormData(prev => ({
      ...prev,
      [name]: value,
    }))
    // Clear error for this field
    if (errors[name]) {
      setErrors(prev => ({ ...prev, [name]: '' }))
    }
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-50 via-white to-blue-50 px-4 py-12">
      <div className="max-w-md w-full">
        {/* Header */}
        <div className="text-center mb-8">
          <h1 className="text-4xl font-bold text-gray-900 mb-2">Tạo tài khoản</h1>
          <p className="text-gray-600">Tham gia cộng đồng Soleil Hostel</p>
        </div>

        {/* Registration Form Card */}
        <div className="bg-white rounded-2xl shadow-xl p-8">
          <form onSubmit={handleSubmit} noValidate>
            {/* Success Message */}
            {success && (
              <div className="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                <p className="text-green-800 text-sm font-medium">
                  ✓ Đăng ký thành công! Đang chuyển hướng...
                </p>
              </div>
            )}

            {/* Error Message */}
            {authError && (
              <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                <p className="text-red-800 text-sm font-medium">{authError}</p>
              </div>
            )}

            {/* Name Field */}
            <div className="mb-5">
              <label htmlFor="name" className="block text-sm font-semibold text-gray-700 mb-2">
                Họ và tên
              </label>
              <input
                type="text"
                id="name"
                name="name"
                value={formData.name}
                onChange={handleChange}
                disabled={loading}
                className={`w-full px-4 py-3 border rounded-lg focus:outline-none focus:ring-2 transition-colors ${
                  errors.name
                    ? 'border-red-300 focus:ring-red-500'
                    : 'border-gray-300 focus:ring-blue-500'
                } ${loading ? 'bg-gray-50 cursor-not-allowed' : 'bg-white'}`}
                placeholder="Nguyễn Văn A"
                aria-required="true"
                aria-invalid={!!errors.name}
                aria-describedby={errors.name ? 'name-error' : undefined}
              />
              {errors.name && (
                <p id="name-error" className="mt-2 text-sm text-red-600">
                  {errors.name}
                </p>
              )}
            </div>

            {/* Email Field */}
            <div className="mb-5">
              <label htmlFor="email" className="block text-sm font-semibold text-gray-700 mb-2">
                Địa chỉ email
              </label>
              <input
                type="email"
                id="email"
                name="email"
                value={formData.email}
                onChange={handleChange}
                disabled={loading}
                className={`w-full px-4 py-3 border rounded-lg focus:outline-none focus:ring-2 transition-colors ${
                  errors.email
                    ? 'border-red-300 focus:ring-red-500'
                    : 'border-gray-300 focus:ring-blue-500'
                } ${loading ? 'bg-gray-50 cursor-not-allowed' : 'bg-white'}`}
                placeholder="you@example.com"
                aria-required="true"
                aria-invalid={!!errors.email}
                aria-describedby={errors.email ? 'email-error' : undefined}
              />
              {errors.email && (
                <p id="email-error" className="mt-2 text-sm text-red-600">
                  {errors.email}
                </p>
              )}
            </div>

            {/* Password Field */}
            <div className="mb-5">
              <label htmlFor="password" className="block text-sm font-semibold text-gray-700 mb-2">
                Mật khẩu
              </label>
              <input
                type="password"
                id="password"
                name="password"
                value={formData.password}
                onChange={handleChange}
                disabled={loading}
                className={`w-full px-4 py-3 border rounded-lg focus:outline-none focus:ring-2 transition-colors ${
                  errors.password
                    ? 'border-red-300 focus:ring-red-500'
                    : 'border-gray-300 focus:ring-blue-500'
                } ${loading ? 'bg-gray-50 cursor-not-allowed' : 'bg-white'}`}
                placeholder="••••••••"
                aria-required="true"
                aria-invalid={!!errors.password}
                aria-describedby={
                  errors.password ? 'password-error password-help' : 'password-help'
                }
              />
              {errors.password && (
                <p id="password-error" className="mt-2 text-sm text-red-600">
                  {errors.password}
                </p>
              )}
              <p id="password-help" className="mt-2 text-xs text-gray-500">
                Tối thiểu 8 ký tự, bao gồm chữ hoa, chữ thường và số
              </p>
            </div>

            {/* Password Confirmation Field */}
            <div className="mb-6">
              <label
                htmlFor="passwordConfirmation"
                className="block text-sm font-semibold text-gray-700 mb-2"
              >
                Xác nhận mật khẩu
              </label>
              <input
                type="password"
                id="passwordConfirmation"
                name="passwordConfirmation"
                value={formData.passwordConfirmation}
                onChange={handleChange}
                disabled={loading}
                className={`w-full px-4 py-3 border rounded-lg focus:outline-none focus:ring-2 transition-colors ${
                  errors.passwordConfirmation
                    ? 'border-red-300 focus:ring-red-500'
                    : 'border-gray-300 focus:ring-blue-500'
                } ${loading ? 'bg-gray-50 cursor-not-allowed' : 'bg-white'}`}
                placeholder="••••••••"
                aria-required="true"
                aria-invalid={!!errors.passwordConfirmation}
                aria-describedby={
                  errors.passwordConfirmation ? 'password-confirmation-error' : undefined
                }
              />
              {errors.passwordConfirmation && (
                <p id="password-confirmation-error" className="mt-2 text-sm text-red-600">
                  {errors.passwordConfirmation}
                </p>
              )}
            </div>

            {/* Submit Button */}
            <button
              type="submit"
              disabled={loading}
              className={`w-full py-3 px-4 rounded-lg font-semibold text-white transition-all ${
                loading
                  ? 'bg-blue-400 cursor-not-allowed'
                  : 'bg-blue-600 hover:bg-blue-700 hover:shadow-lg'
              }`}
              aria-busy={loading}
            >
              {loading ? (
                <span className="flex items-center justify-center">
                  <svg
                    className="animate-spin -ml-1 mr-3 h-5 w-5 text-white"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
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
                  Đang tạo tài khoản...
                </span>
              ) : (
                'Tạo tài khoản'
              )}
            </button>
          </form>

          {/* Login Link */}
          <div className="mt-6 text-center">
            <p className="text-sm text-gray-600">
              Đã có tài khoản?{' '}
              <button
                onClick={() => navigate('/login')}
                className="text-blue-600 font-semibold hover:text-blue-700 transition-colors"
              >
                Đăng nhập tại đây
              </button>
            </p>
          </div>
        </div>

        {/* Back to Home */}
        <div className="mt-6 text-center">
          <button
            onClick={() => navigate('/')}
            className="text-sm text-gray-600 hover:text-gray-900 transition-colors"
          >
            ← Về trang chủ
          </button>
        </div>
      </div>
    </div>
  )
}

export default RegisterPage
