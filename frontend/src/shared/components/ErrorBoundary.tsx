import React from 'react'

/**
 * ErrorBoundary Component
 *
 * Catches JavaScript errors anywhere in the child component tree,
 * logs those errors, and displays a fallback UI.
 *
 * Features:
 * - Prevents app crashes from component errors
 * - Beautiful fallback UI with Tailwind CSS
 * - Shows stack trace in development mode
 * - "Try Again" and "Go Home" action buttons
 */

interface ErrorBoundaryProps {
  children: React.ReactNode
}

interface ErrorBoundaryState {
  hasError: boolean
  error: Error | null
  errorInfo: React.ErrorInfo | null
}

class ErrorBoundary extends React.Component<ErrorBoundaryProps, ErrorBoundaryState> {
  constructor(props: ErrorBoundaryProps) {
    super(props)
    this.state = {
      hasError: false,
      error: null,
      errorInfo: null,
    }
  }

  /**
   * Update state so the next render shows fallback UI
   */
  static getDerivedStateFromError(error: Error): Partial<ErrorBoundaryState> {
    return {
      hasError: true,
      error,
    }
  }

  /**
   * Log error details to console (or send to error tracking service)
   */
  componentDidCatch(error: Error, errorInfo: React.ErrorInfo): void {
    console.error('ErrorBoundary caught an error:', error, errorInfo)

    this.setState({
      error,
      errorInfo,
    })

    // TODO: Send to error tracking service (e.g., Sentry)
    // Sentry.captureException(error, { extra: errorInfo });
  }

  /**
   * Reset error boundary state
   */
  handleReset = (): void => {
    this.setState({
      hasError: false,
      error: null,
      errorInfo: null,
    })
  }

  /**
   * Navigate to homepage
   */
  handleGoHome = (): void => {
    window.location.href = '/'
  }

  render(): React.ReactNode {
    if (this.state.hasError) {
      return (
        <div className="flex items-center justify-center min-h-screen p-4 bg-gradient-to-br from-blue-50 via-white to-blue-50">
          <div className="w-full max-w-2xl p-8 bg-white shadow-2xl rounded-2xl md:p-12">
            {/* Error Icon */}
            <div className="flex justify-center mb-6">
              <div className="flex items-center justify-center w-20 h-20 bg-red-100 rounded-full">
                <svg
                  className="w-10 h-10 text-red-600"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                  />
                </svg>
              </div>
            </div>

            {/* Error Title */}
            <h1 className="mb-4 text-3xl font-bold text-center text-gray-900">
              Oops! Something went wrong
            </h1>

            {/* Error Description */}
            <p className="mb-8 text-center text-gray-600">
              We encountered an unexpected error. Don't worry, it's not your fault!
            </p>

            {/* Error Details (Development Mode) */}
            {import.meta.env.DEV && this.state.error && (
              <div className="p-4 mb-8 border border-gray-200 rounded-lg bg-gray-50">
                <h2 className="mb-2 text-sm font-semibold text-gray-700">Error Details:</h2>
                <pre className="overflow-x-auto text-xs text-red-600 break-words whitespace-pre-wrap">
                  {this.state.error.toString()}
                </pre>
                {this.state.errorInfo && (
                  <details className="mt-4">
                    <summary className="text-sm font-semibold text-gray-700 cursor-pointer hover:text-gray-900">
                      Component Stack Trace
                    </summary>
                    <pre className="mt-2 overflow-x-auto text-xs text-gray-600 whitespace-pre-wrap">
                      {this.state.errorInfo.componentStack}
                    </pre>
                  </details>
                )}
              </div>
            )}

            {/* Action Buttons */}
            <div className="flex flex-col justify-center gap-4 sm:flex-row">
              <button
                onClick={this.handleReset}
                className="px-6 py-3 font-semibold text-white transition-colors bg-blue-600 rounded-lg shadow-md hover:bg-blue-700 hover:shadow-lg"
              >
                Try Again
              </button>
              <button
                onClick={this.handleGoHome}
                className="px-6 py-3 font-semibold text-gray-800 transition-colors bg-gray-200 rounded-lg hover:bg-gray-300"
              >
                Go to Homepage
              </button>
            </div>
          </div>
        </div>
      )
    }

    return this.props.children
  }
}

export default ErrorBoundary
