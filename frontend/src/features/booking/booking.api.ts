import api from '@/shared/lib/api'
import {
  Booking,
  BookingApiRaw,
  BookingDetailRaw,
  BookingDetailResponse,
  BookingFormData,
  BookingResponse,
  BookingsListResponse,
  CancelBookingResponse,
  ReviewSubmitData,
  ReviewSubmitResponse,
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

/**
 * Fetch a single booking by ID
 *
 * GET /v1/bookings/:id
 * Requires authentication. Returns booking with eager-loaded room relationship.
 */
export async function getBookingById(id: number, signal?: AbortSignal): Promise<BookingDetailRaw> {
  const response = await api.get<BookingDetailResponse>(`/v1/bookings/${id}`, { signal })
  return response.data.data
}

/**
 * Submit a review for a booking
 *
 * POST /v1/reviews
 * Requires authentication. Policy enforced server-side:
 * - booking must be owned by authenticated user
 * - booking status must be confirmed
 * - check_out must be in the past
 * - one review per booking (DB unique constraint enforced)
 */
export async function submitReview(data: ReviewSubmitData): Promise<ReviewSubmitResponse> {
  const response = await api.post<ReviewSubmitResponse>('/v1/reviews', data)
  return response.data
}
