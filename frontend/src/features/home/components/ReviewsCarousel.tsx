import React from 'react'
import type { HomeReview } from '../home.types'

interface ReviewsCarouselProps {
  reviews: HomeReview[]
}

/**
 * ReviewsCarousel — guest reviews section (Stitch spec).
 *
 * Mobile  (<md): Dark bg section, stacked cards with gold avatar initials
 * Desktop (≥md): Cream bg, centred heading + 5 gold stars, 3-col grid, white cards
 */
const ReviewsCarousel: React.FC<ReviewsCarouselProps> = ({ reviews }) => {
  return (
    <section aria-label="Đánh giá của khách">
      {/* ── Mobile layout (<md): dark background ───────────────────────────── */}
      <div className="md:hidden bg-[#1C1A17] text-white py-12 px-4">
        <h2 className="font-serif italic text-3xl mb-8">
          Tiếng nói từ
          <br />
          lữ khách
        </h2>
        <div className="space-y-6" role="list">
          {reviews.map(review => (
            <ReviewCardDark key={review.id} review={review} />
          ))}
        </div>
      </div>

      {/* ── Desktop layout (≥md): cream background ─────────────────────────── */}
      <div className="hidden md:block bg-[#F6ECE5] py-20">
        <div className="max-w-7xl mx-auto px-8 lg:px-12">
          {/* Centred heading */}
          <div className="text-center mb-14">
            <h2 className="font-serif italic text-[#1C1A17] text-3xl lg:text-4xl mb-4">
              Trải Nghiệm Khách Hàng
            </h2>
            <div className="flex justify-center gap-1 text-[#C9920A]" aria-label="5 sao">
              {Array.from({ length: 5 }).map((_, i) => (
                <svg
                  key={i}
                  viewBox="0 0 20 20"
                  fill="currentColor"
                  className="w-5 h-5"
                  aria-hidden="true"
                >
                  <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                </svg>
              ))}
            </div>
          </div>

          <div className="grid grid-cols-3 gap-6" role="list">
            {reviews.map(review => (
              <ReviewCardLight key={review.id} review={review} />
            ))}
          </div>
        </div>
      </div>
    </section>
  )
}

/** Derive initials from a guest name (up to 2 chars). */
function initials(name: string): string {
  return name
    .split(' ')
    .map(w => w[0])
    .slice(0, 2)
    .join('')
    .toUpperCase()
}

/** Dark variant used on mobile. */
const ReviewCardDark: React.FC<{ review: HomeReview }> = ({ review }) => (
  <article role="listitem" className="space-y-4">
    <div className="flex items-center gap-3">
      <div
        className="w-10 h-10 rounded-full bg-[#C9920A] flex items-center justify-center font-bold text-white text-sm shrink-0"
        aria-hidden="true"
      >
        {initials(review.guestName)}
      </div>
      <div>
        <p className="text-sm font-bold">{review.guestName}</p>
        <div className="flex gap-0.5 mt-0.5" aria-label={`${review.rating} sao`}>
          {Array.from({ length: review.rating }).map((_, i) => (
            <svg
              key={i}
              viewBox="0 0 20 20"
              fill="currentColor"
              className="w-3 h-3 text-[#C9920A]"
              aria-hidden="true"
            >
              <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
            </svg>
          ))}
        </div>
      </div>
    </div>
    <p className="text-white/75 text-sm italic leading-relaxed">"{review.quote}"</p>
  </article>
)

/** Light variant used on desktop. */
const ReviewCardLight: React.FC<{ review: HomeReview }> = ({ review }) => (
  <article role="listitem" className="bg-white rounded-2xl p-8 flex flex-col">
    <div className="flex items-center gap-4 mb-6">
      <div
        className="w-10 h-10 rounded-full bg-[#C9920A] flex items-center justify-center font-bold text-white text-lg shrink-0"
        aria-hidden="true"
      >
        {initials(review.guestName)}
      </div>
      <div>
        <p className="font-bold text-[#1C1A17]">{review.guestName}</p>
        <p className="text-[10px] uppercase tracking-widest font-bold text-[#C9920A]">
          Đã xác thực
        </p>
      </div>
    </div>

    <p className="italic text-sm text-[#1C1A17]/80 leading-relaxed flex-1 mb-5">"{review.quote}"</p>

    <div className="flex flex-wrap gap-2">
      <span className="px-3 py-1 bg-[#F5EFE3] text-[#504534] text-[10px] font-bold rounded-full">
        {review.guestLocation}
      </span>
      <span className="px-3 py-1 bg-[#FAE5D8] text-[#D4622A] text-[10px] font-bold rounded-full">
        {review.roomName}
      </span>
    </div>
  </article>
)

export default ReviewsCarousel
