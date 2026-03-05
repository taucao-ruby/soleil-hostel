/**
 * Booking Types
 *
 * TypeScript interfaces for booking-related data
 */

export interface BookingFormData {
  room_id: number
  guest_name: string
  guest_email: string
  check_in: string // ISO date string
  check_out: string // ISO date string
  number_of_guests: number
  special_requests?: string
}

export interface Booking {
  id: number
  room_id: number
  guest_name: string
  guest_email: string
  check_in: string
  check_out: string
  number_of_guests: number
  special_requests: string | null
  status: 'pending' | 'confirmed' | 'cancelled' | 'completed'
  total_price: number
  created_at: string
  updated_at: string
}

export interface BookingResponse {
  data: Booking
  message?: string
}

// Import for local use + re-export for other features (admin, bookings)
import type { BookingApiRaw } from '@/shared/types/booking.types'
export type { BookingApiRaw } from '@/shared/types/booking.types'

export interface BookingsListResponse {
  success: boolean
  data: BookingApiRaw[]
}

export interface CancelBookingResponse {
  success: boolean
  message: string
  data: BookingApiRaw
}

/** Room summary returned inside BookingDetailRaw (whenLoaded in BookingResource) */
export interface BookingDetailRoom {
  id: number
  name: string
  display_name: string | null
  room_number: string | null
  max_guests: number
  price: number
}

/**
 * Single-booking detail shape from GET /v1/bookings/:id
 * Extends BookingApiRaw with additional fields returned when the room
 * relationship is eager-loaded (BookingService::getBookingById loads room).
 */
export interface BookingDetailRaw extends BookingApiRaw {
  cancelled_at?: string
  refund_amount_formatted?: string
  room?: BookingDetailRoom
}

export interface BookingDetailResponse {
  success: boolean
  data: BookingDetailRaw
}
