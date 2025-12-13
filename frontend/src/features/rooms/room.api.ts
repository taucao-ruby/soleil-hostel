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

/**
 * Get single room by ID
 *
 * GET /rooms/:id
 */
export async function getRoomById(id: number): Promise<Room> {
  const response = await api.get<{ data: Room }>(`/rooms/${id}`)
  return response.data.data
}
