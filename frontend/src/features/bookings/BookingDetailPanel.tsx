import React, { useEffect, useRef, useState } from 'react'
import { getBookingById } from '@/features/booking/booking.api'
import type { BookingDetailRaw } from '@/features/booking/booking.types'
import { getStatusConfig, formatDateVN } from './booking.constants'
import Skeleton from '@/shared/components/ui/Skeleton'
import Button from '@/shared/components/ui/Button'

// ── Props ────────────────────────────────────────────────────
interface BookingDetailPanelProps {
  /** ID of the booking to load. Pass null to reset panel state. */
  bookingId: number | null
  open: boolean
  onClose: () => void
}

// ── Detail content ───────────────────────────────────────────
interface DetailContentProps {
  booking: BookingDetailRaw
}

const DetailContent: React.FC<DetailContentProps> = ({ booking }) => {
  const statusConfig = getStatusConfig(booking.status)
  const checkInDate = new Date(booking.check_in)
  const checkOutDate = new Date(booking.check_out)
  const createdAtDate = new Date(booking.created_at)

  return (
    <dl className="space-y-4 text-sm">
      {/* Room */}
      {booking.room && (
        <div className="flex justify-between gap-4">
          <dt className="text-gray-500 shrink-0">Phòng</dt>
          <dd className="text-gray-900 text-right font-medium">
            {booking.room.name}
            {booking.room.room_number ? ` (#${booking.room.room_number})` : ''}
          </dd>
        </div>
      )}

      {/* Dates */}
      <div className="flex justify-between gap-4">
        <dt className="text-gray-500 shrink-0">Nhận phòng</dt>
        <dd className="text-gray-900 text-right">{formatDateVN(checkInDate)}</dd>
      </div>
      <div className="flex justify-between gap-4">
        <dt className="text-gray-500 shrink-0">Trả phòng</dt>
        <dd className="text-gray-900 text-right">{formatDateVN(checkOutDate)}</dd>
      </div>

      {/* Nights */}
      <div className="flex justify-between gap-4">
        <dt className="text-gray-500 shrink-0">Số đêm</dt>
        <dd className="text-gray-900 text-right">{booking.nights} đêm</dd>
      </div>

      {/* Status */}
      <div className="flex justify-between gap-4">
        <dt className="text-gray-500 shrink-0">Trạng thái</dt>
        <dd>
          <span
            className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusConfig.colorClass}`}
          >
            {booking.status_label ?? statusConfig.label}
          </span>
        </dd>
      </div>

      {/* Guest */}
      <div className="flex justify-between gap-4">
        <dt className="text-gray-500 shrink-0">Khách</dt>
        <dd className="text-gray-900 text-right">{booking.guest_name}</dd>
      </div>
      <div className="flex justify-between gap-4">
        <dt className="text-gray-500 shrink-0">Email</dt>
        <dd className="text-gray-900 text-right break-all">{booking.guest_email}</dd>
      </div>

      {/* Amount */}
      {booking.amount_formatted && (
        <div className="flex justify-between gap-4">
          <dt className="text-gray-500 shrink-0">Tổng tiền</dt>
          <dd className="text-gray-900 text-right font-medium">{booking.amount_formatted}</dd>
        </div>
      )}

      {/* Refund (cancelled with refund) */}
      {booking.refund_amount_formatted && (
        <div className="flex justify-between gap-4">
          <dt className="text-gray-500 shrink-0">Hoàn tiền</dt>
          <dd className="text-gray-900 text-right">{booking.refund_amount_formatted}</dd>
        </div>
      )}

      {/* Cancelled at */}
      {booking.cancelled_at && (
        <div className="flex justify-between gap-4">
          <dt className="text-gray-500 shrink-0">Hủy lúc</dt>
          <dd className="text-gray-900 text-right">
            {formatDateVN(new Date(booking.cancelled_at))}
          </dd>
        </div>
      )}

      {/* Created at */}
      <div className="flex justify-between gap-4 pt-2 border-t border-gray-100">
        <dt className="text-gray-500 shrink-0">Ngày đặt</dt>
        <dd className="text-gray-900 text-right">{formatDateVN(createdAtDate)}</dd>
      </div>
    </dl>
  )
}

// ── Loading skeleton ─────────────────────────────────────────
const DetailSkeleton: React.FC = () => (
  <div className="space-y-4">
    {[80, 100, 100, 60, 100, 100, 80].map((w, i) => (
      <Skeleton key={i} width={`${w}%`} height="1rem" rounded="sm" />
    ))}
  </div>
)

// ── BookingDetailPanel ───────────────────────────────────────
const BookingDetailPanel: React.FC<BookingDetailPanelProps> = ({ bookingId, open, onClose }) => {
  const [booking, setBooking] = useState<BookingDetailRaw | null>(null)
  const [isLoading, setIsLoading] = useState(false)
  const [isError, setIsError] = useState(false)
  const [retryCount, setRetryCount] = useState(0)
  const mountedRef = useRef(true)

  // Escape key closes panel
  useEffect(() => {
    if (!open) return
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose()
    }
    document.addEventListener('keydown', handleKeyDown)
    return () => document.removeEventListener('keydown', handleKeyDown)
  }, [open, onClose])

  // Fetch booking detail
  useEffect(() => {
    if (!open || bookingId === null) {
      setBooking(null)
      return
    }

    mountedRef.current = true
    const controller = new AbortController()

    setIsLoading(true)
    setIsError(false)
    setBooking(null)

    getBookingById(bookingId, controller.signal)
      .then(data => {
        if (mountedRef.current) {
          setBooking(data)
          setIsLoading(false)
        }
      })
      .catch(err => {
        if (err instanceof DOMException && err.name === 'AbortError') return
        if (mountedRef.current) {
          setIsError(true)
          setIsLoading(false)
        }
      })

    return () => {
      mountedRef.current = false
      controller.abort()
    }
  }, [open, bookingId, retryCount])

  if (!open) return null

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
      onClick={onClose}
      role="dialog"
      aria-modal="true"
      aria-labelledby="booking-detail-title"
    >
      <div
        className="w-full max-w-lg bg-white rounded-xl shadow-xl overflow-y-auto max-h-[90vh]"
        onClick={e => e.stopPropagation()}
      >
        {/* Header */}
        <div className="px-6 py-4 border-b border-gray-200">
          <h2 id="booking-detail-title" className="text-lg font-semibold text-gray-900">
            Chi tiết đặt phòng
            {bookingId !== null ? ` #${bookingId}` : ''}
          </h2>
        </div>

        {/* Body */}
        <div className="px-6 py-5">
          {isLoading && <DetailSkeleton />}

          {isError && !isLoading && (
            <div className="text-center py-4">
              <p className="text-red-600 mb-3">Không thể tải chi tiết đặt phòng.</p>
              <Button variant="outline" size="sm" onClick={() => setRetryCount(c => c + 1)}>
                Thử lại
              </Button>
            </div>
          )}

          {!isLoading && !isError && booking && <DetailContent booking={booking} />}
        </div>

        {/* Footer */}
        <div className="flex justify-end px-6 py-4 border-t border-gray-100">
          <Button variant="ghost" size="sm" onClick={onClose}>
            Đóng
          </Button>
        </div>
      </div>
    </div>
  )
}

export default BookingDetailPanel
