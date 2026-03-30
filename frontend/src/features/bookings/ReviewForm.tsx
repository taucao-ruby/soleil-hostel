import React, { useState } from 'react'
import { submitReview } from '@/features/booking/booking.api'
import Button from '@/shared/components/ui/Button'

interface ReviewFormProps {
  bookingId: number
}

// ── Star rating ──────────────────────────────────────────────
interface StarRatingProps {
  value: number
  hovered: number
  onRate: (v: number) => void
  onHover: (v: number) => void
  onHoverEnd: () => void
  disabled: boolean
}

const StarRating: React.FC<StarRatingProps> = ({
  value,
  hovered,
  onRate,
  onHover,
  onHoverEnd,
  disabled,
}) => (
  <div className="flex gap-1" role="radiogroup" aria-label="Xếp hạng sao">
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
        className={`text-2xl leading-none transition-colors disabled:cursor-not-allowed ${
          (hovered || value) >= star ? 'text-yellow-400' : 'text-gray-300'
        }`}
      >
        ★
      </button>
    ))}
  </div>
)

// ── ReviewForm ───────────────────────────────────────────────
const ReviewForm: React.FC<ReviewFormProps> = ({ bookingId }) => {
  const [isOpen, setIsOpen] = useState(false)
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
      <div className="mt-4 pt-4 border-t border-gray-100">
        <div className="p-3 bg-green-50 border border-green-200 rounded-lg">
          <p className="text-sm font-medium text-green-800">
            Đánh giá của bạn đã được gửi thành công.
          </p>
          <p className="text-xs text-green-700 mt-1">Cảm ơn bạn đã chia sẻ trải nghiệm!</p>
        </div>
      </div>
    )
  }

  // ── Collapsed trigger ────────────────────────────────────
  if (!isOpen) {
    return (
      <div className="mt-4 pt-4 border-t border-gray-100">
        <Button
          variant="outline"
          size="sm"
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
      await submitReview({ booking_id: bookingId, title, content, rating })
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
    <div className="mt-4 pt-4 border-t border-gray-100">
      <h3 className="text-sm font-semibold text-gray-900 mb-3">Viết đánh giá</h3>
      <form onSubmit={handleSubmit} noValidate>
        {/* Star rating */}
        <div className="mb-3">
          <label className="block text-xs text-gray-500 mb-1">Xếp hạng</label>
          <StarRating
            value={rating}
            hovered={hovered}
            onRate={setRating}
            onHover={setHovered}
            onHoverEnd={() => setHovered(0)}
            disabled={isLoading}
          />
        </div>

        {/* Title */}
        <div className="mb-3">
          <label htmlFor={`review-title-${bookingId}`} className="block text-xs text-gray-500 mb-1">
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
            className="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-50"
          />
        </div>

        {/* Content */}
        <div className="mb-3">
          <label
            htmlFor={`review-content-${bookingId}`}
            className="block text-xs text-gray-500 mb-1"
          >
            Nội dung <span aria-hidden="true">*</span>
          </label>
          <textarea
            id={`review-content-${bookingId}`}
            value={content}
            onChange={e => setContent(e.target.value)}
            maxLength={5000}
            required
            rows={3}
            disabled={isLoading}
            placeholder="Chia sẻ chi tiết trải nghiệm của bạn..."
            className="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-50 resize-none"
          />
        </div>

        {/* Error */}
        {error && (
          <p role="alert" className="text-xs text-red-600 mb-3">
            {error}
          </p>
        )}

        {/* Actions */}
        <div className="flex gap-2">
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
        </div>
      </form>
    </div>
  )
}

export default ReviewForm
