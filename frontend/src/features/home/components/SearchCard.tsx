import React, { useState, useEffect, useCallback } from 'react'
import { useNavigate } from 'react-router-dom'
import { getLocations } from '@/shared/lib/location.api'
import type { Location } from '@/shared/types/location.types'

/**
 * SearchCard — floating search form per PROMPT_1A spec.
 *
 * Layout:
 *   "Tìm phòng trống" heading (satisfies H-02 regression — appears exactly once)
 *   Row 1: <select> Chi nhánh — full-width, lazy fetch /v1/locations
 *   Row 2 (2-col): Nhận phòng date | Trả phòng date
 *   Row 3: "Tìm kiếm →" button — brand amber #C9973A fill
 *
 * States: loading skeleton | location error + retry | empty locations | default
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
      }
      if (!signal?.aborted && data.length > 0) {
        setSelectedSlug(data[0].slug)
      }
    } catch (err: unknown) {
      if (err instanceof Error && (err.name === 'AbortError' || err.name === 'CanceledError')) {
        return
      }
      if (!signal?.aborted) {
        setLocationsError(true)
      }
    } finally {
      if (!signal?.aborted) {
        setLocationsLoading(false)
      }
    }
  }, [])

  useEffect(() => {
    const controller = new AbortController()
    void fetchLocationsData(controller.signal)
    return () => {
      controller.abort()
    }
  }, [fetchLocationsData])

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault()
    setValidationError(null)

    if (!selectedSlug) {
      setValidationError('Vui lòng chọn chi nhánh và ngày.')
      return
    }
    if (!checkIn || !checkOut) {
      setValidationError('Vui lòng chọn chi nhánh và ngày.')
      return
    }
    if (checkOut <= checkIn) {
      setValidationError('Ngày trả phòng phải sau ngày nhận phòng.')
      return
    }

    navigate(`/locations/${selectedSlug}?check_in=${checkIn}&check_out=${checkOut}`)
  }

  const inputCls =
    'w-full bg-[#F7F3EE] border border-[#E2DDD6] rounded-lg px-3 py-2.5 text-sm text-[#1C1A17] focus:outline-none focus:ring-2 focus:ring-[#C9973A] focus:border-transparent'

  return (
    <form
      role="search"
      aria-label="Tìm kiếm phòng"
      onSubmit={handleSearch}
      className="bg-white rounded-2xl shadow-[0_4px_24px_rgba(0,0,0,0.12)] border border-[#E2DDD6] p-4"
    >
      {/* Section label — H-02 guard: this text appears exactly once on the page */}
      <p className="text-sm font-medium text-[#6B6760] mb-3">Tìm phòng trống</p>

      {/* Row 1: Chi nhánh — full-width */}
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
          <div className={`${inputCls} text-[#6B6760]`}>Không có chi nhánh</div>
        ) : (
          <select
            value={selectedSlug}
            onChange={e => setSelectedSlug(e.target.value)}
            className={inputCls}
          >
            {locations.map(loc => (
              <option key={loc.slug} value={loc.slug}>
                {loc.name}
              </option>
            ))}
          </select>
        )}
      </label>

      {/* Row 2: Dates — 2-col */}
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
            className={inputCls}
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
            className={inputCls}
          />
        </label>
      </div>

      {/* Validation error */}
      {validationError && (
        <p className="mb-2 text-xs text-red-600 font-medium" role="alert">
          {validationError}
        </p>
      )}

      {/* Submit — brand amber fill */}
      <button
        type="submit"
        disabled={locationsLoading || locationsError || locations.length === 0}
        className="w-full h-11 bg-[#C9973A] hover:bg-[#B8872A] text-white font-medium text-sm rounded-xl transition-colors duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#C9973A] focus-visible:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
      >
        Tìm kiếm →
      </button>
    </form>
  )
}

export default SearchCard
