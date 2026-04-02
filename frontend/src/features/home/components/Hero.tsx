import React, { useMemo } from 'react'
import { HERO_IMAGE_URL } from '../home.mock'

/**
 * Hero — full-bleed hero section (PROMPT_1A spec).
 *
 * - Height: 52vh min 360px
 * - H1 bottom-left: "Khám phá Huế theo cách của bạn" — white, 26px, weight 500
 * - Subline: "Đặt phòng nhanh — không cần thẻ tín dụng" — white, 14px, 70% opacity
 * - Bottom-to-top gradient overlay for text legibility
 * - Ken Burns disabled when prefers-reduced-motion: reduce
 *
 * Regression guards:
 *   C-01: No "Soleil" or "Hostel" as DOM text content in this section
 *   C-02: Real hostel photo via <img> object-cover (aria-hidden)
 */
const Hero: React.FC = () => {
  const prefersReduced = useMemo(
    () => window.matchMedia('(prefers-reduced-motion: reduce)').matches,
    []
  )

  return (
    <section
      data-testid="hero-section"
      className="relative h-[52vh] min-h-[360px] overflow-hidden"
      aria-label="Trang chủ Soleil Hostel Huế"
    >
      {/* Real hostel photo — C-02 guard: must be a real URL, not placehold.co */}
      <img
        src={HERO_IMAGE_URL}
        alt="Không gian ấm cúng của Soleil Hostel Huế"
        className={[
          'absolute inset-0 w-full h-full object-cover object-center',
          prefersReduced ? '' : 'animate-ken-burns',
        ].join(' ')}
        aria-hidden="true"
      />

      {/* Bottom-to-top gradient for text legibility */}
      <div
        className="absolute inset-0"
        style={{
          background:
            'linear-gradient(to top, rgba(0,0,0,0.72) 0%, rgba(0,0,0,0.25) 55%, transparent 100%)',
        }}
        aria-hidden="true"
      />

      {/* Content — bottom-left anchored */}
      <div className="absolute bottom-0 left-0 z-10 px-5 pb-6 max-w-[280px]">
        <h1 className="text-white leading-tight mb-2" style={{ fontSize: '26px', fontWeight: 500 }}>
          Khám phá Huế theo cách của bạn
        </h1>
        <p className="text-white/70 text-sm leading-relaxed">
          Đặt phòng nhanh — không cần thẻ tín dụng
        </p>
      </div>
    </section>
  )
}

export default Hero
