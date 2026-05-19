import React from 'react'
import { createPortal } from 'react-dom'

/**
 * Toast Notification Utility
 *
 * Self-contained notification helper with the same public call sites
 * previously used by the application.
 */

export interface ToastOptions {
  autoClose?: number | false
  className?: string
  toastId?: string
}

type ToastVariant = 'success' | 'error' | 'warning' | 'info'

interface ToastItem {
  id: string
  message: string
  variant: ToastVariant
  autoClose: number | false
  className?: string
}

type ToastListener = (toast: ToastItem) => void

const listeners = new Set<ToastListener>()
let queuedToasts: ToastItem[] = []
let nextToastId = 0

const defaultOptions: ToastOptions = {
  autoClose: 5000,
}

const variantClassNames: Record<ToastVariant, string> = {
  success: 'border-green-200 bg-green-50 text-green-800',
  error: 'border-red-200 bg-red-50 text-red-800',
  warning: 'border-yellow-200 bg-yellow-50 text-yellow-800',
  info: 'border-blue-200 bg-blue-50 text-blue-800',
}

const subscribe = (listener: ToastListener): (() => void) => {
  listeners.add(listener)
  queuedToasts.forEach(listener)
  queuedToasts = []

  return () => {
    listeners.delete(listener)
  }
}

const emitToast = (variant: ToastVariant, message: string, options?: ToastOptions) => {
  const toastItem: ToastItem = {
    id: options?.toastId ?? `toast-${Date.now()}-${nextToastId++}`,
    message,
    variant,
    autoClose: options?.autoClose ?? defaultOptions.autoClose ?? 5000,
    className: options?.className,
  }

  if (listeners.size === 0) {
    queuedToasts.push(toastItem)
    return
  }

  listeners.forEach(listener => listener(toastItem))
}

export const showToast = {
  /**
   * Success notification
   */
  success: (message: string, options?: ToastOptions) => {
    emitToast('success', message, options)
  },

  /**
   * Error notification
   */
  error: (message: string, options?: ToastOptions) => {
    emitToast('error', message, { autoClose: 7000, ...options })
  },

  /**
   * Warning notification
   */
  warning: (message: string, options?: ToastOptions) => {
    emitToast('warning', message, options)
  },

  /**
   * Info notification
   */
  info: (message: string, options?: ToastOptions) => {
    emitToast('info', message, options)
  },

  /**
   * Promise-based notification with loading/success/error states
   */
  promise: <T>(
    promise: Promise<T>,
    messages: {
      pending: string
      success: string
      error: string
    },
    options?: ToastOptions
  ) => {
    const toastId = options?.toastId ?? `toast-promise-${Date.now()}-${nextToastId++}`

    emitToast('info', messages.pending, { ...options, toastId })

    return promise.then(
      value => {
        emitToast('success', messages.success, { ...options, toastId })
        return value
      },
      error => {
        emitToast('error', messages.error, { ...options, toastId, autoClose: 7000 })
        throw error
      }
    )
  },
}

/**
 * Toast Container Component
 * Add this once at the root level of your app
 */
export function ToastContainer(): React.ReactElement {
  const [items, setItems] = React.useState<ToastItem[]>([])

  React.useEffect(() => {
    const timers = new Map<string, number>()

    const removeToast = (id: string) => {
      const timer = timers.get(id)
      if (timer) {
        window.clearTimeout(timer)
        timers.delete(id)
      }

      setItems(current => current.filter(item => item.id !== id))
    }

    const unsubscribe = subscribe(toastItem => {
      setItems(current => [toastItem, ...current.filter(item => item.id !== toastItem.id)])

      if (toastItem.autoClose !== false) {
        const existingTimer = timers.get(toastItem.id)
        if (existingTimer) {
          window.clearTimeout(existingTimer)
        }

        timers.set(
          toastItem.id,
          window.setTimeout(() => removeToast(toastItem.id), toastItem.autoClose)
        )
      }
    })

    return () => {
      timers.forEach(timer => window.clearTimeout(timer))
      timers.clear()
      unsubscribe()
    }
  }, [])

  if (typeof document === 'undefined') {
    return React.createElement(React.Fragment)
  }

  return createPortal(
    React.createElement(
      'div',
      {
        'aria-live': 'polite',
        'aria-relevant': 'additions text',
        className:
          'fixed right-4 top-4 z-[9999] flex w-[min(24rem,calc(100vw-2rem))] flex-col gap-3',
      },
      items.map(item =>
        React.createElement(
          'div',
          {
            key: item.id,
            role: 'status',
            className: `flex items-start justify-between gap-3 rounded-md border px-4 py-3 text-sm shadow-lg shadow-black/10 ${
              variantClassNames[item.variant]
            } ${item.className ?? ''}`,
          },
          React.createElement('span', null, item.message),
          React.createElement(
            'button',
            {
              type: 'button',
              onClick: () => setItems(current => current.filter(toast => toast.id !== item.id)),
              className: 'text-current opacity-60 hover:opacity-100',
              'aria-label': 'Đóng thông báo',
            },
            'x'
          )
        )
      )
    ),
    document.body
  )
}

/**
 * Helper to extract a user-safe error message from various error shapes.
 *
 * Surfaces server-provided 4xx business messages (Vietnamese, written for end
 * users) while suppressing 5xx internals, raw axios diagnostics, HTML error
 * pages, and other operational text that must never reach a guest. Status-
 * specific copy handles 401/429/network so callers can render a single string
 * without branching themselves.
 */
const INTERNAL_ERROR_PATTERNS: readonly RegExp[] = [
  /SQLSTATE/i,
  /stack trace/i,
  /\bexception\b/i,
  /vendor[\\/]/i,
  /Illuminate\\/i,
  /Stripe\\/i,
  /<html/i,
  /<!doctype/i,
  /Authorization:\s*Bearer/i,
  /Bearer\s+[A-Za-z0-9._-]{8,}/,
  /XSRF-TOKEN/i,
  /set-cookie/i,
  /Request failed with status code/i,
  /Network Error/i,
  /PDOException/i,
  /file_get_contents/i,
]

const FALLBACK_GENERIC = 'Đã có lỗi xảy ra. Vui lòng thử lại.'
const FALLBACK_NETWORK = 'Không thể kết nối máy chủ. Vui lòng kiểm tra mạng và thử lại.'
const FALLBACK_SESSION = 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.'
const FALLBACK_RATE_LIMIT = 'Bạn thao tác quá nhanh. Vui lòng thử lại sau.'

function safeOrNull(value: unknown): string | null {
  if (typeof value !== 'string') return null
  const trimmed = value.trim()
  if (!trimmed) return null
  if (INTERNAL_ERROR_PATTERNS.some(pattern => pattern.test(trimmed))) return null
  return trimmed
}

function joinValidationMessages(errors: unknown): string | null {
  if (!errors || typeof errors !== 'object') return null
  const messages: string[] = []
  for (const value of Object.values(errors as Record<string, unknown>)) {
    if (Array.isArray(value)) {
      for (const item of value) {
        const safe = safeOrNull(item)
        if (safe) messages.push(safe)
      }
    } else {
      const safe = safeOrNull(value)
      if (safe) messages.push(safe)
    }
  }
  return messages.length > 0 ? messages.join(', ') : null
}

function extractPayloadMessage(data: unknown): string | null {
  if (!data || typeof data !== 'object') return null
  const d = data as { message?: unknown; error?: unknown; errors?: unknown }

  // Laravel validation: { errors: { field: ["..."] } } takes precedence so the
  // per-field reasons reach the user instead of a generic summary.
  const validation = joinValidationMessages(d.errors)
  if (validation) return validation

  const direct = safeOrNull(d.message)
  if (direct) return direct

  if (d.error && typeof d.error === 'object' && 'message' in d.error) {
    const nested = safeOrNull((d.error as { message?: unknown }).message)
    if (nested) return nested
  }

  const errorString = safeOrNull(d.error)
  if (errorString) return errorString

  return null
}

export function getErrorMessage(error: unknown): string {
  if (typeof error === 'string') {
    return safeOrNull(error) ?? FALLBACK_GENERIC
  }

  if (!error || typeof error !== 'object') {
    return FALLBACK_GENERIC
  }

  // Axios-shape with a response → server responded with a status code.
  if ('response' in error && (error as { response?: unknown }).response) {
    const response = (error as { response: { status?: number; data?: unknown } }).response
    const status = typeof response.status === 'number' ? response.status : null

    if (status === 401) return FALLBACK_SESSION
    if (status === 429) return FALLBACK_RATE_LIMIT

    // 5xx must never surface the server payload — it may contain SQLSTATE,
    // stack traces, vendor paths, or other operational internals.
    if (status !== null && status >= 500) return FALLBACK_GENERIC

    if (status !== null && status >= 400 && status < 500) {
      return extractPayloadMessage(response.data) ?? FALLBACK_GENERIC
    }

    return extractPayloadMessage(response.data) ?? FALLBACK_GENERIC
  }

  // Axios-shape without a response → network/transport failure (DNS, offline,
  // CORS, timeout). Real AxiosError sets isAxiosError; tests duck-type the same.
  const errObj = error as { isAxiosError?: unknown; code?: unknown }
  if (errObj.isAxiosError === true || typeof errObj.code === 'string') {
    return FALLBACK_NETWORK
  }

  if ('message' in error && typeof (error as { message?: unknown }).message === 'string') {
    const safe = safeOrNull((error as { message: string }).message)
    if (safe) return safe
  }

  return FALLBACK_GENERIC
}
