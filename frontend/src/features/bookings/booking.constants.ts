/**
 * Booking Display Constants
 *
 * Single source of truth for status badge rendering and date formatting.
 */

export interface StatusConfig {
  label: string
  colorClass: string
}

const STATUS_MAP: Record<string, StatusConfig> = {
  pending: { label: 'Chờ xác nhận', colorClass: 'bg-yellow-100 text-yellow-800' },
  confirmed: { label: 'Đã xác nhận', colorClass: 'bg-green-100 text-green-800' },
  cancelled: { label: 'Đã hủy', colorClass: 'bg-gray-100 text-gray-500' },
  completed: { label: 'Hoàn thành', colorClass: 'bg-blue-100 text-blue-800' },
  refund_pending: { label: 'Đang hoàn tiền', colorClass: 'bg-blue-100 text-blue-800' },
  refund_failed: { label: 'Hoàn tiền thất bại', colorClass: 'bg-red-100 text-red-800' },
}

const UNKNOWN_STATUS: StatusConfig = {
  label: 'Không xác định',
  colorClass: 'bg-gray-100 text-gray-700',
}

export function getStatusConfig(status: string): StatusConfig {
  return STATUS_MAP[status] ?? UNKNOWN_STATUS
}

/**
 * Format a Date to Vietnamese display: dd/MM/yyyy
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
 * Format a check-in / check-out range for display.
 */
export function formatDateRangeVN(checkIn: Date, checkOut: Date): string {
  return `${formatDateVN(checkIn)} — ${formatDateVN(checkOut)}`
}
