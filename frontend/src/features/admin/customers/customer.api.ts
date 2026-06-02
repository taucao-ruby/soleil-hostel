import api from '@/shared/lib/api'
import {
  parseBookingStatusPayload,
  type BookingDetailRaw,
  type UnvalidatedBooking,
} from '@/shared/types/booking.types'

export interface CustomerSummary {
  email: string
  name: string
  total_stays: number
  total_spent: string | number
  last_visit: string
  first_visit: string
}

export interface CustomerProfile extends CustomerSummary {
  total_nights: string | number
  preferred_location: string | null
  average_rating: number | null
}

export interface PaginatedCustomers {
  current_page: number
  data: CustomerSummary[]
  last_page: number
  total: number
}

export interface CustomerStats {
  total_customers: number
  total_revenue: string | number
  returning_customers: number
  return_rate: number
}

export const getCustomers = async (
  search?: string,
  page: number = 1,
  signal?: AbortSignal
): Promise<PaginatedCustomers> => {
  const response = await api.get('/v1/admin/customers', { params: { search, page }, signal })
  // Assuming the backend wraps the paginated resource in { success: true, data: [...], meta: {...} }
  return {
    data: response.data.data,
    current_page: response.data.meta.current_page,
    last_page: response.data.meta.last_page,
    total: response.data.meta.total,
  }
}

export const getCustomerProfile = async (
  email: string,
  signal?: AbortSignal
): Promise<CustomerProfile> => {
  const response = await api.get(`/v1/admin/customers/${encodeURIComponent(email)}`, { signal })
  return response.data.data
}

export const getCustomerBookings = async (
  email: string,
  signal?: AbortSignal
): Promise<BookingDetailRaw[]> => {
  const response = await api.get<{ data: Array<UnvalidatedBooking<BookingDetailRaw>> }>(
    `/v1/admin/customers/${encodeURIComponent(email)}/bookings`,
    { signal }
  )
  return response.data.data.map(parseBookingStatusPayload)
}

export const getCustomerStats = async (signal?: AbortSignal): Promise<CustomerStats> => {
  const response = await api.get('/v1/admin/customers/stats', { signal })
  return response.data.data
}
