import api from '@/shared/lib/api'
import { getHostelToday } from '@/shared/lib/hostelDate'
import {
  parseBookingStatusPayload,
  type BookingDetailRaw,
  type BookingStatus,
  type UnvalidatedBooking,
} from '@/shared/types/booking.types'

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
  status?: BookingStatus
  check_in_start?: string
  check_in_end?: string
  check_out_start?: string
  check_out_end?: string
  search?: string
  page?: number
}

type AdminBookingsResponsePayload = Omit<AdminBookingsResponse, 'bookings'> & {
  bookings: Array<UnvalidatedBooking<BookingDetailRaw>>
}

type BookingDetailPayload = UnvalidatedBooking<BookingDetailRaw>

// ----------------------------------------------------
// API Calls
// ----------------------------------------------------

export const getAllBookings = async (
  filters?: AdminBookingFilters,
  signal?: AbortSignal
): Promise<AdminBookingsResponse> => {
  const response = await api.get<{ data: AdminBookingsResponsePayload }>('/v1/admin/bookings', {
    params: filters,
    signal,
  })
  return {
    ...response.data.data,
    bookings: response.data.data.bookings.map(parseBookingStatusPayload),
  }
}

export const confirmBooking = async (id: number): Promise<BookingDetailRaw> => {
  const response = await api.post<{ data: BookingDetailPayload }>(`/v1/bookings/${id}/confirm`)
  return parseBookingStatusPayload(response.data.data)
}

export const adminCancelBooking = async (id: number, reason: string): Promise<BookingDetailRaw> => {
  const response = await api.post<{ data: BookingDetailPayload }>(`/v1/bookings/${id}/cancel`, {
    reason,
  })
  return parseBookingStatusPayload(response.data.data)
}

export const getTrashedBookings = async (): Promise<BookingDetailRaw[]> => {
  const response = await api.get<{ data: AdminBookingsResponsePayload }>(
    '/v1/admin/bookings/trashed'
  )
  return response.data.data.bookings.map(parseBookingStatusPayload)
}

export const restoreBooking = async (id: number): Promise<BookingDetailRaw> => {
  const response = await api.post<{ data: BookingDetailPayload }>(
    `/v1/admin/bookings/${id}/restore`
  )
  return parseBookingStatusPayload(response.data.data)
}

export const forceDeleteBooking = async (id: number): Promise<void> => {
  await api.delete(`/v1/admin/bookings/${id}/force`)
}

export const getTodayArrivals = async (
  locationId?: number,
  signal?: AbortSignal
): Promise<BookingDetailRaw[]> => {
  const today = getHostelToday()
  const response = await api.get<{ data: AdminBookingsResponsePayload }>('/v1/admin/bookings', {
    params: {
      location_id: locationId,
      check_in_start: today,
      check_in_end: today,
      status: 'confirmed',
    },
    signal,
  })
  return response.data.data.bookings.map(parseBookingStatusPayload)
}

export const getTodayDepartures = async (
  locationId?: number,
  signal?: AbortSignal
): Promise<BookingDetailRaw[]> => {
  const today = getHostelToday()
  const response = await api.get<{ data: AdminBookingsResponsePayload }>('/v1/admin/bookings', {
    params: {
      location_id: locationId,
      check_out_start: today,
      check_out_end: today,
      status: 'confirmed',
    },
    signal,
  })
  return response.data.data.bookings.map(parseBookingStatusPayload)
}
