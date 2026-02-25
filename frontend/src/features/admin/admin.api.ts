/**
 * Admin API Service
 *
 * API functions for admin-only endpoints.
 * Uses the shared api instance (httpOnly cookie auth + CSRF auto-handled).
 */

import api from '@/shared/lib/api'
import type {
  AdminBookingRaw,
  ContactMessageRaw,
  AdminBookingsResponse,
  TrashedBookingsResponse,
  ContactMessagesResponse,
} from './admin.types'

/**
 * Fetch all bookings (admin view, includes soft-deleted).
 *
 * GET /v1/admin/bookings
 * Paginated 50/page. V1: returns first page only.
 */
export async function fetchAdminBookings(signal?: AbortSignal): Promise<AdminBookingRaw[]> {
  const response = await api.get<AdminBookingsResponse>('/v1/admin/bookings', { signal })
  return response.data.data.bookings
}

/**
 * Fetch only soft-deleted (trashed) bookings.
 *
 * GET /v1/admin/bookings/trashed
 */
export async function fetchTrashedBookings(signal?: AbortSignal): Promise<AdminBookingRaw[]> {
  const response = await api.get<TrashedBookingsResponse>('/v1/admin/bookings/trashed', { signal })
  return response.data.data.bookings
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
