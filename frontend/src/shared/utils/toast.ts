import React from 'react'
import { toast, ToastOptions, ToastContainer as ToastifyContainer } from 'react-toastify'

/**
 * Toast Notification Utility
 *
 * Wrapper around react-toastify for consistent notification styling
 * and behavior across the application.
 */

const defaultOptions: ToastOptions = {
  position: 'top-right',
  autoClose: 5000,
  hideProgressBar: false,
  closeOnClick: true,
  pauseOnHover: true,
  draggable: true,
}

export const showToast = {
  /**
   * Success notification
   */
  success: (message: string, options?: ToastOptions) => {
    toast.success(message, {
      ...defaultOptions,
      ...options,
      className: 'bg-green-50 text-green-800',
    })
  },

  /**
   * Error notification
   */
  error: (message: string, options?: ToastOptions) => {
    toast.error(message, {
      ...defaultOptions,
      autoClose: 7000, // Keep errors visible longer
      ...options,
      className: 'bg-red-50 text-red-800',
    })
  },

  /**
   * Warning notification
   */
  warning: (message: string, options?: ToastOptions) => {
    toast.warning(message, {
      ...defaultOptions,
      ...options,
      className: 'bg-yellow-50 text-yellow-800',
    })
  },

  /**
   * Info notification
   */
  info: (message: string, options?: ToastOptions) => {
    toast.info(message, {
      ...defaultOptions,
      ...options,
      className: 'bg-blue-50 text-blue-800',
    })
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
    return toast.promise(
      promise,
      {
        pending: messages.pending,
        success: messages.success,
        error: messages.error,
      },
      {
        ...defaultOptions,
        ...options,
      }
    )
  },
}

/**
 * Toast Container Component
 * Add this once at the root level of your app
 */
export function ToastContainer(): React.ReactElement {
  return React.createElement(ToastifyContainer, {
    position: 'top-right',
    autoClose: 5000,
    hideProgressBar: false,
    newestOnTop: true,
    closeOnClick: true,
    rtl: false,
    pauseOnFocusLoss: true,
    draggable: true,
    pauseOnHover: true,
    theme: 'light',
    className: 'toast-container',
  })
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
