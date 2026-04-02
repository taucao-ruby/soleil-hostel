import React, { useCallback, useEffect, useState } from 'react'
import { Link, useParams, useSearchParams } from 'react-router-dom'
import { formatVND } from '@/shared/lib/formatCurrency'
import type { LocationWithRooms } from '@/shared/types/location.types'
import { amenityIcons } from './constants'
import { getLocationBySlug } from './location.api'

const amenityLabels: Record<string, string> = {
  wifi: 'Wifi miễn phí',
  air_conditioning: 'Điều hòa',
  hot_water: 'Nước nóng',
  breakfast: 'Ăn sáng',
  parking: 'Bãi đỗ xe',
  pool: 'Hồ bơi',
  gym: 'Phòng gym',
  laundry: 'Giặt ủi',
  garden: 'Sân vườn',
  bbq: 'BBQ',
  kayaking: 'Chèo kayak',
  fishing: 'Câu cá',
}

const defaultDescription =
  'Không gian lưu trú được chăm chút để bạn nghỉ ngơi thoải mái, dễ dàng khám phá nhịp sống địa phương và tận hưởng một hành trình gọn gàng, thư thái.'

const defaultRoomDescription =
  'Không gian nghỉ ngơi sáng sủa, phù hợp cho chuyến đi thư giãn hoặc công tác ngắn ngày.'

function getLocalDateString(date = new Date()): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

function addDays(dateString: string, amount: number): string {
  const [year, month, day] = dateString.split('-').map(Number)
  if (!year || !month || !day) {
    return dateString
  }

  const date = new Date(Date.UTC(year, month - 1, day + amount))
  return date.toISOString().slice(0, 10)
}

function clampGuests(value: number): number {
  if (!Number.isFinite(value)) {
    return 1
  }

  return Math.min(10, Math.max(1, Math.trunc(value)))
}

function formatAmenityLabel(amenity: string): string {
  return (
    amenityLabels[amenity] ??
    amenity.replace(/_/g, ' ').replace(/\b\w/g, letter => letter.toUpperCase())
  )
}

const LocationDetail: React.FC = () => {
  const { slug } = useParams<{ slug: string }>()
  const [searchParams, setSearchParams] = useSearchParams()

  const [location, setLocation] = useState<LocationWithRooms | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [searchError, setSearchError] = useState<string | null>(null)

  const [checkIn, setCheckIn] = useState(searchParams.get('check_in') || '')
  const [checkOut, setCheckOut] = useState(searchParams.get('check_out') || '')
  const [guests, setGuests] = useState(clampGuests(Number(searchParams.get('guests') || '1')))

  const today = getLocalDateString()
  const defaultCheckOutMin = addDays(today, 1)

  const submittedCheckIn = searchParams.get('check_in') || ''
  const submittedCheckOut = searchParams.get('check_out') || ''
  const submittedGuests = clampGuests(Number(searchParams.get('guests') || '1'))
  const hasSearched = Boolean(submittedCheckIn && submittedCheckOut)

  useEffect(() => {
    setCheckIn(searchParams.get('check_in') || '')
    setCheckOut(searchParams.get('check_out') || '')
    setGuests(clampGuests(Number(searchParams.get('guests') || '1')))
  }, [searchParams])

  const fetchLocation = useCallback(
    async (
      params?: { check_in?: string; check_out?: string; guests?: number },
      signal?: AbortSignal
    ) => {
      if (!slug) {
        return
      }

      try {
        setLoading(true)
        setError(null)

        const data = await getLocationBySlug(slug, params, signal)
        if (!signal?.aborted) {
          setLocation(data)
        }
      } catch (err: unknown) {
        if (err instanceof Error && (err.name === 'AbortError' || err.name === 'CanceledError')) {
          return
        }

        const status =
          typeof err === 'object' && err !== null && 'response' in err
            ? (err as { response?: { status?: number } }).response?.status
            : undefined

        if (!signal?.aborted) {
          setError(
            status === 404
              ? 'Không tìm thấy chi nhánh phù hợp.'
              : 'Không thể tải thông tin chi nhánh. Vui lòng thử lại sau.'
          )
        }
      } finally {
        if (!signal?.aborted) {
          setLoading(false)
        }
      }
    },
    [slug]
  )

  useEffect(() => {
    const controller = new AbortController()
    const params =
      hasSearched && submittedCheckIn && submittedCheckOut
        ? {
            check_in: submittedCheckIn,
            check_out: submittedCheckOut,
            ...(submittedGuests > 1 ? { guests: submittedGuests } : {}),
          }
        : undefined

    void fetchLocation(params, controller.signal)

    return () => {
      controller.abort()
    }
  }, [fetchLocation, hasSearched, submittedCheckIn, submittedCheckOut, submittedGuests])

  const handleSearch = (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setSearchError(null)

    if (!checkIn || !checkOut) {
      setSearchError('Vui lòng chọn ngày nhận phòng và ngày trả phòng.')
      return
    }

    if (checkOut <= checkIn) {
      setSearchError('Ngày trả phòng phải sau ngày nhận phòng.')
      return
    }

    const nextParams: Record<string, string> = {
      check_in: checkIn,
      check_out: checkOut,
    }

    if (guests > 1) {
      nextParams.guests = String(guests)
    }

    setSearchParams(nextParams)
  }

  const handleResetSearch = () => {
    setSearchError(null)
    setCheckIn('')
    setCheckOut('')
    setGuests(1)
    setSearchParams({})
  }

  const minCheckOutDate = checkIn ? addDays(checkIn, 1) : defaultCheckOutMin
  const heroImage = location?.images?.[0]?.url || ''
  const locationDescription = location?.description?.trim() || defaultDescription
  const roomCount = location?.rooms.length ?? 0

  if (loading) {
    return (
      <section className="min-h-screen bg-hueSurface">
        <div className="h-[300px] animate-pulse bg-gradient-to-br from-[#2b2418] via-[#5b4525] to-[#c9973a] sm:h-[360px] lg:h-[420px]" />
        <div className="mx-auto flex max-w-6xl flex-col gap-6 px-4 py-8 sm:px-6 lg:py-12">
          <div className="grid gap-6 lg:grid-cols-2">
            <div className="rounded-3xl bg-white p-6 shadow-sm">
              <div className="h-6 w-32 rounded bg-gray-200" />
              <div className="mt-4 h-4 rounded bg-gray-100" />
              <div className="mt-3 h-4 rounded bg-gray-100" />
              <div className="mt-3 h-4 w-5/6 rounded bg-gray-100" />
            </div>
            <div className="rounded-3xl bg-white p-6 shadow-sm">
              <div className="h-6 w-24 rounded bg-gray-200" />
              <div className="mt-4 grid grid-cols-2 gap-3">
                {[1, 2, 3, 4].map(item => (
                  <div key={item} className="h-11 rounded-xl bg-gray-100" />
                ))}
              </div>
            </div>
          </div>
          <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
            <div className="h-5 w-40 rounded bg-amber-100" />
            <div className="mt-4 grid gap-4 lg:grid-cols-4">
              {[1, 2, 3, 4].map(item => (
                <div key={item} className="h-16 rounded-xl bg-white/70" />
              ))}
            </div>
          </div>
        </div>
      </section>
    )
  }

  if (error || !location) {
    return (
      <section className="min-h-screen bg-hueSurface px-4 py-12">
        <div className="mx-auto max-w-2xl rounded-3xl border border-red-200 bg-white p-8 text-center shadow-sm">
          <div className="text-4xl" aria-hidden="true">
            ⚠️
          </div>
          <h1 className="mt-4 text-2xl font-semibold text-hueBlack">
            {error || 'Không tìm thấy chi nhánh phù hợp.'}
          </h1>
          <p className="mt-3 text-sm leading-6 text-hueMuted">
            Vui lòng thử tải lại trang hoặc quay lại danh sách chi nhánh để tiếp tục khám phá.
          </p>
          <div className="mt-6 flex flex-col justify-center gap-3 sm:flex-row">
            <button
              type="button"
              onClick={() => void fetchLocation()}
              className="inline-flex items-center justify-center rounded-xl bg-brandAmber px-4 py-2.5 text-sm font-medium text-hueBlack transition hover:bg-[#b8872a] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brandAmber/30"
            >
              Thử lại
            </button>
            <Link
              to="/locations"
              className="inline-flex items-center justify-center rounded-xl border border-hueBorder px-4 py-2.5 text-sm font-medium text-hueBlack transition hover:border-brandAmber hover:text-brandAmber"
            >
              Xem tất cả chi nhánh
            </Link>
          </div>
        </div>
      </section>
    )
  }

  return (
    <section className="bg-hueSurface pb-12 text-hueBlack">
      <div className="relative h-[300px] overflow-hidden bg-hueBlack sm:h-[360px] lg:h-[420px]">
        {heroImage ? (
          <img
            src={heroImage}
            alt={location.name}
            className="absolute inset-0 h-full w-full object-cover"
            loading="eager"
          />
        ) : (
          <div className="absolute inset-0 bg-gradient-to-br from-[#2b2418] via-[#5b4525] to-[#c9973a]" />
        )}

        <div className="absolute inset-0 bg-gradient-to-t from-black/85 via-black/45 to-black/10" />

        <div className="absolute inset-x-0 bottom-0">
          <div className="mx-auto max-w-6xl px-4 pb-6 sm:px-6 sm:pb-8">
            <div className="max-w-3xl">
              <h1 className="text-[22px] font-medium leading-tight text-white sm:text-4xl">
                {location.name}
              </h1>
              <p className="mt-2 text-[13px] text-white/75 sm:text-sm">{location.address.full}</p>
              {(location.contact.phone || location.contact.email) && (
                <div className="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-[12px] text-white/90">
                  {location.contact.phone && (
                    <a
                      href={`tel:${location.contact.phone}`}
                      className="transition hover:text-white"
                    >
                      📞 {location.contact.phone}
                    </a>
                  )}
                  {location.contact.phone && location.contact.email && (
                    <span aria-hidden="true" className="text-white/40">
                      |
                    </span>
                  )}
                  {location.contact.email && (
                    <a
                      href={`mailto:${location.contact.email}`}
                      className="transition hover:text-white"
                    >
                      ✉️ {location.contact.email}
                    </a>
                  )}
                </div>
              )}
            </div>
          </div>
        </div>
      </div>

      <div className="mx-auto flex max-w-6xl flex-col gap-6 px-4 py-8 sm:px-6 lg:py-12">
        <div className="grid gap-6 lg:grid-cols-2">
          <article className="rounded-3xl bg-white p-6 shadow-[0_20px_45px_rgba(28,26,23,0.08)]">
            <p className="text-sm font-medium uppercase tracking-[0.18em] text-brandAmber">Mô tả</p>
            <div className="mt-4 space-y-4 text-[15px] leading-[1.7] text-hueMuted">
              {locationDescription.split('\n').map((paragraph, index) => (
                <p key={`${index}-${paragraph}`}>{paragraph}</p>
              ))}
            </div>
          </article>

          <aside className="rounded-3xl border border-amber-100 bg-white p-6 shadow-[0_20px_45px_rgba(28,26,23,0.08)]">
            <h2 className="text-xl font-semibold text-hueBlack">Tiện nghi</h2>
            <div className="mt-4 grid grid-cols-2 gap-x-4 gap-y-3">
              {location.amenities.length > 0 ? (
                location.amenities.map(amenity => (
                  <div
                    key={amenity}
                    className="flex items-center gap-2 rounded-2xl bg-amber-50 px-3 py-3 text-[13px] text-hueBlack"
                  >
                    <span className="text-base" aria-hidden="true">
                      {amenityIcons[amenity] || '✨'}
                    </span>
                    <span>{formatAmenityLabel(amenity)}</span>
                  </div>
                ))
              ) : (
                <p className="col-span-2 text-sm leading-6 text-hueMuted">
                  Tiện nghi của chi nhánh đang được cập nhật.
                </p>
              )}
            </div>
          </aside>
        </div>

        <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
          <h2 className="text-lg font-semibold text-hueBlack">Kiểm tra phòng trống</h2>
          <form
            role="search"
            aria-label="Kiểm tra phòng trống"
            noValidate
            onSubmit={handleSearch}
            className="mt-4 grid gap-4 lg:grid-cols-[repeat(3,minmax(0,1fr))_180px]"
          >
            <label className="block">
              <span className="mb-1.5 block text-sm font-medium text-hueBlack">
                Ngày nhận phòng
              </span>
              <input
                type="date"
                value={checkIn}
                min={today}
                onChange={event => {
                  const nextCheckIn = event.target.value
                  setCheckIn(nextCheckIn)
                  setSearchError(null)

                  if (checkOut && nextCheckIn && checkOut <= nextCheckIn) {
                    setCheckOut(addDays(nextCheckIn, 1))
                  }
                }}
                className="w-full rounded-xl border border-amber-200 bg-white px-3 py-2.5 text-sm text-hueBlack outline-none transition focus:border-brandAmber focus:ring-2 focus:ring-brandAmber/20"
              />
            </label>

            <label className="block">
              <span className="mb-1.5 block text-sm font-medium text-hueBlack">Ngày trả phòng</span>
              <input
                type="date"
                value={checkOut}
                min={minCheckOutDate}
                onChange={event => {
                  setCheckOut(event.target.value)
                  setSearchError(null)
                }}
                className="w-full rounded-xl border border-amber-200 bg-white px-3 py-2.5 text-sm text-hueBlack outline-none transition focus:border-brandAmber focus:ring-2 focus:ring-brandAmber/20"
              />
            </label>

            <label className="block">
              <span className="mb-1.5 block text-sm font-medium text-hueBlack">Số khách</span>
              <input
                type="number"
                min={1}
                max={10}
                value={guests}
                onChange={event => {
                  setGuests(clampGuests(Number(event.target.value)))
                  setSearchError(null)
                }}
                className="w-full rounded-xl border border-amber-200 bg-white px-3 py-2.5 text-sm text-hueBlack outline-none transition focus:border-brandAmber focus:ring-2 focus:ring-brandAmber/20"
              />
            </label>

            <button
              type="submit"
              className="inline-flex h-11 items-center justify-center rounded-xl bg-brandAmber px-4 text-sm font-medium text-hueBlack transition hover:bg-[#b8872a] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brandAmber/30 lg:self-end"
            >
              Tìm phòng
            </button>
          </form>

          {searchError && (
            <p className="mt-3 text-sm font-medium text-red-600" role="alert">
              {searchError}
            </p>
          )}
        </div>

        {!hasSearched && (
          <div
            data-testid="location-search-prompt"
            className="rounded-3xl border border-dashed border-hueBorder bg-white px-6 py-10 text-center shadow-sm"
          >
            <div className="text-4xl" aria-hidden="true">
              🛏️
            </div>
            <h2 className="mt-4 text-2xl font-semibold text-hueBlack">
              Chọn ngày để xem phòng phù hợp
            </h2>
            <p className="mx-auto mt-3 max-w-2xl text-sm leading-6 text-hueMuted">
              Nhập ngày lưu trú và số khách để kiểm tra những hạng phòng còn trống tại{' '}
              {location.name}.
            </p>
          </div>
        )}

        {hasSearched && roomCount > 0 && (
          <div>
            <div className="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
              <div>
                <h2 className="text-2xl font-semibold text-hueBlack">
                  Phòng còn trống ({roomCount})
                </h2>
                <p className="mt-1 text-sm text-hueMuted">
                  Từ {submittedCheckIn} đến {submittedCheckOut}
                  {submittedGuests > 1 ? ` · ${submittedGuests} khách` : ''}
                </p>
              </div>
            </div>

            <div className="grid gap-4 md:grid-cols-2">
              {location.rooms.map(room => {
                const bookingParams = new URLSearchParams({
                  room_id: String(room.id),
                  check_in: submittedCheckIn,
                  check_out: submittedCheckOut,
                })

                if (submittedGuests > 1) {
                  bookingParams.set('guests', String(submittedGuests))
                }

                return (
                  <article
                    key={room.id}
                    className="flex h-full flex-col rounded-3xl border border-amber-100 bg-white p-5 shadow-[0_18px_35px_rgba(28,26,23,0.08)]"
                  >
                    <div className="flex-1">
                      <h3 className="text-[16px] font-semibold text-hueBlack">
                        {room.display_name || room.name}
                      </h3>
                      <p className="mt-2 text-sm leading-6 text-hueMuted line-clamp-2">
                        {room.description?.trim() || defaultRoomDescription}
                      </p>
                      <div className="mt-4 flex items-center gap-2 text-sm text-hueMuted">
                        <span aria-hidden="true">👥</span>
                        <span>Tối đa {room.max_guests} khách</span>
                      </div>
                    </div>

                    <div className="mt-6 flex items-end justify-between gap-4">
                      <div>
                        <div className="text-[20px] font-medium text-brandAmber">
                          {formatVND(room.price)}
                        </div>
                        <div className="text-xs text-hueMuted">/đêm</div>
                      </div>

                      <Link
                        to={`/booking?${bookingParams.toString()}`}
                        className="inline-flex items-center justify-center rounded-xl bg-brandAmber px-4 py-2.5 text-sm font-medium text-hueBlack transition hover:bg-[#b8872a] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brandAmber/30"
                      >
                        Đặt ngay →
                      </Link>
                    </div>
                  </article>
                )
              })}
            </div>
          </div>
        )}

        {hasSearched && roomCount === 0 && (
          <div className="rounded-3xl border border-hueBorder bg-white px-6 py-12 text-center shadow-sm">
            <div className="text-4xl" aria-hidden="true">
              🔍
            </div>
            <h2 className="mt-4 text-2xl font-semibold text-hueBlack">
              Không có phòng trống cho ngày này
            </h2>
            <button
              type="button"
              onClick={handleResetSearch}
              className="mt-3 text-sm font-medium text-brandAmber transition hover:text-hueBlack"
            >
              Thử chọn ngày khác
            </button>
          </div>
        )}
      </div>
    </section>
  )
}

export default LocationDetail
