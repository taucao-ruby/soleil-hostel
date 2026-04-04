import React from 'react'
import type { Location } from '@/shared/types/location.types'
import { amenityCardLabels, amenityIcons, locationFallbackImages } from './constants'

interface LocationCardProps {
  location: Location
  onClick: () => void
}

const fallbackDescription =
  'Không gian lưu trú ấm cúng với vị trí thuận tiện để khám phá thành phố và tận hưởng nhịp sống địa phương.'

const LocationCard: React.FC<LocationCardProps> = ({ location, onClick }) => {
  const primaryImage = location.images?.[0]?.url || locationFallbackImages[location.slug]
  const summary = location.description?.trim() || fallbackDescription
  const amenityPreview = location.amenities.slice(0, 4)
  const totalRooms = location.stats.rooms_count ?? location.stats.total_rooms
  const roomAvailability = location.stats.available_rooms ?? totalRooms

  return (
    <article
      className="group overflow-hidden rounded-[24px] border border-hueBorder bg-white shadow-[0_18px_40px_rgba(28,26,23,0.08)] transition duration-300 hover:-translate-y-1 hover:shadow-[0_24px_54px_rgba(28,26,23,0.12)]"
      onClick={onClick}
      role="button"
      tabIndex={0}
      aria-label={`Xem chi tiết ${location.name}`}
      onKeyDown={event => {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault()
          onClick()
        }
      }}
    >
      <div className="relative h-56 overflow-hidden bg-gradient-to-br from-[#dcc292] via-[#c9973a] to-[#5d4220]">
        {primaryImage ? (
          <img
            src={primaryImage}
            alt={location.name}
            className="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]"
            loading="lazy"
          />
        ) : null}
        <div className="absolute inset-0 pointer-events-none bg-gradient-to-t from-black/15 via-transparent to-transparent" />
      </div>

      <div className="p-5">
        <h3 className="text-[1.9rem] font-semibold leading-tight text-hueBlack sm:text-[2rem]">
          {location.name}
        </h3>

        <div className="flex items-start gap-2 mt-2 text-sm text-hueMuted">
          <svg
            className="mt-0.5 h-4 w-4 flex-none text-hueMuted"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
            aria-hidden="true"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={1.8}
              d="M12 21s-6-4.35-6-10a6 6 0 1112 0c0 5.65-6 10-6 10z"
            />
            <circle cx="12" cy="11" r="2.5" strokeWidth="1.8" />
          </svg>
          <span>{location.address.full}</span>
        </div>

        <p className="mt-3 line-clamp-2 text-[15px] leading-6 text-hueBlack/80">{summary}</p>

        {amenityPreview.length > 0 && (
          <div className="grid grid-cols-5 gap-2 pb-4 mt-5 border-b border-hueBorder">
            {amenityPreview.map(amenity => (
              <div key={amenity} className="flex flex-col items-center gap-1 text-center">
                <span className="text-[17px]" aria-hidden="true">
                  {amenityIcons[amenity] || '✨'}
                </span>
                <span className="text-[10px] font-medium leading-3 text-hueBlack">
                  {amenityCardLabels[amenity] || amenity}
                </span>
              </div>
            ))}

            {location.amenities.length > amenityPreview.length ? (
              <div className="flex flex-col items-center gap-1 text-center">
                <span className="text-[17px]" aria-hidden="true">
                  ➕
                </span>
                <span className="text-[10px] font-medium leading-3 text-hueBlack">Khác</span>
              </div>
            ) : (
              <div className="hidden sm:block" aria-hidden="true" />
            )}
          </div>
        )}

        <div className="flex items-center justify-between gap-4 mt-4 text-sm">
          <span className="inline-flex items-center gap-2 text-hueMuted">
            <svg
              className="w-4 h-4 text-hueMuted"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
              aria-hidden="true"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={1.8}
                d="M3 12h18M5 12V9a2 2 0 012-2h10a2 2 0 012 2v3M7 12v5m10-5v5M5 17h14"
              />
            </svg>
            {totalRooms} Phòng
          </span>
          <span className="font-medium text-hueBlack">{roomAvailability} còn trống</span>
        </div>
      </div>
    </article>
  )
}

export default LocationCard
