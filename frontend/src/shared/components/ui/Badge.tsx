import React from 'react'

/**
 * Badge — Soleil "Modern Archivist" design-system primitive.
 *
 * Design source: Claude Design handoff (components/Badge). Pill-shaped status and
 * label chips. `typeChip` is the only square (4px) variant; `location` and
 * `eyebrowPill` are translucent for use over imagery / dark surfaces.
 */

export type BadgeVariant =
  | 'available'
  | 'unavailable'
  | 'popular'
  | 'premium'
  | 'location'
  | 'eyebrowPill'
  | 'typeChip'

interface BadgeProps extends React.HTMLAttributes<HTMLSpanElement> {
  variant?: BadgeVariant
  children: React.ReactNode
}

/** Each variant is self-contained (shape + size + color) to avoid Tailwind conflicts. */
const VARIANT_STYLES: Record<BadgeVariant, string> = {
  available:
    'gap-1.5 rounded-full px-3 py-[5px] text-[11px] font-bold tracking-[0.02em] bg-[#DCFCE7] text-[#166534] border border-[#BBF7D0]',
  unavailable:
    'gap-1.5 rounded-full px-3 py-[5px] text-[11px] font-bold tracking-[0.02em] bg-gray-100 text-gray-500 border border-gray-200',
  popular:
    'gap-1.5 rounded-full px-3 py-[5px] text-[11px] font-bold tracking-[0.02em] bg-[#FECACA] text-[#DC2626]',
  premium:
    'gap-1.5 rounded-full px-3 py-[5px] text-[11px] font-bold tracking-[0.02em] bg-gradient-gold text-white',
  location:
    'gap-1.5 rounded-full px-3 py-[5px] text-[11px] font-bold tracking-[0.02em] bg-black/50 backdrop-blur-sm text-white',
  eyebrowPill:
    'gap-1.5 rounded-full px-3 py-[5px] text-[11px] font-semibold uppercase tracking-[0.15em] bg-white/10 backdrop-blur-md text-white border border-white/20',
  typeChip:
    'rounded px-2.5 py-[3px] text-[10px] font-bold uppercase tracking-[0.18em] bg-cream-beige text-ink',
}

const Badge: React.FC<BadgeProps> = ({
  variant = 'available',
  className = '',
  children,
  ...props
}) => (
  <span
    className={`inline-flex items-center whitespace-nowrap font-sans ${VARIANT_STYLES[variant]} ${className}`}
    {...props}
  >
    {children}
  </span>
)

export default Badge
