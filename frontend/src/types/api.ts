/**
 * ==========================================
 * API RESPONSE TYPES (TypeScript)
 * ==========================================
 */

// Base API Response
export interface ApiResponse {
  message?: string
  success?: boolean
}

// Room Type
export interface Room {
  id: number
  name: string
  price: number
  max_guests: number
  status: 'available' | 'booked' | 'maintenance'
  description?: string
  image_url?: string
  created_at?: string
  updated_at?: string
}

export interface RoomsResponse extends ApiResponse {
  data: Room[]
}

// Canonical User type - use this everywhere
export interface User {
  id: number
  name: string
  email: string
  role: 'guest' | 'staff' | 'admin'
  email_verified_at: string | null
  created_at: string
  updated_at: string
}

// Auth Response
export interface AuthResponse {
  user: User
  csrf_token: string
}

// Booking Type
export interface Booking {
  id: number
  room_id: number
  user_id?: number
  guest_name: string
  guest_email: string
  check_in: string
  check_out: string
  number_of_guests?: number
  status?: 'pending' | 'confirmed' | 'cancelled' | 'completed'
  total_price?: number
  created_at?: string
  updated_at?: string
}

export interface BookingResponse extends ApiResponse {
  data: Booking
}

export interface BookingsResponse extends ApiResponse {
  data: Booking[]
}

// Review Type
export interface Review {
  id: number
  user_id: number
  room_id?: number
  rating: number
  content: string
  created_at?: string
  updated_at?: string
}

export interface ReviewsResponse extends ApiResponse {
  data: Review[]
}

/**
 * ==========================================
 * API ERROR RESPONSE
 * ==========================================
 */

export interface ApiError {
  message: string
  errors?: Record<string, string[]> // Laravel validation errors
  exception?: string
  file?: string
  line?: number
  trace?: unknown[]
}
