import React, { useState, useEffect, useCallback } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import FilterChips from './FilterChips'
import { getRooms } from '@/features/rooms/room.api'
import type { Room } from '@/features/rooms/room.types'
import { FILTER_CHIPS } from '../home.mock'

/**
 * RoomsSection — featured rooms fetched live from /v1/rooms.
 *
 * Mobile  (<sm): 2-col grid, px-4
 * Tablet  (sm):  2-col grid, max-w container
 * Desktop (lg):  3-col grid, max-w-7xl container
 * Wide    (xl):  4-col grid
 *
 * Picks a diverse mix across room types (dorm → standard → double →
 * deluxe → family → vip) so the homepage showcases the full price range.
 * Max 8 cards displayed; at most 2 per type.
 */
const TYPE_ORDER = ['dorm', 'standard', 'double', 'deluxe', 'family', 'vip'] as const
const MAX_PER_TYPE = 2
const MAX_DISPLAY = 8

/** Return up to MAX_DISPLAY rooms with at most MAX_PER_TYPE per room_type_code. */
function pickFeatured(rooms: Room[]): Room[] {
  const buckets = new Map<string, Room[]>()
  for (const room of rooms) {
    const key = room.room_type_code ?? 'other'
    if (!buckets.has(key)) buckets.set(key, [])
    buckets.get(key)!.push(room)
  }

  const result: Room[] = []
  // First pass: one per type in order
  for (const type of TYPE_ORDER) {
    const bucket = buckets.get(type)
    if (bucket?.length) result.push(bucket[0])
    if (result.length >= MAX_DISPLAY) return result
  }
  // Second pass: second room per type
  for (const type of TYPE_ORDER) {
    const bucket = buckets.get(type)
    if (bucket && bucket.length >= MAX_PER_TYPE) result.push(bucket[1])
    if (result.length >= MAX_DISPLAY) return result
  }
  // Fill remaining with any leftover
  for (const bucket of buckets.values()) {
    for (const room of bucket) {
      if (!result.includes(room)) result.push(room)
      if (result.length >= MAX_DISPLAY) return result
    }
  }
  return result
}

const RoomsSection: React.FC = () => {
  const [rooms, setRooms] = useState<Room[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(false)
  const [fetchKey, setFetchKey] = useState(0)

  const fetchRooms = useCallback(async (signal?: AbortSignal) => {
    setLoading(true)
    setError(false)
    try {
      const data = await getRooms(signal)
      if (!signal?.aborted) setRooms(pickFeatured(data))
    } catch (err: unknown) {
      if (err instanceof Error && (err.name === 'AbortError' || err.name === 'CanceledError'))
        return
      if (!signal?.aborted) setError(true)
    } finally {
      if (!signal?.aborted) setLoading(false)
    }
  }, [])

  useEffect(() => {
    const controller = new AbortController()
    void fetchRooms(controller.signal)
    return () => controller.abort()
  }, [fetchRooms, fetchKey])

  return (
    <section aria-label="Phòng nổi bật" className="mt-10 md:mt-16">
      <div className="max-w-7xl mx-auto px-4 md:px-8 lg:px-12">
        {/* Header row */}
        <div className="flex justify-between items-baseline mb-4 md:mb-6">
          <div>
            <h2 className="font-serif font-semibold text-[#1C1A17] text-xl md:text-2xl lg:text-3xl">
              Phòng nổi bật
            </h2>
            <p className="mt-1 text-sm text-[#6B6760] hidden md:block">
              Lựa chọn phòng phù hợp với nhu cầu của bạn
            </p>
          </div>
          <Link
            to="/rooms"
            className="text-sm font-medium text-[#C9973A] hover:text-[#B8872A] transition-colors focus-visible:outline-none focus-visible:underline shrink-0"
          >
            Xem tất cả →
          </Link>
        </div>

        {/* Amenity filter chips */}
        <div className="-mx-4 md:mx-0">
          <FilterChips chips={FILTER_CHIPS} />
        </div>

        {/* Error state */}
        {error && (
          <div className="mt-6 flex flex-col items-center gap-3 py-10 text-center">
            <p className="text-sm text-[#6B6760]">Không thể tải danh sách phòng.</p>
            <button
              onClick={() => setFetchKey(k => k + 1)}
              className="text-sm font-medium text-[#C9973A] hover:text-[#B8872A] underline"
            >
              Thử lại
            </button>
          </div>
        )}

        {/* Skeleton */}
        {loading && !error && (
          <div className="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 md:gap-6 mt-4 md:mt-6">
            {Array.from({ length: 4 }).map((_, i) => (
              <div
                key={i}
                className="animate-pulse rounded-xl border border-[#E2DDD6] overflow-hidden bg-white"
              >
                <div className="bg-[#F0EDE8] h-[180px]" />
                <div className="p-3 space-y-2">
                  <div className="h-3.5 bg-[#F0EDE8] rounded w-4/5" />
                  <div className="h-3 bg-[#F0EDE8] rounded w-2/5" />
                  <div className="h-9 bg-[#F0EDE8] rounded mt-3" />
                </div>
              </div>
            ))}
          </div>
        )}

        {/* Room grid */}
        {!loading && !error && rooms.length === 0 && (
          <p className="mt-8 text-center text-sm text-[#6B6760]">Hiện chưa có phòng nào.</p>
        )}

        {!loading && !error && rooms.length > 0 && (
          <div className="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 md:gap-6 mt-4 md:mt-6">
            {rooms.map(room => (
              <RoomCard key={room.id} room={room} />
            ))}
          </div>
        )}
      </div>
    </section>
  )
}

// ── VND formatter ────────────────────────────────────────────────────────────
const VND = new Intl.NumberFormat('vi-VN')

// ── RoomCard ─────────────────────────────────────────────────────────────────
const RoomCard: React.FC<{ room: Room }> = ({ room }) => {
  const navigate = useNavigate()
  const isAvailable = room.status === 'available'
  const locationName = room.location?.name ?? null

  return (
    <article className="border border-[#E2DDD6] rounded-xl overflow-hidden bg-white">
      {/* Image */}
      <div className="relative" style={{ height: '180px' }}>
        {room.image_url ? (
          <img
            src={room.image_url}
            alt={room.name}
            className="absolute inset-0 w-full h-full object-cover"
            loading="lazy"
          />
        ) : (
          <div className="absolute inset-0 bg-[#1C1C1E] flex items-center justify-center">
            <svg
              viewBox="0 0 24 24"
              fill="none"
              stroke="#C9973A"
              strokeWidth="1.2"
              className="w-10 h-10 opacity-60"
              aria-hidden="true"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"
              />
            </svg>
          </div>
        )}

        {/* Location badge */}
        {locationName && (
          <span className="absolute bottom-2 left-2 inline-flex items-center gap-1 bg-black/50 backdrop-blur-sm text-white text-[11px] px-2 py-0.5 rounded-full">
            📍 {locationName}
          </span>
        )}

        {/* Status badge */}
        <span
          className={[
            'absolute top-2 right-2 text-[11px] font-medium px-2 py-0.5 rounded-full border',
            isAvailable
              ? 'bg-green-100 text-green-800 border-green-200'
              : 'bg-gray-100 text-gray-600 border-gray-200',
          ].join(' ')}
        >
          {isAvailable ? 'Còn phòng' : 'Hết phòng'}
        </span>
      </div>

      {/* Card body */}
      <div className="p-3">
        <h3 className="font-medium text-[15px] text-[#1C1A17] leading-snug mb-1.5 line-clamp-2">
          {room.name}
        </h3>
        <p className="text-[13px] mb-2.5">
          <span className="text-[#C9973A] font-medium">Từ {VND.format(room.price)}₫</span>
          <span className="text-[#6B6760]"> / đêm</span>
        </p>
        {isAvailable ? (
          <button
            aria-label="Đặt ngay"
            onClick={() => navigate('/booking')}
            className="w-full h-9 bg-[#C9973A] hover:bg-[#B8872A] text-white text-sm font-medium rounded-lg transition-colors duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#C9973A] focus-visible:ring-offset-1"
          >
            Đặt ngay
          </button>
        ) : (
          <p className="text-xs text-[#6B6760] text-center py-2">Không còn phòng trống</p>
        )}
      </div>
    </article>
  )
}

export default RoomsSection
