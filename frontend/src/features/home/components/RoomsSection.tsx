import React from 'react'
import { Link } from 'react-router-dom'
import FilterChips from './FilterChips'
import RoomCard from './RoomCard'
import { MOCK_ROOMS, FILTER_CHIPS } from '../home.mock'

/**
 * RoomsSection — composes FilterChips + RoomCard list, spec § 04.
 *
 * Section header: "Phòng nổi bật" (left) + "Xem tất cả →" link (right).
 * Filter chips: Tất cả | Dorm | Phòng riêng | Deluxe (single-select).
 * Room cards: vertical stack from MOCK_ROOMS.
 */
const RoomsSection: React.FC = () => {
  return (
    <section aria-label="Phòng nổi bật">
      {/* Header row */}
      <div className="flex justify-between items-center px-4 mt-8">
        <h2 className="font-serif font-semibold text-[#5C3D1E] text-xl">Phòng nổi bật</h2>
        <Link
          to="/rooms"
          className="font-sans text-sm font-medium text-[#D4622A] hover:text-[#E8845A] transition-colors focus-visible:outline-none focus-visible:underline"
        >
          Xem tất cả →
        </Link>
      </div>

      {/* Filter chips */}
      <FilterChips chips={FILTER_CHIPS} />

      {/* Room cards */}
      <div className="px-4 mt-4 flex flex-col gap-4">
        {MOCK_ROOMS.map(room => (
          <RoomCard key={room.id} room={room} />
        ))}
      </div>
    </section>
  )
}

export default RoomsSection
