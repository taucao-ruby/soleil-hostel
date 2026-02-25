import React, { useState, useMemo } from 'react'
import { Link } from 'react-router-dom'
import { useMyBookingsQuery, useCancelBookingMutation } from './useMyBookings'
import { isUpcoming, isPast, type BookingViewModel } from './bookingViewModel'
import { getStatusConfig, formatDateRangeVN } from './booking.constants'
import Skeleton from '@/shared/components/ui/Skeleton'
import Button from '@/shared/components/ui/Button'
import ConfirmDialog from '@/shared/components/ui/ConfirmDialog'
import { showToast } from '@/utils/toast'
import { getErrorMessage } from '@/utils/toast'

type FilterTab = 'all' | 'upcoming' | 'past'

// ── BookingCard ──────────────────────────────────────────────
interface BookingCardProps {
  booking: BookingViewModel
  onCancel: (id: number) => void
}

const BookingCard: React.FC<BookingCardProps> = ({ booking, onCancel }) => {
  const statusConfig = getStatusConfig(booking.status)

  return (
    <div className="p-5 bg-white border border-gray-200 rounded-xl shadow-sm">
      <div className="flex items-start justify-between gap-4">
        <div className="min-w-0">
          <p className="text-sm font-medium text-gray-900">
            {formatDateRangeVN(booking.checkIn, booking.checkOut)}
          </p>
          <p className="mt-1 text-sm text-gray-500">
            {booking.nights} đêm
            {booking.amountFormatted ? ` · ${booking.amountFormatted}` : ''}
          </p>
        </div>
        <span
          className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium whitespace-nowrap ${statusConfig.colorClass}`}
        >
          {statusConfig.label}
        </span>
      </div>
      {booking.canCancel && (
        <div className="mt-4 flex justify-end">
          <Button
            variant="outline"
            size="sm"
            onClick={() => onCancel(booking.id)}
            aria-label={`Hủy đặt phòng #${booking.id}`}
          >
            Hủy
          </Button>
        </div>
      )}
    </div>
  )
}

// ── Skeleton list ────────────────────────────────────────────
const BookingListSkeleton: React.FC = () => (
  <div className="space-y-3">
    {[0, 1, 2].map(i => (
      <Skeleton key={i} width="100%" height="5.5rem" rounded="lg" />
    ))}
  </div>
)

// ── GuestDashboard ───────────────────────────────────────────
const GuestDashboard: React.FC = () => {
  const { bookings, isLoading, isError, refetch } = useMyBookingsQuery()
  const { cancel, isPending, error: cancelError } = useCancelBookingMutation()

  const [activeTab, setActiveTab] = useState<FilterTab>('all')
  const [cancelTarget, setCancelTarget] = useState<number | null>(null)

  const filtered = useMemo(() => {
    if (activeTab === 'upcoming') return bookings.filter(isUpcoming)
    if (activeTab === 'past') return bookings.filter(isPast)
    return bookings
  }, [bookings, activeTab])

  const handleCancelClick = (id: number) => setCancelTarget(id)
  const handleCancelDismiss = () => {
    if (!isPending) setCancelTarget(null)
  }

  const handleCancelConfirm = async () => {
    if (cancelTarget === null) return
    const success = await cancel(cancelTarget)
    if (success) {
      setCancelTarget(null)
      showToast.success('Đã hủy đặt phòng thành công.')
      refetch()
    } else {
      showToast.error(getErrorMessage(cancelError))
    }
  }

  // ── Filter tabs ────────────────────────────────────────
  const tabs: { key: FilterTab; label: string }[] = [
    { key: 'all', label: 'Tất cả' },
    { key: 'upcoming', label: 'Sắp tới' },
    { key: 'past', label: 'Đã qua' },
  ]

  return (
    <section>
      <div className="flex items-center justify-between mb-6">
        <h2 className="text-2xl font-bold text-gray-900">Đặt phòng của tôi</h2>
      </div>

      {/* Filter tabs */}
      <div className="flex gap-2 mb-6" role="tablist">
        {tabs.map(tab => (
          <button
            key={tab.key}
            role="tab"
            aria-selected={activeTab === tab.key}
            onClick={() => setActiveTab(tab.key)}
            className={`px-4 py-1.5 text-sm font-medium rounded-full transition-colors ${
              activeTab === tab.key
                ? 'bg-blue-600 text-white'
                : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
            }`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {/* Loading state */}
      {isLoading && <BookingListSkeleton />}

      {/* Error state */}
      {!isLoading && isError && (
        <div className="p-6 text-center bg-white border border-red-200 rounded-xl">
          <p className="text-red-600 mb-3">Không thể tải danh sách đặt phòng.</p>
          <Button variant="outline" size="sm" onClick={refetch}>
            Thử lại
          </Button>
        </div>
      )}

      {/* Empty state */}
      {!isLoading && !isError && filtered.length === 0 && (
        <div className="p-8 text-center bg-white border border-gray-200 rounded-xl">
          <p className="text-gray-500 mb-4">
            {activeTab === 'all'
              ? 'Bạn chưa có đặt phòng nào.'
              : activeTab === 'upcoming'
                ? 'Không có đặt phòng sắp tới.'
                : 'Không có đặt phòng đã qua.'}
          </p>
          {activeTab === 'all' && (
            <Link
              to="/rooms"
              className="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2"
            >
              Đặt phòng ngay
            </Link>
          )}
        </div>
      )}

      {/* Booking list */}
      {!isLoading && !isError && filtered.length > 0 && (
        <div className="space-y-3">
          {filtered.map(booking => (
            <BookingCard key={booking.id} booking={booking} onCancel={handleCancelClick} />
          ))}
        </div>
      )}

      {/* Cancel confirmation modal */}
      <ConfirmDialog
        open={cancelTarget !== null}
        title="Hủy đặt phòng"
        description="Bạn có chắc chắn muốn hủy đặt phòng này? Hành động này không thể hoàn tác."
        confirmLabel="Xác nhận hủy"
        cancelLabel="Quay lại"
        onConfirm={handleCancelConfirm}
        onCancel={handleCancelDismiss}
        isPending={isPending}
      />
    </section>
  )
}

export default GuestDashboard
