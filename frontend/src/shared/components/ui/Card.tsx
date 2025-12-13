import React from 'react'

/**
 * Card Component
 *
 * Reusable card container with optional header and footer.
 */

interface CardProps {
  children: React.ReactNode
  className?: string
  hover?: boolean
}

const Card: React.FC<CardProps> = ({ children, className = '', hover = false }) => {
  return (
    <div
      className={`bg-white rounded-xl shadow-md overflow-hidden ${
        hover ? 'hover:shadow-xl transition-shadow duration-300' : ''
      } ${className}`}
    >
      {children}
    </div>
  )
}

interface CardHeaderProps {
  children: React.ReactNode
  className?: string
}

const CardHeader: React.FC<CardHeaderProps> = ({ children, className = '' }) => {
  return <div className={`px-6 py-4 border-b border-gray-200 ${className}`}>{children}</div>
}

interface CardContentProps {
  children: React.ReactNode
  className?: string
}

const CardContent: React.FC<CardContentProps> = ({ children, className = '' }) => {
  return <div className={`px-6 py-4 ${className}`}>{children}</div>
}

interface CardFooterProps {
  children: React.ReactNode
  className?: string
}

const CardFooter: React.FC<CardFooterProps> = ({ children, className = '' }) => {
  return <div className={`px-6 py-4 border-t border-gray-200 ${className}`}>{children}</div>
}

// Compound component pattern
Card.Header = CardHeader
Card.Content = CardContent
Card.Footer = CardFooter

export default Card
