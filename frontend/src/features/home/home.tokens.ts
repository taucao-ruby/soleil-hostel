/**
 * Soleil brand tokens — mirrors tailwind.config.js `colors` additions.
 * Use tailwind class names for styling; use these constants for JS contexts
 * (e.g., inline styles, canvas, dynamic values).
 */

export const COLORS = {
  cream: '#F5EFE0',
  warmWhite: '#FDFAF3',
  orangeCTA: '#D4622A',
  orangeHover: '#E8845A',
  orangePale: '#FAE5D8',
  woodDark: '#5C3D1E',
  brandGold: '#F5A623',
  navy: '#1A2744',
  soleilBorder: '#E2D5C3',
} as const

export type SoleilColor = keyof typeof COLORS
