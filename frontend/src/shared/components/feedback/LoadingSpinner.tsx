import React from 'react'

/**
 * LoadingSpinner Component
 *
 * Full-screen or inline loading spinner.
 */

interface LoadingSpinnerProps {
  size?: 'sm' | 'md' | 'lg' | 'xl'
  fullScreen?: boolean
  message?: string
}

const LoadingSpinner: React.FC<LoadingSpinnerProps> = ({
  size = 'md',
  fullScreen = false,
  message,
}) => {
  const sizeStyles = {
    sm: 'w-6 h-6',
    md: 'w-12 h-12',
    lg: 'w-16 h-16',
    xl: 'w-24 h-24',
  }

  const spinner = (
    <div className="flex flex-col items-center justify-center">
      <div
        className={`${sizeStyles[size]} border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin`}
        role="status"
        aria-label="Loading"
      >
        <span className="sr-only">Loading...</span>
      </div>
      {message && <p className="mt-4 font-medium text-gray-600 animate-pulse">{message}</p>}
    </div>
  )

  if (fullScreen) {
    return (
      <div className="fixed inset-0 z-50 flex items-center justify-center bg-white bg-opacity-90">
        {spinner}
      </div>
    )
  }

  return spinner
}

export default LoadingSpinner
