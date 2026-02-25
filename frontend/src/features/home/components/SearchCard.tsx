import React, { useState, useEffect, useCallback } from 'react'
import { useNavigate } from 'react-router-dom'
import { getLocations } from '@/features/locations/location.api'
import type { Location } from '@/features/locations/location.types'

/**
 * SearchCard — THE only place "Tìm phòng trống" appears (FIX H-02).
 *
 * Floating card that overlaps the hero by 48px (applied via wrapper -mt-12 in HomePage).
 * Fetches locations on mount, then navigates to /locations/:slug with search params.
 */
const SearchCard: React.FC = () => {
  const navigate = useNavigate()
  const today = new Date().toISOString().slice(0, 10)
  const tomorrow = new Date(Date.now() + 86_400_000).toISOString().slice(0, 10)

  const [locations, setLocations] = useState<Location[]>([])
  const [locationsLoading, setLocationsLoading] = useState(true)
  const [locationsError, setLocationsError] = useState(false)

  const [selectedSlug, setSelectedSlug] = useState('')
  const [guests, setGuests] = useState(1)
  const [checkIn, setCheckIn] = useState(today)
  const [checkOut, setCheckOut] = useState(tomorrow)
  const [validationError, setValidationError] = useState<string | null>(null)

  const fetchLocationsData = useCallback(async () => {
    setLocationsLoading(true)
    setLocationsError(false)
    try {
      const data = await getLocations()
      setLocations(data)
      if (data.length > 0) {
        setSelectedSlug(data[0].slug)
      }
    } catch {
      setLocationsError(true)
    } finally {
      setLocationsLoading(false)
    }
  }, [])

  useEffect(() => {
    fetchLocationsData()
  }, [fetchLocationsData])

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault()
    setValidationError(null)

    if (!selectedSlug) {
      setValidationError('Vui lòng chọn chi nhánh.')
      return
    }
    if (!checkIn || !checkOut) {
      setValidationError('Vui lòng chọn ngày nhận và trả phòng.')
      return
    }
    if (checkOut <= checkIn) {
      setValidationError('Ngày trả phòng phải sau ngày nhận phòng.')
      return
    }

    const params = new URLSearchParams({
      check_in: checkIn,
      check_out: checkOut,
    })
    if (guests > 1) params.set('guests', String(guests))

    navigate(`/locations/${selectedSlug}?${params.toString()}`)
  }

  const inputClass =
    'bg-[#F5EFE0] rounded-xl p-3 text-sm font-medium text-[#5C3D1E] border-0 focus:outline-none focus:ring-2 focus:ring-[#D4622A]'

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
          {locationsLoading ? (
            <div
              className="animate-pulse bg-[#F5EFE0] rounded-xl h-[44px]"
              aria-hidden="true"
              data-testid="locations-skeleton"
            />
          ) : locationsError ? (
            <button
              type="button"
              onClick={fetchLocationsData}
              className={`${inputClass} text-red-600 text-left`}
            >
              Lỗi — Thử lại
            </button>
          ) : locations.length === 0 ? (
            <div className={`${inputClass} text-gray-400`}>Không có chi nhánh</div>
          ) : (
            <select
              value={selectedSlug}
              onChange={e => setSelectedSlug(e.target.value)}
              className={inputClass}
            >
              {locations.map(loc => (
                <option key={loc.slug} value={loc.slug}>
                  {loc.name}
                </option>
              ))}
            </select>
          )}
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
            className={inputClass}
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
            className={inputClass}
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
            className={inputClass}
          />
        </label>
      </div>

      {/* Validation error */}
      {validationError && (
        <p className="mt-2 text-xs text-red-600 font-medium" role="alert">
          {validationError}
        </p>
      )}

      {/* THE ONE AND ONLY "Tìm phòng trống" in the entire page (FIX H-02) */}
      <button
        type="submit"
        disabled={locationsLoading || locationsError || locations.length === 0}
        className="w-full mt-3 h-12 bg-[#D4622A] hover:bg-[#E8845A] text-white font-sans font-semibold text-base rounded-xl transition-colors duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#D4622A] focus-visible:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
      >
        🔍 Tìm phòng trống
      </button>
    </form>
  )
}

export default SearchCard
