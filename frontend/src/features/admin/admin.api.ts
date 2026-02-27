/**
 * Admin API Service
 *
 * API functions for admin-only endpoints.
 * Uses the shared api instance (httpOnly cookie auth + CSRF auto-handled).
 */

import api from '@/shared/lib/api'
import type {
  ContactMessageRaw,
  AdminBookingsResponse,
  AdminBookingsPaginatedResult,
  TrashedBookingsResponse,
  ContactMessagesResponse,
} from './admin.types'

/**
 * Fetch all bookings (admin view, includes soft-deleted).
 *
 * GET /v1/admin/bookings?page=N
 * Paginated 50/page. Returns bookings + pagination meta.
 */
export async function fetchAdminBookings(
  page: number = 1,
  signal?: AbortSignal
): Promise<AdminBookingsPaginatedResult> {
  const response = await api.get<AdminBookingsResponse>('/v1/admin/bookings', {
    params: { page },
    signal,
  })
  return {
    bookings: response.data.data.bookings,
    meta: response.data.data.meta,
  }
}

/**
 * Fetch only soft-deleted (trashed) bookings.
 *
 * GET /v1/admin/bookings/trashed
 */
export async function fetchTrashedBookings(
  signal?: AbortSignal
): Promise<AdminBookingsPaginatedResult> {
  const response = await api.get<TrashedBookingsResponse>('/v1/admin/bookings/trashed', { signal })
  return {
    bookings: response.data.data.bookings,
    meta: {
      current_page: 1,
      last_page: 1,
      per_page: response.data.data.bookings.length,
      total: response.data.data.meta.total_trashed,
    },
  }
}

/**
 * Fetch contact messages.
 *
 * GET /v1/admin/contact-messages
 * Paginated 15/page. V1: returns first page only.
 */
export async function fetchContactMessages(signal?: AbortSignal): Promise<ContactMessageRaw[]> {
  const response = await api.get<ContactMessagesResponse>('/v1/admin/contact-messages', { signal })
  return response.data.data.data
}

/**
 * Restore a soft-deleted booking.
 *
 * POST /v1/admin/bookings/:id/restore
 */
export async function restoreBooking(id: number): Promise<void> {
  await api.post(`/v1/admin/bookings/${id}/restore`)
}

/**
 * Permanently delete a soft-deleted booking.
 *
 * DELETE /v1/admin/bookings/:id/force
 */
export async function forceDeleteBooking(id: number): Promise<void> {
  await api.delete(`/v1/admin/bookings/${id}/force`)
}
