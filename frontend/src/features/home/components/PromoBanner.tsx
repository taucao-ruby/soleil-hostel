import React from 'react'
import { Link } from 'react-router-dom'

/**
 * PromoBanner — wood gradient promotional section, spec § 05.
 *
 * Headline: "Ở dài, tiết kiệm hơn"
 * Subtext:  "Giảm 15% cho đặt phòng từ 5 đêm trở lên"
 * CTA:      outlined white button "Xem ưu đãi →"
 */
const PromoBanner: React.FC = () => {
  return (
    <section
      className="mx-4 mt-8 rounded-2xl overflow-hidden p-6"
      style={{ background: 'linear-gradient(135deg, #5C3D1E 0%, #8B6340 100%)' }}
      aria-label="Ưu đãi ở dài"
    >
      <p className="text-4xl mb-2" aria-hidden="true">
        🌙
      </p>

      <h2 className="font-serif font-bold text-white text-[22px] leading-snug mb-1">
        Ở dài, tiết kiệm hơn
      </h2>

      <p className="font-sans text-white/80 text-[13px] leading-relaxed mb-4">
        Giảm 15% cho đặt phòng từ 5 đêm trở lên
      </p>

      <Link
        to="/rooms"
        className="inline-block border border-white/60 text-white text-sm font-medium rounded-xl px-6 py-2.5 mt-0 hover:border-white transition-colors duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white"
      >
        Xem ưu đãi →
      </Link>
    </section>
  )
}

export default PromoBanner
