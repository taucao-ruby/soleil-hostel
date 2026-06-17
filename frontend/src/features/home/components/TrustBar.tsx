import React from 'react'

/**
 * TrustBar — 4 social-proof signals between SearchCard and Rooms.
 *
 * Matches the "Modern Archivist" design-system spec (preview/trust-bar.html):
 * a rounded cream-beige bar of circular gold icon-chips (inline SVG, not emoji)
 * with a bold bark headline + muted subtext, divided by hairline rules on desktop.
 *
 * Mobile  (<md): 2×2 grid, no dividers
 * Desktop (≥md): single row, hairline dividers between items
 */

type Signal = {
  headline: string
  sub: string
  icon: React.ReactNode
}

const SIGNALS: Signal[] = [
  {
    headline: 'Xác nhận ngay',
    sub: 'không cần chờ',
    icon: (
      <svg
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth="1.85"
        strokeLinecap="round"
        strokeLinejoin="round"
      >
        <circle cx="12" cy="12" r="9" />
        <path d="M8 12.5l2.5 2.5 5-5" />
      </svg>
    ),
  },
  {
    headline: '4.9 / 5 sao',
    sub: '820 đánh giá',
    icon: (
      <svg viewBox="0 0 24 24" fill="currentColor" stroke="none">
        <polygon points="12,3 14.5,9 21,9.5 16,14 17.5,20.5 12,17 6.5,20.5 8,14 3,9.5 9.5,9" />
      </svg>
    ),
  },
  {
    headline: 'Miễn thẻ',
    sub: 'trả tại cơ sở',
    icon: (
      <svg
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth="1.85"
        strokeLinecap="round"
        strokeLinejoin="round"
      >
        <rect x="5" y="11" width="14" height="10" rx="1.5" />
        <path d="M8 11V7a4 4 0 0 1 8 0v4" />
      </svg>
    ),
  },
  {
    headline: 'Huỷ miễn phí',
    sub: 'trong 24 giờ',
    icon: (
      <svg
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth="1.85"
        strokeLinecap="round"
        strokeLinejoin="round"
      >
        <path d="M4 12a8 8 0 0 1 13.5-5.8L21 9" />
        <path d="M21 4v5h-5" />
        <path d="M20 12a8 8 0 0 1-13.5 5.8L3 15" />
        <path d="M3 20v-5h5" />
      </svg>
    ),
  },
]

const TrustBar: React.FC = () => {
  return (
    <section aria-label="Cam kết dịch vụ" className="py-8">
      <div className="max-w-7xl mx-auto px-4 md:px-8 lg:px-12">
        <ul className="bg-cream-beige border border-line rounded-[14px] p-4 md:px-5 grid grid-cols-2 md:grid-cols-4 gap-y-5 gap-x-3">
          {SIGNALS.map(({ headline, sub, icon }) => (
            <li
              key={headline}
              className="flex items-center gap-2.5 min-h-[44px] md:border-r md:border-line md:pr-3 md:last:border-r-0 md:last:pr-0"
            >
              <span
                className="grid place-items-center w-8 h-8 shrink-0 rounded-full bg-white border border-line text-gold [&>svg]:w-4 [&>svg]:h-4"
                aria-hidden="true"
              >
                {icon}
              </span>
              <span className="min-w-0">
                <b className="block text-[12px] font-bold text-bark leading-tight tracking-[-0.01em]">
                  {headline}
                </b>
                <span className="block text-[10.5px] text-ink-soft leading-snug mt-0.5">{sub}</span>
              </span>
            </li>
          ))}
        </ul>
      </div>
    </section>
  )
}

export default TrustBar
