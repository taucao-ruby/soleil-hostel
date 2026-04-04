import React, { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { useAuth } from '@/features/auth/AuthContext'
import api from '@/shared/lib/api'
import { useMyBookingsQuery, useCancelBookingMutation } from './useMyBookings'
import { isUpcoming, isPast, type BookingViewModel } from './bookingViewModel'
import { formatDateRangeVN } from '@/shared/lib/booking.utils'
import { getErrorMessage, showToast } from '@/shared/utils/toast'

type FilterTab = 'all' | 'upcoming' | 'past'

const FILTER_TABS: { key: FilterTab; label: string }[] = [
  { key: 'all', label: 'Tất cả' },
  { key: 'upcoming', label: 'Sắp tới' },
  { key: 'past', label: 'Đã qua' },
]

const DASHBOARD_STATUS_STYLES: Record<string, { label: string; className: string }> = {
  pending: {
    label: 'Chờ xác nhận',
    className:
      'bg-amber-50 text-amber-800 border border-amber-200 text-xs px-2 py-0.5 rounded-full',
  },
  confirmed: {
    label: 'Đã xác nhận',
    className:
      'bg-green-50 text-green-800 border border-green-200 text-xs px-2 py-0.5 rounded-full',
  },
  cancelled: {
    label: 'Đã hủy',
    className: 'bg-gray-100 text-gray-600 border border-gray-200 text-xs px-2 py-0.5 rounded-full',
  },
  refund_pending: {
    label: 'Hoàn tiền đang xử lý',
    className: 'bg-blue-50 text-blue-800 border border-blue-200 text-xs px-2 py-0.5 rounded-full',
  },
  refund_failed: {
    label: 'Hoàn tiền thất bại',
    className:
      'bg-orange-50 text-orange-800 border border-orange-200 text-xs px-2 py-0.5 rounded-full',
  },
}

function getDashboardStatus(booking: BookingViewModel) {
  return (
    DASHBOARD_STATUS_STYLES[booking.status] ?? {
      label: booking.statusLabel,
      className:
        'bg-gray-100 text-gray-700 border border-gray-200 text-xs px-2 py-0.5 rounded-full',
    }
  )
}

function formatBookingReference(booking: Pick<BookingViewModel, 'id' | 'createdAt'>): string {
  const year = Number.isNaN(booking.createdAt.getFullYear())
    ? new Date().getFullYear()
    : booking.createdAt.getFullYear()

  return `SOL-${year}-${String(booking.id).padStart(4, '0')}`
}

function formatDashboardDateRange(booking: BookingViewModel): string {
  return formatDateRangeVN(booking.checkIn, booking.checkOut).replace(' — ', ' → ')
}

interface BookingCardProps {
  booking: BookingViewModel
  onCancel: (booking: BookingViewModel) => void
}

const BookingCard: React.FC<BookingCardProps> = ({ booking, onCancel }) => {
  const statusConfig = getDashboardStatus(booking)

  return (
    <article className="flex flex-col gap-4 p-4 bg-white border border-gray-200 shadow-sm rounded-xl sm:flex-row sm:items-start sm:justify-between">
      <div className="min-w-0 space-y-1.5">
        <p className="text-[15px] font-medium text-gray-900">{booking.roomName}</p>
        <p className="text-[13px] text-gray-500">
          <span aria-hidden="true" className="mr-1">
            📅
          </span>
          {formatDashboardDateRange(booking)}
        </p>
        <p className="text-[13px] text-gray-900">
          {booking.nights} đêm
          {booking.amountFormatted ? ` · ${booking.amountFormatted}` : ''}
        </p>
      </div>

      <div className="flex flex-col items-start gap-2 sm:items-end">
        <span className={statusConfig.className}>{statusConfig.label}</span>
        {booking.canCancel && (
          <button
            type="button"
            onClick={() => onCancel(booking)}
            className="px-3 py-1 text-sm text-red-600 transition-colors border border-red-300 rounded-lg hover:bg-red-50"
            aria-label={`Hủy đặt phòng #${formatBookingReference(booking)}`}
          >
            Hủy đặt phòng
          </button>
        )}
      </div>
    </article>
  )
}

const BookingListSkeleton: React.FC = () => (
  <div className="space-y-4">
    {Array.from({ length: 3 }).map((_, index) => (
      <div
        key={index}
        className="p-4 bg-white border border-gray-200 animate-pulse rounded-xl"
        role="status"
        aria-label="Đang tải danh sách đặt phòng"
      >
        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div className="flex-1 min-w-0 space-y-2">
            <div className="w-48 h-4 bg-gray-200 rounded" />
            <div className="h-3 bg-gray-200 rounded w-44" />
            <div className="w-32 h-3 bg-gray-200 rounded" />
          </div>
          <div className="flex flex-col items-start gap-2 sm:items-end">
            <div className="h-6 bg-gray-200 rounded-full w-28" />
            <div className="h-8 bg-gray-200 rounded-lg w-28" />
          </div>
        </div>
      </div>
    ))}
  </div>
)

const GuestDashboard: React.FC = () => {
  const { user } = useAuth()
  const { bookings, isLoading, isError, refetch } = useMyBookingsQuery()
  const { cancel, isPending } = useCancelBookingMutation()

  const [activeTab, setActiveTab] = useState<FilterTab>('all')
  const [cancelTarget, setCancelTarget] = useState<BookingViewModel | null>(null)
  const [isResendingVerification, setIsResendingVerification] = useState(false)

  const filtered = useMemo(() => {
    if (activeTab === 'upcoming') return bookings.filter(isUpcoming)
    if (activeTab === 'past') return bookings.filter(isPast)
    return bookings
  }, [bookings, activeTab])

  const isEmailUnverified = Boolean(user && !user.email_verified_at)
  const isVerificationBlocked = isEmailUnverified && isError

  const handleCancelDismiss = () => {
    if (!isPending) {
      setCancelTarget(null)
    }
  }

  const handleCancelConfirm = async () => {
    if (!cancelTarget) return

    const success = await cancel(cancelTarget.id)
    if (success) {
      setCancelTarget(null)
      showToast.success('Đã hủy đặt phòng thành công.')
      refetch()
      return
    }

    showToast.error('Không thể hủy đặt phòng. Vui lòng thử lại.')
  }

  const handleResendVerification = async () => {
    if (isResendingVerification) return

    setIsResendingVerification(true)
    try {
      await api.post('/email/send-code')
      showToast.success('Đã gửi mã xác minh đến email của bạn.')
    } catch (error) {
      showToast.error(getErrorMessage(error))
    } finally {
      setIsResendingVerification(false)
    }
  }

  return (
    <section className="space-y-6">
      {isEmailUnverified && (
        <div
          className="p-4 border-l-4 rounded-r-lg border-amber-400 bg-amber-50"
          role="alert"
          aria-live="polite"
        >
          <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-sm text-amber-900">
              <span aria-hidden="true" className="mr-2">
                ⚠️
              </span>
              Email của bạn chưa được xác minh. Vui lòng kiểm tra hộp thư đến để lấy mã xác minh.
            </p>
            <div className="flex gap-3">
              <button
                type="button"
                onClick={handleResendVerification}
                disabled={isResendingVerification}
                className="text-sm font-medium text-left underline transition-colors text-amber-800 underline-offset-4 hover:text-amber-900 disabled:cursor-not-allowed disabled:no-underline disabled:opacity-70"
              >
                {isResendingVerification ? 'Đang gửi...' : 'Gửi lại mã →'}
              </button>
              <Link
                to="/email/verify"
                className="text-sm font-medium underline transition-colors text-amber-800 underline-offset-4 hover:text-amber-900"
              >
                Nhập mã xác minh →
              </Link>
            </div>
          </div>
        </div>
      )}

      <div
        role="tablist"
        aria-label="Bộ lọc đặt phòng"
        className="flex gap-6 border-b border-gray-200"
      >
        {FILTER_TABS.map(tab => (
          <button
            key={tab.key}
            type="button"
            role="tab"
            id={`dashboard-tab-${tab.key}`}
            aria-selected={activeTab === tab.key}
            aria-controls={`dashboard-panel-${tab.key}`}
            onClick={() => setActiveTab(tab.key)}
            className={`-mb-px border-b-2 px-1 pb-3 text-sm transition-colors ${
              activeTab === tab.key
                ? 'border-amber-500 font-medium text-amber-700'
                : 'border-transparent text-gray-600 hover:text-gray-900'
            }`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {isLoading && <BookingListSkeleton />}

      {!isLoading && !isVerificationBlocked && isError && (
        <div className="p-6 text-center bg-white border border-red-200 rounded-xl">
          <p className="mb-3 text-red-600">Không thể tải danh sách đặt phòng.</p>
          <button
            type="button"
            onClick={refetch}
            className="px-4 py-2 text-sm font-medium text-gray-700 transition-colors border border-gray-300 rounded-lg hover:bg-gray-50"
          >
            Thử lại
          </button>
        </div>
      )}

      {!isLoading && !isError && filtered.length === 0 && (
        <div
          id={`dashboard-panel-${activeTab}`}
          role="tabpanel"
          aria-labelledby={`dashboard-tab-${activeTab}`}
          className="px-6 py-12 text-center bg-white border border-gray-200 rounded-2xl"
        >
          <div className="flex items-center justify-center w-8 h-8 mx-auto mb-4 text-gray-400">
            <svg aria-hidden="true" className="w-8 h-8" fill="none" viewBox="0 0 24 24">
              <path
                d="M8 2v3M16 2v3M3.5 9.5h17M6.5 5.5h11a3 3 0 013 3v8a3 3 0 01-3 3h-11a3 3 0 01-3-3v-8a3 3 0 013-3z"
                stroke="currentColor"
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth="1.8"
              />
            </svg>
          </div>
          <p className="mb-2 text-xl font-semibold text-gray-900">
            {activeTab === 'all' ? 'Bạn chưa có đặt phòng nào' : 'Không có đặt phòng phù hợp'}
          </p>
          <p className="mb-6 text-sm text-gray-500">
            {activeTab === 'all'
              ? 'Bạn có thể bắt đầu đặt chỗ cho kỳ nghỉ tiếp theo ngay hôm nay.'
              : activeTab === 'upcoming'
                ? 'Không có đặt phòng sắp tới.'
                : 'Không có đặt phòng đã qua.'}
          </p>
          {activeTab === 'all' && (
            <Link
              to="/rooms"
              className="inline-flex items-center px-4 py-2 text-sm font-medium text-white transition-colors rounded-lg bg-amber-500 hover:bg-amber-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:ring-offset-2"
            >
              Đặt phòng ngay →
            </Link>
          )}
        </div>
      )}

      {!isLoading && !isError && filtered.length > 0 && (
        <div
          id={`dashboard-panel-${activeTab}`}
          role="tabpanel"
          aria-labelledby={`dashboard-tab-${activeTab}`}
          className="space-y-4"
        >
          {filtered.map(booking => (
            <BookingCard key={booking.id} booking={booking} onCancel={setCancelTarget} />
          ))}
        </div>
      )}

      {!isLoading && isVerificationBlocked && (
        <div className="px-6 py-8 text-center bg-white border border-gray-200 rounded-2xl">
          <p className="text-sm text-gray-600">
            Xác minh email để xem và quản lý danh sách đặt phòng của bạn.
          </p>
        </div>
      )}

      {cancelTarget && (
        <div
          className="fixed inset-0 z-50 px-4 py-6 bg-black/40"
          role="dialog"
          aria-modal="true"
          aria-labelledby="cancel-booking-title"
          aria-describedby="cancel-booking-description"
          onClick={handleCancelDismiss}
        >
          <div className="flex items-center justify-center min-h-full mx-auto">
            <div
              className="w-full max-w-sm p-6 bg-white shadow-xl rounded-xl"
              onClick={event => event.stopPropagation()}
            >
              <h2 id="cancel-booking-title" className="text-xl font-semibold text-gray-900">
                Hủy đặt phòng #{formatBookingReference(cancelTarget)}
              </h2>
              <p id="cancel-booking-description" className="mt-3 text-sm leading-6 text-gray-600">
                Hành động này không thể hoàn tác. Bạn có chắc muốn hủy?
              </p>

              <div className="flex justify-end gap-3 mt-6">
                <button
                  type="button"
                  onClick={handleCancelDismiss}
                  disabled={isPending}
                  autoFocus
                  className="px-4 py-2 text-sm font-medium text-gray-700 transition-colors border border-gray-300 rounded-lg hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60"
                >
                  Hủy bỏ
                </button>
                <button
                  type="button"
                  onClick={handleCancelConfirm}
                  disabled={isPending}
                  className="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white transition-colors bg-red-600 rounded-lg hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-70"
                >
                  {isPending && (
                    <svg
                      aria-hidden="true"
                      className="w-4 h-4 mr-2 animate-spin"
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
    </section>
  )
}

export default GuestDashboard
