import api from '@/shared/lib/api'
import {
  Booking,
  BookingApiRaw,
  BookingFormData,
  BookingResponse,
  BookingsListResponse,
  CancelBookingResponse,
} from './booking.types'

/**
 * Booking API Service
 *
 * All booking-related API calls using the shared api instance.
 */

/**
 * Create a new booking
 *
 * POST /bookings
 * Creates a new room booking
 */
export async function createBooking(data: BookingFormData): Promise<Booking> {
  const response = await api.post<BookingResponse>('/v1/bookings', data)
  return response.data.data
}

/**
 * Fetch current user's bookings
 *
 * GET /v1/bookings
 * Returns list of bookings for the authenticated user.
 * Response shape: { success: boolean, data: BookingApiRaw[] }
 */
export async function fetchMyBookings(signal?: AbortSignal): Promise<BookingApiRaw[]> {
  const response = await api.get<BookingsListResponse>('/v1/bookings', { signal })
  return response.data.data
}

/**
 * Cancel a booking
 *
 * POST /v1/bookings/:id/cancel
 * CSRF token auto-attached by request interceptor.
 */
export async function cancelBooking(id: number): Promise<CancelBookingResponse> {
  const response = await api.post<CancelBookingResponse>(`/v1/bookings/${id}/cancel`)
  return response.data
}
