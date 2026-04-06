import React from 'react'
import Hero from '@/features/home/components/Hero'
import SearchCard from '@/features/home/components/SearchCard'
import TrustBar from '@/features/home/components/TrustBar'
import RoomsSection from '@/features/home/components/RoomsSection'
import PromoBanner from '@/features/home/components/PromoBanner'
import ReviewsCarousel from '@/features/home/components/ReviewsCarousel'
import SiteFooter from '@/features/home/components/SiteFooter'
import { MOCK_REVIEWS } from '@/features/home/home.mock'

/**
 * HomePage — Soleil Hostel mobile-first public landing page (Stitch spec).
 *
 * Section render order:
 *   01. HeaderMobile  — fixed, in PublicLayout (router.tsx)
 *   02. Hero          — full-bleed under fixed header, ken burns, eyebrow badge
 *   03. SearchCard    — −48px overlap on mobile, horizontal bar on desktop
 *   04. PromoBanner   — full-width gold strip (placed here because HeaderMobile is
 *                       fixed and bleeds over top content; Stitch uses sticky header)
 *   05. TrustBar      — 4 trust signals
 *   06. RoomsSection  — filter chips + room cards (3-col desktop)
 *   07. ReviewsCarousel — dark mobile / cream desktop
 *   08. SiteFooter
 *   09. BottomNav     — in PublicLayout (router.tsx)
 */
const HomePage: React.FC = () => {
  return (
    <main className="bg-[#F5EFE0]">
      {/* 02. Hero — full-bleed */}
      <Hero />

      {/* 03. SearchCard — overlaps hero by 48px on mobile; desktop floats naturally */}
      <div className="-mt-12 md:-mt-10 relative z-10 px-4 md:px-8 lg:px-12 max-w-7xl mx-auto">
        <SearchCard />
      </div>

      {/* 04. PromoBanner — full-width gold strip */}
      <PromoBanner />

      {/* 05. Trust Bar */}
      <TrustBar />

      {/* 06. Rooms */}
      <RoomsSection />

      {/* 07. Reviews */}
      <ReviewsCarousel reviews={MOCK_REVIEWS} />

      {/* 08. Footer (includes pb-20 for BottomNav clearance) */}
      <SiteFooter />
    </main>
  )
}

export default HomePage
