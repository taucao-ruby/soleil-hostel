import type { HomeRoom, HomeReview, FilterChip } from './home.types'

// TODO: replace with local hostel photo asset
// TEMPORARY — remove before production
export const HERO_IMAGE_URL =
  'https://images.unsplash.com/photo-1555854877-bab0e564b8d5?auto=format&fit=crop&w=1200&q=80'

export const MOCK_ROOMS: HomeRoom[] = [
  {
    id: 1,
    slug: 'dorm-bunk-8',
    name: 'Giường tầng trong phòng ngủ tập thể 8 người',
    type: 'Dorm',
    badge: 'Phổ biến',
    price: 350_000,
    maxGuests: 8,
    rating: 4.9,
    reviewCount: 124,
    amenities: ['Wifi', 'AC'],
    imageUrl:
      'https://images.unsplash.com/photo-1555854877-bab0e564b8d5?auto=format&fit=crop&w=400&q=80', // TODO: local asset
    location: 'Soleil House',
    available: true,
  },
  {
    id: 2,
    slug: 'dorm-bunk-river',
    name: 'Giường tầng trong phòng ngủ tập thể 8 người',
    type: 'Dorm',
    badge: 'Còn ít phòng',
    price: 350_000,
    maxGuests: 8,
    rating: 4.8,
    reviewCount: 87,
    amenities: ['Wifi', 'AC'],
    imageUrl:
      'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?auto=format&fit=crop&w=400&q=80', // TODO: local asset
    location: 'Soleil House',
    available: true,
  },
  {
    id: 3,
    slug: 'dorm-bunk-garden',
    name: 'Giường tầng trong phòng ngủ tập thể 8 người',
    type: 'Dorm',
    badge: 'Phổ biến',
    price: 350_000,
    maxGuests: 8,
    rating: 4.7,
    reviewCount: 56,
    amenities: ['Wifi', 'AC'],
    imageUrl:
      'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?auto=format&fit=crop&w=400&q=80', // TODO: local asset
    location: 'Soleil House',
    available: true,
  },
  {
    id: 4,
    slug: 'dorm-bunk-deluxe',
    name: 'Giường tầng trong phòng ngủ tập thể 8 người',
    type: 'Dorm',
    badge: 'Mới',
    price: 350_000,
    maxGuests: 8,
    rating: 5.0,
    reviewCount: 12,
    amenities: ['Wifi', 'AC'],
    imageUrl:
      'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?auto=format&fit=crop&w=400&q=80', // TODO: local asset
    location: 'Soleil House',
    available: true,
  },
]

export const MOCK_REVIEWS: HomeReview[] = [
  {
    id: 1,
    rating: 5,
    quote: 'Hostel ấm cúng, nhân viên thân thiện. Vị trí trung tâm Huế rất tiện lợi. Sẽ quay lại!',
    guestName: 'Nguyễn Văn A',
    guestLocation: 'Hà Nội',
    roomName: 'Phòng Dorm Giường Tầng',
  },
  {
    id: 2,
    rating: 5,
    quote: 'Phòng dorm rộng rãi, điều hòa mát. Bữa sáng ngon. Recommend cho budget traveler.',
    guestName: 'Nguyễn Văn B',
    guestLocation: 'TP. Hồ Chí Minh',
    roomName: 'Phòng Dorm Giường Tầng',
  },
  {
    id: 3,
    rating: 5,
    quote: 'View phòng đẹp lắm. Gần cầu Trường Tiền, đi bộ ra Thành Nội 10 phút. Xuất sắc!',
    guestName: 'Nguyễn Văn C',
    guestLocation: 'Đà Nẵng',
    roomName: 'Phòng Đôi Hướng Sông',
  },
]

// Amenity filter chips (PROMPT_1A spec)
export const FILTER_CHIPS: FilterChip[] = [
  { id: 'wifi', label: '📶 WiFi' },
  { id: 'ac', label: '❄️ Điều hòa' },
  { id: 'breakfast', label: '🍳 Bữa sáng' },
  { id: 'pool', label: '🏊 Hồ bơi' },
  { id: 'parking', label: '🅿️ Đỗ xe' },
  { id: 'hotwater', label: '🚿 Nước nóng' },
]
