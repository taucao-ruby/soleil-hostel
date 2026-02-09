/**
 * Location Types
 *
 * TypeScript interfaces matching the backend Location model & API responses.
 */

export interface LocationAddress {
  full: string
  street: string
  ward: string | null
  district: string | null
  city: string
  postal_code: string | null
}

export interface LocationCoordinates {
  lat: number
  lng: number
}

export interface LocationContact {
  phone: string | null
  email: string | null
}

export interface LocationStats {
  total_rooms: number
  available_rooms?: number
  rooms_count?: number
}

export interface LocationImage {
  url: string
  alt: string
  order: number
}

export interface Location {
  id: number
  name: string
  slug: string
  address: LocationAddress
  coordinates: LocationCoordinates | null
  contact: LocationContact
  description: string | null
  amenities: string[]
  images: LocationImage[]
  stats: LocationStats
  is_active: boolean
  created_at: string
}

export interface LocationWithRooms extends Location {
  rooms: LocationRoom[]
}

export interface LocationRoom {
  id: number
  name: string
  display_name: string
  room_number: string | null
  description: string | null
  price: number
  max_guests: number
  status: 'available' | 'occupied' | 'maintenance'
  location_id: number
  lock_version: number
  active_bookings_count: number
  created_at: string
  updated_at: string
}

export interface LocationsResponse {
  success: boolean
  message: string
  data: Location[]
}

export interface LocationResponse {
  success: boolean
  message: string
  data: LocationWithRooms
}

export interface AvailabilityResponse {
  success: boolean
  message: string
  data: {
    location: Location
    available_rooms: LocationRoom[]
    total_available: number
  }
}
