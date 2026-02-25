import React, { useEffect, useState, useCallback } from 'react'
import { useParams, useNavigate, useSearchParams } from 'react-router-dom'
import { getLocationBySlug } from './location.api'
import type { LocationWithRooms, LocationRoom } from './location.types'
import { amenityIcons } from './constants'

/**
 * LocationDetail Page
 *
 * Displays a single location with:
 * - Hero section (name, address, contact, description)
 * - Amenities list
 * - Availability search form
 * - Available rooms grid
 */
const LocationDetail: React.FC = () => {
  const { slug } = useParams<{ slug: string }>()
  const navigate = useNavigate()
  const [searchParams, setSearchParams] = useSearchParams()

  const [location, setLocation] = useState<LocationWithRooms | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  // Search form state (initialize from URL params)
  const [checkIn, setCheckIn] = useState(searchParams.get('check_in') || '')
  const [checkOut, setCheckOut] = useState(searchParams.get('check_out') || '')
  const [guests, setGuests] = useState(Number(searchParams.get('guests')) || 1)

  const fetchLocation = useCallback(
    async (params?: { check_in?: string; check_out?: string; guests?: number }) => {
      if (!slug) return
      try {
        setLoading(true)
        setError(null)
        const data = await getLocationBySlug(slug, params)
        setLocation(data)
      } catch (err: unknown) {
        const message =
          err && typeof err === 'object' && 'response' in err
            ? 'Không tìm thấy chi nhánh'
            : 'Không thể tải thông tin chi nhánh. Vui lòng thử lại sau.'
        setError(message)
      } finally {
        setLoading(false)
      }
    },
    [slug]
  )

  useEffect(() => {
    const params: Record<string, string | number> = {}
    if (searchParams.get('check_in')) params.check_in = searchParams.get('check_in')!
    if (searchParams.get('check_out')) params.check_out = searchParams.get('check_out')!
    if (searchParams.get('guests')) params.guests = Number(searchParams.get('guests'))

    fetchLocation(Object.keys(params).length > 0 ? params : undefined)
  }, [fetchLocation, searchParams])

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault()
    if (!checkIn || !checkOut) return

    const params: Record<string, string> = {
      check_in: checkIn,
      check_out: checkOut,
    }
    if (guests > 1) params.guests = String(guests)
    setSearchParams(params)
  }

  const handleBookRoom = (room: LocationRoom) => {
    const params = new URLSearchParams()
    params.set('room_id', String(room.id))
    if (checkIn) params.set('check_in', checkIn)
    if (checkOut) params.set('check_out', checkOut)
    if (guests > 1) params.set('guests', String(guests))
    navigate(`/booking?${params.toString()}`)
  }

  // Today's date for min date constraint
  const today = new Date().toISOString().split('T')[0]

  if (loading) {
    return (
      <section className="min-h-screen px-4 py-12 bg-gray-50">
        <div className="mx-auto max-w-7xl animate-pulse">
          <div className="h-10 mb-4 bg-gray-200 rounded w-72" />
          <div className="h-6 mb-8 bg-gray-200 rounded w-96" />
          <div className="h-40 mb-8 bg-gray-200 rounded-lg" />
          <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
            {[1, 2, 3, 4].map(i => (
              <div key={i} className="p-6 bg-white rounded-lg shadow h-52" />
            ))}
          </div>
        </div>
      </section>
    )
  }

  if (error || !location) {
    return (
      <section className="min-h-screen px-4 py-12 bg-gray-50">
        <div className="max-w-2xl p-6 mx-auto text-center">
          <svg
            className="w-16 h-16 mx-auto mb-4 text-red-400"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
            />
          </svg>
          <h2 className="mb-2 text-2xl font-bold text-gray-900">
            {error || 'Không tìm thấy chi nhánh'}
          </h2>
          <div className="flex justify-center gap-3 mt-4">
            <button
              onClick={() => fetchLocation()}
              className="px-4 py-2 text-sm font-medium text-blue-700 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2"
            >
              Thử lại
            </button>
            <button
              onClick={() => navigate('/locations')}
              className="px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-900 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 focus-visible:ring-offset-2"
            >
              ← Tất cả chi nhánh
            </button>
          </div>
        </div>
      </section>
    )
  }

  return (
    <section className="min-h-screen px-4 py-12 bg-gray-50">
      <div className="mx-auto max-w-7xl">
        {/* Back Link */}
        <button
          onClick={() => navigate('/locations')}
          className="mb-6 text-sm text-blue-600 hover:text-blue-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 rounded"
        >
          ← Tất cả chi nhánh
        </button>

        {/* Hero Section */}
        <div className="mb-8">
          <h1 className="mb-2 text-4xl font-bold text-gray-900">{location.name}</h1>
          <p className="mb-4 text-lg text-gray-600">{location.address.full}</p>

          {/* Contact Info */}
          <div className="flex flex-wrap gap-6 mb-4">
            {location.contact.phone && (
              <a
                href={`tel:${location.contact.phone}`}
                className="text-blue-600 hover:text-blue-800"
              >
                📞 {location.contact.phone}
              </a>
            )}
            {location.contact.email && (
              <a
                href={`mailto:${location.contact.email}`}
                className="text-blue-600 hover:text-blue-800"
              >
                ✉️ {location.contact.email}
              </a>
            )}
          </div>

          {/* Description */}
          {location.description && <p className="text-gray-700">{location.description}</p>}
        </div>

        {/* Amenities */}
        {location.amenities.length > 0 && (
          <div className="p-6 mb-8 bg-white shadow-sm rounded-xl">
            <h2 className="mb-4 text-xl font-bold text-gray-900">Tiện ích</h2>
            <div className="flex flex-wrap gap-3">
              {location.amenities.map(amenity => (
                <span
                  key={amenity}
                  className="inline-flex items-center gap-1 px-3 py-1 text-sm bg-gray-100 rounded-full"
                >
                  <span role="img" aria-label={amenity}>
                    {amenityIcons[amenity] || '✓'}
                  </span>
                  {amenity.replace(/_/g, ' ')}
                </span>
              ))}
            </div>
          </div>
        )}

        {/* Availability Search */}
        <div className="p-6 mb-8 shadow-sm bg-blue-50 rounded-xl">
          <h2 className="mb-4 text-xl font-bold text-gray-900">Kiểm tra phòng trống</h2>
          <form onSubmit={handleSearch} className="flex flex-wrap items-end gap-4">
            <div>
              <label htmlFor="check-in" className="block mb-1 text-sm font-medium text-gray-700">
                Nhận phòng
              </label>
              <input
                id="check-in"
                type="date"
                value={checkIn}
                onChange={e => setCheckIn(e.target.value)}
                min={today}
                required
                className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
            </div>
            <div>
              <label htmlFor="check-out" className="block mb-1 text-sm font-medium text-gray-700">
                Trả phòng
              </label>
              <input
                id="check-out"
                type="date"
                value={checkOut}
                onChange={e => setCheckOut(e.target.value)}
                min={checkIn || today}
                required
                className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
            </div>
            <div>
              <label htmlFor="guests" className="block mb-1 text-sm font-medium text-gray-700">
                Số khách
              </label>
              <input
                id="guests"
                type="number"
                value={guests}
                onChange={e => setGuests(Number(e.target.value))}
                min={1}
                max={20}
                className="w-24 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
            </div>
            <button
              type="submit"
              className="px-6 py-2 font-semibold text-white transition-colors bg-blue-600 rounded-lg hover:bg-blue-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2"
            >
              Tìm kiếm
            </button>
          </form>
        </div>

        {/* Available Rooms */}
        <div>
          <h2 className="mb-6 text-2xl font-bold text-gray-900">
            Phòng trống <span className="text-lg text-gray-500">({location.rooms.length})</span>
          </h2>

          {location.rooms.length > 0 ? (
            <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
              {location.rooms.map(room => (
                <article key={room.id} className="overflow-hidden bg-white shadow-md rounded-xl">
                  {/* Room Image Placeholder */}
                  <div className="h-40 bg-gradient-to-br from-blue-400 to-blue-600">
                    <div className="flex items-center justify-center w-full h-full">
                      <svg
                        className="w-12 h-12 text-white opacity-50"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                      >
                        <path
                          strokeLinecap="round"
                          strokeLinejoin="round"
                          strokeWidth={1.5}
                          d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"
                        />
                      </svg>
                    </div>
                  </div>

                  <div className="p-6">
                    <h3 className="mb-1 text-xl font-bold text-gray-900">
                      {room.display_name || room.name}
                    </h3>
                    {room.description && (
                      <p className="mb-4 text-sm text-gray-600 line-clamp-2">{room.description}</p>
                    )}

                    <div className="flex items-center justify-between mb-4">
                      <div className="text-2xl font-bold text-blue-600">
                        ${room.price}
                        <span className="text-sm font-normal text-gray-500">/night</span>
                      </div>
                      <span className="text-sm text-gray-500">Tối đa {room.max_guests} khách</span>
                    </div>

                    <button
                      onClick={() => handleBookRoom(room)}
                      className="w-full px-4 py-2 font-semibold text-white transition-colors bg-blue-600 rounded-lg hover:bg-blue-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2"
                    >
                      Đặt ngay
                    </button>
                  </div>
                </article>
              ))}
            </div>
          ) : (
            <div className="py-12 text-center">
              <p className="text-gray-500">
                {checkIn && checkOut
                  ? 'Không có phòng trống cho ngày đã chọn. Vui lòng thử ngày khác.'
                  : 'Tìm kiếm theo ngày để xem phòng trống.'}
              </p>
            </div>
          )}
        </div>
      </div>
    </section>
  )
}

export default LocationDetail
