import React from 'react'

/**
 * Card Component
 *
 * Reusable card container with optional header and footer.
 * Uses compound component pattern for flexible composition.
 *
 * Usage:
 * <Card hover>
 *   <Card.Header>Title</Card.Header>
 *   <Card.Content>Body content</Card.Content>
 *   <Card.Footer>Footer content</Card.Footer>
 * </Card>
 */

interface CardProps {
  children: React.ReactNode
  className?: string
  hover?: boolean
}

interface CardHeaderProps {
  children: React.ReactNode
  className?: string
}

interface CardContentProps {
  children: React.ReactNode
  className?: string
}

interface CardFooterProps {
  children: React.ReactNode
  className?: string
}

const CardRoot = ({ children, className = '', hover = false }: CardProps) => {
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

const CardHeader = ({ children, className = '' }: CardHeaderProps) => {
  return <div className={`px-6 py-4 border-b border-gray-200 ${className}`}>{children}</div>
}

const CardContent = ({ children, className = '' }: CardContentProps) => {
  return <div className={`px-6 py-4 ${className}`}>{children}</div>
}

const CardFooter = ({ children, className = '' }: CardFooterProps) => {
  return <div className={`px-6 py-4 border-t border-gray-200 ${className}`}>{children}</div>
}

// Compound component with proper TypeScript typing
const Card = Object.assign(CardRoot, {
  Header: CardHeader,
  Content: CardContent,
  Footer: CardFooter,
})

export default Card
