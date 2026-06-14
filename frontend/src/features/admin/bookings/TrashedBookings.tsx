import React, { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { useAuth } from '@/features/auth/AuthContext'
import { getStatusConfig, formatDateRangeVN } from '@/shared/lib/booking.utils'
import { formatVND } from '@/shared/lib/formatCurrency'
import { getErrorMessage, showToast } from '@/shared/utils/toast'
import { isAbortError } from '@/shared/lib/request-error'
import ConfirmDialog from '@/shared/components/ui/ConfirmDialog'
import type { AdminBookingRaw } from '../admin.types'
import { fetchTrashedBookings, forceDeleteBooking, restoreBooking } from '../admin.api'

function parseDisplayDate(value: string): Date {
  return /^\d{4}-\d{2}-\d{2}$/.test(value) ? new Date(`${value}T00:00:00`) : new Date(value)
}

function formatDateTime(value: string | null | undefined): string {
  if (!value) return 'Chưa cập nhật'

  return new Intl.DateTimeFormat('vi-VN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(parseDisplayDate(value))
}

function formatBookingAmount(booking: AdminBookingRaw): string {
  if (booking.amount_formatted) return booking.amount_formatted
  if (typeof booking.amount === 'number') return formatVND(booking.amount)
  return 'Chưa cập nhật'
}

/**
 * TrashedBookings — dedicated `/admin/bookings/trashed` page.
 *
 * Lists soft-deleted bookings. Viewable by moderator+admin (matches backend
 * `role:moderator` on GET /v1/admin/bookings/trashed — PERMISSION_MATRIX Table A,
 * row A8). Restore and force-delete are admin-only (rows A10/A11), so those
 * actions are gated behind `isAdmin` here in addition to backend enforcement.
 */
const TrashedBookings: React.FC = () => {
  const { user } = useAuth()
  const isAdmin = user?.role === 'admin'

  const [bookings, setBookings] = useState<AdminBookingRaw[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)

  const [processingBookingId, setProcessingBookingId] = useState<number | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<AdminBookingRaw | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)

  useEffect(() => {
    const controller = new AbortController()
    let active = true

    setIsLoading(true)
    setErrorMessage(null)

    void fetchTrashedBookings(controller.signal)
      .then(result => {
        if (!active) return
        setBookings(result.bookings)
      })
      .catch((error: unknown) => {
        if (!active || isAbortError(error)) return
        setBookings([])
        setErrorMessage('Không thể tải danh sách đặt phòng đã xóa. Vui lòng thử lại.')
      })
      .finally(() => {
        if (active) {
          setIsLoading(false)
        }
      })

    return () => {
      active = false
      controller.abort()
    }
  }, [])

  const handleRestore = async (booking: AdminBookingRaw) => {
    setProcessingBookingId(booking.id)

    try {
      await restoreBooking(booking.id)
      setBookings(current => current.filter(item => item.id !== booking.id))
      showToast.success('Đã khôi phục đặt phòng.')
    } catch (error) {
      showToast.error(getErrorMessage(error))
    } finally {
      setProcessingBookingId(null)
    }
  }

  const handleConfirmForceDelete = async () => {
    if (!deleteTarget) return

    setIsDeleting(true)

    try {
      await forceDeleteBooking(deleteTarget.id)
      setBookings(current => current.filter(item => item.id !== deleteTarget.id))
      showToast.success('Đã xóa vĩnh viễn đặt phòng.')
      setDeleteTarget(null)
    } catch (error) {
      showToast.error(getErrorMessage(error))
    } finally {
      setIsDeleting(false)
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:justify-between">
        <h1 className="text-[20px] font-semibold text-gray-950">Đặt phòng đã xóa</h1>
        <Link
          to="/admin/bookings"
          className="text-sm font-medium text-amber-700 transition hover:text-amber-800"
        >
          ← Quay lại đặt phòng
        </Link>
      </div>

      {!isLoading && !errorMessage && (
        <p className="text-sm text-gray-500">{bookings.length} đặt phòng trong thùng rác</p>
      )}

      {isLoading && (
        <div className="space-y-3" aria-label="Đang tải danh sách đặt phòng đã xóa">
          {Array.from({ length: 3 }, (_, index) => (
            <div
              key={index}
              className="h-28 animate-pulse rounded-2xl border border-stone-200 bg-stone-100/80"
            />
          ))}
        </div>
      )}

      {!isLoading && errorMessage && (
        <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-5 text-sm text-red-700">
          {errorMessage}
        </div>
      )}

      {!isLoading && !errorMessage && bookings.length === 0 && (
        <div className="rounded-2xl border border-dashed border-stone-300 bg-stone-50 px-4 py-12 text-center text-sm text-stone-500">
          Không có đặt phòng nào trong thùng rác.
        </div>
      )}

      {!isLoading && !errorMessage && bookings.length > 0 && (
        <div className="space-y-3">
          {bookings.map(booking => {
            const statusConfig = getStatusConfig(booking.status)
            const isProcessing = processingBookingId === booking.id

            return (
              <article
                key={booking.id}
                className="rounded-2xl border border-stone-200 bg-[#fcfbf8] p-4 shadow-sm"
              >
                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                  <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                      <p className="text-sm font-semibold text-stone-900">ĐP #{booking.id}</p>
                      <span
                        className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ${statusConfig.colorClass}`}
                      >
                        {statusConfig.label}
                      </span>
                      <span className="inline-flex items-center rounded-full bg-stone-100 px-2.5 py-1 text-xs font-medium text-stone-700">
                        Đã xóa
                      </span>
                    </div>
                    <p className="mt-2 text-sm font-medium text-stone-900">{booking.guest_name}</p>
                    <p className="text-sm text-stone-500">{booking.guest_email}</p>
                    <p className="mt-3 text-sm text-stone-600">
                      {booking.room?.display_name || booking.room?.name || 'Chưa gán phòng'} ·{' '}
                      {formatDateRangeVN(
                        parseDisplayDate(booking.check_in),
                        parseDisplayDate(booking.check_out)
                      )}{' '}
                      · {formatBookingAmount(booking)}
                    </p>
                    <p className="mt-1 text-sm text-stone-500">
                      Xóa lúc {formatDateTime(booking.deleted_at)}{' '}
                      {booking.deleted_by ? `· bởi ${booking.deleted_by.name}` : ''}
                    </p>
                  </div>

                  {isAdmin && (
                    <div className="flex flex-wrap items-center gap-3">
                      <button
                        type="button"
                        onClick={() => handleRestore(booking)}
                        disabled={isProcessing}
                        className="rounded-lg border border-emerald-600 px-3 py-2 text-sm font-medium text-emerald-700 transition hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-50"
                      >
                        {isProcessing ? 'Đang xử lý...' : 'Khôi phục'}
                      </button>
                      <button
                        type="button"
                        onClick={() => setDeleteTarget(booking)}
                        disabled={isProcessing}
                        className="rounded-lg bg-red-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
                      >
                        Xóa vĩnh viễn
                      </button>
                    </div>
                  )}
                </div>
              </article>
            )
          })}
        </div>
      )}

      <ConfirmDialog
        open={deleteTarget !== null}
        title="Xóa vĩnh viễn đặt phòng"
        description={
          deleteTarget
            ? `Xóa vĩnh viễn đặt phòng ĐP #${deleteTarget.id}? Thao tác này không thể hoàn tác.`
            : ''
        }
        confirmLabel="Xóa vĩnh viễn"
        cancelLabel="Quay lại"
        isPending={isDeleting}
        onConfirm={handleConfirmForceDelete}
        onCancel={() => {
          if (!isDeleting) {
            setDeleteTarget(null)
          }
        }}
      />
    </div>
  )
}

export default TrashedBookings
