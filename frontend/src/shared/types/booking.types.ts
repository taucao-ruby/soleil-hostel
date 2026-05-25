/**
 * Shared Booking Types
 *
 * Cross-feature booking types used by admin, bookings, and booking features.
 */

export const BOOKING_STATUSES = [
  'pending',
  'confirmed',
  'refund_pending',
  'cancelled',
  'refund_failed',
] as const

export type BookingStatus = (typeof BOOKING_STATUSES)[number]

export const CANCELLABLE_BOOKING_STATUSES = [
  'pending',
  'confirmed',
  'refund_failed',
] as const satisfies readonly BookingStatus[]

export const CANCELLED_BOOKING_STATUSES = [
  'cancelled',
  'refund_pending',
  'refund_failed',
] as const satisfies readonly BookingStatus[]

export interface BookingRoomSummary {
  id: number
  name: string
  display_name?: string | null
  room_number?: string | null
}

export interface BookingDetailRoom extends BookingRoomSummary {
  display_name?: string | null
  room_number?: string | null
  max_guests?: number
  price?: number
}

export interface BookingActorSummary {
  id: number
  name: string
}

/**
 * Booking API raw shape from GET /v1/bookings (BookingResource)
 *
 * Matches BookingResource::toArray — only fields confirmed in the resource.
 * Optional fields use `$this->when()` in Laravel and may be absent.
 */
export interface BookingApiRaw {
  id: number
  room_id: number
  user_id: number
  check_in: string
  check_out: string
  guest_name: string
  guest_email: string
  number_of_guests: number | null
  special_requests: string | null
  status: BookingStatus
  status_label: string | null
  nights: number
  amount?: number
  amount_formatted?: string
  room?: BookingRoomSummary
  refund_amount?: number
  refund_amount_formatted?: string
  refund_status?: string
  cancelled_at?: string | null
  cancelled_by?: BookingActorSummary | null
  refund_percentage?: number
  created_at: string
  updated_at: string
}

export interface BookingDetailRaw extends BookingApiRaw {
  room?: BookingDetailRoom
}

export interface Booking extends BookingDetailRaw {
  total_price?: number
}

export type UnvalidatedBooking<T extends { status: BookingStatus }> = Omit<T, 'status'> & {
  status: unknown
}

export function isBookingStatus(value: unknown): value is BookingStatus {
  return typeof value === 'string' && (BOOKING_STATUSES as readonly string[]).includes(value)
}

export function parseBookingStatus(value: unknown): BookingStatus {
  if (isBookingStatus(value)) {
    return value
  }

  throw new Error(`Unknown booking status: ${String(value)}`)
}

export function parseBookingStatusPayload<T extends { status: unknown }>(
  booking: T
): Omit<T, 'status'> & { status: BookingStatus } {
  return {
    ...booking,
    status: parseBookingStatus(booking.status),
  }
}

export function isCancellableBookingStatus(status: BookingStatus): boolean {
  return (CANCELLABLE_BOOKING_STATUSES as readonly BookingStatus[]).includes(status)
}

export function isCancelledBookingStatus(status: BookingStatus): boolean {
  return (CANCELLED_BOOKING_STATUSES as readonly BookingStatus[]).includes(status)
}

export function assertNever(value: never): never {
  throw new Error(`Unhandled booking status: ${String(value)}`)
}
