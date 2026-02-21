import type { HomeRoom, HomeReview, FilterChip } from './home.types'

// TODO: replace with local hostel photo asset
// TEMPORARY — remove before production
export const HERO_IMAGE_URL =
  'https://images.unsplash.com/photo-1555854877-bab0e564b8d5?auto=format&fit=crop&w=1200&q=80'

export const MOCK_ROOMS: HomeRoom[] = [
  {
    id: 1,
    slug: 'dorm-bunk',
    name: 'Phòng Dorm Giường Tầng',
    type: 'Dorm',
    badge: 'Phổ biến',
    price: 250_000,
    maxGuests: 6,
    rating: 4.9,
    reviewCount: 124,
    amenities: ['AC', 'Bath', 'Wifi'],
    imageUrl:
      'https://images.unsplash.com/photo-1555854877-bab0e564b8d5?auto=format&fit=crop&w=400&q=80', // TODO: local asset
  },
  {
    id: 2,
    slug: 'private-river',
    name: 'Phòng Đôi Hướng Sông',
    type: 'Private',
    badge: 'Còn ít phòng',
    price: 850_000,
    maxGuests: 2,
    rating: 4.8,
    reviewCount: 87,
    amenities: ['AC', 'Bath', 'Wifi'],
    imageUrl:
      'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?auto=format&fit=crop&w=400&q=80', // TODO: local asset
  },
  {
    id: 3,
    slug: 'suite-rooftop',
    name: 'Suite Deluxe Sân Thượng',
    type: 'Deluxe',
    badge: 'Mới',
    price: 1_200_000,
    maxGuests: 2,
    rating: 5.0,
    reviewCount: 12,
    amenities: ['AC', 'Bath', 'Wifi'],
    imageUrl:
      'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?auto=format&fit=crop&w=400&q=80', // TODO: local asset
  },
]

export const MOCK_REVIEWS: HomeReview[] = [
  {
    id: 1,
    rating: 5,
    quote: 'Hostel ấm cúng, nhân viên thân thiện. Vị trí trung tâm Huế rất tiện lợi. Sẽ quay lại!',
    guestName: 'Nguyễn Minh Châu',
    guestLocation: 'Hà Nội',
    roomName: 'Phòng Dorm Giường Tầng',
  },
  {
    id: 2,
    rating: 5,
    quote: 'Phòng dorm rộng rãi, điều hòa mát. Bữa sáng ngon. Recommend cho budget traveler.',
    guestName: 'Trần Bảo Long',
    guestLocation: 'TP. Hồ Chí Minh',
    roomName: 'Phòng Dorm Giường Tầng',
  },
  {
    id: 3,
    rating: 5,
    quote: 'View phòng đẹp lắm. Gần cầu Trường Tiền, đi bộ ra Thành Nội 10 phút. Xuất sắc!',
    guestName: 'Lê Thị Hoa',
    guestLocation: 'Đà Nẵng',
    roomName: 'Phòng Đôi Hướng Sông',
  },
]

export const FILTER_CHIPS: FilterChip[] = [
  { id: 'all', label: 'Tất cả' },
  { id: 'dorm', label: 'Dorm' },
  { id: 'private', label: 'Phòng riêng' },
  { id: 'deluxe', label: 'Deluxe' },
]
