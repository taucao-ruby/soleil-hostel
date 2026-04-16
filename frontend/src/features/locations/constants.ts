/**
 * Amenity Material Symbols icon name mapping.
 * Maps amenity keys to Material Symbols Outlined icon names.
 * Used by LocationDetail for the redesigned amenity grid.
 */
export const amenityMaterialIcons: Record<string, string> = {
  wifi: 'wifi',
  air_conditioning: 'ac_unit',
  hot_water: 'shower',
  breakfast: 'free_breakfast',
  parking: 'local_parking',
  pool: 'pool',
  gym: 'fitness_center',
  laundry: 'local_laundry_service',
  garden: 'park',
  bbq: 'outdoor_grill',
  kayaking: 'kayaking',
  fishing: 'set_meal',
}

/**
 * Amenity icon mapping (legacy emoji)
 *
 * Maps amenity keys from the backend to display emoji icons.
 * Used by LocationCard component.
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

/**
 * Hero fallback images — keyed by location slug.
 * Used when the API returns no images for a location.
 * All images are from Unsplash (free-to-use).
 */
export const locationFallbackImages: Record<string, string> = {
  // Central hostel — warm corridor / common area
  'soleil-hostel':
    'https://images.unsplash.com/photo-1590490360182-c33d57733427?auto=format&fit=crop&w=1400&q=85',
  // House — bright living room
  'soleil-house':
    'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=1400&q=85',
  // Urban villa — modern courtyard / pool
  'soleil-urban-villa':
    'https://images.unsplash.com/photo-1600585154526-990dced4db0d?auto=format&fit=crop&w=1400&q=85',
  // Boutique homestay — intimate colonial interior
  'soleil-boutique-homestay':
    'https://images.unsplash.com/photo-1571896349842-33c89424de2d?auto=format&fit=crop&w=1400&q=85',
  // Riverside villa — lush green riverfront
  'soleil-riverside-villa':
    'https://images.unsplash.com/photo-1439130490301-25e322d88054?auto=format&fit=crop&w=1400&q=85',
}

/**
 * Generic location hero pool — used when slug has no specific fallback.
 * Rotates by location id to give variety.
 */
export const locationHeroPool: string[] = [
  'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?auto=format&fit=crop&w=1400&q=85',
  'https://images.unsplash.com/photo-1582719508461-905c673771fd?auto=format&fit=crop&w=1400&q=85',
  'https://images.unsplash.com/photo-1445019980597-93fa8acb246c?auto=format&fit=crop&w=1400&q=85',
  'https://images.unsplash.com/photo-1496417263034-38ec4f0b665a?auto=format&fit=crop&w=1400&q=85',
  'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?auto=format&fit=crop&w=1400&q=85',
]

/**
 * Room image pool — cycled by room index to give each card a distinct image.
 * Boutique-hospitality feel: warm tones, natural light, minimal styling.
 */
export const roomImagePool: string[] = [
  'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?auto=format&fit=crop&w=600&q=80',
  'https://images.unsplash.com/photo-1582719508461-905c673771fd?auto=format&fit=crop&w=600&q=80',
  'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=600&q=80',
  'https://images.unsplash.com/photo-1611892440504-42a792e24d32?auto=format&fit=crop&w=600&q=80',
  'https://images.unsplash.com/photo-1540518614846-7eded433c457?auto=format&fit=crop&w=600&q=80',
  'https://images.unsplash.com/photo-1595576508898-0ad5c879a061?auto=format&fit=crop&w=600&q=80',
]
