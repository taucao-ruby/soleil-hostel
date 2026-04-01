/**
 * Amenity icon mapping
 *
 * Maps amenity keys from the backend to display emoji icons.
 * Used by LocationCard and LocationDetail components.
 */
export const amenityIcons: Record<string, string> = {
  wifi: '📶',
  air_conditioning: '❄️',
  hot_water: '🚿',
  breakfast: '🍳',
  parking: '🅿️',
  pool: '🏊',
  gym: '🏋️',
  laundry: '🧺',
  garden: '🌿',
  bbq: '🍖',
  kayaking: '🛶',
  fishing: '🎣',
}

export const amenityCardLabels: Record<string, string> = {
  wifi: 'Wifi',
  air_conditioning: 'AC',
  hot_water: 'Nước nóng',
  breakfast: 'Bữa sáng',
  parking: 'Đỗ xe',
  pool: 'Hồ bơi',
  gym: 'Gym',
  laundry: 'Giặt ủi',
  garden: 'Sân vườn',
  bbq: 'BBQ',
  kayaking: 'Kayak',
  fishing: 'Câu cá',
}

export const locationFallbackImages: Record<string, string> = {
  'soleil-hostel':
    'https://images.unsplash.com/photo-1590490360182-c33d57733427?auto=format&fit=crop&w=1200&q=80',
  'soleil-house':
    'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=1200&q=80',
  'soleil-urban-villa':
    'https://images.unsplash.com/photo-1600585154526-990dced4db0d?auto=format&fit=crop&w=1200&q=80',
  'soleil-boutique-homestay':
    'https://images.unsplash.com/photo-1568605114967-8130f3a36994?auto=format&fit=crop&w=1200&q=80',
  'soleil-riverside-villa':
    'https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=1200&q=80',
}
