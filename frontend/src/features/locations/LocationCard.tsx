import React from 'react'
import type { Location } from './location.types'

/**
 * Amenity icon mapping
 */
const amenityIcons: Record<string, string> = {
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

interface LocationCardProps {
  location: Location
  onClick: () => void
}

/**
 * LocationCard Component
 *
 * Displays a single location in a card layout with:
 * - Cover image (or gradient placeholder)
 * - Name, address, amenity icons
 * - Room stats (total / available)
 */
const LocationCard: React.FC<LocationCardProps> = ({ location, onClick }) => {
  const primaryImage = location.images?.[0]?.url

  return (
    <article
      className="overflow-hidden transition-shadow duration-300 bg-white shadow-md cursor-pointer rounded-xl hover:shadow-xl"
      onClick={onClick}
      role="button"
      tabIndex={0}
      onKeyDown={e => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault()
          onClick()
        }
      }}
    >
      {/* Location Image */}
      <div className="relative h-48 overflow-hidden bg-gradient-to-br from-amber-400 to-orange-600">
        {primaryImage ? (
          <img
            src={primaryImage}
            alt={location.name}
            className="object-cover w-full h-full"
            loading="lazy"
          />
        ) : (
          <div className="flex items-center justify-center w-full h-full">
            <svg
              className="w-16 h-16 text-white opacity-50"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={1.5}
                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
              />
            </svg>
          </div>
        )}
      </div>

      {/* Card Content */}
      <div className="p-6">
        {/* Name */}
        <h3 className="mb-2 text-xl font-bold text-gray-900">{location.name}</h3>

        {/* Address */}
        <p className="mb-4 text-sm text-gray-600">{location.address.full}</p>

        {/* Amenities */}
        {location.amenities.length > 0 && (
          <div className="flex flex-wrap gap-2 mb-4">
            {location.amenities.slice(0, 6).map(amenity => (
              <span
                key={amenity}
                className="text-lg"
                title={amenity.replace(/_/g, ' ')}
                role="img"
                aria-label={amenity.replace(/_/g, ' ')}
              >
                {amenityIcons[amenity] || '✓'}
              </span>
            ))}
            {location.amenities.length > 6 && (
              <span className="text-sm text-gray-500">+{location.amenities.length - 6} more</span>
            )}
          </div>
        )}

        {/* Stats */}
        <div className="flex items-center justify-between text-sm">
          <span className="text-gray-600">{location.stats.total_rooms} rooms</span>
          {location.stats.available_rooms !== undefined && (
            <span className="font-medium text-green-600">
              {location.stats.available_rooms} available
            </span>
          )}
        </div>
      </div>
    </article>
  )
}

export default LocationCard
