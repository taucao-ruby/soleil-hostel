import api from '@/shared/lib/api'
import type { AdminRoom, CreateRoomData, UpdateRoomData } from './adminRoom.types'

// Note: Ensure that the backend actually provides these endpoints in this format.
// The prompt indicated /api/v1/rooms as the base.

export const getRoomsByLocation = async (
  locationId?: number,
  signal?: AbortSignal
): Promise<AdminRoom[]> => {
  const response = await api.get('/v1/rooms', {
    params: typeof locationId === 'number' ? { location_id: locationId } : undefined,
    signal,
  })
  return response.data.data
}

export const getRoomById = async (id: number, signal?: AbortSignal): Promise<AdminRoom> => {
  const response = await api.get(`/v1/rooms/${id}`, { signal })
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

// TODO: [XL-CONTRACT-02] updateRoomStatus — backend route PATCH /v1/rooms/{id}/status not yet implemented
// TODO: [XL-CONTRACT-02] batchUpdateStatus — backend route POST /v1/rooms/batch-status not yet implemented
