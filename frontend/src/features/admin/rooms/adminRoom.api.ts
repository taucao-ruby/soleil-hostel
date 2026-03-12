import api from '@/shared/lib/api'
import type { AdminRoom, CreateRoomData, UpdateRoomData, RoomStatus } from './adminRoom.types'

// Note: Ensure that the backend actually provides these endpoints in this format.
// The prompt indicated /api/v1/rooms as the base.

export const getRoomsByLocation = async (locationId: number): Promise<AdminRoom[]> => {
  const response = await api.get('/v1/rooms', { params: { location_id: locationId } })
  return response.data.data
}

export const createRoom = async (data: CreateRoomData): Promise<AdminRoom> => {
  const response = await api.post('/v1/rooms', data)
  return response.data.data
}

export const updateRoom = async (id: number, data: UpdateRoomData): Promise<AdminRoom> => {
  // Pass lock_version for Optimistic Locking
  const response = await api.put(`/v1/rooms/${id}`, data)
  return response.data.data
}

export const deleteRoom = async (id: number): Promise<void> => {
  await api.delete(`/v1/rooms/${id}`)
}

export const updateRoomStatus = async (
  id: number,
  status: RoomStatus,
  lock_version: number
): Promise<AdminRoom> => {
  const response = await api.patch(`/v1/rooms/${id}/status`, { status, lock_version })
  return response.data.data
}

export const batchUpdateStatus = async (
  roomIds: number[],
  status: RoomStatus
): Promise<AdminRoom[]> => {
  const response = await api.post('/v1/rooms/batch-status', { room_ids: roomIds, status })
  return response.data.data
}
