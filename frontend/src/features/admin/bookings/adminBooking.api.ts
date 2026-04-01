import api from '@/shared/lib/api'
import type { BookingDetailRaw } from '@/features/booking/booking.types'

export interface AdminBookingsResponse {
  bookings: BookingDetailRaw[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

// ----------------------------------------------------
// Admin Booking Types
// ----------------------------------------------------
export interface AdminBookingFilters {
  location_id?: number
  status?: string
  check_in_start?: string
  check_in_end?: string
  check_out_start?: string
  check_out_end?: string
  search?: string
  page?: number
}

// ----------------------------------------------------
// API Calls
// ----------------------------------------------------

export const getAllBookings = async (
  filters?: AdminBookingFilters,
  signal?: AbortSignal
): Promise<AdminBookingsResponse> => {
  const response = await api.get('/v1/admin/bookings', { params: filters, signal })
  return response.data.data
}

export const confirmBooking = async (id: number): Promise<BookingDetailRaw> => {
  const response = await api.post(`/v1/bookings/${id}/confirm`)
  return response.data.data
}

export const adminCancelBooking = async (id: number, reason: string): Promise<BookingDetailRaw> => {
  const response = await api.post(`/v1/bookings/${id}/cancel`, { reason })
  return response.data.data
}

export const getTrashedBookings = async (): Promise<BookingDetailRaw[]> => {
  const response = await api.get('/v1/admin/bookings/trashed')
  return response.data.data
}

export const restoreBooking = async (id: number): Promise<BookingDetailRaw> => {
  const response = await api.post(`/v1/admin/bookings/${id}/restore`)
  return response.data.data
}

export const forceDeleteBooking = async (id: number): Promise<void> => {
  await api.delete(`/v1/admin/bookings/${id}/force`)
}

export const getTodayArrivals = async (locationId?: number): Promise<BookingDetailRaw[]> => {
  const today = new Date().toISOString().split('T')[0]
  const response = await api.get('/v1/admin/bookings', {
    params: {
      location_id: locationId,
      check_in_start: today,
      check_in_end: today,
      status: 'confirmed',
    },
  })
  return response.data.data.bookings
}

export const getTodayDepartures = async (locationId?: number): Promise<BookingDetailRaw[]> => {
  const today = new Date().toISOString().split('T')[0]
  const response = await api.get('/v1/admin/bookings', {
    params: {
      location_id: locationId,
      check_out_start: today,
      check_out_end: today,
      status: 'confirmed',
    },
  })
  return response.data.data.bookings
}
