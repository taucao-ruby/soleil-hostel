/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{js,ts,jsx,tsx}'],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Plus Jakarta Sans', 'Inter', 'system-ui', 'sans-serif'],
        serif: ['Newsreader', 'Playfair Display', 'Georgia', 'serif'],
      },
      backgroundImage: {
        // Primary CTA fill + hero protection gradients (token-backed).
        'gradient-gold': 'var(--gradient-gold)',
        'hero-mobile': 'var(--gradient-hero-mobile)',
        'hero-desktop': 'var(--gradient-hero-desktop)',
      },
      boxShadow: {
        // Editorial, warm-black elevations.
        card: 'var(--shadow-card)',
        'card-hover': 'var(--shadow-card-hover)',
        'search-mobile': 'var(--shadow-search-mobile)',
        'search-desktop': 'var(--shadow-search-desktop)',
        drawer: 'var(--shadow-drawer)',
      },
      animation: {
        'spin-slow': 'spin 20s linear infinite',
        'fade-in': 'fade-in 0.6s ease-out',
        'slide-in-right': 'slide-in-right 0.5s ease-out',
        'slide-in-left': 'slide-in-left 0.5s ease-out',
        'scale-in': 'scale-in 0.4s ease-out',
        'ken-burns': 'ken-burns 12s ease-in-out infinite alternate',
      },
      keyframes: {
        'ken-burns': {
          from: { transform: 'scale(1)' },
          to: { transform: 'scale(1.08)' },
        },
        'slide-in-left': {
          from: { transform: 'translateX(-100%)' },
          to: { transform: 'translateX(0)' },
        },
      },
      colors: {
        // Soleil brand tokens (legacy — kept for non-home components)
        warmWhite: '#FDFAF3',
        orangeCTA: '#D4622A',
        orangeHover: '#E8845A',
        orangePale: '#FAE5D8',
        woodDark: '#5C3D1E',
        brandGold: '#F5A623',
        navy: '#1A2744',
        soleilBorder: '#E2D5C3',
        // PROMPT_0 design system tokens (legacy aliases)
        brandAmber: '#C9973A',
        hueSurface: '#F7F3EE',
        hueBlack: '#1C1A17',
        hueMuted: '#6B6760',
        hueBorder: '#E2DDD6',
        // ── "Modern Archivist" design-system palette (canonical) ──
        // Gold is the single chromatic accent — every CTA, link, brand mark.
        gold: {
          DEFAULT: '#C9920A',
          hover: '#A87808',
          soft: '#C9973A',
          'soft-hover': '#B8872A',
          pale: '#F9BC3D',
        },
        // Warm bark — foreground + dark sections (footer).
        bark: {
          DEFAULT: '#1C1A17',
          deep: '#151311',
          soft: '#1A1612',
        },
        // Secondary copy on cream.
        ink: {
          DEFAULT: '#504534',
          soft: '#6B6760',
        },
        // Tonal cream surface stack (page cream → warm white).
        cream: {
          DEFAULT: '#F5EFE3', // primary page cream
          warm: '#FFF8F4', // brightest surface
          low: '#FCF2EB', // below-cream container
          high: '#F0E6DF', // above-cream container (trust bar)
          tint: '#F6ECE5', // reviews section tint
          beige: '#F0E8DC', // trust bar bg
          paper: '#F7F3EE', // form field fill
        },
        // Hairline borders.
        line: {
          DEFAULT: '#E2DDD6',
          warm: '#D4C4AE',
        },
        primary: {
          50: '#f0f9ff',
          100: '#e0f2fe',
          200: '#bae6fd',
          300: '#7dd3fc',
          400: '#38bdf8',
          500: '#3b82f6',
          600: '#2563eb',
          700: '#1d4ed8',
          800: '#1e40af',
          900: '#1e3a8a',
        },
        accent: {
          50: '#fffbeb',
          100: '#fef3c7',
          200: '#fde68a',
          300: '#fcd34d',
          400: '#fbbf24',
          500: '#f59e0b',
          600: '#d97706',
          700: '#b45309',
          800: '#92400e',
          900: '#78350f',
        },
      },
    },
  },
  plugins: [],
  future: {
    hoverOnlyWhenSupported: true,
  },
}
