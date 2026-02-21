import React from 'react'
import type { HomeReview } from '../home.types'

interface ReviewsCarouselProps {
  reviews: HomeReview[]
}

/**
 * ReviewsCarousel — horizontally scrollable guest review cards, spec § 06.
 * Adapted for new HomeReview type: quote, guestName, guestLocation, roomName.
 */
const ReviewsCarousel: React.FC<ReviewsCarouselProps> = ({ reviews }) => {
  return (
    <section className="mt-8" aria-label="Đánh giá của khách">
      <h2 className="font-serif font-semibold text-[#5C3D1E] text-xl px-4 mb-4">Khách nói gì?</h2>

      {/* Horizontal scroll — no visible scrollbar */}
      <div
        className="flex gap-4 overflow-x-auto px-4 pb-2"
        style={{ scrollbarWidth: 'none', msOverflowStyle: 'none' }}
        role="list"
      >
        {reviews.map(review => (
          <article
            key={review.id}
            role="listitem"
            className="flex-shrink-0 w-[280px] bg-[#FDFAF3] rounded-2xl border border-[#E2D5C3] p-4"
          >
            {/* Star rating */}
            <div className="text-sm mb-2" aria-label={`${review.rating} sao`}>
              {'⭐'.repeat(review.rating)}
            </div>

            {/* Quote */}
            <p className="font-sans text-sm text-[#5C3D1E]/80 italic leading-relaxed line-clamp-3 mb-3">
              "{review.quote}"
            </p>

            {/* Reviewer name */}
            <p className="font-sans font-medium text-[#5C3D1E] text-xs mb-2">{review.guestName}</p>

            {/* Location pill */}
            <span className="inline-block bg-[#F5EFE0] text-[#5C3D1E] text-xs px-2 py-0.5 rounded-full mr-1">
              {review.guestLocation}
            </span>

            {/* Room badge */}
            <span className="inline-block bg-[#FAE5D8] text-[#D4622A] text-xs px-2 py-0.5 rounded-full mt-1">
              {review.roomName}
            </span>
          </article>
        ))}
      </div>
    </section>
  )
}

export default ReviewsCarousel
