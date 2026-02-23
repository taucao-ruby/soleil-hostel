import React, { useState } from 'react'

/**
 * SearchCard — THE only place "Tìm phòng trống" appears (FIX H-02).
 *
 * Floating card that overlaps the hero by 48px (applied via wrapper -mt-12 in HomePage).
 * onClick stub: wire to availability API in a future sprint.
 */
const SearchCard: React.FC = () => {
  const today = new Date().toISOString().slice(0, 10)
  const tomorrow = new Date(Date.now() + 86_400_000).toISOString().slice(0, 10)

  const [branch, setBranch] = useState('Huế — Phú Hội')
  const [guests, setGuests] = useState(1)
  const [checkIn, setCheckIn] = useState(today)
  const [checkOut, setCheckOut] = useState(tomorrow)

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault()
    // TODO: wire to availability API
  }

  return (
    <form
      role="search"
      aria-label="Tìm kiếm phòng"
      onSubmit={handleSearch}
      className="bg-[#FDFAF3] rounded-2xl shadow-[0_4px_24px_rgba(92,61,30,0.12)] border border-[#E2D5C3] p-4"
    >
      {/* 2×2 grid — spec § 03 */}
      <div className="grid grid-cols-2 gap-3">
        {/* Chi nhánh */}
        <label className="flex flex-col gap-1">
          <span className="text-xs font-sans font-medium text-[#5C3D1E]/60 uppercase tracking-wide">
            📍 Chi nhánh
          </span>
          <select
            value={branch}
            onChange={e => setBranch(e.target.value)}
            className="bg-[#F5EFE0] rounded-xl p-3 text-sm font-medium text-[#5C3D1E] border-0 focus:outline-none focus:ring-2 focus:ring-[#D4622A]"
          >
            <option value="Huế — Phú Hội">Huế — Phú Hội</option>
            <option value="Huế — Thành Nội">Huế — Thành Nội</option>
          </select>
        </label>

        {/* Số khách */}
        <label className="flex flex-col gap-1">
          <span className="text-xs font-sans font-medium text-[#5C3D1E]/60 uppercase tracking-wide">
            👥 Số khách
          </span>
          <input
            type="number"
            value={guests}
            min={1}
            max={20}
            onChange={e => setGuests(Number(e.target.value))}
            className="bg-[#F5EFE0] rounded-xl p-3 text-sm font-medium text-[#5C3D1E] border-0 focus:outline-none focus:ring-2 focus:ring-[#D4622A]"
          />
        </label>

        {/* Nhận phòng */}
        <label className="flex flex-col gap-1">
          <span className="text-xs font-sans font-medium text-[#5C3D1E]/60 uppercase tracking-wide">
            📅 Nhận phòng
          </span>
          <input
            type="date"
            value={checkIn}
            min={today}
            onChange={e => setCheckIn(e.target.value)}
            className="bg-[#F5EFE0] rounded-xl p-3 text-sm font-medium text-[#5C3D1E] border-0 focus:outline-none focus:ring-2 focus:ring-[#D4622A]"
          />
        </label>

        {/* Trả phòng */}
        <label className="flex flex-col gap-1">
          <span className="text-xs font-sans font-medium text-[#5C3D1E]/60 uppercase tracking-wide">
            📅 Trả phòng
          </span>
          <input
            type="date"
            value={checkOut}
            min={checkIn}
            onChange={e => setCheckOut(e.target.value)}
            className="bg-[#F5EFE0] rounded-xl p-3 text-sm font-medium text-[#5C3D1E] border-0 focus:outline-none focus:ring-2 focus:ring-[#D4622A]"
          />
        </label>
      </div>

      {/* THE ONE AND ONLY "Tìm phòng trống" in the entire page (FIX H-02) */}
      <button
        type="submit"
        className="w-full mt-3 h-12 bg-[#D4622A] hover:bg-[#E8845A] text-white font-sans font-semibold text-base rounded-xl transition-colors duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#D4622A] focus-visible:ring-offset-2"
      >
        🔍 Tìm phòng trống
      </button>
    </form>
  )
}

export default SearchCard
