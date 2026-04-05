import React from 'react'
import { Link } from 'react-router-dom'

/**
 * PromoBanner — editorial promotional strip (PROMPT_1A spec).
 *
 * Mobile: compact banner with text + CTA button
 * Desktop: wider layout with more breathing room, max-w-7xl container
 */
const PromoBanner: React.FC = () => {
  return (
    <section aria-label="Ưu đãi mùa hè" className="mt-10 md:mt-16">
      <div className="max-w-7xl mx-auto px-4 md:px-8 lg:px-12">
        <div
          className="rounded-2xl overflow-hidden px-6 py-5 md:px-10 md:py-8 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4"
          style={{ background: 'linear-gradient(135deg, #C9973A 0%, #a07830 100%)' }}
        >
          <div>
            <p className="font-sans font-semibold text-white text-[15px] md:text-[18px] lg:text-[20px] leading-snug">
              Ưu đãi mùa hè: Giảm 10% khi đặt phòng trực tiếp
            </p>
            <p className="mt-1 text-white/70 text-sm md:text-[15px] hidden sm:block">
              Áp dụng cho tất cả phòng khi đặt trước 30/06/2026
            </p>
          </div>
          <Link
            to="/rooms"
            className="shrink-0 bg-white text-[#1C1A17] text-sm md:text-[15px] font-semibold rounded-xl px-5 py-2.5 md:px-6 md:py-3 hover:bg-[#F7F3EE] transition-colors duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white"
          >
            Xem ưu đãi
          </Link>
        </div>
      </div>
    </section>
  )
}

export default PromoBanner
