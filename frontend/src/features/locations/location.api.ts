import api from '@/shared/lib/api'
import type {
  Location,
  LocationWithRooms,
  LocationsResponse,
  LocationResponse,
  AvailabilityResponse,
  LocationRoom,
} from './location.types'

/**
 * Location API Service
 *
 * All location-related API calls using the shared api instance.
 * Endpoints are public (no authentication required).
 */

/**
 * Get all active locations with room counts.
 *
 * GET /v1/locations
 */
export async function getLocations(): Promise<Location[]> {
  const response = await api.get<LocationsResponse>('/v1/locations')
  return response.data.data
}

/**
 * Get a single location by slug with its rooms.
 *
 * GET /v1/locations/:slug
 *
 * @param slug - Location URL slug (e.g., 'soleil-hostel')
 * @param params - Optional date/guest filters
 */
export async function getLocationBySlug(
  slug: string,
  params?: {
    check_in?: string
    check_out?: string
    guests?: number
  }
): Promise<LocationWithRooms> {
  const response = await api.get<LocationResponse>(`/v1/locations/${slug}`, {
    params,
  })
  return response.data.data
}

/**
 * Check room availability at a specific location.
 *
 * GET /v1/locations/:slug/availability
 *
 * @param slug - Location URL slug
 * @param checkIn - Check-in date (YYYY-MM-DD)
 * @param checkOut - Check-out date (YYYY-MM-DD)
 * @param guests - Optional minimum guest capacity
 */
export async function checkAvailability(
  slug: string,
  checkIn: string,
  checkOut: string,
  guests?: number
): Promise<{
  location: Location
  available_rooms: LocationRoom[]
  total_available: number
}> {
  const response = await api.get<AvailabilityResponse>(`/v1/locations/${slug}/availability`, {
    params: {
      check_in: checkIn,
      check_out: checkOut,
      ...(guests ? { guests } : {}),
    },
  })
  return response.data.data
}
