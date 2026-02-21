import React, { useMemo } from 'react'
import { HERO_IMAGE_URL } from '../home.mock'

/**
 * Hero — full-bleed hero section.
 *
 * Defect fixes:
 *   C-01: Brand name rendered ONLY in StickyHeader. Zero decorative text layers here.
 *   C-02: Real hostel photo via <img> object-cover + black/40 overlay.
 *   H-01: "Cuộn xuống" scroll indicator removed entirely.
 *   H-02: No CTA button in hero — ONE CTA lives in SearchCard only.
 *   H-03: Location displayed as a proper pill with role="status".
 *   M-01: Content vertically centered via flex items-center justify-center.
 *   M-03: No hero button. CTA is in SearchCard.
 *
 * Ken Burns animation disabled when prefers-reduced-motion: reduce.
 */
const Hero: React.FC = () => {
  const prefersReduced = useMemo(
    () => window.matchMedia('(prefers-reduced-motion: reduce)').matches,
    []
  )

  return (
    <section
      data-testid="hero-section"
      className="relative h-[100svh] min-h-[520px] overflow-hidden"
      aria-label="Soleil Hostel Huế — trang chủ"
    >
      {/* Real hostel photo — FIX C-02: actual photo, not flat brown placeholder */}
      <img
        src={HERO_IMAGE_URL}
        alt="Không gian ấm cúng của Soleil Hostel Huế"
        className={[
          'absolute inset-0 w-full h-full object-cover object-center',
          prefersReduced ? '' : 'animate-ken-burns',
        ].join(' ')}
        aria-hidden="true"
      />

      {/* Dark overlay for legibility — spec: bg-black/40 */}
      <div className="absolute inset-0 bg-black/40" aria-hidden="true" />

      {/* Hero content — vertically + horizontally centered — FIX M-01 */}
      <div className="relative z-10 flex flex-col items-center justify-center h-full px-6 text-center gap-4">
        {/* Location pill — FIX H-03: proper pill + role="status" + ☀️ prefix */}
        <span
          role="status"
          className="inline-flex items-center gap-1 bg-[#F5EFE0] text-[#5C3D1E] rounded-full px-4 py-1.5 text-sm font-medium font-sans"
        >
          ☀️ Huế · Việt Nam
        </span>

        {/* H1 — FIX C-01: brand name is ONLY in header. No "Soleil/Hostel" here. */}
        <h1 className="font-serif font-bold text-white text-4xl leading-tight max-w-xs drop-shadow-lg">
          Nơi nghỉ ngơi của bạn tại Huế
        </h1>

        {/* Subtitle */}
        <p className="font-sans text-white/80 text-base max-w-xs leading-relaxed">
          Không gian ấm cúng, giá cả phải chăng
        </p>

        {/* FIX H-02 + M-03: NO CTA button here. The ONE CTA lives in SearchCard. */}
      </div>
    </section>
  )
}

export default Hero
