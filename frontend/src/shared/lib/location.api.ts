import api from '@/shared/lib/api'
import type { Location, LocationsResponse } from '@/shared/types/location.types'

/**
 * Get all active locations with room counts.
 *
 * GET /v1/locations
 */
export async function getLocations(signal?: AbortSignal): Promise<Location[]> {
  const response = await api.get<LocationsResponse>('/v1/locations', { signal })
  return response.data.data
}
