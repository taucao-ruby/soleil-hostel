import React, { useState, useEffect, useCallback } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import FilterChips from './FilterChips'
import { getRooms } from '@/features/rooms/room.api'
import type { Room } from '@/features/rooms/room.types'
import { FILTER_CHIPS } from '../home.mock'

/**
 * RoomsSection — featured rooms fetched live from /v1/rooms (Stitch spec).
 *
 * Mobile  (<md): 2-col grid
 * Desktop (≥md): 3-col grid, max-w-7xl container
 * Wide    (xl):  4-col grid
 *
 * Section heading: font-serif italic (Stitch)
 * Card: image, room-type chip, name, price, "Xem chi tiết" link + "Đặt ngay" CTA
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
  for (const type of TYPE_ORDER) {
    const bucket = buckets.get(type)
    if (bucket?.length) result.push(bucket[0])
    if (result.length >= MAX_DISPLAY) return result
  }
  for (const type of TYPE_ORDER) {
    const bucket = buckets.get(type)
    if (bucket && bucket.length >= MAX_PER_TYPE) result.push(bucket[1])
    if (result.length >= MAX_DISPLAY) return result
  }
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
    <section aria-label="Phòng nổi bật" className="py-12 md:py-20">
      <div className="max-w-7xl mx-auto px-4 md:px-8 lg:px-12">
        {/* Header row */}
        <div className="flex justify-between items-end mb-6 md:mb-10">
          <div>
            <h2 className="font-serif italic text-[#1C1A17] text-2xl md:text-3xl lg:text-4xl">
              Phòng Nghỉ Nổi Bật
            </h2>
            <p className="mt-1 text-sm text-[#6B6760] hidden md:block">
              Sự kết hợp giữa tiện nghi hiện đại và di sản văn hóa.
            </p>
          </div>
          <Link
            to="/rooms"
            className="text-[13px] font-bold text-[#C9920A] hover:text-[#a87808] border-b border-[#C9920A]/50 pb-0.5 tracking-wide uppercase transition-colors focus-visible:outline-none shrink-0"
          >
            Xem tất cả loại phòng
          </Link>
        </div>

        {/* Amenity filter chips */}
        <div className="-mx-4 md:mx-0 mb-6">
          <FilterChips chips={FILTER_CHIPS} />
        </div>

        {/* Error state */}
        {error && (
          <div className="flex flex-col items-center gap-3 py-10 text-center">
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
          <div className="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 md:gap-6">
            {Array.from({ length: 4 }).map((_, i) => (
              <div
                key={i}
                className="animate-pulse rounded-2xl border border-[#E2DDD6] overflow-hidden bg-white"
              >
                <div className="bg-[#F0EDE8] h-[220px]" />
                <div className="p-4 space-y-2">
                  <div className="h-3 bg-[#F0EDE8] rounded w-1/3" />
                  <div className="h-4 bg-[#F0EDE8] rounded w-4/5" />
                  <div className="h-3 bg-[#F0EDE8] rounded w-2/5 mt-2" />
                  <div className="h-9 bg-[#F0EDE8] rounded mt-3" />
                </div>
              </div>
            ))}
          </div>
        )}

        {!loading && !error && rooms.length === 0 && (
          <p className="mt-8 text-center text-sm text-[#6B6760]">Hiện chưa có phòng nào.</p>
        )}

        {!loading && !error && rooms.length > 0 && (
          <div className="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 md:gap-6">
            {rooms.map(room => (
              <RoomCard key={room.id} room={room} />
            ))}
          </div>
        )}
      </div>
    </section>
  )
}

// ── VND formatter ─────────────────────────────────────────────────────────────
const VND = new Intl.NumberFormat('vi-VN')

// ── Room type label map ────────────────────────────────────────────────────────
const TYPE_LABELS: Record<string, string> = {
  dorm: 'Dorm',
  standard: 'Standard',
  double: 'Double',
  deluxe: 'Deluxe',
  family: 'Family',
  vip: 'VIP',
}

// ── RoomCard ──────────────────────────────────────────────────────────────────
const RoomCard: React.FC<{ room: Room }> = ({ room }) => {
  const navigate = useNavigate()
  const isAvailable = room.status === 'available'
  const locationName = room.location?.name ?? null
  const typeLabel = room.room_type_code
    ? (TYPE_LABELS[room.room_type_code] ?? room.room_type_code)
    : null

  return (
    <article className="group border border-[#E2DDD6] rounded-2xl overflow-hidden bg-white hover:shadow-[0_20px_40px_rgba(26,22,18,0.08)] transition-shadow duration-300">
      {/* Image */}
      <div className="relative overflow-hidden" style={{ height: '220px' }}>
        {room.image_url ? (
          <img
            src={room.image_url}
            alt={room.name}
            className="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-700"
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
      <div className="p-4">
        {/* Room type chip */}
        {typeLabel && (
          <span className="inline-block bg-[#F0EDE8] text-[#504534] text-[11px] font-bold uppercase tracking-wide px-2.5 py-0.5 rounded mb-2">
            {typeLabel}
          </span>
        )}

        <h3 className="font-serif italic text-[16px] text-[#1C1A17] leading-snug mb-2 line-clamp-2">
          {room.name}
        </h3>

        <p className="text-[13px] mb-3">
          <span className="text-[#C9920A] font-bold text-[16px]">{VND.format(room.price)}₫</span>
          <span className="text-[#6B6760] text-[12px]"> / đêm</span>
        </p>

        {/* Actions row */}
        <div className="flex items-center justify-between pt-3 border-t border-[#E2DDD6]/60">
          <Link
            to="/rooms"
            className="text-[13px] font-bold text-[#C9920A] hover:underline flex items-center gap-0.5 focus-visible:outline-none focus-visible:underline"
          >
            Xem chi tiết →
          </Link>
          {isAvailable ? (
            <button
              aria-label="Đặt ngay"
              onClick={() => navigate('/booking')}
              className="h-8 px-4 bg-[#C9920A] hover:bg-[#a87808] text-white text-[12px] font-bold rounded-lg transition-colors duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#C9920A] focus-visible:ring-offset-1 active:scale-95"
            >
              Đặt ngay
            </button>
          ) : (
            <span className="text-xs text-[#6B6760]">Hết phòng</span>
          )}
        </div>
      </div>
    </article>
  )
}

export default RoomsSection
