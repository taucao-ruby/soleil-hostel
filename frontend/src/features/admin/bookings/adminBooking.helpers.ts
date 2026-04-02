import type { BookingDetailRaw } from '@/features/booking/booking.types'
import { formatVND } from '@/shared/lib/formatCurrency'

export interface AdminBookingStatusConfig {
  label: string
  className: string
}

const ADMIN_BOOKING_STATUS_MAP: Record<string, AdminBookingStatusConfig> = {
  pending: {
    label: 'Chờ xác nhận',
    className: 'border border-amber-200 bg-amber-50 text-amber-800',
  },
  confirmed: {
    label: 'Đã xác nhận',
    className: 'border border-emerald-200 bg-emerald-50 text-emerald-800',
  },
  cancelled: {
    label: 'Đã hủy',
    className: 'border border-gray-200 bg-gray-100 text-gray-600',
  },
  refund_pending: {
    label: 'Hoàn tiền đang xử lý',
    className: 'border border-sky-200 bg-sky-50 text-sky-700',
  },
  refund_failed: {
    label: 'Hoàn tiền thất bại',
    className: 'border border-rose-200 bg-rose-50 text-rose-700',
  },
}

const FALLBACK_STATUS_CONFIG: AdminBookingStatusConfig = {
  label: 'Không xác định',
  className: 'border border-gray-200 bg-gray-100 text-gray-600',
}

const shortDateFormatter = new Intl.DateTimeFormat('vi-VN', {
  day: '2-digit',
  month: '2-digit',
})

function parseDisplayDate(value: string): Date {
  return /^\d{4}-\d{2}-\d{2}$/.test(value) ? new Date(`${value}T00:00:00`) : new Date(value)
}

export function buildBookingReference(
  booking: Pick<BookingDetailRaw, 'id' | 'created_at'>
): string {
  const createdAt = parseDisplayDate(booking.created_at)
  const year = Number.isNaN(createdAt.getFullYear())
    ? new Date().getFullYear()
    : createdAt.getFullYear()

  return `SOL-${year}-${String(booking.id).padStart(4, '0')}`
}

export function formatShortBookingDate(value: string): string {
  return shortDateFormatter.format(parseDisplayDate(value))
}

export function formatAdminBookingAmount(
  booking: Pick<BookingDetailRaw, 'amount' | 'amount_formatted'>
): string {
  if (typeof booking.amount === 'number') {
    return formatVND(booking.amount).replace(/\s?₫/, '₫')
  }

  if (booking.amount_formatted) {
    return booking.amount_formatted.replace(/\s?₫/, '₫')
  }

  return '—'
}

export function getAdminBookingStatusConfig(status: string): AdminBookingStatusConfig {
  return ADMIN_BOOKING_STATUS_MAP[status] ?? FALLBACK_STATUS_CONFIG
}

export function getAdminBookingRoomLabel(booking: BookingDetailRaw): string {
  const roomName = booking.room?.display_name ?? booking.room?.name
  return roomName && roomName.trim().length > 0 ? roomName : `Phòng #${booking.room_id}`
}

export function normalizeAdminBookingSearch(value: string): string {
  const trimmed = value.trim()
  const codeMatch = trimmed.match(/^sol-\d{4}-(\d+)$/i)

  if (!codeMatch) {
    return trimmed
  }

  const bookingId = Number.parseInt(codeMatch[1], 10)
  return Number.isNaN(bookingId) ? trimmed : String(bookingId)
}
