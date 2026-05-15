/**
 * Booking Display Constants
 *
 * Single source of truth for status badge rendering and date formatting.
 */

import type { BookingStatus } from '@/shared/types/booking.types'

export interface StatusConfig {
  label: string
  colorClass: string
}

const STATUS_MAP: Record<BookingStatus, StatusConfig> = {
  pending: { label: 'Chờ xác nhận', colorClass: 'bg-yellow-100 text-yellow-800' },
  confirmed: { label: 'Đã xác nhận', colorClass: 'bg-green-100 text-green-800' },
  cancelled: { label: 'Đã hủy', colorClass: 'bg-gray-100 text-gray-500' },
  refund_pending: { label: 'Đang hoàn tiền', colorClass: 'bg-blue-100 text-blue-800' },
  refund_failed: { label: 'Hoàn tiền thất bại', colorClass: 'bg-red-100 text-red-800' },
}

export function getStatusConfig(status: BookingStatus): StatusConfig {
  return STATUS_MAP[status]
}

/**
 * Format a `Date` instant to Vietnamese display (dd/MM/yyyy) in the runtime's
 * local timezone. For zoneless calendar values — booking check-in/check-out,
 * which arrive as YYYY-MM-DD strings — use `formatDateOnly`, which cannot drift
 * across timezones. Passing a date-only value here renders the wrong day in
 * negative-offset zones.
 */
const dateFormatterVN = new Intl.DateTimeFormat('vi-VN', {
  day: '2-digit',
  month: '2-digit',
  year: 'numeric',
})

export function formatDateVN(date: Date): string {
  return dateFormatterVN.format(date)
}

/**
 * Format a check-in / check-out range of `Date` instants for display. Same
 * timezone caveat as `formatDateVN`; for raw YYYY-MM-DD strings format each end
 * with `formatDateOnly` instead.
 */
export function formatDateRangeVN(checkIn: Date, checkOut: Date): string {
  return `${formatDateVN(checkIn)} — ${formatDateVN(checkOut)}`
}

const ISO_DATE_ONLY_RE = /^\d{4}-\d{2}-\d{2}$/

/**
 * Format a civil date-only string (YYYY-MM-DD) for Vietnamese display: dd/MM/yyyy.
 *
 * Booking check-in/check-out values are zoneless calendar dates. Routing them
 * through `new Date(str)` anchors them to UTC midnight, and `Intl` then renders
 * that instant in the runtime's local zone — shifting the displayed day in any
 * negative-offset timezone. This rearranges the calendar parts directly, so the
 * output is identical in every timezone. Nullish input renders as an em dash;
 * a string that is not date-only is returned unchanged.
 */
export function formatDateOnly(dateStr: string | null | undefined): string {
  if (!dateStr) return '—'
  if (!ISO_DATE_ONLY_RE.test(dateStr)) return dateStr
  const [year, month, day] = dateStr.split('-')
  return `${day}/${month}/${year}`
}

/**
 * Parse a civil date-only string (YYYY-MM-DD) into a UTC-midnight `Date`.
 *
 * Use only where an actual `Date` is unavoidable (e.g. `Intl` weekday
 * formatting); always render the result with `timeZone: 'UTC'` so the calendar
 * date cannot drift. For plain dd/MM/yyyy display, prefer `formatDateOnly`.
 */
export function parseDateOnly(dateStr: string): Date {
  const [year, month, day] = dateStr.split('-').map(Number)
  return new Date(Date.UTC(year, month - 1, day))
}

/**
 * Today's civil date as YYYY-MM-DD in the runtime's local zone — for lexical
 * comparison against zoneless booking check-in/check-out values.
 */
export function todayDateOnly(): string {
  const now = new Date()
  const year = now.getFullYear()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}
