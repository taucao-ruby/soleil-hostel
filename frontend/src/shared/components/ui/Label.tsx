import React from 'react'

/**
 * Label Component
 *
 * Reusable label for form fields.
 */

interface LabelProps extends React.LabelHTMLAttributes<HTMLLabelElement> {
  required?: boolean
  children: React.ReactNode
}

const Label: React.FC<LabelProps> = ({ required = false, className = '', children, ...props }) => {
  return (
    <label className={`block text-sm font-semibold text-gray-700 mb-2 ${className}`} {...props}>
      {children}
      {required && <span className="ml-1 text-red-500">*</span>}
    </label>
  )
}

export default Label
