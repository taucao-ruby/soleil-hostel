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
 * Helper to extract error message from various error types
 */
export function getErrorMessage(error: unknown): string {
  if (typeof error === 'string') return error

  if (error && typeof error === 'object') {
    // Axios error
    if ('response' in error && error.response && typeof error.response === 'object') {
      const response = error.response as {
        data?: { message?: string; errors?: Record<string, string[]> }
      }

      // Laravel validation errors
      if (response.data?.errors) {
        const errors = Object.values(response.data.errors).flat()
        return errors.join(', ')
      }

      // Standard error message
      if (response.data?.message) {
        return response.data.message
      }
    }

    // Standard Error object
    if ('message' in error && typeof error.message === 'string') {
      return error.message
    }
  }

  return 'An unexpected error occurred'
}
