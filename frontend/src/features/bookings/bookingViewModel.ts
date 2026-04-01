import type { BookingApiRaw } from '@/features/booking/booking.types'
import { formatVND } from '@/shared/lib/formatCurrency'

/**
 * Booking ViewModel
 *
 * Transforms raw API data into a view-friendly shape.
 * All display logic lives here — components stay dumb.
 */

const CANCELLABLE_STATUSES: readonly string[] = ['pending', 'confirmed'] as const

export interface BookingViewModel {
  id: number
  status: string
  statusLabel: string
  roomName: string
  checkIn: Date
  checkOut: Date
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
    checkIn: new Date(raw.check_in),
    checkOut: new Date(raw.check_out),
    guestName: raw.guest_name,
    nights: raw.nights,
    amountFormatted: getAmountFormatted(raw),
    canCancel: CANCELLABLE_STATUSES.includes(raw.status),
    createdAt: new Date(raw.created_at),
  }
}

export function isUpcoming(booking: BookingViewModel): boolean {
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  return booking.checkIn >= today
}

export function isPast(booking: BookingViewModel): boolean {
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  return booking.checkOut < today
}
