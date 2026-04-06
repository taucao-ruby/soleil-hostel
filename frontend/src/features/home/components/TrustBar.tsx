import React from 'react'

/**
 * TrustBar — 4 social-proof signals between SearchCard and Rooms (Stitch spec).
 *
 * Mobile  (<md): 2×2 grid
 * Desktop (≥md): single horizontal row
 */

const SIGNALS = [
  { icon: '✓', label: 'Xác nhận ngay' },
  { icon: '⭐', label: '4.9 / 5 từ 820 đánh giá' },
  { icon: '🔒', label: 'Không cần thẻ tín dụng' },
  { icon: '↩', label: 'Hủy miễn phí 24h' },
] as const

const TrustBar: React.FC = () => {
  return (
    <section aria-label="Cam kết dịch vụ" className="bg-[#F0E8DC] border-y border-[#E2DDD6]">
      <div className="max-w-7xl mx-auto px-4 md:px-8 lg:px-12 py-5 md:py-4">
        <ul className="grid grid-cols-2 md:flex md:justify-between gap-y-4 gap-x-4 md:gap-0">
          {SIGNALS.map(({ icon, label }) => (
            <li
              key={label}
              className="flex items-center gap-2 text-[12px] md:text-[13px] font-semibold text-[#504534]"
            >
              <span className="text-[#C9920A] text-[15px] leading-none" aria-hidden="true">
                {icon}
              </span>
              {label}
            </li>
          ))}
        </ul>
      </div>
    </section>
  )
}

export default TrustBar
