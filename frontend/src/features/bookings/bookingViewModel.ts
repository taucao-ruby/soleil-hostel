import {
  isCancellableBookingStatus,
  type BookingApiRaw,
  type BookingStatus,
} from '@/shared/types/booking.types'
import { formatVND } from '@/shared/lib/formatCurrency'
import { formatDateOnly, parseDateOnly } from '@/shared/lib/booking.utils'
import { getHostelToday } from '@/shared/lib/hostelDate'

/**
 * Booking ViewModel
 *
 * Transforms raw API data into a view-friendly shape.
 * All display logic lives here — components stay dumb.
 */

export interface BookingViewModel {
  id: number
  status: BookingStatus
  statusLabel: string
  roomName: string
  // `checkIn`/`checkOut` are UTC-midnight Dates for date math (isUpcoming/isPast).
  // `checkInDisplay`/`checkOutDisplay` are the timezone-stable dd/MM/yyyy strings
  // to render — formatting the Dates directly would drift a day off-UTC.
  checkIn: Date
  checkOut: Date
  checkInDisplay: string
  checkOutDisplay: string
  guestName: string
  nights: number
  amountFormatted: string | undefined
  canCancel: boolean
  createdAt: Date
}

function formatCompactVND(amount: number): string {
  return formatVND(amount).replace(/\s?₫/, '₫')
}

function getRoomName(raw: BookingApiRaw): string {
  const roomName = raw.room?.display_name ?? raw.room?.name
  return roomName && roomName.trim().length > 0 ? roomName : `Phòng #${raw.room_id}`
}

function getAmountFormatted(raw: BookingApiRaw): string | undefined {
  if (raw.amount_formatted) {
    return raw.amount_formatted.replace(/\s?₫/, '₫')
  }

  return typeof raw.amount === 'number' ? formatCompactVND(raw.amount) : undefined
}

export function toBookingViewModel(raw: BookingApiRaw): BookingViewModel {
  return {
    id: raw.id,
    status: raw.status,
    statusLabel: raw.status_label ?? raw.status,
    roomName: getRoomName(raw),
    checkIn: parseDateOnly(raw.check_in),
    checkOut: parseDateOnly(raw.check_out),
    checkInDisplay: formatDateOnly(raw.check_in),
    checkOutDisplay: formatDateOnly(raw.check_out),
    guestName: raw.guest_name,
    nights: raw.nights,
    amountFormatted: getAmountFormatted(raw),
    canCancel: isCancellableBookingStatus(raw.status),
    createdAt: new Date(raw.created_at),
  }
}

export function isUpcoming(booking: BookingViewModel): boolean {
  const today = parseDateOnly(getHostelToday())
  return booking.checkIn >= today
}

export function isPast(booking: BookingViewModel): boolean {
  const today = parseDateOnly(getHostelToday())
  return booking.checkOut < today
}
