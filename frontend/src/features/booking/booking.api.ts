import api from '@/shared/lib/api'
import { Booking, BookingFormData, BookingResponse } from './booking.types'

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
  const response = await api.post<BookingResponse>('/bookings', data)
  return response.data.data
}

/**
 * Get all bookings (for authenticated user)
 *
 * GET /bookings
 */
export async function getMyBookings(): Promise<Booking[]> {
  const response = await api.get<{ data: Booking[] }>('/bookings')
  return response.data.data
}

/**
 * Get single booking by ID
 *
 * GET /bookings/:id
 */
export async function getBookingById(id: number): Promise<Booking> {
  const response = await api.get<{ data: Booking }>(`/bookings/${id}`)
  return response.data.data
}

/**
 * Cancel a booking
 *
 * DELETE /bookings/:id
 */
export async function cancelBooking(id: number): Promise<void> {
  await api.delete(`/bookings/${id}`)
}
