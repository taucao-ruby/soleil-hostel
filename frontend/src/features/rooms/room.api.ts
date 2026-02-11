import api from '@/shared/lib/api'
import { Room, RoomsResponse } from './room.types'

/**
 * Room API Service
 *
 * All room-related API calls using the shared api instance.
 */

/**
 * Get all rooms
 *
 * GET /rooms
 * Returns list of all available rooms
 */
export async function getRooms(): Promise<Room[]> {
  const response = await api.get<RoomsResponse>('/rooms')
  return response.data.data
}
