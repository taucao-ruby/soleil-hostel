import React from 'react'

/**
 * Skeleton Component
 *
 * Loading skeleton for content placeholders.
 */

interface SkeletonProps {
  className?: string
  width?: string
  height?: string
  rounded?: 'none' | 'sm' | 'md' | 'lg' | 'full'
}

const Skeleton: React.FC<SkeletonProps> = ({ className = '', width, height, rounded = 'md' }) => {
  const roundedStyles = {
    none: 'rounded-none',
    sm: 'rounded-sm',
    md: 'rounded-md',
    lg: 'rounded-lg',
    full: 'rounded-full',
  }

  return (
    <div
      className={`animate-pulse bg-gray-200 ${roundedStyles[rounded]} ${className}`}
      style={{ width, height }}
      role="status"
      aria-label="Loading..."
    >
      <span className="sr-only">Loading...</span>
    </div>
  )
}

// Preset skeleton components
export const SkeletonText: React.FC<{ lines?: number; className?: string }> = ({
  lines = 3,
  className = '',
}) => {
  return (
    <div className={`space-y-3 ${className}`}>
      {Array.from({ length: lines }).map((_, i) => (
        <Skeleton key={i} height="16px" width={i === lines - 1 ? '75%' : '100%'} rounded="sm" />
      ))}
    </div>
  )
}

export const SkeletonCard: React.FC<{ className?: string }> = ({ className = '' }) => {
  return (
    <div className={`bg-white rounded-xl shadow-md overflow-hidden ${className}`}>
      <Skeleton height="192px" rounded="none" />
      <div className="p-6 space-y-4">
        <Skeleton height="24px" width="60%" />
        <SkeletonText lines={2} />
        <div className="flex items-center justify-between">
          <Skeleton height="32px" width="80px" />
          <Skeleton height="24px" width="100px" />
        </div>
      </div>
    </div>
  )
}

export default Skeleton
