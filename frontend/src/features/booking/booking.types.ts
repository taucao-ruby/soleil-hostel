/**
 * Booking Types
 *
 * TypeScript interfaces for booking-related data
 */

import type { Booking, BookingApiRaw, BookingDetailRaw } from '@/shared/types/booking.types'

export interface BookingFormData {
  room_id: number
  guest_name: string
  guest_email: string
  check_in: string // YYYY-MM-DD format (date-only)
  check_out: string // YYYY-MM-DD format (date-only)
  number_of_guests: number
  special_requests?: string
}

export interface BookingResponse {
  data: Booking
  message?: string
}

export type {
  Booking,
  BookingApiRaw,
  BookingDetailRaw,
  BookingDetailRoom,
  BookingStatus,
} from '@/shared/types/booking.types'

export interface BookingsListResponse {
  success: boolean
  data: BookingApiRaw[]
}

export interface CancelBookingResponse {
  success: boolean
  message: string
  data: BookingApiRaw
}

export interface BookingDetailResponse {
  success: boolean
  data: BookingDetailRaw
}

export interface ReviewSubmitData {
  booking_id: number
  title: string
  content: string
  rating: number
}

export interface ReviewSubmitResponse {
  success: boolean
  message: string
  data: {
    id: number
    title: string
    content: string
    rating: number
    booking_id: number
    room_id: number
    user_id: number
    approved: boolean
    created_at: string
    updated_at: string
  }
}
