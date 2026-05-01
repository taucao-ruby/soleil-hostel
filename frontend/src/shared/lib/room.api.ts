import api from '@/shared/lib/api'

export type RoomAvailabilityStatus = 'available' | 'booked' | 'maintenance'
export type RoomReadinessStatus =
  | 'ready'
  | 'occupied'
  | 'dirty'
  | 'cleaning'
  | 'inspected'
  | 'out_of_service'

interface RoomResourceData {
  id: number
  location_id: number
  name: string
  description: string | null
  price: number
  max_guests: number
  status: string
  readiness_status: RoomReadinessStatus | null
  room_type_code?: string | null
  room_tier?: number | null
  created_at?: string
  updated_at?: string
}

interface RoomResponse {
  data: RoomResourceData
}

interface RoomUpdatePayload {
  location_id: number
  name: string
  description: string
  price: number
  max_guests: number
  status: RoomAvailabilityStatus
  readiness_status: RoomReadinessStatus
  room_type_code: string | null
  room_tier: number | null
}

const normalizeAvailabilityStatus = (status: string): RoomAvailabilityStatus => {
  if (status === 'booked' || status === 'maintenance') {
    return status
  }

  return 'available'
}

const buildRoomUpdatePayload = (
  room: RoomResourceData,
  readinessStatus: RoomReadinessStatus
): RoomUpdatePayload => ({
  location_id: room.location_id,
  name: room.name,
  description: room.description ?? '',
  price: room.price,
  max_guests: room.max_guests,
  status: normalizeAvailabilityStatus(room.status),
  readiness_status: readinessStatus,
  room_type_code: room.room_type_code ?? null,
  room_tier: room.room_tier ?? null,
})

export const roomApi = {
  async updateStatus(
    roomId: number,
    readinessStatus: RoomReadinessStatus
  ): Promise<RoomResourceData> {
    const currentRoom = await api.get<RoomResponse>(`/v1/rooms/${roomId}`)
    const response = await api.patch<RoomResponse>(
      `/v1/rooms/${roomId}`,
      buildRoomUpdatePayload(currentRoom.data.data, readinessStatus)
    )

    return response.data.data
  },
}
