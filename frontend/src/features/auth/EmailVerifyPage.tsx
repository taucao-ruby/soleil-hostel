import React, { useCallback, useEffect, useRef, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from './AuthContext'
import api from '@/shared/lib/api'

const CODE_LENGTH = 6

type VerifyState = 'idle' | 'submitting' | 'success'

const EmailVerifyPage: React.FC = () => {
  const navigate = useNavigate()
  const { user, loading: authLoading, me } = useAuth()

  const [digits, setDigits] = useState<string[]>(Array(CODE_LENGTH).fill(''))
  const [verifyState, setVerifyState] = useState<VerifyState>('idle')
  const [error, setError] = useState<string | null>(null)
  const [cooldown, setCooldown] = useState(0)
  const [resending, setResending] = useState(false)
  const [resendMessage, setResendMessage] = useState<string | null>(null)

  const inputRefs = useRef<(HTMLInputElement | null)[]>([])
  const cooldownRef = useRef<number | null>(null)

  // Redirect already-verified users
  useEffect(() => {
    if (!authLoading && user?.email_verified_at) {
      navigate('/dashboard', { replace: true })
    }
  }, [authLoading, user, navigate])

  // Fetch initial cooldown from status endpoint
  useEffect(() => {
    if (authLoading || !user) return
    const controller = new AbortController()

    api
      .get<{ data: { cooldown_remaining: number } }>('/email/verification-status', {
        signal: controller.signal,
      })
      .then(res => {
        const remaining = res.data?.data?.cooldown_remaining ?? 0
        if (remaining > 0) setCooldown(remaining)
      })
      .catch(() => {
        /* non-critical */
      })

    return () => controller.abort()
  }, [authLoading, user])

  // Countdown timer
  useEffect(() => {
    if (cooldown <= 0) return

    cooldownRef.current = window.setInterval(() => {
      setCooldown(prev => {
        if (prev <= 1) {
          if (cooldownRef.current) clearInterval(cooldownRef.current)
          return 0
        }
        return prev - 1
      })
    }, 1000)

    return () => {
      if (cooldownRef.current) clearInterval(cooldownRef.current)
    }
  }, [cooldown])

  const focusInput = (index: number) => {
    inputRefs.current[index]?.focus()
  }

  const handleDigitChange = useCallback((index: number, value: string) => {
    // Accept only single digit
    const digit = value.replace(/\D/g, '').slice(-1)

    setDigits(prev => {
      const next = [...prev]
      next[index] = digit
      return next
    })
    setError(null)

    if (digit && index < CODE_LENGTH - 1) {
      focusInput(index + 1)
    }
  }, [])

  const handleKeyDown = useCallback(
    (index: number, e: React.KeyboardEvent<HTMLInputElement>) => {
      if (e.key === 'Backspace' && !digits[index] && index > 0) {
        focusInput(index - 1)
      }
      if (e.key === 'ArrowLeft' && index > 0) {
        e.preventDefault()
        focusInput(index - 1)
      }
      if (e.key === 'ArrowRight' && index < CODE_LENGTH - 1) {
        e.preventDefault()
        focusInput(index + 1)
      }
    },
    [digits]
  )

  const handlePaste = useCallback((e: React.ClipboardEvent) => {
    e.preventDefault()
    const pasted = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, CODE_LENGTH)
    if (!pasted) return

    const next = Array(CODE_LENGTH).fill('')
    for (let i = 0; i < pasted.length; i++) {
      next[i] = pasted[i]
    }
    setDigits(next)
    setError(null)

    // Focus the next empty or last input
    const focusIdx = Math.min(pasted.length, CODE_LENGTH - 1)
    focusInput(focusIdx)
  }, [])

  const code = digits.join('')
  const isCodeComplete = code.length === CODE_LENGTH

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!isCodeComplete || verifyState === 'submitting') return

    setVerifyState('submitting')
    setError(null)

    try {
      await api.post('/email/verify-code', { code })
      setVerifyState('success')
      // Refresh user state so email_verified_at is updated
      await me()
    } catch (err: unknown) {
      setVerifyState('idle')
      const axiosErr = err as { response?: { data?: { message?: string } } }
      setError(axiosErr?.response?.data?.message ?? 'Xác minh thất bại. Vui lòng thử lại.')
      // Clear inputs on error for retry
      setDigits(Array(CODE_LENGTH).fill(''))
      focusInput(0)
    }
  }

  const handleResendCode = async () => {
    if (resending || cooldown > 0) return

    setResending(true)
    setResendMessage(null)
    setError(null)

    try {
      const res = await api.post<{ data: { cooldown: number }; message: string }>(
        '/email/send-code'
      )
      setCooldown(res.data?.data?.cooldown ?? 60)
      setResendMessage(res.data?.message ?? 'Đã gửi mã xác minh mới.')
      setDigits(Array(CODE_LENGTH).fill(''))
      focusInput(0)
    } catch (err: unknown) {
      const axiosErr = err as { response?: { data?: { message?: string } } }
      setError(axiosErr?.response?.data?.message ?? 'Không thể gửi mã. Vui lòng thử lại.')
    } finally {
      setResending(false)
    }
  }

  // Loading state
  if (authLoading) {
    return (
      <section className="flex min-h-[60vh] items-center justify-center px-4">
        <p className="text-hueMuted">Đang tải...</p>
      </section>
    )
  }

  // Not authenticated
  if (!user) {
    return (
      <section className="flex min-h-[60vh] items-center justify-center px-4">
        <div className="w-full max-w-sm p-8 text-center bg-white border rounded-lg shadow-sm border-hueBorder">
          <p className="text-base font-medium text-hueBlack">
            Vui lòng đăng nhập để xác minh email của bạn.
          </p>
          <p className="mt-2 text-sm text-hueMuted">
            Sau khi đăng nhập, bạn sẽ được chuyển về trang này tự động.
          </p>
          <Link
            to="/login"
            className="mt-6 inline-block rounded-lg bg-brandAmber px-6 py-3 text-sm font-medium text-hueBlack transition hover:bg-[#b88933]"
          >
            Đăng nhập
          </Link>
        </div>
      </section>
    )
  }

  // Success state
  if (verifyState === 'success') {
    return (
      <section className="flex min-h-[60vh] items-center justify-center px-4">
        <div className="w-full max-w-sm p-8 text-center border border-green-200 rounded-lg bg-green-50">
          <p className="text-base font-medium text-green-800">Email đã được xác minh thành công!</p>
          <Link
            to="/dashboard"
            className="mt-6 inline-block rounded-lg bg-brandAmber px-6 py-3 text-sm font-medium text-hueBlack transition hover:bg-[#b88933]"
          >
            Về trang quản lý →
          </Link>
        </div>
      </section>
    )
  }

  // OTP input form
  return (
    <section className="flex min-h-[60vh] items-center justify-center px-4">
      <div className="w-full max-w-sm p-8 bg-white border rounded-lg shadow-sm border-hueBorder">
        <div className="mb-6 text-center">
          <p className="text-sm font-medium uppercase tracking-[0.2em] text-brandAmber">
            Soleil Hostel
          </p>
          <h1 className="mt-3 text-2xl font-medium text-hueBlack">Xác minh email</h1>
          <p className="mt-2 text-sm text-hueMuted">
            Nhập mã 6 chữ số đã gửi đến{' '}
            <span className="font-medium text-hueBlack">{user.email}</span>
          </p>
        </div>

        {error && (
          <div className="px-4 py-3 mb-4 border border-red-200 rounded-lg bg-red-50" role="alert">
            <p className="text-sm font-medium text-red-800">{error}</p>
          </div>
        )}

        {resendMessage && !error && (
          <div
            className="px-4 py-3 mb-4 border border-green-200 rounded-lg bg-green-50"
            role="status"
          >
            <p className="text-sm font-medium text-green-800">{resendMessage}</p>
          </div>
        )}

        <form onSubmit={handleSubmit} noValidate>
          <div className="flex justify-center gap-2" role="group" aria-label="Mã xác minh 6 chữ số">
            {digits.map((digit, i) => (
              <input
                key={i}
                ref={el => {
                  inputRefs.current[i] = el
                }}
                type="text"
                inputMode="numeric"
                autoComplete="one-time-code"
                maxLength={1}
                value={digit}
                onChange={e => handleDigitChange(i, e.target.value)}
                onKeyDown={e => handleKeyDown(i, e)}
                onPaste={i === 0 ? handlePaste : undefined}
                disabled={verifyState === 'submitting'}
                aria-label={`Chữ số ${i + 1}`}
                className={`h-12 w-11 rounded-lg border text-center text-lg font-semibold outline-none transition
                  ${error ? 'border-red-400' : 'border-hueBorder'}
                  focus:border-brandAmber focus:ring-2 focus:ring-brandAmber/20
                  disabled:cursor-not-allowed disabled:bg-stone-50 disabled:text-hueMuted`}
              />
            ))}
          </div>

          <button
            type="submit"
            disabled={!isCodeComplete || verifyState === 'submitting'}
            className="mt-6 w-full rounded-lg bg-brandAmber py-3 text-sm font-medium text-hueBlack transition hover:bg-[#b88933] disabled:cursor-not-allowed disabled:opacity-60"
          >
            {verifyState === 'submitting' ? 'Đang xác minh...' : 'Xác minh'}
          </button>
        </form>

        <div className="mt-4 text-center">
          <p className="text-sm text-hueMuted">
            Chưa nhận được mã?{' '}
            <button
              type="button"
              onClick={handleResendCode}
              disabled={resending || cooldown > 0}
              className="font-medium text-brandAmber underline-offset-2 hover:underline disabled:cursor-not-allowed disabled:opacity-60 disabled:no-underline"
            >
              {resending ? 'Đang gửi...' : cooldown > 0 ? `Gửi lại sau ${cooldown}s` : 'Gửi lại mã'}
            </button>
          </p>
        </div>

        <div className="mt-6 text-center">
          <Link
            to="/dashboard"
            className="text-sm text-hueMuted underline-offset-2 hover:text-hueBlack hover:underline"
          >
            Về trang quản lý
          </Link>
        </div>
      </div>
    </section>
  )
}

export default EmailVerifyPage
