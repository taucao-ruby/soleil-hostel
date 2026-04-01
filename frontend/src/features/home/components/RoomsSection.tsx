import React from 'react'
import { Link } from 'react-router-dom'
import FilterChips from './FilterChips'
import RoomCard from './RoomCard'
import { MOCK_ROOMS, FILTER_CHIPS } from '../home.mock'

/**
 * RoomsSection — amenity filter chips + 2-col room card grid (PROMPT_1A spec).
 *
 * Spec: 2-col grid, 16px gap.
 */
const RoomsSection: React.FC = () => {
  return (
    <section aria-label="Phòng nổi bật" className="mt-8">
      {/* Header row */}
      <div className="flex justify-between items-center px-4 mb-1">
        <h2 className="font-serif font-medium text-[#1C1A17] text-xl">Phòng nổi bật</h2>
        <Link
          to="/rooms"
          className="font-sans text-sm font-medium text-[#C9973A] hover:text-[#B8872A] transition-colors focus-visible:outline-none focus-visible:underline"
        >
          Xem tất cả →
        </Link>
      </div>

      {/* Amenity filter chips */}
      <FilterChips chips={FILTER_CHIPS} />

      {/* 2-col room card grid — 16px gap */}
      <div className="px-4 grid grid-cols-2 gap-4">
        {MOCK_ROOMS.map(room => (
          <RoomCard key={room.id} room={room} />
        ))}
      </div>
    </section>
  )
}

export default RoomsSection
