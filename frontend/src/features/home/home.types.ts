export interface HomeRoom {
  id: number
  slug: string
  name: string
  type: string
  badge: string
  price: number // VND per night
  maxGuests: number
  rating: number
  reviewCount: number
  amenities: string[] // e.g. ['AC', 'Bath', 'Wifi']
  imageUrl: string
  location: string // e.g. "Soleil House" — shown as badge on card image
  available: boolean // controls "Đặt ngay" button visibility
}

export interface HomeReview {
  id: number
  rating: number
  quote: string
  guestName: string
  guestLocation: string
  roomName: string
}

export interface FilterChip {
  id: string
  label: string
}

export interface SearchParams {
  branch: string
  guests: number
  checkIn: string
  checkOut: string
}
