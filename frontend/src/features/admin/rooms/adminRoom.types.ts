export type RoomStatus = 'available' | 'occupied' | 'maintenance'

// We extend the base Room type from our generic API types
export interface AdminRoom {
  id: number
  location_id: number
  name: string
  room_number: string
  description: string | null
  price: number
  max_guests: number
  status: RoomStatus
  lock_version: number
  created_at: string
  updated_at: string
}

export interface CreateRoomData {
  location_id: number
  name: string
  room_number?: string
  description?: string
  price: number
  max_guests: number
  status: RoomStatus
}

export interface UpdateRoomData {
  location_id: number
  name: string
  room_number?: string
  description?: string
  price: number
  max_guests: number
  status: RoomStatus
  lock_version: number
}
