import React from 'react'
import Hero from '@/features/home/components/Hero'
import SearchCard from '@/features/home/components/SearchCard'
import RoomsSection from '@/features/home/components/RoomsSection'
import PromoBanner from '@/features/home/components/PromoBanner'
import ReviewsCarousel from '@/features/home/components/ReviewsCarousel'
import SiteFooter from '@/features/home/components/SiteFooter'
import { MOCK_REVIEWS } from '@/features/home/home.mock'

/**
 * HomePage — Soleil Hostel mobile-first public landing page.
 *
 * Section render order (spec §§ 01–08):
 *   01. StickyHeader  — in PublicLayout (router.tsx)
 *   02. Hero          — full-bleed, ken burns, no brand name, no CTA button
 *   03. SearchCard    — −48px overlap, THE only "Tìm phòng trống"
 *   04. RoomsSection  — filter chips + room cards
 *   05. PromoBanner   — "Ở dài, tiết kiệm hơn"
 *   06. ReviewsCarousel
 *   07. SiteFooter
 *   08. BottomNav     — in PublicLayout (router.tsx)
 */
const HomePage: React.FC = () => {
  return (
    <main className="bg-[#F5EFE0]">
      {/* 02. Hero — full-bleed */}
      <Hero />

      {/* 03. SearchCard — overlaps hero by 48px (FIX C-03) */}
      <div className="-mt-12 relative z-10 px-4">
        <SearchCard />
      </div>

      {/* 04. Rooms */}
      <RoomsSection />

      {/* 05. Promo */}
      <PromoBanner />

      {/* 06. Reviews */}
      <ReviewsCarousel reviews={MOCK_REVIEWS} />

      {/* 07. Footer (includes pb-20 for BottomNav clearance) */}
      <SiteFooter />
    </main>
  )
}

export default HomePage
