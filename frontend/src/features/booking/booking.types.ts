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
