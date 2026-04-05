import React, { useState, useEffect, useCallback } from 'react'
import { useNavigate } from 'react-router-dom'
import { getLocations } from '@/shared/lib/location.api'
import type { Location } from '@/shared/types/location.types'

/**
 * SearchCard — responsive search form per PROMPT_1A spec.
 *
 * Mobile  (<md): Vertical stacked card with shadow
 * Tablet+ (≥md): Horizontal inline bar — location | check-in | check-out | button
 *
 * H-02 regression: "Tìm phòng trống" appears exactly once on the page.
 */
const SearchCard: React.FC = () => {
  const navigate = useNavigate()
  const today = new Date().toISOString().slice(0, 10)
  const tomorrow = new Date(Date.now() + 86_400_000).toISOString().slice(0, 10)

  const [locations, setLocations] = useState<Location[]>([])
  const [locationsLoading, setLocationsLoading] = useState(true)
  const [locationsError, setLocationsError] = useState(false)

  const [selectedSlug, setSelectedSlug] = useState('')
  const [checkIn, setCheckIn] = useState(today)
  const [checkOut, setCheckOut] = useState(tomorrow)
  const [validationError, setValidationError] = useState<string | null>(null)

  const fetchLocationsData = useCallback(async (signal?: AbortSignal) => {
    setLocationsLoading(true)
    setLocationsError(false)
    try {
      const data = await getLocations(signal)
      if (!signal?.aborted) {
        setLocations(data)
        if (data.length > 0) setSelectedSlug(data[0].slug)
      }
    } catch (err: unknown) {
      if (err instanceof Error && (err.name === 'AbortError' || err.name === 'CanceledError'))
        return
      if (!signal?.aborted) setLocationsError(true)
    } finally {
      if (!signal?.aborted) setLocationsLoading(false)
    }
  }, [])

  useEffect(() => {
    const controller = new AbortController()
    void fetchLocationsData(controller.signal)
    return () => controller.abort()
  }, [fetchLocationsData])

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault()
    setValidationError(null)
    if (!selectedSlug || !checkIn || !checkOut) {
      setValidationError('Vui lòng chọn chi nhánh và ngày.')
      return
    }
    if (checkOut <= checkIn) {
      setValidationError('Ngày trả phòng phải sau ngày nhận phòng.')
      return
    }
    navigate(`/locations/${selectedSlug}?check_in=${checkIn}&check_out=${checkOut}`)
  }

  const baseCls =
    'w-full bg-[#F7F3EE] border border-[#E2DDD6] rounded-lg px-3 py-2.5 text-sm text-[#1C1A17] focus:outline-none focus:ring-2 focus:ring-[#C9973A] focus:border-transparent'

  return (
    <form role="search" aria-label="Tìm kiếm phòng" onSubmit={handleSearch}>
      {/* ── Mobile layout (<md): vertical stacked card ─────────────── */}
      <div className="md:hidden bg-white rounded-2xl shadow-[0_8px_32px_rgba(0,0,0,0.14)] border border-[#E2DDD6] p-4">
        <p className="text-sm font-medium text-[#6B6760] mb-3">Tìm phòng trống</p>

        {/* Chi nhánh */}
        <label className="block mb-3">
          <span className="text-xs font-medium text-[#6B6760] uppercase tracking-wide mb-1.5 block">
            Chi nhánh
          </span>
          {locationsLoading ? (
            <div
              className="animate-pulse bg-[#F7F3EE] rounded-lg h-[40px]"
              aria-hidden="true"
              data-testid="locations-skeleton"
            />
          ) : locationsError ? (
            <button
              type="button"
              onClick={() => void fetchLocationsData()}
              className="w-full text-left px-3 py-2.5 bg-[#F7F3EE] border border-red-300 rounded-lg text-sm text-red-600"
            >
              Lỗi — Thử lại
            </button>
          ) : locations.length === 0 ? (
            <div className={`${baseCls} text-[#6B6760]`}>Không có chi nhánh</div>
          ) : (
            <select
              value={selectedSlug}
              onChange={e => setSelectedSlug(e.target.value)}
              className={baseCls}
            >
              {locations.map(loc => (
                <option key={loc.slug} value={loc.slug}>
                  {loc.name}
                </option>
              ))}
            </select>
          )}
        </label>

        {/* Dates */}
        <div className="grid grid-cols-2 gap-3 mb-3">
          <label>
            <span className="text-xs font-medium text-[#6B6760] uppercase tracking-wide mb-1.5 block">
              Nhận phòng
            </span>
            <input
              type="date"
              value={checkIn}
              min={today}
              onChange={e => setCheckIn(e.target.value)}
              className={baseCls}
            />
          </label>
          <label>
            <span className="text-xs font-medium text-[#6B6760] uppercase tracking-wide mb-1.5 block">
              Trả phòng
            </span>
            <input
              type="date"
              value={checkOut}
              min={checkIn}
              onChange={e => setCheckOut(e.target.value)}
              className={baseCls}
            />
          </label>
        </div>

        {validationError && (
          <p className="mb-2 text-xs text-red-600 font-medium" role="alert">
            {validationError}
          </p>
        )}

        <button
          type="submit"
          disabled={locationsLoading || locationsError || locations.length === 0}
          className="w-full h-11 bg-[#C9973A] hover:bg-[#B8872A] text-white font-semibold text-sm rounded-xl transition-colors duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#C9973A] focus-visible:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          Tìm kiếm →
        </button>
      </div>

      {/* ── Desktop layout (≥md): horizontal inline bar ─────────────── */}
      <div className="hidden md:flex bg-white rounded-2xl shadow-[0_8px_40px_rgba(0,0,0,0.16)] border border-[#E2DDD6] overflow-hidden">
        {/* Label strip above — outside the bar */}
        {/* Integrated inline */}

        {/* Chi nhánh */}
        <div className="flex flex-col px-5 py-3 min-w-0 flex-[2]">
          <span className="text-[11px] font-semibold text-[#6B6760] uppercase tracking-widest mb-1">
            Chi nhánh
          </span>
          {locationsLoading ? (
            <div
              className="animate-pulse bg-[#F0EDE8] rounded h-6 w-32"
              data-testid="locations-skeleton"
            />
          ) : locationsError ? (
            <button
              type="button"
              onClick={() => void fetchLocationsData()}
              className="text-left text-sm text-red-500"
            >
              Thử lại
            </button>
          ) : (
            <select
              value={selectedSlug}
              onChange={e => setSelectedSlug(e.target.value)}
              className="bg-transparent border-0 text-sm text-[#1C1A17] focus:outline-none focus:ring-0 cursor-pointer appearance-none"
            >
              {locations.map(loc => (
                <option key={loc.slug} value={loc.slug}>
                  {loc.name}
                </option>
              ))}
            </select>
          )}
        </div>

        {/* Divider */}
        <div className="w-px bg-[#E2DDD6] my-3" />

        {/* Nhận phòng */}
        <div className="flex flex-col px-5 py-3 min-w-0 flex-1">
          <span className="text-[11px] font-semibold text-[#6B6760] uppercase tracking-widest mb-1">
            Nhận phòng
          </span>
          <input
            type="date"
            value={checkIn}
            min={today}
            onChange={e => setCheckIn(e.target.value)}
            className="bg-transparent border-0 text-sm text-[#1C1A17] focus:outline-none focus:ring-0 cursor-pointer"
          />
        </div>

        {/* Divider */}
        <div className="w-px bg-[#E2DDD6] my-3" />

        {/* Trả phòng */}
        <div className="flex flex-col px-5 py-3 min-w-0 flex-1">
          <span className="text-[11px] font-semibold text-[#6B6760] uppercase tracking-widest mb-1">
            Trả phòng
          </span>
          <input
            type="date"
            value={checkOut}
            min={checkIn}
            onChange={e => setCheckOut(e.target.value)}
            className="bg-transparent border-0 text-sm text-[#1C1A17] focus:outline-none focus:ring-0 cursor-pointer"
          />
        </div>

        {/* Submit */}
        <button
          type="submit"
          disabled={locationsLoading || locationsError || locations.length === 0}
          className="shrink-0 m-2 px-8 bg-[#C9973A] hover:bg-[#B8872A] text-white font-semibold text-sm rounded-xl transition-colors duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#C9973A] focus-visible:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap"
        >
          Tìm kiếm →
        </button>
      </div>

      {validationError && (
        <p
          className="hidden md:block mt-2 text-xs text-red-600 font-medium text-center"
          role="alert"
        >
          {validationError}
        </p>
      )}
    </form>
  )
}

export default SearchCard
