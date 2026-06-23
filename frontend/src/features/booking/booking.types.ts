/**
 * Booking Types
 *
 * TypeScript interfaces for booking-related data
 */

import type {
  Booking,
  BookingApiRaw,
  BookingDetailRaw,
  PaymentPolicy,
  PaymentStatus,
} from '@/shared/types/booking.types'

export interface BookingFormData {
  room_id: number
  guest_name: string
  guest_email: string
  check_in: string // YYYY-MM-DD format (date-only)
  check_out: string // YYYY-MM-DD format (date-only)
  number_of_guests: number
  special_requests: string | null
}

export interface BookingUpdateData {
  guest_name: string
  guest_email: string
  check_in: string // YYYY-MM-DD format (date-only)
  check_out: string // YYYY-MM-DD format (date-only)
  special_requests?: string | null
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
  PaymentPolicy,
  PaymentStatus,
} from '@/shared/types/booking.types'

export interface BookingPaymentIntentResponse {
  success: boolean
  data: {
    client_secret: string | null
    payment_policy: PaymentPolicy
    payment_status: PaymentStatus
  }
}

export interface BookingPaymentVerifyResponse {
  success: boolean
  message?: string
  data: BookingDetailRaw
}

export interface MoMoPaymentStartResponse {
  success: boolean
  data: {
    payUrl: string
    qrCodeUrl: string | null
    deeplink: string | null
    orderId: string
  }
}

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
