import React from 'react'
import { Link } from 'react-router-dom'

/**
 * PromoBanner — full-width announcement strip above the hero (Stitch spec).
 *
 * Renders as a slim gold horizontal bar: text centred + pill CTA.
 * No container cap — bleeds edge-to-edge.
 */
const PromoBanner: React.FC = () => {
  return (
    <div
      role="banner"
      aria-label="Ưu đãi mùa hè"
      className="w-full bg-[#C9920A] py-2 px-4 flex items-center justify-center gap-3 flex-wrap"
    >
      <span className="text-white text-[12px] font-semibold tracking-wide text-center">
        🌞 Ưu đãi mùa hè: Giảm 10% khi đặt phòng trực tiếp · HSD 30/06/2026
      </span>
      <Link
        to="/rooms"
        className="shrink-0 bg-white text-[#7c5800] px-3 py-0.5 rounded-full text-[11px] font-extrabold uppercase tracking-tight hover:bg-[#F5EFE0] transition-colors duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-1 focus-visible:ring-offset-[#C9920A]"
      >
        Xem ưu đãi
      </Link>
    </div>
  )
}

export default PromoBanner
