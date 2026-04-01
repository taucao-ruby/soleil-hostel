import React, { useCallback, useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { getLocations } from '@/shared/lib/location.api'
import type { Location } from '@/shared/types/location.types'
import LocationCard from './LocationCard'

const ALL_CITIES = '__all__'

const LocationList: React.FC = () => {
  const [locations, setLocations] = useState<Location[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [draftCity, setDraftCity] = useState<string>('')
  const [selectedCity, setSelectedCity] = useState<string>('')
  const navigate = useNavigate()

  const fetchLocations = useCallback(async (signal?: AbortSignal) => {
    try {
      setLoading(true)
      setError(null)

      const data = await getLocations(signal)
      if (!signal?.aborted) {
        setLocations(data)
      }
    } catch (err: unknown) {
      if (err instanceof Error && (err.name === 'AbortError' || err.name === 'CanceledError')) {
        return
      }

      if (!signal?.aborted) {
        setError('Không thể tải danh sách chi nhánh. Vui lòng thử lại sau.')
      }
    } finally {
      if (!signal?.aborted) {
        setLoading(false)
      }
    }
  }, [])

  useEffect(() => {
    const controller = new AbortController()
    void fetchLocations(controller.signal)

    return () => {
      controller.abort()
    }
  }, [fetchLocations])

  const cities = useMemo(() => {
    return [...new Set(locations.map(location => location.address.city))].sort()
  }, [locations])

  useEffect(() => {
    if (!loading && cities.length > 0 && draftCity === '') {
      setDraftCity(cities[0])
    }
  }, [cities, draftCity, loading])

  const filteredLocations = useMemo(() => {
    if (!selectedCity) {
      return locations
    }

    return locations.filter(location => location.address.city === selectedCity)
  }, [locations, selectedCity])

  const handleFilterSubmit = (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setSelectedCity(draftCity === ALL_CITIES ? '' : draftCity)
  }

  const handleLocationClick = (slug: string) => {
    navigate(`/locations/${slug}`)
  }

  const resultSummary = selectedCity
    ? `Hiển thị ${filteredLocations.length} chi nhánh tại ${selectedCity}`
    : `Hiển thị ${filteredLocations.length} chi nhánh trên toàn bộ hệ thống`

  return (
    <section className="min-h-screen bg-[radial-gradient(circle_at_top,_rgba(201,151,58,0.16),_transparent_30%),linear-gradient(180deg,_#fbf7f1_0%,_#f5efe6_100%)] px-4 py-10 sm:px-6 lg:py-14">
      <div className="mx-auto max-w-6xl">
        <div className="mx-auto max-w-4xl text-center">
          <h1 className="text-4xl font-semibold leading-tight text-hueBlack sm:text-5xl">
            Danh sách chi nhánh thực tế - Soleil Hostel
          </h1>
          <p className="mt-4 text-sm leading-6 text-hueMuted sm:text-base">
            Chọn thành phố phù hợp để khám phá những không gian lưu trú đặc trưng của Soleil trước
            khi đặt phòng.
          </p>
          <div className="mx-auto mt-7 h-px w-full max-w-5xl bg-gradient-to-r from-transparent via-[#d8c29e] to-transparent" />
        </div>

        <div className="mx-auto mt-8 max-w-5xl rounded-[28px] border border-[#e6dccf] bg-white/90 p-4 shadow-[0_18px_50px_rgba(28,26,23,0.08)] backdrop-blur sm:p-5">
          <form
            onSubmit={handleFilterSubmit}
            className="flex flex-col gap-4 md:flex-row md:items-end"
          >
            <label className="flex-1">
              <span className="mb-2 block text-sm font-medium text-hueBlack">Thành phố</span>
              <select
                id="city-filter"
                value={draftCity}
                onChange={event => setDraftCity(event.target.value)}
                disabled={loading || Boolean(error) || cities.length === 0}
                className="w-full rounded-xl border border-hueBorder bg-[#fcfaf6] px-4 py-3 text-sm text-hueBlack outline-none transition focus:border-brandAmber focus:ring-2 focus:ring-brandAmber/20 disabled:cursor-not-allowed disabled:opacity-60"
              >
                {cities.length > 0 ? null : <option value="">Đang tải thành phố...</option>}
                {cities.length > 0 && (
                  <>
                    <option value={ALL_CITIES}>Tất cả thành phố</option>
                    {cities.map(city => (
                      <option key={city} value={city}>
                        {city}
                      </option>
                    ))}
                  </>
                )}
              </select>
            </label>

            <button
              type="submit"
              disabled={loading || Boolean(error) || cities.length === 0 || !draftCity}
              className="inline-flex h-12 items-center justify-center rounded-xl bg-hueBlack px-6 text-sm font-medium text-white transition hover:bg-[#2a2722] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brandAmber/30 disabled:cursor-not-allowed disabled:bg-hueBlack/50"
            >
              Tìm kiếm
            </button>
          </form>
        </div>

        {error && (
          <div className="mx-auto mt-6 max-w-4xl rounded-3xl border border-red-200 bg-white p-6 text-center shadow-sm">
            <p className="text-sm font-medium text-red-700">{error}</p>
            <button
              type="button"
              onClick={() => void fetchLocations()}
              className="mt-4 inline-flex items-center justify-center rounded-xl border border-red-200 px-4 py-2 text-sm font-medium text-red-700 transition hover:bg-red-50"
            >
              Thử lại
            </button>
          </div>
        )}

        {loading && (
          <div className="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
            {[1, 2, 3, 4].map(item => (
              <div
                key={item}
                className="overflow-hidden rounded-[24px] border border-hueBorder bg-white shadow-[0_18px_40px_rgba(28,26,23,0.06)]"
              >
                <div className="h-56 animate-pulse bg-gradient-to-br from-[#efe6d8] via-[#e4d1ae] to-[#d4ba8a]" />
                <div className="space-y-4 p-5">
                  <div className="h-8 w-1/2 animate-pulse rounded bg-gray-200" />
                  <div className="h-4 w-2/3 animate-pulse rounded bg-gray-100" />
                  <div className="h-4 w-full animate-pulse rounded bg-gray-100" />
                  <div className="grid grid-cols-5 gap-2">
                    {[1, 2, 3, 4, 5].map(tile => (
                      <div key={tile} className="h-10 animate-pulse rounded-xl bg-gray-100" />
                    ))}
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}

        {!loading && !error && filteredLocations.length > 0 && (
          <>
            <p className="mt-8 text-sm font-medium text-hueMuted">{resultSummary}</p>
            <div className="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-2">
              {filteredLocations.map(location => (
                <LocationCard
                  key={location.id}
                  location={location}
                  onClick={() => handleLocationClick(location.slug)}
                />
              ))}
            </div>
          </>
        )}

        {!loading && !error && filteredLocations.length === 0 && (
          <div className="mx-auto mt-10 max-w-4xl rounded-[28px] border border-hueBorder bg-white px-6 py-12 text-center shadow-[0_18px_40px_rgba(28,26,23,0.06)]">
            <div className="text-5xl" aria-hidden="true">
              📍
            </div>
            <h2 className="mt-4 text-3xl font-semibold text-hueBlack">
              Không tìm thấy chi nhánh phù hợp
            </h2>
            <p className="mt-3 text-sm leading-6 text-hueMuted">
              Hãy thử chọn thành phố khác hoặc chuyển về chế độ hiển thị tất cả chi nhánh.
            </p>
          </div>
        )}
      </div>
    </section>
  )
}

export default LocationList
