/**
 * Room Types
 *
 * TypeScript interfaces matching the backend Room model
 */

export interface Room {
  id: number
  name: string
  description: string
  price: number
  status: 'available' | 'occupied' | 'maintenance'
  image_url: string | null
  created_at: string
  updated_at: string
}

export interface RoomsResponse {
  data: Room[]
}
