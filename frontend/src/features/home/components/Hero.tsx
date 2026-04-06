import React, { useMemo } from 'react'
import { HERO_IMAGE_URL } from '../home.mock'

/**
 * Hero — full-bleed hero section (Stitch spec).
 *
 * Mobile  (<md): bottom-aligned content, bottom-to-top gradient
 * Desktop (≥md): bottom-left content, left-to-right gradient, larger type
 * Eyebrow badge: social-proof pill above H1
 * H1: font-serif italic, responsive sizing
 * Ken Burns: disabled when prefers-reduced-motion: reduce
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
      className="relative h-[100svh] min-h-[600px] overflow-hidden"
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

      {/* Mobile: bottom-to-top gradient */}
      <div
        className="absolute inset-0 md:hidden"
        style={{
          background:
            'linear-gradient(to top, rgba(26,22,18,0.82) 0%, rgba(26,22,18,0.28) 55%, transparent 100%)',
        }}
        aria-hidden="true"
      />

      {/* Desktop: left-to-right gradient */}
      <div
        className="absolute inset-0 hidden md:block"
        style={{
          background:
            'linear-gradient(to right, rgba(26,22,18,0.80) 0%, rgba(26,22,18,0.40) 55%, transparent 100%)',
        }}
        aria-hidden="true"
      />

      {/* Content — bottom-left anchored, pb-28 to clear SearchCard overlap */}
      <div className="absolute bottom-0 left-0 z-10 px-5 md:px-12 lg:px-20 pb-28 md:pb-24 max-w-xs sm:max-w-sm md:max-w-[540px] lg:max-w-[680px]">
        {/* Eyebrow badge */}
        <div className="inline-flex items-center gap-1.5 bg-white/10 backdrop-blur-md border border-white/20 px-3 py-1.5 rounded-full text-white text-[11px] font-semibold tracking-wider mb-4">
          <span className="text-[#F9BC3D]" aria-hidden="true">
            ✦
          </span>
          Được đặt 240 lần trong 30 ngày qua
        </div>

        <h1 className="font-serif italic text-white leading-tight mb-3 text-[36px] md:text-[50px] lg:text-[62px]">
          Khám phá Huế theo cách của bạn
        </h1>
        <p className="text-white/75 text-[15px] md:text-[17px] leading-relaxed">
          Đặt phòng nhanh — không cần thẻ tín dụng
        </p>
      </div>
    </section>
  )
}

export default Hero
