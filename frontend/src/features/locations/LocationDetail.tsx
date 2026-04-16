import React, { useCallback, useEffect, useRef, useState } from 'react'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { formatVND } from '@/shared/lib/formatCurrency'
import type { LocationWithRooms } from '@/shared/types/location.types'
import {
  amenityMaterialIcons,
  locationFallbackImages,
  locationHeroPool,
  roomImagePool,
} from './constants'
import { getLocationBySlug } from './location.api'

// ─────────────────────────────────────────────────────────────────────────────
// Static data
// ─────────────────────────────────────────────────────────────────────────────

const amenityLabels: Record<string, string> = {
  wifi: 'WiFi miễn phí',
  air_conditioning: 'Điều hòa',
  hot_water: 'Nước nóng',
  breakfast: 'Ăn sáng miễn phí',
  parking: 'Gửi xe miễn phí',
  pool: 'Hồ bơi',
  gym: 'Phòng gym',
  laundry: 'Giặt ủi',
  garden: 'Sân vườn',
  bbq: 'BBQ',
  kayaking: 'Chèo kayak',
  fishing: 'Câu cá',
}

const defaultDescription =
  'Không gian ấm cúng, thiết kế boutique tinh xảo, nằm ngay trung tâm Phú Hội — nơi hòa quyện giữa nhịp sống hiện đại và nét cổ kính của Cố đô, chỉ cách Hoàng Thành 400m bộ hành.'

const defaultRoomDescription =
  'Không gian nghỉ ngơi sáng sủa, phù hợp cho chuyến đi thư giãn hoặc công tác ngắn ngày.'

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function getLocalDateString(date = new Date()): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

function addDays(dateString: string, amount: number): string {
  const [year, month, day] = dateString.split('-').map(Number)
  if (!year || !month || !day) return dateString
  const date = new Date(Date.UTC(year, month - 1, day + amount))
  return date.toISOString().slice(0, 10)
}

function clampGuests(value: number): number {
  if (!Number.isFinite(value)) return 1
  return Math.min(10, Math.max(1, Math.trunc(value)))
}

function formatAmenityLabel(amenity: string): string {
  return amenityLabels[amenity] ?? amenity.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
}

// ─────────────────────────────────────────────────────────────────────────────
// Primitives
// ─────────────────────────────────────────────────────────────────────────────

function StarFill({ className = 'h-4 w-4' }: { className?: string }) {
  return (
    <svg viewBox="0 0 20 20" fill="currentColor" className={className} aria-hidden="true">
      <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
    </svg>
  )
}

function Stars({ count = 5, className = 'h-3.5 w-3.5' }: { count?: number; className?: string }) {
  return (
    <>
      {Array.from({ length: count }).map((_, i) => (
        <StarFill key={i} className={className} />
      ))}
    </>
  )
}

/** Renders a Material Symbols Outlined icon. Size is controlled via Tailwind text-* classes. */
function MIcon({ name, className = '' }: { name: string; className?: string }) {
  return (
    <span
      className={['material-symbols-outlined', className].filter(Boolean).join(' ')}
      aria-hidden="true"
    >
      {name}
    </span>
  )
}

// ─────────────────────────────────────────────────────────────────────────────
// Main component
// ─────────────────────────────────────────────────────────────────────────────

const LocationDetail: React.FC = () => {
  const { slug } = useParams<{ slug: string }>()
  const [searchParams, setSearchParams] = useSearchParams()
  const navigate = useNavigate()
  const bookingRef = useRef<HTMLDivElement>(null)

  const [location, setLocation] = useState<LocationWithRooms | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [searchError, setSearchError] = useState<string | null>(null)
  const [heroIndex, setHeroIndex] = useState(0)

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
      if (!slug) return
      try {
        setLoading(true)
        setError(null)
        const data = await getLocationBySlug(slug, params, signal)
        if (!signal?.aborted) setLocation(data)
      } catch (err: unknown) {
        if (err instanceof Error && (err.name === 'AbortError' || err.name === 'CanceledError'))
          return
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
        if (!signal?.aborted) setLoading(false)
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
    const nextParams: Record<string, string> = { check_in: checkIn, check_out: checkOut }
    if (guests > 1) nextParams.guests = String(guests)
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
  const images = location?.images ?? []
  const resolvedFallback =
    locationFallbackImages[location?.slug ?? ''] ??
    locationHeroPool[(location?.id ?? 0) % locationHeroPool.length]
  const heroImage = images[heroIndex]?.url || images[0]?.url || resolvedFallback
  const locationDescription = location?.description?.trim() || defaultDescription
  const roomCount = location?.rooms.length ?? 0
  const lowestPrice = roomCount > 0 ? Math.min(...(location?.rooms.map(r => r.price) ?? [])) : null

  const THUMB_COUNT = 5
  const thumbImages = images.slice(1, 1 + THUMB_COUNT)
  const extraCount = images.length > THUMB_COUNT + 1 ? images.length - THUMB_COUNT - 1 : 0

  // ── Loading skeleton ──────────────────────────────────────────────────────
  if (loading) {
    return (
      <div className="min-h-screen bg-[#fff8f4]">
        <div className="mx-auto max-w-screen-xl px-6 py-4">
          <div className="h-3 w-64 rounded bg-[#e8e0d8]" />
        </div>
        <div className="h-[380px] animate-pulse bg-gradient-to-br from-[#2b2418] via-[#5b4525] to-[#c9920a] sm:h-[460px] lg:h-[560px]" />
        <div className="mx-auto max-w-screen-xl px-6 py-8">
          <div className="flex flex-col lg:flex-row gap-12">
            <div className="flex-1 space-y-5">
              <div className="h-10 w-80 rounded-xl bg-[#ddd5cc]" />
              <div className="h-4 w-40 rounded bg-[#e8e0d8]" />
              <div className="space-y-2">
                {[100, 90, 75].map(w => (
                  <div key={w} className="h-4 rounded bg-[#f0ebe4]" style={{ width: `${w}%` }} />
                ))}
              </div>
            </div>
            <div className="hidden lg:block lg:w-80">
              <div className="rounded-2xl bg-white p-6 shadow-sm">
                <div className="h-5 w-40 rounded bg-[#e8e0d8]" />
                <div className="mt-4 space-y-3">
                  {[1, 2, 3].map(i => (
                    <div key={i} className="h-12 rounded-xl bg-[#f0ebe4]" />
                  ))}
                  <div className="h-12 rounded-xl bg-[#f5e8cc]" />
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    )
  }

  // ── Error state ───────────────────────────────────────────────────────────
  if (error || !location) {
    return (
      <section className="min-h-screen bg-[#fff8f4] px-4 py-12">
        <div className="mx-auto max-w-2xl rounded-2xl border border-red-100 bg-white p-8 text-center shadow-sm">
          <div className="text-4xl" aria-hidden="true">
            ⚠️
          </div>
          <h1 className="mt-4 font-serif text-2xl font-semibold text-[#1f1b17]">
            {error || 'Không tìm thấy chi nhánh phù hợp.'}
          </h1>
          <p className="mt-3 text-sm leading-6 text-[#6b6458]">
            Vui lòng thử tải lại trang hoặc quay lại danh sách chi nhánh để tiếp tục khám phá.
          </p>
          <div className="mt-6 flex flex-col justify-center gap-3 sm:flex-row">
            <button
              type="button"
              onClick={() => void fetchLocation()}
              className="inline-flex items-center justify-center rounded-xl bg-gradient-to-br from-[#c9920a] to-[#a87808] px-5 py-2.5 text-sm font-semibold text-white transition hover:opacity-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#c9920a]/40"
            >
              Thử lại
            </button>
            <Link
              to="/locations"
              className="inline-flex items-center justify-center rounded-xl border border-[#d6cfc7] px-5 py-2.5 text-sm font-medium text-[#1f1b17] transition hover:border-[#c9920a] hover:text-[#c9920a]"
            >
              Xem tất cả chi nhánh
            </Link>
          </div>
        </div>
      </section>
    )
  }

  // ── Main render ───────────────────────────────────────────────────────────
  return (
    <div className="bg-[#fff8f4] pb-24 text-[#1f1b17] lg:pb-0">
      {/* ══════════════════════════════════════════════════════════════════
          BREADCRUMB
      ══════════════════════════════════════════════════════════════════ */}
      <div className="mx-auto max-w-screen-xl px-6">
        <nav aria-label="Vị trí" className="flex items-center gap-2 py-4 text-sm">
          <Link
            to="/"
            className="opacity-60 transition-opacity hover:opacity-100 hover:text-[#c9920a]"
          >
            Trang chủ
          </Link>
          <span className="font-serif italic text-lg text-[#c9920a]" aria-hidden="true">
            /
          </span>
          <Link
            to="/locations"
            className="opacity-60 transition-opacity hover:opacity-100 hover:text-[#c9920a]"
          >
            Chi nhánh
          </Link>
          <span className="font-serif italic text-lg text-[#c9920a]" aria-hidden="true">
            /
          </span>
          <span className="font-medium">{location.name}</span>
        </nav>
      </div>

      {/* ══════════════════════════════════════════════════════════════════
          HERO GALLERY
      ══════════════════════════════════════════════════════════════════ */}
      <section className="mb-12">
        {/* Main hero image */}
        <div className="relative h-[340px] overflow-hidden bg-[#1a1612] group sm:h-[460px] lg:h-[560px]">
          <img
            src={heroImage}
            alt={images[heroIndex]?.alt || location.name}
            className="absolute inset-0 h-full w-full object-cover opacity-70 transition-opacity duration-700 group-hover:opacity-80"
            loading="eager"
          />
          <div className="absolute inset-0 bg-gradient-to-b from-black/20 via-transparent to-black/40" />

          {/* Mobile overlay nav */}
          <div className="absolute inset-x-0 top-0 flex items-start justify-between px-4 pt-4 lg:hidden">
            <button
              type="button"
              aria-label="Quay lại"
              onClick={() => navigate(-1)}
              className="flex h-10 w-10 items-center justify-center rounded-full bg-white/80 text-[#1a1612] shadow backdrop-blur-md transition hover:bg-white"
            >
              <MIcon name="arrow_back" className="text-[20px]" />
            </button>
            <div className="flex gap-2">
              <button
                type="button"
                aria-label="Chia sẻ"
                className="flex h-10 w-10 items-center justify-center rounded-full bg-white/80 text-[#1a1612] shadow backdrop-blur-md transition hover:bg-white"
              >
                <MIcon name="share" className="text-[20px]" />
              </button>
              <button
                type="button"
                aria-label="Lưu yêu thích"
                className="flex h-10 w-10 items-center justify-center rounded-full bg-white/80 text-[#1a1612] shadow backdrop-blur-md transition hover:bg-white"
              >
                <MIcon name="favorite" className="text-[20px]" />
              </button>
            </div>
          </div>

          {/* Image counter pill */}
          {images.length > 1 && (
            <div className="absolute bottom-6 right-6 rounded-full bg-black/40 px-4 py-2 text-sm text-white backdrop-blur-md">
              {heroIndex + 1} / {images.length} ảnh
            </div>
          )}
        </div>

        {/* Thumbnail strip */}
        {images.length > 1 && (
          <div className="mx-auto mt-4 grid max-w-screen-xl grid-cols-5 gap-3 px-6">
            {thumbImages.map((img, pos) => {
              const imgIdx = pos + 1
              const isLast = pos === thumbImages.length - 1 && extraCount > 0
              return (
                <button
                  key={imgIdx}
                  type="button"
                  aria-label={isLast ? `Xem tất cả ${images.length} ảnh` : `Xem ảnh ${imgIdx + 1}`}
                  aria-pressed={imgIdx === heroIndex}
                  onClick={() => setHeroIndex(imgIdx)}
                  className={[
                    'relative aspect-square overflow-hidden rounded-xl cursor-pointer transition-all',
                    'hover:opacity-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#c9920a]',
                    imgIdx === heroIndex ? 'ring-2 ring-[#c9920a]' : '',
                  ].join(' ')}
                >
                  <img
                    src={img.url}
                    alt={img.alt || `Ảnh ${imgIdx + 1}`}
                    className="h-full w-full object-cover"
                    loading="lazy"
                  />
                  {isLast && (
                    <div className="absolute inset-0 flex items-center justify-center bg-black/50">
                      <span className="text-sm font-semibold text-white">+{extraCount} ảnh</span>
                    </div>
                  )}
                </button>
              )
            })}
          </div>
        )}
      </section>

      {/* ══════════════════════════════════════════════════════════════════
          MAIN LAYOUT — left content + right sticky widget
      ══════════════════════════════════════════════════════════════════ */}
      <div className="mx-auto max-w-screen-xl px-6">
        <div className="flex flex-col gap-12 lg:flex-row">
          {/* ─────────────── LEFT COLUMN ─────────────── */}
          <div className="flex-1">
            {/* ── Property identity ── */}
            <div className="mb-4 flex flex-wrap items-center gap-4">
              <h1 className="font-serif text-[2rem] font-bold leading-tight sm:text-4xl">
                {location.name}
              </h1>
              <span className="inline-flex items-center gap-1.5 rounded-full bg-green-50 px-3 py-1 text-xs font-semibold text-green-700">
                <span className="h-2 w-2 rounded-full bg-green-500" aria-hidden="true" />
                Đang mở cửa
              </span>
            </div>

            <div className="mb-6 flex flex-wrap items-center gap-3">
              <div className="flex gap-0.5 text-[#c9920a]">
                <Stars className="h-4 w-4" />
              </div>
              <span className="text-sm font-bold">4.9</span>
              <span className="border-l border-[#d6cfc7] pl-3 text-sm text-[#9a9088]">
                124 đánh giá
              </span>
              <span className="flex items-center gap-1 text-sm text-[#6b6458]">
                <MIcon name="location_on" className="text-[14px] text-[#c9920a]" />
                {location.address.city}
                {location.address.district ? `, ${location.address.district}` : ''}
              </span>
            </div>

            {/* ── Description ── */}
            <div className="mb-10 max-w-2xl text-lg leading-relaxed opacity-80">
              {locationDescription.split('\n').map((p, i) => (
                <p key={`${i}-${p.slice(0, 16)}`}>{p}</p>
              ))}
            </div>

            {/* ── Amenities grid ── */}
            <div className="mb-10 grid grid-cols-1 gap-x-12 gap-y-6 border-y border-[#ede5da] py-8 sm:grid-cols-2">
              {/* Static: check-in / check-out */}
              <div className="flex items-center gap-4">
                <MIcon name="vpn_key" className="text-[22px] text-[#c9920a]" />
                <div>
                  <span className="block text-[10px] uppercase tracking-wider opacity-60">
                    Nhận phòng
                  </span>
                  <span className="font-medium">14:00</span>
                </div>
              </div>
              <div className="flex items-center gap-4">
                <MIcon name="door_open" className="text-[22px] text-[#c9920a]" />
                <div>
                  <span className="block text-[10px] uppercase tracking-wider opacity-60">
                    Trả phòng
                  </span>
                  <span className="font-medium">12:00</span>
                </div>
              </div>
              {/* Dynamic amenities */}
              {location.amenities.map(amenity => (
                <div key={amenity} className="flex items-center gap-4">
                  <MIcon
                    name={amenityMaterialIcons[amenity] ?? 'check_circle'}
                    className="text-[22px] text-[#c9920a]"
                  />
                  <span className="font-medium">{formatAmenityLabel(amenity)}</span>
                </div>
              ))}
            </div>

            {/* ── Map card ── */}
            <div className="mb-10 rounded-2xl bg-[#f6ece5] p-6">
              <h2 className="mb-1 font-serif text-xl font-semibold">Khu vực lân cận</h2>
              <p className="mb-4 text-sm text-[#9a9088]">{location.address.full}</p>

              <div className="relative mb-4 h-[200px] overflow-hidden rounded-xl">
                {location.coordinates ? (
                  <a
                    href={`https://www.google.com/maps/search/?api=1&query=${location.coordinates.lat},${location.coordinates.lng}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label="Xem trên Google Maps"
                    className="group block h-full"
                  >
                    <div
                      className="h-full w-full bg-[#e4dfd6]"
                      style={{
                        backgroundImage:
                          'linear-gradient(rgba(180,170,155,0.35) 1px,transparent 1px),linear-gradient(90deg,rgba(180,170,155,0.35) 1px,transparent 1px)',
                        backgroundSize: '36px 36px',
                      }}
                    />
                    <div className="absolute inset-0 flex flex-col items-center justify-center gap-2">
                      <MIcon name="location_on" className="text-[40px] text-[#c9920a]" />
                      <span className="rounded-full bg-[#c9920a] px-4 py-1 text-xs font-semibold text-white shadow-md transition-colors group-hover:bg-[#a87808]">
                        Xem trên Google Maps ↗
                      </span>
                    </div>
                  </a>
                ) : (
                  <div
                    className="flex h-full w-full items-center justify-center bg-[#e4dfd6]"
                    style={{
                      backgroundImage:
                        'linear-gradient(rgba(180,170,155,0.35) 1px,transparent 1px),linear-gradient(90deg,rgba(180,170,155,0.35) 1px,transparent 1px)',
                      backgroundSize: '36px 36px',
                    }}
                  >
                    <MIcon name="location_on" className="text-[40px] text-[#c9920a]" />
                  </div>
                )}
              </div>

              <div className="flex flex-wrap gap-3">
                {[
                  { icon: 'account_balance', label: '500m Hoàng Thành' },
                  { icon: 'directions_bus', label: '1.2km Bến xe' },
                  { icon: 'flight', label: '12km Sân bay' },
                ].map(item => (
                  <div
                    key={item.label}
                    className="flex items-center gap-2 rounded-lg border border-[#e5ddd5] bg-white px-3 py-1.5 text-sm"
                  >
                    <MIcon name={item.icon} className="text-[14px]" />
                    {item.label}
                  </div>
                ))}
              </div>
            </div>

            {/* ── Rooms section ── */}
            <div className="mb-12">
              {/* Pre-search prompt */}
              {!hasSearched && (
                <div
                  data-testid="location-search-prompt"
                  className="rounded-2xl border border-dashed border-[#d6cfc7] bg-white px-6 py-12 text-center"
                >
                  <MIcon name="bed" className="text-[48px] text-[#c9920a]" />
                  <h2 className="mt-4 font-serif text-xl font-semibold text-[#1f1b17]">
                    Chọn ngày để xem phòng
                  </h2>
                  <p className="mx-auto mt-2 max-w-sm text-sm leading-6 text-[#6b6458]">
                    Nhập ngày lưu trú bên trên để kiểm tra phòng còn trống tại {location.name}.
                  </p>
                </div>
              )}

              {/* Room list */}
              {hasSearched && roomCount > 0 && (
                <div>
                  <div className="mb-6 flex flex-wrap items-end gap-3">
                    <h2 className="font-serif text-2xl font-bold text-[#1f1b17]">
                      Phòng &amp; Giường
                    </h2>
                    <span className="rounded-full border border-[#e8d9b8] bg-[#fdf6ee] px-2.5 py-0.5 text-xs font-semibold text-[#7c5800]">
                      {roomCount} lựa chọn
                    </span>
                  </div>

                  <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    {location.rooms.map((room, index) => {
                      const bookingParams = new URLSearchParams({
                        room_id: String(room.id),
                        check_in: submittedCheckIn,
                        check_out: submittedCheckOut,
                      })
                      if (submittedGuests > 1) bookingParams.set('guests', String(submittedGuests))
                      const isBestseller = index === 0 && roomCount > 1

                      return (
                        <article
                          key={room.id}
                          className="flex flex-col overflow-hidden rounded-2xl bg-white shadow-[0_4px_12px_rgba(26,22,18,0.06)] transition-shadow duration-300 hover:shadow-xl"
                        >
                          {/* Room image */}
                          <div className="relative h-48">
                            <img
                              src={
                                roomImagePool[
                                  location.rooms.findIndex(r => r.id === room.id) %
                                    roomImagePool.length
                                ]
                              }
                              alt={room.display_name || room.name}
                              className="h-full w-full object-cover"
                              loading="lazy"
                            />
                            <span
                              className={[
                                'absolute left-3 top-3 rounded-full px-3 py-1 text-[10px] font-bold uppercase tracking-wider',
                                isBestseller
                                  ? 'bg-[#c9920a] text-white'
                                  : 'bg-[#FECACA] text-[#DC2626]',
                              ].join(' ')}
                            >
                              {isBestseller ? 'Bán chạy' : 'Phòng trống'}
                            </span>
                          </div>

                          {/* Room info */}
                          <div className="flex items-end justify-between p-5">
                            <div>
                              <h3 className="mb-1 font-serif text-lg font-bold text-[#1f1b17]">
                                {room.display_name || room.name}
                              </h3>
                              <p className="mb-1 text-xs text-[#9a9088]">
                                {room.description?.trim()
                                  ? room.description.trim().slice(0, 60) +
                                    (room.description.trim().length > 60 ? '…' : '')
                                  : defaultRoomDescription.slice(0, 60) + '…'}
                              </p>
                              <p className="text-xl font-bold text-[#c9920a]">
                                {formatVND(room.price)}
                                <span className="text-sm font-normal text-[#9a9088]">/đêm</span>
                              </p>
                            </div>
                            <Link
                              to={`/booking?${bookingParams.toString()}`}
                              aria-label="Đặt ngay"
                              className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-[#c9920a] to-[#a87808] text-white transition-transform hover:scale-105 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#c9920a]/40"
                            >
                              <MIcon name="arrow_forward" className="text-[18px]" />
                            </Link>
                          </div>
                        </article>
                      )
                    })}
                  </div>
                </div>
              )}

              {/* Empty state */}
              {hasSearched && roomCount === 0 && (
                <div className="rounded-2xl border border-[#e5ddd5] bg-white px-6 py-12 text-center">
                  <MIcon name="search_off" className="text-[48px] text-[#9a9088]" />
                  <h2 className="mt-4 font-serif text-xl font-semibold text-[#1f1b17]">
                    Không có phòng trống cho ngày này
                  </h2>
                  <button
                    type="button"
                    onClick={handleResetSearch}
                    className="mt-3 text-sm font-medium text-[#c9920a] transition hover:text-[#1f1b17]"
                  >
                    Thử chọn ngày khác
                  </button>
                </div>
              )}
            </div>

            {/* ── Reviews section ── */}
            <section className="border-t border-[#ede5da] pt-12">
              <div className="mb-10 flex flex-col gap-8 md:flex-row md:items-end md:justify-between">
                <div>
                  <h2 className="mb-6 font-serif text-3xl font-bold">Đánh giá từ khách hàng</h2>
                  <div className="flex items-center gap-8">
                    <div className="text-center">
                      <div className="mb-1 font-serif text-5xl font-bold text-[#c9920a]">4.9</div>
                      <div className="text-[10px] font-bold uppercase tracking-widest opacity-60">
                        trên 5
                      </div>
                    </div>
                    <div className="flex min-w-[200px] flex-1 flex-col gap-2">
                      {[
                        { star: 5, pct: 92 },
                        { star: 4, pct: 6 },
                        { star: 3, pct: 0 },
                        { star: 2, pct: 0 },
                        { star: 1, pct: 2 },
                      ].map(({ star, pct }) => (
                        <div key={star} className="flex items-center gap-3 text-xs font-bold">
                          <span className="w-3 text-right">{star}</span>
                          <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-[#e8e0d8]">
                            <div
                              className="h-full rounded-full bg-gradient-to-r from-[#c9920a] to-[#a87808]"
                              style={{ width: `${pct}%` }}
                              aria-hidden="true"
                            />
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                </div>
                <button
                  type="button"
                  className="rounded-lg border border-[#c9920a] bg-white px-8 py-3 text-sm font-bold text-[#c9920a] transition-colors hover:bg-[#fdf6ee]"
                >
                  Viết đánh giá
                </button>
              </div>

              <div className="rounded-xl border border-dashed border-[#e5ddd5] bg-[#fff8f4] px-6 py-10 text-center">
                <p className="text-sm text-[#9a9088]">
                  Chưa có đánh giá nào được hiển thị.
                  <br />
                  Đặt phòng và trải nghiệm để chia sẻ cảm nhận của bạn.
                </p>
              </div>
            </section>
          </div>
          {/* end left column */}

          {/* ─────────────── RIGHT COLUMN: BOOKING WIDGET ─────────────── */}
          <div ref={bookingRef} className="lg:w-80">
            <div className="lg:sticky lg:top-28">
              <div className="rounded-2xl border border-[#e5ddd5] bg-white p-6 shadow-[0_20px_40px_rgba(26,22,18,0.06)]">
                <h3 className="mb-4 font-serif text-xl font-bold">Kiểm tra phòng trống</h3>

                {/* Location chip */}
                <div className="mb-6 flex items-center gap-2 rounded-full bg-[#fef3d7] px-4 py-2 text-xs font-semibold text-[#7c5800]">
                  <MIcon name="location_on" className="text-[14px] text-[#c9920a]" />
                  <span className="truncate">{location.name}</span>
                </div>

                {/* Search form */}
                <form
                  role="search"
                  aria-label="Kiểm tra phòng trống"
                  noValidate
                  onSubmit={handleSearch}
                  className="mb-6 space-y-4"
                >
                  {/* Dates */}
                  <div className="grid grid-cols-2 gap-2">
                    <label className="block">
                      <span className="mb-1 block text-[10px] font-bold uppercase tracking-widest text-[#9a9088]">
                        Nhận phòng
                      </span>
                      <input
                        type="date"
                        value={checkIn}
                        min={today}
                        onChange={event => {
                          const next = event.target.value
                          setCheckIn(next)
                          setSearchError(null)
                          if (checkOut && next && checkOut <= next) setCheckOut(addDays(next, 1))
                        }}
                        className="w-full rounded-lg border-none bg-[#fff8f4] px-3 py-3 text-sm text-[#1f1b17] outline-none transition focus:ring-1 focus:ring-[#c9920a]"
                      />
                    </label>
                    <label className="block">
                      <span className="mb-1 block text-[10px] font-bold uppercase tracking-widest text-[#9a9088]">
                        Trả phòng
                      </span>
                      <input
                        type="date"
                        value={checkOut}
                        min={minCheckOutDate}
                        onChange={event => {
                          setCheckOut(event.target.value)
                          setSearchError(null)
                        }}
                        className="w-full rounded-lg border-none bg-[#fff8f4] px-3 py-3 text-sm text-[#1f1b17] outline-none transition focus:ring-1 focus:ring-[#c9920a]"
                      />
                    </label>
                  </div>

                  {/* Guests stepper */}
                  <div>
                    <span className="mb-1 block text-[10px] font-bold uppercase tracking-widest text-[#9a9088]">
                      Số khách
                    </span>
                    <div className="flex items-center justify-between rounded-lg bg-[#fff8f4] px-4 py-2">
                      <button
                        type="button"
                        aria-label="Giảm số khách"
                        onClick={() => {
                          setGuests(g => clampGuests(g - 1))
                          setSearchError(null)
                        }}
                        disabled={guests <= 1}
                        className="flex h-8 w-8 items-center justify-center rounded-full text-[#c9920a] transition hover:bg-[#f0e8d8] disabled:cursor-not-allowed disabled:opacity-30 focus-visible:outline-none"
                      >
                        <MIcon name="remove" className="text-[18px]" />
                      </button>
                      <span className="text-sm font-bold">{guests}</span>
                      <button
                        type="button"
                        aria-label="Tăng số khách"
                        onClick={() => {
                          setGuests(g => clampGuests(g + 1))
                          setSearchError(null)
                        }}
                        disabled={guests >= 10}
                        className="flex h-8 w-8 items-center justify-center rounded-full text-[#c9920a] transition hover:bg-[#f0e8d8] disabled:cursor-not-allowed disabled:opacity-30 focus-visible:outline-none"
                      >
                        <MIcon name="add" className="text-[18px]" />
                      </button>
                    </div>
                  </div>

                  {searchError && (
                    <p role="alert" className="text-sm font-medium text-red-600">
                      {searchError}
                    </p>
                  )}

                  <button
                    type="submit"
                    className="h-12 w-full rounded-lg bg-gradient-to-br from-[#c9920a] to-[#a87808] font-bold text-white shadow-lg shadow-[#c9920a]/20 transition-transform hover:scale-[1.02] active:scale-[0.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#c9920a]/40"
                  >
                    Tìm phòng
                  </button>

                  {hasSearched && (
                    <button
                      type="button"
                      onClick={handleResetSearch}
                      className="w-full text-center text-sm text-[#9a9088] transition hover:text-[#1f1b17]"
                    >
                      Xóa bộ lọc
                    </button>
                  )}
                </form>

                {/* Availability badge */}
                {hasSearched && roomCount > 0 && (
                  <div className="flex gap-3 rounded-xl border border-green-100 bg-green-50 p-4">
                    <MIcon name="check_circle" className="text-[20px] text-green-600" />
                    <div className="text-xs leading-relaxed">
                      <span className="mb-1 block font-bold text-green-700">
                        Còn {roomCount} phòng khả dụng
                      </span>
                      <span className="opacity-60">
                        {submittedCheckIn} → {submittedCheckOut}
                      </span>
                    </div>
                  </div>
                )}

                {/* Contact info */}
                {(location.contact.phone || location.contact.email) && (
                  <div className="mt-4 flex flex-col gap-1.5 border-t border-[#f0ebe4] pt-4 text-sm text-[#6b6458]">
                    {location.contact.phone && (
                      <a
                        href={`tel:${location.contact.phone}`}
                        className="flex items-center gap-2 transition hover:text-[#c9920a]"
                      >
                        <MIcon name="phone" className="text-[16px]" />
                        {location.contact.phone}
                      </a>
                    )}
                    {location.contact.email && (
                      <a
                        href={`mailto:${location.contact.email}`}
                        className="flex items-center gap-2 transition hover:text-[#c9920a]"
                      >
                        <MIcon name="mail" className="text-[16px]" />
                        {location.contact.email}
                      </a>
                    )}
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* ══════════════════════════════════════════════════════════════════
          MOBILE STICKY BOTTOM BAR
      ══════════════════════════════════════════════════════════════════ */}
      <div
        className="fixed inset-x-0 bottom-0 z-40 border-t border-[#e5ddd5] bg-white/95 px-6 py-4 shadow-[0_-4px_20px_rgba(31,27,23,0.08)] backdrop-blur-xl lg:hidden"
        aria-label="Đặt phòng nhanh"
      >
        <div className="flex items-center justify-between">
          <div className="flex flex-col">
            <span className="text-[10px] font-bold uppercase tracking-wider text-[#9a9088]">
              Từ
            </span>
            <div className="flex items-baseline gap-1">
              {hasSearched && lowestPrice !== null ? (
                <>
                  <span className="text-lg font-bold text-[#c9920a]">{formatVND(lowestPrice)}</span>
                  <span className="text-xs text-[#9a9088]">/đêm</span>
                </>
              ) : (
                <span className="max-w-[180px] truncate text-sm font-semibold text-[#1f1b17]">
                  {location.name}
                </span>
              )}
            </div>
          </div>
          <button
            type="button"
            onClick={() =>
              bookingRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' })
            }
            className="flex items-center gap-2 rounded-xl bg-gradient-to-br from-[#c9920a] to-[#a87808] px-6 py-3 text-sm font-bold text-white shadow-lg shadow-[#c9920a]/20 transition-transform hover:scale-[1.02] active:scale-[0.98]"
          >
            {hasSearched && roomCount > 0 ? 'Xem phòng' : 'Kiểm tra phòng'}
            <MIcon name="arrow_forward" className="text-[16px]" />
          </button>
        </div>
      </div>
    </div>
  )
}

export default LocationDetail
