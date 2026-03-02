import api from '@/shared/lib/api'
import type {
  Location,
  LocationWithRooms,
  LocationsResponse,
  LocationResponse,
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
export async function getLocations(signal?: AbortSignal): Promise<Location[]> {
  const response = await api.get<LocationsResponse>('/v1/locations', { signal })
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
