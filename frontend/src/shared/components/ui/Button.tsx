import React from 'react'

/**
 * Button — Soleil "Modern Archivist" design-system primitive.
 *
 * Design source: Claude Design handoff (components/Button). Gold is the single
 * chromatic accent; CTA labels are never UPPERCASE and never wrap; press = scale(0.95).
 *
 * Variants:
 *  - primary   gold gradient fill (default CTA)
 *  - soft      warmer solid gold (room-card actions)
 *  - ghost     transparent + hairline border, bark text
 *  - link      inline gold text link (no padding/radius)
 *  - darkGhost transparent on dark surfaces, white text
 *  - danger    functional red (destructive actions only)
 *  - secondary / outline  legacy aliases (→ soft / ghost) kept for back-compat
 */

export type ButtonVariant =
  | 'primary'
  | 'soft'
  | 'ghost'
  | 'link'
  | 'darkGhost'
  | 'danger'
  | 'secondary' // legacy alias → soft
  | 'outline' // legacy alias → ghost

export type ButtonSize = 'sm' | 'md' | 'lg'

interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: ButtonVariant
  size?: ButtonSize
  loading?: boolean
  fullWidth?: boolean
  children: React.ReactNode
}

const SIZE_STYLES: Record<ButtonSize, string> = {
  sm: 'px-4 py-2 text-[13px] rounded-[10px]',
  md: 'px-6 py-3 text-sm rounded-xl',
  lg: 'px-7 py-3.5 text-[15px] rounded-xl',
}

const VARIANT_STYLES: Record<ButtonVariant, string> = {
  primary:
    'bg-gradient-gold text-white font-bold shadow-[0_6px_16px_rgba(201,146,10,0.25)] hover:bg-[linear-gradient(135deg,#b8830a_0%,#966b08_100%)]',
  soft: 'bg-gold-soft text-white font-bold hover:bg-gold-soft-hover',
  secondary: 'bg-gold-soft text-white font-bold hover:bg-gold-soft-hover',
  ghost: 'bg-transparent text-bark border border-line font-semibold hover:bg-cream-paper',
  outline: 'bg-transparent text-bark border border-line font-semibold hover:bg-cream-paper',
  link: 'bg-transparent text-gold font-bold hover:text-gold-hover',
  darkGhost: 'bg-transparent text-white border border-white/20 font-semibold hover:bg-white/5',
  danger: 'bg-red-600 text-white font-bold shadow-md hover:bg-red-700',
}

const BASE_STYLES =
  'inline-flex items-center justify-center gap-2 whitespace-nowrap font-sans tracking-[0.02em] ' +
  'transition-[background,color,transform] duration-150 ease-[cubic-bezier(0.4,0,0.2,1)] active:scale-95 ' +
  'focus:outline-none focus-visible:ring-2 focus-visible:ring-gold focus-visible:ring-offset-2'

const DISABLED_STYLES = 'bg-none !bg-line text-[#9E958B] border-0 shadow-none cursor-not-allowed'

const Button: React.FC<ButtonProps> = ({
  variant = 'primary',
  size = 'md',
  loading = false,
  fullWidth = false,
  disabled,
  className = '',
  children,
  ...props
}) => {
  const isDisabled = disabled || loading
  const isLink = variant === 'link'

  const sizeCls = isLink ? 'text-[13px]' : SIZE_STYLES[size]
  const variantCls = isDisabled ? DISABLED_STYLES : VARIANT_STYLES[variant]
  const widthCls = fullWidth ? 'w-full' : ''

  return (
    <button
      className={`${BASE_STYLES} ${sizeCls} ${variantCls} ${widthCls} ${className}`}
      disabled={isDisabled}
      {...props}
    >
      {loading && (
        <svg
          className="w-5 h-5 -ml-1 animate-spin"
          xmlns="http://www.w3.org/2000/svg"
          fill="none"
          viewBox="0 0 24 24"
          aria-hidden="true"
        >
          <circle
            className="opacity-25"
            cx="12"
            cy="12"
            r="10"
            stroke="currentColor"
            strokeWidth="4"
          ></circle>
          <path
            className="opacity-75"
            fill="currentColor"
            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
          ></path>
        </svg>
      )}
      {children}
    </button>
  )
}

export default Button
