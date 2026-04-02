export type RoomStatus = 'available' | 'booked' | 'occupied' | 'maintenance'
export type RoomReadinessStatus =
  | 'ready'
  | 'occupied'
  | 'dirty'
  | 'cleaning'
  | 'inspected'
  | 'out_of_service'

export interface AdminRoomLocation {
  id: number
  name: string
  slug: string
}

// We extend the base Room type from our generic API types
export interface AdminRoom {
  id: number
  location_id: number
  name: string
  display_name?: string
  room_number: string | null
  description: string | null
  price: number
  max_guests: number
  room_type_code?: string | null
  room_tier?: number | null
  status: RoomStatus
  readiness_status: RoomReadinessStatus
  readiness_updated_at?: string | null
  readiness_updated_by?: number | null
  location?: AdminRoomLocation
  active_bookings_count?: number
  lock_version: number
  created_at: string
  updated_at: string
}

export interface CreateRoomData {
  location_id: number
  name: string
  room_number?: string | null
  description: string
  price: number
  max_guests: number
  status: RoomStatus
  readiness_status: RoomReadinessStatus
  room_type_code?: string | null
  room_tier?: number | null
}

export interface UpdateRoomData {
  location_id: number
  name: string
  room_number?: string | null
  description: string
  price: number
  max_guests: number
  status: RoomStatus
  readiness_status: RoomReadinessStatus
  room_type_code?: string | null
  room_tier?: number | null
  lock_version: number
}
