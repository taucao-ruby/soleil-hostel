import React from 'react'
import { Link } from 'react-router-dom'

/**
 * PromoBanner — amber promotional banner (PROMPT_1A spec).
 *
 * Spec: brand amber #C9973A bg, horizontal layout with CTA button.
 */
const PromoBanner: React.FC = () => {
  return (
    <section
      className="mx-4 mt-8 rounded-2xl overflow-hidden px-5 py-4 flex items-center justify-between gap-3"
      style={{ background: '#C9973A' }}
      aria-label="Ưu đãi mùa hè"
    >
      <p className="font-sans font-medium text-white text-[15px] leading-snug">
        Ưu đãi mùa hè: Giảm 10% khi đặt phòng trực tiếp
      </p>
      <Link
        to="/rooms"
        className="flex-shrink-0 bg-white text-[#1C1A17] text-sm font-medium rounded-xl px-4 py-2 hover:bg-[#F7F3EE] transition-colors duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white"
      >
        Xem ưu đãi
      </Link>
    </section>
  )
}

export default PromoBanner
