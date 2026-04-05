import React, { useCallback, useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { getRooms } from './room.api'
import { Room } from './room.types'
import { formatVND } from '@/shared/lib/formatCurrency'

// ── Filter ──────────────────────────────────────────────────────────────────
// OPTION A: status-based filter.
// "Đã đặt" tab removed — guests cannot act on booked rooms.
// "Có thể đặt" uses status=available as a reliable proxy for bookable rooms.
// Real date-scoped availability is enforced by the backend on booking submission.
type FilterKey = 'all' | 'available'

const FILTER_TABS: { key: FilterKey; label: string }[] = [
  { key: 'all', label: 'Tất cả' },
  { key: 'available', label: 'Có thể đặt' },
]

// ── Status badge config ──────────────────────────────────────────────────────
const STATUS_CONFIG: Record<string, { label: string; className: string }> = {
  available: {
    label: 'Có thể đặt',
    className: 'bg-green-100 text-green-700',
  },
  booked: {
    label: 'Đã đặt',
    className: 'bg-amber-100 text-amber-600',
  },
  maintenance: {
    label: 'Bảo trì',
    className: 'bg-red-100 text-red-600',
  },
}

function getStatusConfig(status: Room['status']) {
  return (
    STATUS_CONFIG[status ?? ''] ?? {
      label: status ?? '',
      className: 'bg-zinc-100 text-zinc-500',
    }
  )
}

// ── SVG icons ────────────────────────────────────────────────────────────────
const BedIcon = ({ className }: { className?: string }) => (
  <svg
    className={className}
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    strokeWidth="1.5"
    strokeLinecap="round"
    strokeLinejoin="round"
    aria-hidden="true"
  >
    <path d="M2 4v16M2 8h20v12M2 8a4 4 0 0 1 4-4h12a4 4 0 0 1 4 4M10 12h4" />
  </svg>
)

const PeopleIcon = () => (
  <svg
    className="w-4 h-4 shrink-0"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    strokeWidth="1.5"
    strokeLinecap="round"
    strokeLinejoin="round"
    aria-hidden="true"
  >
    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
    <circle cx="9" cy="7" r="4" />
    <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
    <path d="M16 3.13a4 4 0 0 1 0 7.75" />
  </svg>
)

const AlertIcon = () => (
  <svg
    className="w-5 h-5 shrink-0"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    strokeWidth="1.5"
    strokeLinecap="round"
    strokeLinejoin="round"
    aria-hidden="true"
  >
    <circle cx="12" cy="12" r="10" />
    <path d="M12 8v4m0 4h.01" />
  </svg>
)

// ── Skeleton card ─────────────────────────────────────────────────────────────
// Mirrors final card anatomy: aspect-video image → name → subtitle → desc → price row
const RoomCardSkeleton: React.FC = () => (
  <div className="overflow-hidden bg-white rounded-xl border border-zinc-200 shadow-sm animate-pulse">
    {/* Image placeholder */}
    <div className="aspect-video bg-zinc-200" />
    {/* Body */}
    <div className="p-4 space-y-3">
      <div className="h-4 bg-zinc-200 rounded w-3/4" />
      <div className="h-3 bg-zinc-200 rounded w-1/2" />
      <div className="h-3 bg-zinc-200 rounded" />
      <div className="h-3 bg-zinc-200 rounded w-4/5" />
      <div className="flex items-center justify-between pt-2">
        <div className="h-4 bg-zinc-200 rounded w-16" />
        <div className="h-5 bg-zinc-200 rounded w-24" />
      </div>
      <div className="flex justify-end pt-1">
        <div className="h-9 bg-zinc-200 rounded-lg w-28" />
      </div>
    </div>
  </div>
)

// ── Main component ────────────────────────────────────────────────────────────
const RoomList: React.FC = () => {
  const navigate = useNavigate()
  const [rooms, setRooms] = useState<Room[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [activeFilter, setActiveFilter] = useState<FilterKey>('all')
  const [fetchKey, setFetchKey] = useState(0)

  const handleRetry = useCallback(() => {
    setError(null)
    setFetchKey(k => k + 1)
  }, [])

  useEffect(() => {
    const controller = new AbortController()

    const fetchRooms = async () => {
      try {
        setLoading(true)
        setError(null)
        const data = await getRooms(controller.signal)
        if (!controller.signal.aborted) {
          setRooms(data)
        }
      } catch (err) {
        const isCanceled =
          (err instanceof DOMException && err.name === 'AbortError') ||
          (err instanceof Error && (err as { code?: string }).code === 'ERR_CANCELED')
        if (isCanceled) return
        if (!controller.signal.aborted) {
          setError('Không thể tải danh sách phòng')
        }
      } finally {
        if (!controller.signal.aborted) {
          setLoading(false)
        }
      }
    }

    fetchRooms()
    return () => controller.abort()
  }, [fetchKey])

  const filtered = useMemo(() => {
    if (activeFilter === 'available') return rooms.filter(r => r.status === 'available')
    return rooms
  }, [rooms, activeFilter])

  return (
    <div className="min-h-screen bg-stone-50">
      {/* ── Page header ───────────────────────────────────────────────────── */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8 pb-6">
        {/* Back + title row */}
        <div className="flex items-center gap-3 mb-1">
          <button
            type="button"
            onClick={() => navigate(-1)}
            className="flex items-center justify-center w-8 h-8 rounded-full hover:bg-zinc-200 transition-colors focus:outline-none focus:ring-2 focus:ring-yellow-700/40"
            aria-label="Quay lại"
          >
            <svg
              className="w-5 h-5 text-zinc-700"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={1.8}
                d="M15 19l-7-7 7-7"
              />
            </svg>
          </button>
          <h1 className="text-2xl sm:text-3xl font-semibold text-zinc-900">Danh sách phòng</h1>
        </div>

        {/* Subtitle: room count */}
        {!loading && !error && (
          <p className="ml-11 text-sm text-zinc-500">{filtered.length} phòng</p>
        )}

        {/* Filter tabs */}
        <div className="flex gap-6 mt-5 border-b border-zinc-200">
          {FILTER_TABS.map(tab => (
            <button
              key={tab.key}
              type="button"
              onClick={() => setActiveFilter(tab.key)}
              className={`pb-3 text-sm transition-colors border-b-2 -mb-px focus:outline-none focus:ring-2 focus:ring-yellow-700/30 ${
                activeFilter === tab.key
                  ? 'border-yellow-700 text-zinc-900 font-medium'
                  : 'border-transparent text-zinc-500 hover:text-zinc-700'
              }`}
            >
              {tab.label}
            </button>
          ))}
        </div>
      </div>

      {/* ── Main content ──────────────────────────────────────────────────── */}
      <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-16">
        {/* Error state */}
        {error && (
          <div className="flex flex-col items-center justify-center py-16 text-center max-w-xs mx-auto">
            <div className="flex items-center justify-center w-14 h-14 rounded-full bg-red-50 text-red-500 mb-4">
              <AlertIcon />
            </div>
            <h3 className="text-base font-medium text-zinc-900">{error}</h3>
            <p className="mt-1 text-sm text-zinc-500">Vui lòng thử lại.</p>
            <button
              type="button"
              onClick={handleRetry}
              className="mt-5 px-5 py-2.5 rounded-lg bg-yellow-700 text-white text-sm font-medium
                         hover:bg-yellow-800 transition-colors
                         focus:outline-none focus:ring-2 focus:ring-yellow-700/40"
            >
              Thử lại
            </button>
          </div>
        )}

        {/* Loading — 6 skeleton cards in same grid */}
        {loading && (
          <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6">
            {Array.from({ length: 6 }).map((_, i) => (
              <RoomCardSkeleton key={i} />
            ))}
          </div>
        )}

        {/* Room grid */}
        {!loading && !error && filtered.length > 0 && (
          <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6">
            {filtered.map(room => {
              const statusCfg = getStatusConfig(room.status)
              const displayName = room.display_name ?? room.name
              const locationName = room.location?.name ?? null

              return (
                <article
                  key={room.id}
                  data-testid="room-card"
                  className="overflow-hidden bg-white rounded-xl border border-zinc-200
                             shadow-sm hover:shadow-md transition-shadow duration-200
                             flex flex-col"
                >
                  {/* Image area — 16:9, status badge overlaid */}
                  <div className="relative aspect-video overflow-hidden bg-[#1C1C1E] shrink-0">
                    {room.image_url ? (
                      <img
                        src={room.image_url}
                        alt={displayName}
                        loading="lazy"
                        className="object-cover w-full h-full"
                      />
                    ) : (
                      <div className="flex items-center justify-center w-full h-full">
                        <BedIcon className="w-10 h-10 text-[#B8860B]" />
                      </div>
                    )}

                    {/* Status badge — overlaid top-right */}
                    <span
                      className={`absolute top-3 right-3 px-2.5 py-1 rounded-full
                                  text-[11px] font-medium leading-none shadow-sm
                                  ${statusCfg.className}`}
                    >
                      {statusCfg.label}
                    </span>
                  </div>

                  {/* Card body */}
                  <div className="flex flex-col flex-1 p-4">
                    {/* Room name */}
                    <h3 className="text-base font-semibold text-zinc-900 leading-snug">
                      {displayName}
                    </h3>

                    {/* Branch/location subtitle */}
                    {locationName && (
                      <p className="mt-0.5 text-[13px] font-medium text-[#B8860B]">
                        {locationName}
                      </p>
                    )}

                    {/* Description */}
                    <p className="mt-2 text-[13px] text-zinc-500 leading-5 line-clamp-2 flex-1">
                      {room.description ||
                        'Phòng thoải mái với đầy đủ tiện nghi cơ bản tại Soleil Hostel.'}
                    </p>

                    {/* Divider */}
                    <div className="my-3 border-t border-zinc-100" />

                    {/* Guests + Price row */}
                    <div className="flex items-center justify-between">
                      {/* Max guests */}
                      <div className="flex items-center gap-1.5 text-zinc-500">
                        <PeopleIcon />
                        <span className="text-[13px]">
                          {room.max_guests != null ? `Tối đa ${room.max_guests} khách` : '—'}
                        </span>
                      </div>

                      {/* Price */}
                      <div data-testid="room-price" className="text-right">
                        <span className="text-xl font-semibold text-zinc-900">
                          {formatVND(room.price)}
                        </span>
                        <span className="text-[13px] text-zinc-500">/đêm</span>
                      </div>
                    </div>

                    {/* CTA — only for available rooms, NOT full-width */}
                    {room.status === 'available' && (
                      <div className="flex justify-end mt-3">
                        <button
                          type="button"
                          onClick={() => navigate(`/booking?room_id=${room.id}`)}
                          className="px-5 py-2.5 min-w-[120px] rounded-lg
                                     bg-[#B8860B] text-white text-sm font-medium
                                     hover:bg-yellow-800 transition-colors
                                     focus:outline-none focus:ring-2 focus:ring-yellow-700/40"
                        >
                          Đặt ngay
                        </button>
                      </div>
                    )}
                  </div>
                </article>
              )
            })}
          </div>
        )}

        {/* Empty state */}
        {!loading && !error && filtered.length === 0 && (
          <div className="flex flex-col items-center justify-center py-16 text-center max-w-xs mx-auto">
            <BedIcon className="w-12 h-12 text-zinc-300 mb-4" />
            <h3 className="text-base font-medium text-zinc-900">
              {activeFilter === 'all' ? 'Hiện chưa có phòng nào' : 'Không có phòng có thể đặt'}
            </h3>
            <p className="mt-1 text-sm text-zinc-500">
              Vui lòng thử lại sau hoặc liên hệ với chúng tôi.
            </p>
          </div>
        )}
      </main>
    </div>
  )
}

export default RoomList
