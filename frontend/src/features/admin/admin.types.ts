/**
 * Admin Dashboard Types
 *
 * Types for admin-only endpoints. AdminBookingRaw extends the existing
 * BookingApiRaw with trashed/refund fields from BookingResource.
 */

import type { BookingApiRaw } from '@/features/booking/booking.types'

/**
 * Admin booking — extends BookingApiRaw with optional trashed/refund fields.
 * Present when viewing trashed bookings or bookings with cancellation data.
 */
export interface AdminBookingRaw extends BookingApiRaw {
  is_trashed?: boolean
  deleted_at?: string | null
  deleted_by?: { id: number; name: string; email: string } | null
  cancelled_at?: string | null
  cancelled_by?: { id: number; name: string } | null
  refund_amount?: number
  refund_amount_formatted?: string
  refund_status?: string
  refund_percentage?: number
}

/**
 * Contact message — raw shape from GET /v1/admin/contact-messages.
 * No ContactResource exists; backend returns raw model via paginator.
 */
export interface ContactMessageRaw {
  id: number
  name: string
  email: string
  subject: string | null
  message: string
  read_at: string | null
  created_at: string
  updated_at: string
}

/**
 * Pagination metadata from Laravel LengthAwarePaginator.
 */
export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

/**
 * Paginated bookings result (used by useAdminPaginatedFetch).
 */
export interface AdminBookingsPaginatedResult {
  bookings: AdminBookingRaw[]
  meta: PaginationMeta
}

/**
 * Response: GET /v1/admin/bookings
 */
export interface AdminBookingsResponse {
  success: boolean
  data: {
    bookings: AdminBookingRaw[]
    meta: PaginationMeta
  }
}

/**
 * Response: GET /v1/admin/bookings/trashed
 */
export interface TrashedBookingsResponse {
  success: boolean
  data: {
    bookings: AdminBookingRaw[]
    meta: {
      total_trashed: number
    }
  }
}

/**
 * Response: GET /v1/admin/contact-messages
 * Standard Laravel paginator shape.
 */
export interface ContactMessagesResponse {
  success: boolean
  data: {
    data: ContactMessageRaw[]
    current_page: number
    per_page: number
    total: number
  }
  message: string
}
