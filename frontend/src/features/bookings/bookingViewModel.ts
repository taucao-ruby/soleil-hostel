import type { BookingApiRaw } from '@/features/booking/booking.types'

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
  checkIn: Date
  checkOut: Date
  guestName: string
  nights: number
  amountFormatted: string | undefined
  canCancel: boolean
  createdAt: Date
}

export function toBookingViewModel(raw: BookingApiRaw): BookingViewModel {
  return {
    id: raw.id,
    status: raw.status,
    statusLabel: raw.status_label ?? raw.status,
    checkIn: new Date(raw.check_in),
    checkOut: new Date(raw.check_out),
    guestName: raw.guest_name,
    nights: raw.nights,
    amountFormatted: raw.amount_formatted,
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
