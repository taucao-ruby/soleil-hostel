import React, { useEffect, useState } from 'react'
import { Link, useLocation, useNavigate, useParams } from 'react-router-dom'
import { cancelBooking, getBookingById } from '@/features/booking/booking.api'
import type { BookingDetailRaw } from '@/features/booking/booking.types'
import { formatVND } from '@/shared/lib/formatCurrency'
import { getErrorMessage, showToast } from '@/shared/utils/toast'
import BookingDetailPanel from './BookingDetailPanel'
import ReviewForm from './ReviewForm'

const DETAIL_STATUS_STYLES: Record<string, string> = {
  pending:
    'bg-amber-50 text-amber-800 border border-amber-200 text-sm px-3 py-1 rounded-full font-medium',
  confirmed:
    'bg-green-50 text-green-800 border border-green-200 text-sm px-3 py-1 rounded-full font-medium',
  cancelled:
    'bg-gray-100 text-gray-600 border border-gray-200 text-sm px-3 py-1 rounded-full font-medium',
  refund_pending:
    'bg-blue-50 text-blue-800 border border-blue-200 text-sm px-3 py-1 rounded-full font-medium',
  refund_failed:
    'bg-orange-50 text-orange-800 border border-orange-200 text-sm px-3 py-1 rounded-full font-medium',
}

function buildBookingReference(booking: Pick<BookingDetailRaw, 'id' | 'created_at'>): string {
  const createdAt = new Date(booking.created_at)
  const year = Number.isNaN(createdAt.getFullYear())
    ? new Date().getFullYear()
    : createdAt.getFullYear()
  return `SOL-${year}-${String(booking.id).padStart(4, '0')}`
}

function capitalizeFirstLetter(value: string): string {
  if (!value) return value
  return value.charAt(0).toUpperCase() + value.slice(1)
}

function formatDetailDate(dateString: string): string {
  return capitalizeFirstLetter(
    new Intl.DateTimeFormat('vi-VN', {
      weekday: 'long',
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
    }).format(new Date(dateString))
  )
}

function formatDetailDateTime(dateString: string): string {
  return new Intl.DateTimeFormat('vi-VN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).format(new Date(dateString))
}

function formatAmount(booking: BookingDetailRaw): string {
  if (typeof booking.amount === 'number') {
    return formatVND(booking.amount).replace(/\s?₫/, 'đ')
  }

  return booking.amount_formatted?.replace(/\s?₫/, 'đ') ?? '—'
}

function getRoomLabel(booking: BookingDetailRaw): string {
  const roomName = booking.room?.display_name ?? booking.room?.name
  if (!roomName) {
    return `Phòng #${booking.room_id}`
  }

  return booking.room?.room_number ? `${roomName} (#${booking.room.room_number})` : roomName
}

function canCancelBooking(booking: BookingDetailRaw): boolean {
  return booking.status === 'pending' || booking.status === 'confirmed'
}

function canReviewBooking(booking: BookingDetailRaw): boolean {
  return (
    booking.status === 'confirmed' && booking.check_out < new Date().toISOString().split('T')[0]
  )
}

interface InfoCellProps {
  label: string
  value: React.ReactNode
  emphasize?: boolean
  className?: string
}

const InfoCell: React.FC<InfoCellProps> = ({ label, value, emphasize = false, className = '' }) => (
  <div className={`bg-white px-5 py-4 ${className}`.trim()}>
    <dt className="text-sm text-gray-500">{label}</dt>
    <dd
      className={`mt-2 text-xl ${emphasize ? 'font-semibold text-amber-700' : 'font-semibold text-gray-900'}`}
    >
      {value}
    </dd>
  </div>
)

const DetailSkeleton: React.FC = () => (
  <div className="rounded-[28px] border border-gray-200 bg-white p-8 shadow-[0_24px_60px_rgba(15,23,42,0.08)]">
    <div className="animate-pulse space-y-6" role="status" aria-label="Loading booking details">
      <div className="flex items-center justify-between gap-4">
        <div className="h-10 w-64 rounded bg-gray-200" />
        <div className="h-10 w-28 rounded-full bg-gray-200" />
      </div>
      <div className="grid gap-px rounded-2xl border border-gray-200 bg-gray-200 md:grid-cols-4">
        {Array.from({ length: 9 }).map((_, index) => (
          <div key={index} className={`bg-white px-5 py-4 ${index === 8 ? 'md:col-span-4' : ''}`}>
            <div className="h-4 w-24 rounded bg-gray-200" />
            <div className="mt-3 h-6 w-32 rounded bg-gray-200" />
          </div>
        ))}
      </div>
      <div className="h-12 w-40 rounded-xl bg-gray-200" />
    </div>
  </div>
)

const BookingDetailPage: React.FC = () => {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const location = useLocation()

  const isAdminRoute = location.pathname.startsWith('/admin')
  const bookingId = id ? Number.parseInt(id, 10) : Number.NaN

  const [booking, setBooking] = useState<BookingDetailRaw | null>(null)
  const [isLoading, setIsLoading] = useState(!isAdminRoute)
  const [isError, setIsError] = useState(false)
  const [refreshKey, setRefreshKey] = useState(0)
  const [isCancelDialogOpen, setIsCancelDialogOpen] = useState(false)
  const [isCancelling, setIsCancelling] = useState(false)

  useEffect(() => {
    if (isAdminRoute || Number.isNaN(bookingId)) {
      return
    }

    const controller = new AbortController()
    let active = true

    const loadBooking = async () => {
      setIsLoading(true)
      setIsError(false)

      try {
        const data = await getBookingById(bookingId, controller.signal)
        if (active && !controller.signal.aborted) {
          setBooking(data)
        }
      } catch (error) {
        if (
          error instanceof Error &&
          (error.name === 'AbortError' || error.name === 'CanceledError')
        ) {
          return
        }
        if (active && !controller.signal.aborted) {
          setIsError(true)
        }
      } finally {
        if (active && !controller.signal.aborted) {
          setIsLoading(false)
        }
      }
    }

    void loadBooking()

    return () => {
      active = false
      controller.abort()
    }
  }, [bookingId, isAdminRoute, refreshKey])

  if (isAdminRoute) {
    const handleClose = () => navigate('/admin/bookings')

    return (
      <div className="relative min-h-screen bg-gray-50">
        <BookingDetailPanel
          bookingId={Number.isNaN(bookingId) ? null : bookingId}
          open={true}
          onClose={handleClose}
        />
      </div>
    )
  }

  const handleBack = () => navigate('/my-bookings')

  const handleCancelConfirm = async () => {
    if (!booking) return

    setIsCancelling(true)
    try {
      await cancelBooking(booking.id)
      setIsCancelDialogOpen(false)
      setRefreshKey(current => current + 1)
      showToast.success('Đã hủy đặt phòng thành công.')
    } catch (error) {
      showToast.error(getErrorMessage(error))
    } finally {
      setIsCancelling(false)
    }
  }

  if (Number.isNaN(bookingId)) {
    return (
      <section className="min-h-screen bg-[radial-gradient(circle_at_top,_rgba(251,191,36,0.12),_transparent_28%),linear-gradient(to_bottom,_#fffdf9,_#faf7f2)] px-4 py-10 sm:px-6 lg:px-8">
        <div className="mx-auto max-w-5xl">
          <div className="rounded-[28px] border border-gray-200 bg-white p-8 text-center shadow-[0_24px_60px_rgba(15,23,42,0.08)]">
            <h1 className="text-3xl font-bold text-gray-900">Chi tiết đặt phòng</h1>
            <p className="mt-3 text-gray-600">Mã đặt phòng không hợp lệ hoặc đang bị thiếu.</p>
            <button
              type="button"
              onClick={handleBack}
              className="mt-6 rounded-xl border border-gray-300 px-5 py-2.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50"
            >
              Quay lại danh sách đặt phòng
            </button>
          </div>
        </div>
      </section>
    )
  }

  return (
    <section className="min-h-screen bg-[radial-gradient(circle_at_top,_rgba(251,191,36,0.12),_transparent_28%),linear-gradient(to_bottom,_#fffdf9,_#faf7f2)] px-4 py-10 sm:px-6 lg:px-8">
      <div className="mx-auto max-w-5xl">
        <div className="mb-6 flex items-center gap-2 text-sm text-gray-500">
          <Link to="/my-bookings" className="transition-colors hover:text-gray-900">
            Đặt phòng của tôi
          </Link>
          <span aria-hidden="true">→</span>
          <span className="text-gray-900">Chi tiết</span>
        </div>

        {isLoading && <DetailSkeleton />}

        {!isLoading && isError && (
          <div className="rounded-[28px] border border-red-200 bg-white p-8 text-center shadow-[0_24px_60px_rgba(15,23,42,0.08)]">
            <h1 className="text-3xl font-bold text-gray-900">Chi tiết đặt phòng</h1>
            <p className="mt-4 text-red-600">Không thể tải chi tiết đặt phòng.</p>
            <div className="mt-6 flex justify-center gap-3">
              <button
                type="button"
                onClick={() => setRefreshKey(current => current + 1)}
                className="rounded-xl bg-amber-500 px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-amber-600"
              >
                Thử lại
              </button>
              <button
                type="button"
                onClick={handleBack}
                className="rounded-xl border border-gray-300 px-5 py-2.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50"
              >
                Quay lại
              </button>
            </div>
          </div>
        )}

        {!isLoading && !isError && booking && (
          <div className="space-y-8">
            <div className="rounded-[28px] border border-gray-200 bg-white p-8 shadow-[0_24px_60px_rgba(15,23,42,0.08)]">
              <div className="flex flex-col gap-4 border-b border-gray-200 pb-6 sm:flex-row sm:items-start sm:justify-between">
                <div>
                  <h1 className="text-4xl font-bold tracking-tight text-gray-950">
                    Chi tiết đặt phòng
                  </h1>
                  <p className="mt-3 text-sm text-gray-500">
                    Kiểm tra thông tin đặt phòng và theo dõi trạng thái hiện tại của bạn.
                  </p>
                </div>
                <span
                  className={
                    DETAIL_STATUS_STYLES[booking.status] ??
                    'bg-gray-100 text-gray-700 border border-gray-200 text-sm px-3 py-1 rounded-full font-medium'
                  }
                >
                  {booking.status_label ?? booking.status}
                </span>
              </div>

              <dl className="mt-6 grid gap-px overflow-hidden rounded-2xl border border-gray-200 bg-gray-200 md:grid-cols-4">
                <InfoCell label="Mã đặt phòng" value={buildBookingReference(booking)} />
                <InfoCell label="Phòng" value={getRoomLabel(booking)} />
                <InfoCell label="Nhận phòng" value={formatDetailDate(booking.check_in)} />
                <InfoCell label="Trả phòng" value={formatDetailDate(booking.check_out)} />
                <InfoCell label="Số đêm" value={`${booking.nights} đêm`} />
                <InfoCell label="Tên khách" value={booking.guest_name} />
                <InfoCell label="Email" value={booking.guest_email} />
                <InfoCell label="Tổng tiền" value={formatAmount(booking)} emphasize />
                <InfoCell
                  label="Ngày đặt"
                  value={formatDetailDateTime(booking.created_at)}
                  className="md:col-span-4"
                />
              </dl>

              {booking.refund_amount_formatted && (
                <div className="mt-6 rounded-2xl border border-blue-100 bg-blue-50 px-5 py-4 text-sm text-blue-900">
                  <span className="font-medium">Hoàn tiền:</span>{' '}
                  {booking.refund_amount_formatted.replace(/\s?₫/, 'đ')}
                </div>
              )}

              {booking.status === 'refund_failed' && (
                <div className="mt-4 rounded-2xl border border-orange-200 bg-orange-50 px-5 py-4 text-sm text-orange-900">
                  Hoàn tiền đang gặp sự cố. Đội ngũ của Soleil Hostel sẽ xử lý thủ công và liên hệ
                  với bạn nếu cần thêm thông tin.
                </div>
              )}

              {canCancelBooking(booking) && (
                <div className="mt-8 border-t border-gray-200 pt-6">
                  <button
                    type="button"
                    onClick={() => setIsCancelDialogOpen(true)}
                    className="rounded-xl border border-red-300 px-5 py-3 text-base font-medium text-red-600 transition-colors hover:bg-red-50"
                  >
                    Hủy đặt phòng
                  </button>
                </div>
              )}
            </div>

            {canReviewBooking(booking) && (
              <div className="rounded-[28px] border border-gray-200 bg-white p-8 shadow-[0_24px_60px_rgba(15,23,42,0.08)]">
                <div className="border-b border-gray-200 pb-5">
                  <h2 className="text-3xl font-bold tracking-tight text-gray-950">
                    Đánh giá của bạn
                  </h2>
                  <p className="mt-2 text-sm text-gray-500">
                    Chia sẻ cảm nhận của bạn sau kỳ nghỉ để giúp những vị khách tiếp theo.
                  </p>
                </div>
                <ReviewForm bookingId={booking.id} defaultOpen={true} variant="detail" />
              </div>
            )}
          </div>
        )}

        {isCancelDialogOpen && booking && (
          <div
            className="fixed inset-0 z-50 bg-black/40 px-4 py-6"
            role="dialog"
            aria-modal="true"
            aria-labelledby="detail-cancel-title"
            aria-describedby="detail-cancel-description"
            onClick={() => {
              if (!isCancelling) {
                setIsCancelDialogOpen(false)
              }
            }}
          >
            <div className="mx-auto flex min-h-full items-center justify-center">
              <div
                className="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl"
                onClick={event => event.stopPropagation()}
              >
                <h2 id="detail-cancel-title" className="text-2xl font-semibold text-gray-900">
                  Hủy đặt phòng #{buildBookingReference(booking)}
                </h2>
                <p id="detail-cancel-description" className="mt-3 text-sm leading-6 text-gray-600">
                  Hành động này không thể hoàn tác. Bạn có chắc muốn hủy đặt phòng này?
                </p>
                <div className="mt-6 flex justify-end gap-3">
                  <button
                    type="button"
                    onClick={() => setIsCancelDialogOpen(false)}
                    disabled={isCancelling}
                    autoFocus
                    className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60"
                  >
                    Hủy bỏ
                  </button>
                  <button
                    type="button"
                    onClick={handleCancelConfirm}
                    disabled={isCancelling}
                    className="inline-flex items-center justify-center rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-70"
                  >
                    {isCancelling && (
                      <svg
                        aria-hidden="true"
                        className="mr-2 h-4 w-4 animate-spin"
                        fill="none"
                        viewBox="0 0 24 24"
                      >
                        <circle
                          className="opacity-25"
                          cx="12"
                          cy="12"
                          r="10"
                          stroke="currentColor"
                          strokeWidth="4"
                        />
                        <path
                          className="opacity-75"
                          d="M22 12a10 10 0 00-10-10"
                          stroke="currentColor"
                          strokeLinecap="round"
                          strokeWidth="4"
                        />
                      </svg>
                    )}
                    Xác nhận hủy
                  </button>
                </div>
              </div>
            </div>
          </div>
        )}
      </div>
    </section>
  )
}

export default BookingDetailPage
