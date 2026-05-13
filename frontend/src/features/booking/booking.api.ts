import api from '@/shared/lib/api'
import { parseBookingStatusPayload, type UnvalidatedBooking } from '@/shared/types/booking.types'
import type {
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

type BookingResponsePayload = Omit<BookingResponse, 'data'> & {
  data: UnvalidatedBooking<Booking>
}

type BookingsListResponsePayload = Omit<BookingsListResponse, 'data'> & {
  data: Array<UnvalidatedBooking<BookingApiRaw>>
}

type CancelBookingResponsePayload = Omit<CancelBookingResponse, 'data'> & {
  data: UnvalidatedBooking<BookingApiRaw>
}

type BookingDetailResponsePayload = Omit<BookingDetailResponse, 'data'> & {
  data: UnvalidatedBooking<BookingDetailRaw>
}

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
  const response = await api.post<BookingResponsePayload>('/v1/bookings', data)
  return parseBookingStatusPayload(response.data.data)
}

/**
 * Fetch current user's bookings
 *
 * GET /v1/bookings
 * Returns list of bookings for the authenticated user.
 * Response shape: { success: boolean, data: BookingApiRaw[] }
 */
export async function fetchMyBookings(signal?: AbortSignal): Promise<BookingApiRaw[]> {
  const response = await api.get<BookingsListResponsePayload>('/v1/bookings', { signal })
  return response.data.data.map(parseBookingStatusPayload)
}

/**
 * Cancel a booking
 *
 * POST /v1/bookings/:id/cancel
 * CSRF token auto-attached by request interceptor.
 */
export async function cancelBooking(id: number): Promise<CancelBookingResponse> {
  const response = await api.post<CancelBookingResponsePayload>(`/v1/bookings/${id}/cancel`)
  return {
    ...response.data,
    data: parseBookingStatusPayload(response.data.data),
  }
}

/**
 * Fetch a single booking by ID
 *
 * GET /v1/bookings/:id
 * Requires authentication. Returns booking with eager-loaded room relationship.
 */
export async function getBookingById(id: number, signal?: AbortSignal): Promise<BookingDetailRaw> {
  const response = await api.get<BookingDetailResponsePayload>(`/v1/bookings/${id}`, { signal })
  return parseBookingStatusPayload(response.data.data)
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
