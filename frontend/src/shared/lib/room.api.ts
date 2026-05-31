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

export const roomApi = {
  /**
   * Update a room's operational readiness via the canonical readiness endpoint.
   *
   * PATCH /api/v1/rooms/{id}/readiness — sends only `readiness_status`, the
   * canonical physical-readiness field (NOT the deprecated availability `status`,
   * and NOT a full-room read-modify-write). Authorized for moderator+ operators.
   * SH-10 / F-63.
   */
  async updateReadiness(
    roomId: number,
    readinessStatus: RoomReadinessStatus
  ): Promise<RoomResourceData> {
    const response = await api.patch<RoomResponse>(`/v1/rooms/${roomId}/readiness`, {
      readiness_status: readinessStatus,
    })

    return response.data.data
  },
}
