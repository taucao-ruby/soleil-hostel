/**
 * Room Types
 *
 * TypeScript interfaces matching the backend RoomResource output.
 */

export interface Room {
  id: number
  name: string
  display_name?: string
  room_number?: string | null
  description: string | null
  price: number
  max_guests: number | null
  status: 'available' | 'booked' | 'maintenance'
  room_type_code?: string | null
  room_tier?: string | null
  image_url?: string | null
  location?: {
    id: number
    name: string
    slug: string
  } | null
  location_id?: number | null
  active_bookings_count?: number
  lock_version?: number
  created_at: string
  updated_at: string
}

export interface RoomsResponse {
  data: Room[]
}
