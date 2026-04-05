import React from 'react'
import type { HomeReview } from '../home.types'

interface ReviewsCarouselProps {
  reviews: HomeReview[]
}

/**
 * ReviewsCarousel — guest reviews section.
 *
 * Mobile  (<md): Horizontally scrollable cards (w-[280px] flex-shrink-0)
 * Desktop (≥md): 3-column grid, full-width cards
 */
const ReviewsCarousel: React.FC<ReviewsCarouselProps> = ({ reviews }) => {
  return (
    <section className="mt-10 md:mt-16" aria-label="Đánh giá của khách">
      <div className="max-w-7xl mx-auto px-4 md:px-8 lg:px-12">
        {/* Heading */}
        <div className="mb-4 md:mb-8">
          <h2 className="font-serif font-semibold text-[#1C1A17] text-xl md:text-2xl lg:text-3xl">
            Khách nói gì?
          </h2>
          <p className="mt-1 text-sm text-[#6B6760] hidden md:block">
            Trải nghiệm thực tế từ những vị khách đã ở tại Soleil
          </p>
        </div>

        {/* ── Mobile: horizontal scroll ──────────────────────────────── */}
        <div
          className="flex md:hidden gap-4 overflow-x-auto -mx-4 px-4 pb-3"
          style={{ scrollbarWidth: 'none', msOverflowStyle: 'none' }}
          role="list"
        >
          {reviews.map(review => (
            <ReviewCard key={review.id} review={review} />
          ))}
        </div>

        {/* ── Desktop: 3-col grid ────────────────────────────────────── */}
        <div className="hidden md:grid md:grid-cols-3 gap-5 lg:gap-6" role="list">
          {reviews.map(review => (
            <ReviewCard key={review.id} review={review} />
          ))}
        </div>
      </div>
    </section>
  )
}

const ReviewCard: React.FC<{ review: HomeReview }> = ({ review }) => (
  <article
    role="listitem"
    className="flex-shrink-0 w-[280px] md:w-full bg-[#FDFAF3] rounded-2xl border border-[#E2D5C3] p-5 md:p-6 flex flex-col"
  >
    {/* Stars */}
    <div className="text-[15px] mb-3" aria-label={`${review.rating} sao`}>
      {'⭐'.repeat(review.rating)}
    </div>

    {/* Quote */}
    <p className="font-sans text-sm md:text-[15px] text-[#5C3D1E]/80 italic leading-relaxed line-clamp-3 flex-1 mb-4">
      "{review.quote}"
    </p>

    {/* Reviewer */}
    <div>
      <p className="font-sans font-semibold text-[#5C3D1E] text-sm mb-2">{review.guestName}</p>
      <div className="flex flex-wrap gap-1.5">
        <span className="inline-block bg-[#F5EFE0] text-[#5C3D1E] text-xs px-2.5 py-1 rounded-full">
          {review.guestLocation}
        </span>
        <span className="inline-block bg-[#FAE5D8] text-[#D4622A] text-xs px-2.5 py-1 rounded-full">
          {review.roomName}
        </span>
      </div>
    </div>
  </article>
)

export default ReviewsCarousel
