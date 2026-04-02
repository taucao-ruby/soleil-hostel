import React, { useState } from 'react'
import { submitReview } from '@/features/booking/booking.api'
import Button from '@/shared/components/ui/Button'

interface ReviewFormProps {
  bookingId: number
  defaultOpen?: boolean
  variant?: 'compact' | 'detail'
}

// ── Star rating ──────────────────────────────────────────────
interface StarRatingProps {
  value: number
  hovered: number
  onRate: (v: number) => void
  onHover: (v: number) => void
  onHoverEnd: () => void
  disabled: boolean
  large?: boolean
}

const StarRating: React.FC<StarRatingProps> = ({
  value,
  hovered,
  onRate,
  onHover,
  onHoverEnd,
  disabled,
  large = false,
}) => (
  <div className={`flex ${large ? 'gap-2' : 'gap-1'}`} role="radiogroup" aria-label="Xếp hạng sao">
    {[1, 2, 3, 4, 5].map(star => (
      <button
        key={star}
        type="button"
        role="radio"
        aria-checked={value === star}
        aria-label={`${star} sao`}
        disabled={disabled}
        onClick={() => onRate(star)}
        onMouseEnter={() => onHover(star)}
        onMouseLeave={onHoverEnd}
        className={`${large ? 'text-5xl' : 'text-2xl'} leading-none transition-colors disabled:cursor-not-allowed ${
          (hovered || value) >= star ? 'text-yellow-400' : 'text-gray-300'
        }`}
      >
        ★
      </button>
    ))}
  </div>
)

// ── ReviewForm ───────────────────────────────────────────────
const REVIEW_LABELS: Record<number, string> = {
  1: 'Cần cải thiện',
  2: 'Chưa như kỳ vọng',
  3: 'Hài lòng',
  4: 'Rất tốt',
  5: 'Xuất sắc',
}

function getAutoTitle(rating: number): string {
  return REVIEW_LABELS[rating] ?? 'Đánh giá đặt phòng'
}

const ReviewForm: React.FC<ReviewFormProps> = ({
  bookingId,
  defaultOpen = false,
  variant = 'compact',
}) => {
  const isDetail = variant === 'detail'
  const [isOpen, setIsOpen] = useState(defaultOpen)
  const [submitted, setSubmitted] = useState(false)
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const [rating, setRating] = useState(0)
  const [hovered, setHovered] = useState(0)
  const [title, setTitle] = useState('')
  const [content, setContent] = useState('')

  // ── Success state ────────────────────────────────────────
  if (submitted) {
    return (
      <div className={isDetail ? 'pt-8' : 'mt-4 pt-4 border-t border-gray-100'}>
        <div
          className={
            isDetail
              ? 'rounded-2xl border border-green-200 bg-gradient-to-br from-green-50 via-white to-emerald-50 px-6 py-8 text-center'
              : 'rounded-lg border border-green-200 bg-green-50 p-3'
          }
        >
          <p
            className={
              isDetail
                ? 'text-lg font-semibold text-green-800'
                : 'text-sm font-medium text-green-800'
            }
          >
            Đánh giá của bạn đã được gửi thành công.
          </p>
          <p className={isDetail ? 'mt-2 text-sm text-green-700' : 'mt-1 text-xs text-green-700'}>
            Cảm ơn bạn đã chia sẻ trải nghiệm!
          </p>
        </div>
      </div>
    )
  }

  // ── Collapsed trigger ────────────────────────────────────
  if (!isOpen) {
    return (
      <div className={isDetail ? 'pt-8' : 'mt-4 pt-4 border-t border-gray-100'}>
        <Button
          variant={isDetail ? 'secondary' : 'outline'}
          size={isDetail ? 'md' : 'sm'}
          onClick={() => setIsOpen(true)}
          aria-label="Mở form viết đánh giá"
        >
          Viết đánh giá
        </Button>
      </div>
    )
  }

  // ── Submit handler ───────────────────────────────────────
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError(null)

    if (rating === 0) {
      setError('Vui lòng chọn số sao đánh giá.')
      return
    }

    setIsLoading(true)
    try {
      const resolvedTitle = isDetail && title.trim().length === 0 ? getAutoTitle(rating) : title

      await submitReview({ booking_id: bookingId, title: resolvedTitle, content, rating })
      setSubmitted(true)
    } catch (err: unknown) {
      const axiosErr = err as {
        response?: {
          status?: number
          data?: { message?: string; errors?: Record<string, string[]> }
        }
      }
      const status = axiosErr?.response?.status
      if (status === 403) {
        // Policy denial — booking not eligible or already reviewed
        setError(
          axiosErr.response?.data?.message ?? 'Bạn không được phép viết đánh giá cho đặt phòng này.'
        )
      } else if (status === 422) {
        const validationErrors = axiosErr.response?.data?.errors
        if (validationErrors) {
          const firstError = Object.values(validationErrors)[0]?.[0]
          setError(firstError ?? 'Dữ liệu không hợp lệ.')
        } else {
          setError(axiosErr.response?.data?.message ?? 'Dữ liệu không hợp lệ.')
        }
      } else {
        setError('Không thể gửi đánh giá. Vui lòng thử lại.')
      }
    } finally {
      setIsLoading(false)
    }
  }

  // ── Form ─────────────────────────────────────────────────
  return (
    <div className={isDetail ? 'pt-8' : 'mt-4 border-t border-gray-100 pt-4'}>
      {!isDetail && <h3 className="mb-3 text-sm font-semibold text-gray-900">Viết đánh giá</h3>}
      <form onSubmit={handleSubmit} noValidate>
        {/* Star rating */}
        <div className={isDetail ? 'mb-6 text-center' : 'mb-3'}>
          <label
            className={`block ${isDetail ? 'mb-4 text-base font-semibold text-gray-900' : 'mb-1 text-xs text-gray-500'}`}
          >
            {isDetail ? 'Đánh giá của bạn' : 'Xếp hạng'}
          </label>
          <div className={isDetail ? 'flex justify-center' : ''}>
            <StarRating
              value={rating}
              hovered={hovered}
              onRate={setRating}
              onHover={setHovered}
              onHoverEnd={() => setHovered(0)}
              disabled={isLoading}
              large={isDetail}
            />
          </div>
          {isDetail && hovered + rating > 0 && (
            <p className="mt-4 inline-flex rounded-full bg-amber-100 px-3 py-1 text-sm font-medium text-amber-800">
              {REVIEW_LABELS[hovered || rating]}
            </p>
          )}
        </div>

        {/* Title */}
        {!isDetail && (
          <div className="mb-3">
            <label
              htmlFor={`review-title-${bookingId}`}
              className="mb-1 block text-xs text-gray-500"
            >
              Tiêu đề <span aria-hidden="true">*</span>
            </label>
            <input
              id={`review-title-${bookingId}`}
              type="text"
              value={title}
              onChange={e => setTitle(e.target.value)}
              maxLength={255}
              required
              disabled={isLoading}
              placeholder="Ngắn gọn trải nghiệm của bạn"
              className="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-50"
            />
          </div>
        )}

        {/* Content */}
        <div className={isDetail ? 'mb-5' : 'mb-3'}>
          <label
            htmlFor={`review-content-${bookingId}`}
            className={`block ${isDetail ? 'mb-2 text-sm text-gray-500' : 'mb-1 text-xs text-gray-500'}`}
          >
            {isDetail ? 'Chia sẻ trải nghiệm của bạn' : 'Nội dung'}{' '}
            <span aria-hidden="true">*</span>
          </label>
          <textarea
            id={`review-content-${bookingId}`}
            value={content}
            onChange={e => setContent(e.target.value)}
            maxLength={5000}
            required
            rows={isDetail ? 5 : 3}
            disabled={isLoading}
            placeholder="Chia sẻ trải nghiệm của bạn..."
            className={`w-full rounded-2xl border border-gray-300 px-4 py-3 text-sm focus:outline-none focus:ring-2 disabled:bg-gray-50 ${
              isDetail ? 'resize-y focus:ring-amber-400' : 'resize-none focus:ring-blue-500'
            }`}
          />
        </div>

        {/* Error */}
        {error && (
          <p role="alert" className="text-xs text-red-600 mb-3">
            {error}
          </p>
        )}

        {/* Actions */}
        <div className={isDetail ? 'space-y-3' : 'flex gap-2'}>
          {isDetail ? (
            <button
              type="submit"
              disabled={isLoading}
              aria-busy={isLoading}
              className="w-full rounded-xl bg-gradient-to-r from-amber-500 to-yellow-500 px-4 py-3 text-base font-semibold text-white transition-all hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-60"
            >
              {isLoading ? 'Đang gửi...' : 'Gửi đánh giá'}
            </button>
          ) : (
            <>
              <Button type="submit" size="sm" disabled={isLoading} aria-busy={isLoading}>
                {isLoading ? 'Đang gửi...' : 'Gửi đánh giá'}
              </Button>
              <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={() => {
                  setIsOpen(false)
                  setError(null)
                }}
                disabled={isLoading}
              >
                Hủy
              </Button>
            </>
          )}
        </div>
      </form>
    </div>
  )
}

export default ReviewForm
