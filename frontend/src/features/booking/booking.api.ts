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
