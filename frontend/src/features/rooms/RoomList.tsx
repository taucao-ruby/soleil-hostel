import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { getRooms } from './room.api'
import { Room } from './room.types'
import { formatVND } from '@/shared/lib/formatCurrency'

// ── Filter ──────────────────────────────────────────────────────────────────
type FilterKey = 'all' | 'available' | 'dorm' | 'single' | 'double' | 'vip' | 'family'

const FILTER_TABS: { key: FilterKey; label: string }[] = [
  { key: 'all', label: 'Tất cả' },
  { key: 'dorm', label: 'Dorm' },
  { key: 'single', label: 'Đơn' },
  { key: 'double', label: 'Đôi' },
  { key: 'vip', label: 'VIP' },
  { key: 'family', label: 'Gia đình' },
]

const TYPE_CODE_MAP: Record<string, FilterKey> = {
  dorm: 'dorm',
  single: 'single',
  double: 'double',
  vip: 'vip',
  family: 'family',
}

// ── Status badge config ──────────────────────────────────────────────────────
const STATUS_CONFIG: Record<string, { label: string; badgeClass: string }> = {
  available: {
    label: 'Có thể đặt',
    badgeClass: 'bg-[#006C49] text-white',
  },
  booked: {
    label: 'Đã đặt',
    badgeClass: 'bg-amber-500 text-white',
  },
  maintenance: {
    label: 'Bảo trì',
    badgeClass: 'bg-red-600 text-white',
  },
}

function getStatusConfig(status: Room['status']) {
  return (
    STATUS_CONFIG[status ?? ''] ?? {
      label: status ?? '',
      badgeClass: 'bg-zinc-500 text-white',
    }
  )
}

// ── SVG icons ────────────────────────────────────────────────────────────────
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

const NightIcon = () => (
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
    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
  </svg>
)

const LocationIcon = () => (
  <svg
    className="w-3.5 h-3.5 shrink-0"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    strokeWidth="1.5"
    strokeLinecap="round"
    strokeLinejoin="round"
    aria-hidden="true"
  >
    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
    <circle cx="12" cy="10" r="3" />
  </svg>
)

const ChevronDownIcon = () => (
  <svg
    className="w-4 h-4"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    strokeWidth="2"
    aria-hidden="true"
  >
    <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
  </svg>
)

// ── Skeleton cards ─────────────────────────────────────────────────────────
const DesktopCardSkeleton: React.FC = () => (
  <div className="flex bg-white rounded-2xl overflow-hidden shadow-sm h-[200px] animate-pulse">
    <div className="w-[280px] flex-shrink-0 bg-[#EAE1DA]" />
    <div className="flex justify-between flex-1 p-6">
      <div className="flex flex-col justify-between w-full">
        <div className="space-y-3">
          <div className="h-5 bg-[#EAE1DA] rounded w-2/3" />
          <div className="h-3 bg-[#EAE1DA] rounded w-1/3" />
          <div className="flex gap-3 mt-3">
            <div className="h-3 bg-[#EAE1DA] rounded w-24" />
            <div className="h-3 bg-[#EAE1DA] rounded w-20" />
          </div>
        </div>
        <div className="flex gap-3">
          <div className="h-4 bg-[#EAE1DA] rounded-full w-16" />
          <div className="h-4 bg-[#EAE1DA] rounded-full w-16" />
        </div>
      </div>
      <div className="w-[180px] flex flex-col items-end justify-center gap-3 pl-6 border-l border-[#D4C4AE]/20">
        <div className="h-3 bg-[#EAE1DA] rounded w-16" />
        <div className="h-7 bg-[#EAE1DA] rounded w-24" />
        <div className="h-10 bg-[#EAE1DA] rounded-lg w-full mt-2" />
      </div>
    </div>
  </div>
)

// ── Sidebar filter constants ──────────────────────────────────────────────────
const PRICE_MAX = 2000000
const PRICE_STEP = 50000

const BRANCH_OPTIONS = [
  'Soleil Hostel',
  'Soleil House',
  'Soleil Urban Villa',
  'Soleil Boutique Homestay',
  'Soleil Riverside Villa',
]

const ROOM_TYPE_OPTIONS = [
  { code: 'dorm', label: 'Dorm' },
  { code: 'standard', label: 'Phòng đơn' },
  { code: 'double', label: 'Phòng đôi' },
  { code: 'vip', label: 'VIP' },
  { code: 'family', label: 'Gia đình' },
]

function formatPrice(p: number): string {
  if (p === 0) return '0đ'
  if (p >= 1000000) {
    const m = p / 1000000
    return `${m % 1 === 0 ? m : m.toFixed(1)}tr`
  }
  return `${(p / 1000).toFixed(0)}.000đ`
}

// ── Main component ────────────────────────────────────────────────────────────
const RoomList: React.FC = () => {
  const navigate = useNavigate()
  const [rooms, setRooms] = useState<Room[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [activeFilter, setActiveFilter] = useState<FilterKey>('all')
  const [sortOrder, setSortOrder] = useState<'asc' | 'desc'>('asc')
  const [fetchKey, setFetchKey] = useState(0)

  // Sidebar pending state (what the user is configuring)
  const [pendingBranch, setPendingBranch] = useState<string | null>(null)
  const [pendingTypes, setPendingTypes] = useState<string[]>([])
  const [pendingPriceMin, setPendingPriceMin] = useState(0)
  const [pendingPriceMax, setPendingPriceMax] = useState(PRICE_MAX)

  // Sidebar applied state (actually used for filtering)
  const [appliedBranch, setAppliedBranch] = useState<string | null>(null)
  const [appliedTypes, setAppliedTypes] = useState<string[]>([])
  const [appliedPriceMin, setAppliedPriceMin] = useState(0)
  const [appliedPriceMax, setAppliedPriceMax] = useState(PRICE_MAX)

  // Refs for price drag (avoids stale closure)
  const priceMinRef = useRef(0)
  const priceMaxRef = useRef(PRICE_MAX)
  const trackRef = useRef<HTMLDivElement>(null)
  const draggingThumb = useRef<'min' | 'max' | null>(null)

  // Keep refs in sync with state
  useEffect(() => {
    priceMinRef.current = pendingPriceMin
  }, [pendingPriceMin])
  useEffect(() => {
    priceMaxRef.current = pendingPriceMax
  }, [pendingPriceMax])

  const handleRetry = useCallback(() => {
    setError(null)
    setFetchKey(k => k + 1)
  }, [])

  const toggleType = useCallback((code: string) => {
    setPendingTypes(prev => (prev.includes(code) ? prev.filter(t => t !== code) : [...prev, code]))
  }, [])

  const handleApplyFilters = useCallback(() => {
    setAppliedBranch(pendingBranch)
    setAppliedTypes([...pendingTypes])
    setAppliedPriceMin(pendingPriceMin)
    setAppliedPriceMax(pendingPriceMax)
  }, [pendingBranch, pendingTypes, pendingPriceMin, pendingPriceMax])

  const handleClearFilters = useCallback(() => {
    setPendingBranch(null)
    setPendingTypes([])
    setPendingPriceMin(0)
    setPendingPriceMax(PRICE_MAX)
    setAppliedBranch(null)
    setAppliedTypes([])
    setAppliedPriceMin(0)
    setAppliedPriceMax(PRICE_MAX)
    setActiveFilter('all')
  }, [])

  // Dual-range price slider drag logic
  const handleThumbMouseDown = useCallback(
    (thumb: 'min' | 'max') => (e: React.MouseEvent | React.TouchEvent) => {
      e.preventDefault()
      draggingThumb.current = thumb

      const getVal = (clientX: number) => {
        if (!trackRef.current) return 0
        const rect = trackRef.current.getBoundingClientRect()
        const ratio = Math.max(0, Math.min(1, (clientX - rect.left) / rect.width))
        return Math.round((ratio * PRICE_MAX) / PRICE_STEP) * PRICE_STEP
      }

      const onMove = (ev: MouseEvent | TouchEvent) => {
        const clientX = 'touches' in ev ? ev.touches[0].clientX : ev.clientX
        const val = getVal(clientX)
        if (draggingThumb.current === 'min') {
          setPendingPriceMin(Math.min(val, priceMaxRef.current - PRICE_STEP))
        } else {
          setPendingPriceMax(Math.max(val, priceMinRef.current + PRICE_STEP))
        }
      }
      const onUp = () => {
        draggingThumb.current = null
        document.removeEventListener('mousemove', onMove)
        document.removeEventListener('mouseup', onUp)
        document.removeEventListener('touchmove', onMove)
        document.removeEventListener('touchend', onUp)
      }
      document.addEventListener('mousemove', onMove)
      document.addEventListener('mouseup', onUp)
      document.addEventListener('touchmove', onMove, { passive: false })
      document.addEventListener('touchend', onUp)
    },
    []
  )

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
    let result: Room[]
    if (activeFilter === 'available') result = rooms.filter(r => r.status === 'available')
    else if (activeFilter !== 'all') {
      result = rooms.filter(r => {
        const code = r.room_type_code?.toLowerCase() ?? ''
        return TYPE_CODE_MAP[code] === activeFilter
      })
    } else {
      result = rooms
    }
    // Apply sidebar filters
    if (appliedBranch) {
      result = result.filter(r => r.location?.name === appliedBranch)
    }
    if (appliedTypes.length > 0) {
      result = result.filter(r => appliedTypes.includes(r.room_type_code?.toLowerCase() ?? ''))
    }
    result = result.filter(r => r.price >= appliedPriceMin && r.price <= appliedPriceMax)
    return [...result].sort((a, b) => (sortOrder === 'asc' ? a.price - b.price : b.price - a.price))
  }, [
    rooms,
    activeFilter,
    sortOrder,
    appliedBranch,
    appliedTypes,
    appliedPriceMin,
    appliedPriceMax,
  ])

  const tabCount = useMemo(() => {
    const counts: Partial<Record<FilterKey, number>> = { all: rooms.length }
    rooms.forEach(r => {
      const code = r.room_type_code?.toLowerCase() ?? ''
      const mapped = TYPE_CODE_MAP[code]
      if (mapped) counts[mapped] = (counts[mapped] ?? 0) + 1
    })
    return counts
  }, [rooms])

  return (
    <div className="min-h-screen bg-[#F5EFE3]">
      {/* ── Mobile sticky search bar ──────────────────────────────────────── */}
      <div className="md:hidden sticky top-16 z-40 bg-white border-b border-[#D4C4AE]/30 px-5 py-3 flex justify-between items-center shadow-sm">
        <div className="flex flex-col">
          <span className="text-[13px] font-bold text-[#1C1A17]">
            {loading ? 'Đang tải...' : `${filtered.length} phòng phù hợp`}
          </span>
          <span className="text-[11px] text-[#504534] tracking-wide uppercase">Soleil Hostel</span>
        </div>
        <button
          type="button"
          className="flex items-center gap-1 text-[#C9920A] font-semibold text-sm"
        >
          Lọc
          <ChevronDownIcon />
        </button>
      </div>

      <div className="px-4 pt-6 pb-16 mx-auto max-w-7xl md:px-8 md:pt-8 md:flex md:gap-10 md:items-start">
        {/* ── Desktop sidebar ────────────────────────────────────────────── */}
        <aside className="hidden md:flex w-[260px] flex-shrink-0 sticky top-24 flex-col gap-8 bg-[#F6ECE5] p-6 rounded-2xl">
          <div className="flex items-center justify-between">
            <h2 className="text-lg font-semibold text-[#1C1A17]">Bộ lọc</h2>
            <button
              type="button"
              onClick={handleClearFilters}
              className="text-[#C9920A] text-sm font-medium hover:underline"
            >
              Xóa tất cả
            </button>
          </div>

          {/* Branch */}
          <div>
            <p className="text-xs uppercase tracking-widest font-bold text-[#827562] mb-4">
              Chi nhánh
            </p>
            <div className="flex flex-col gap-3">
              {/* "All branches" option */}
              <label className="flex items-center gap-3 cursor-pointer group">
                <div
                  className={`w-5 h-5 rounded-full border-2 flex items-center justify-center p-0.5 ${
                    pendingBranch === null
                      ? 'border-[#C9920A]'
                      : 'border-[#D4C4AE] group-hover:border-[#C9920A]'
                  }`}
                >
                  {pendingBranch === null && (
                    <div className="w-2.5 h-2.5 rounded-full bg-[#C9920A]" />
                  )}
                </div>
                <span className="text-sm font-medium text-[#1C1A17]">Tất cả chi nhánh</span>
              </label>
              {BRANCH_OPTIONS.map(branch => (
                <label key={branch} className="flex items-center gap-3 cursor-pointer group">
                  <div
                    className={`w-5 h-5 rounded-full border-2 flex items-center justify-center p-0.5 ${
                      pendingBranch === branch
                        ? 'border-[#C9920A]'
                        : 'border-[#D4C4AE] group-hover:border-[#C9920A]'
                    }`}
                    onClick={() => setPendingBranch(branch === pendingBranch ? null : branch)}
                  >
                    {pendingBranch === branch && (
                      <div className="w-2.5 h-2.5 rounded-full bg-[#C9920A]" />
                    )}
                  </div>
                  <span
                    className="text-sm font-medium text-[#1C1A17]"
                    onClick={() => setPendingBranch(branch === pendingBranch ? null : branch)}
                  >
                    {branch}
                  </span>
                </label>
              ))}
            </div>
          </div>

          {/* Room type */}
          <div>
            <p className="text-xs uppercase tracking-widest font-bold text-[#827562] mb-4">
              Loại phòng
            </p>
            <div className="grid grid-cols-2 gap-3">
              {ROOM_TYPE_OPTIONS.map(({ code, label }) => {
                const checked = pendingTypes.includes(code)
                return (
                  <label
                    key={code}
                    className="flex items-center gap-2 cursor-pointer"
                    onClick={() => toggleType(code)}
                  >
                    <div
                      className={`w-5 h-5 rounded flex items-center justify-center ${
                        checked ? 'bg-[#C9920A]' : 'border-2 border-[#D4C4AE] bg-white'
                      }`}
                    >
                      {checked && (
                        <svg
                          className="w-3 h-3 text-white"
                          viewBox="0 0 12 12"
                          fill="none"
                          stroke="currentColor"
                          strokeWidth="2"
                          aria-hidden="true"
                        >
                          <path strokeLinecap="round" strokeLinejoin="round" d="M2 6l3 3 5-5" />
                        </svg>
                      )}
                    </div>
                    <span className="text-sm font-medium text-[#1C1A17]">{label}</span>
                  </label>
                )
              })}
            </div>
          </div>

          {/* Price range */}
          <div>
            <p className="text-xs uppercase tracking-widest font-bold text-[#827562] mb-4">
              Khoảng giá
            </p>
            <div ref={trackRef} className="relative h-1 w-full bg-[#D4C4AE] rounded-full mb-6 mt-3">
              {/* Active track */}
              <div
                className="absolute h-full bg-[#C9920A] rounded-full"
                style={{
                  left: `${(pendingPriceMin / PRICE_MAX) * 100}%`,
                  right: `${100 - (pendingPriceMax / PRICE_MAX) * 100}%`,
                }}
              />
              {/* Min thumb */}
              <div
                className="absolute top-1/2 -translate-y-1/2 -translate-x-1/2 w-4 h-4 bg-white border-2 border-[#C9920A] rounded-full shadow-md cursor-grab active:cursor-grabbing touch-none"
                style={{ left: `${(pendingPriceMin / PRICE_MAX) * 100}%` }}
                onMouseDown={handleThumbMouseDown('min')}
                onTouchStart={handleThumbMouseDown('min')}
              />
              {/* Max thumb */}
              <div
                className="absolute top-1/2 -translate-y-1/2 -translate-x-1/2 w-4 h-4 bg-white border-2 border-[#C9920A] rounded-full shadow-md cursor-grab active:cursor-grabbing touch-none"
                style={{ left: `${(pendingPriceMax / PRICE_MAX) * 100}%` }}
                onMouseDown={handleThumbMouseDown('max')}
                onTouchStart={handleThumbMouseDown('max')}
              />
            </div>
            <div className="flex justify-between text-[11px] font-bold text-[#504534]">
              <span>{formatPrice(pendingPriceMin)}</span>
              <span>{formatPrice(pendingPriceMax)} / đêm</span>
            </div>
          </div>

          <button
            type="button"
            onClick={handleApplyFilters}
            className="w-full py-3 rounded-lg font-bold text-sm tracking-wide text-white mt-4
                       bg-gradient-to-br from-[#C9920A] to-[#A87808]
                       hover:shadow-lg transition-all active:scale-95"
          >
            Áp dụng bộ lọc
          </button>
        </aside>

        {/* ── Results area ──────────────────────────────────────────────── */}
        <section className="flex-1 min-w-0">
          {/* Sticky results header */}
          <div className="sticky top-16 md:top-24 z-30 bg-[#F5EFE3] pb-4">
            <div className="flex items-end justify-between mb-4 md:mb-6">
              <div>
                <h1 className="text-2xl md:text-3xl font-serif italic text-[#1C1A17]">
                  Khám phá không gian
                </h1>
                {!loading && !error && (
                  <p className="text-[#504534] text-sm mt-1">{filtered.length} phòng phù hợp</p>
                )}
              </div>
              {/* Sort — desktop only */}
              <button
                type="button"
                onClick={() => setSortOrder(o => (o === 'asc' ? 'desc' : 'asc'))}
                className="hidden md:flex items-center gap-2 px-4 py-2 bg-[#FCF2EB] rounded-lg border border-[#D4C4AE]
                           hover:bg-[#F6ECE5] transition-colors"
              >
                <span className="text-xs font-bold text-[#504534]">Sắp xếp:</span>
                <span className="text-xs font-bold text-[#1C1A17]">
                  {sortOrder === 'asc' ? 'Giá: thấp đến cao' : 'Giá: cao đến thấp'}
                </span>
                <ChevronDownIcon />
              </button>
            </div>

            {/* Filter tabs */}
            <div className="flex gap-4 md:gap-8 border-b border-[#D4C4AE]/30 overflow-x-auto">
              {FILTER_TABS.map(tab => (
                <button
                  key={tab.key}
                  type="button"
                  onClick={() => setActiveFilter(tab.key)}
                  className={`pb-3 text-sm whitespace-nowrap border-b-2 -mb-px transition-colors focus:outline-none ${
                    activeFilter === tab.key
                      ? 'border-[#C9920A] text-[#C9920A] font-bold'
                      : 'border-transparent text-[#504534] hover:text-[#1C1A17] font-medium'
                  }`}
                >
                  {tab.label}
                  {tabCount[tab.key] != null && (
                    <span className="ml-1 text-xs opacity-70">({tabCount[tab.key]})</span>
                  )}
                </button>
              ))}
            </div>
          </div>

          {/* ── Error state ────────────────────────────────────────────── */}
          {error && (
            <div className="flex flex-col items-center justify-center max-w-xs py-16 mx-auto text-center">
              <div className="flex items-center justify-center mb-4 text-red-500 rounded-full w-14 h-14 bg-red-50">
                <AlertIcon />
              </div>
              <h3 className="text-base font-medium text-[#1C1A17]">{error}</h3>
              <p className="mt-1 text-sm text-[#504534]">Vui lòng thử lại.</p>
              <button
                type="button"
                onClick={handleRetry}
                className="mt-5 px-5 py-2.5 rounded-lg text-white text-sm font-bold
                           bg-gradient-to-br from-[#C9920A] to-[#A87808]
                           hover:shadow-md transition-all active:scale-95"
              >
                Thử lại
              </button>
            </div>
          )}

          {/* ── Loading skeletons ──────────────────────────────────────── */}
          {loading && (
            <div className="flex flex-col gap-5 mt-4 md:gap-6">
              {Array.from({ length: 3 }).map((_, i) => (
                <DesktopCardSkeleton key={i} />
              ))}
            </div>
          )}

          {/* ── Room cards ─────────────────────────────────────────────── */}
          {!loading && !error && filtered.length > 0 && (
            <div className="flex flex-col gap-5 mt-4 md:gap-6">
              {filtered.map(room => {
                const statusCfg = getStatusConfig(room.status)
                const displayName = room.display_name ?? room.name
                const locationName = room.location?.name ?? null

                return (
                  <article
                    key={room.id}
                    data-testid="room-card"
                    className="group flex flex-col sm:flex-row bg-white rounded-2xl overflow-hidden shadow-sm
                               hover:shadow-xl hover:-translate-y-0.5 transition-all duration-300"
                  >
                    {/* Image: full-width on mobile, fixed 280px on desktop */}
                    <div className="relative w-full aspect-[4/3] sm:w-[280px] sm:h-[200px] sm:aspect-auto flex-shrink-0 bg-[#1A1612]">
                      {room.image_url ? (
                        <img
                          src={room.image_url}
                          alt={displayName}
                          loading="lazy"
                          className="object-cover w-full h-full transition-opacity opacity-80 group-hover:opacity-100"
                        />
                      ) : (
                        <div className="flex items-center justify-center w-full h-full">
                          <BedIcon className="w-10 h-10 text-[#C9920A]" />
                        </div>
                      )}
                      <div className="absolute inset-0 bg-gradient-to-t from-[#1A1612]/60 to-transparent" />
                      {/* Status badge */}
                      <span
                        className={`absolute top-3 left-3 px-2 py-1 text-[10px] font-bold uppercase
                                    rounded-md tracking-wider flex items-center gap-1 ${statusCfg.badgeClass}`}
                      >
                        {room.status === 'available' && (
                          <span className="w-1.5 h-1.5 bg-white rounded-full" />
                        )}
                        {statusCfg.label}
                      </span>
                      {/* Room type badge */}
                      {room.room_type_code && (
                        <span className="absolute top-9 left-3 px-2 py-1 bg-[#C9920A] text-white text-[10px] font-bold uppercase rounded-md tracking-wider">
                          {room.room_type_code}
                        </span>
                      )}
                    </div>

                    {/* Info + price */}
                    <div className="flex flex-col flex-1 min-w-0 p-5 sm:p-6 sm:flex-row sm:justify-between">
                      {/* Info */}
                      <div className="flex flex-col justify-between flex-1 min-w-0 sm:mr-6">
                        <div>
                          <h3 className="text-[17px] font-semibold text-[#1C1A17] mb-1 truncate">
                            {displayName}
                          </h3>
                          {locationName && (
                            <div className="flex items-center gap-1 text-xs text-[#504534] font-medium">
                              <LocationIcon />
                              <span>Soleil Hostel • {locationName}</span>
                            </div>
                          )}
                          <div className="flex gap-4 mt-3 text-[13px] text-[#504534]">
                            {room.max_guests != null && (
                              <div className="flex items-center gap-1.5">
                                <PeopleIcon />
                                <span>Tối đa {room.max_guests} khách</span>
                              </div>
                            )}
                            <div className="flex items-center gap-1.5">
                              <NightIcon />
                              <span>Từ 1 đêm</span>
                            </div>
                          </div>
                          {room.description && (
                            <p className="mt-3 text-[13px] text-[#504534] leading-5 line-clamp-2">
                              {room.description}
                            </p>
                          )}
                        </div>
                      </div>

                      {/* Price / CTA */}
                      <div
                        className="flex flex-row sm:flex-col items-center sm:items-end justify-between sm:justify-center
                                    mt-4 sm:mt-0 sm:w-[190px] sm:flex-shrink-0 sm:border-l sm:border-[#D4C4AE]/20 sm:pl-6
                                    pt-4 sm:pt-0 border-t sm:border-t-0 border-[#D4C4AE]/20 sm:text-right"
                      >
                        <div>
                          <p className="text-xs text-[#827562] font-bold uppercase tracking-widest mb-1 hidden sm:block">
                            Giá chỉ từ
                          </p>
                          <div data-testid="room-price">
                            <span className="text-xl sm:text-2xl font-bold text-[#C9920A]">
                              {formatVND(room.price)}
                            </span>
                          </div>
                          <p className="text-xs text-[#504534]">/đêm</p>
                        </div>
                        <div className="flex flex-col items-end gap-2 sm:mt-4 sm:w-full">
                          {room.status === 'available' && (
                            <button
                              type="button"
                              onClick={() => navigate(`/booking?room_id=${room.id}`)}
                              className="px-5 sm:w-full py-2.5 sm:py-3 rounded-lg font-bold text-sm text-white
                                         bg-gradient-to-br from-[#C9920A] to-[#A87808]
                                         hover:shadow-md transition-all active:scale-95"
                            >
                              Đặt ngay
                            </button>
                          )}
                          <button
                            type="button"
                            className="hidden sm:block text-xs font-bold text-[#504534] hover:text-[#C9920A] transition-colors"
                          >
                            Xem chi tiết →
                          </button>
                        </div>
                      </div>
                    </div>
                  </article>
                )
              })}
            </div>
          )}

          {/* ── Empty state ─────────────────────────────────────────────── */}
          {!loading && !error && filtered.length === 0 && (
            <div className="flex flex-col items-center justify-center max-w-xs py-20 mx-auto text-center">
              {activeFilter === 'all' && rooms.length === 0 ? (
                <>
                  <BedIcon className="w-12 h-12 text-[#D4C4AE] mb-4" />
                  <h3 className="text-base font-medium text-[#1C1A17]">Hiện chưa có phòng nào</h3>
                  <p className="mt-1 text-sm text-[#504534]">
                    Vui lòng thử lại sau hoặc liên hệ với chúng tôi.
                  </p>
                </>
              ) : (
                <>
                  <div className="mb-6 w-24 h-24 bg-[#F6ECE5] rounded-full flex items-center justify-center">
                    <svg
                      className="w-12 h-12 text-[#C9920A]"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      strokeWidth="1.2"
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      aria-hidden="true"
                    >
                      <circle cx="11" cy="11" r="8" />
                      <path d="M21 21l-4.35-4.35" />
                    </svg>
                  </div>
                  <h2 className="font-serif italic text-xl text-[#1C1A17] mb-2">
                    Không tìm thấy phòng phù hợp
                  </h2>
                  <p className="text-[#504534] text-sm mb-8 leading-relaxed">
                    Thử điều chỉnh bộ lọc hoặc chọn loại phòng khác.
                  </p>
                  <button
                    type="button"
                    onClick={() => setActiveFilter('all')}
                    className="px-10 py-3 border-2 border-[#C9920A] text-[#C9920A] rounded-lg
                               text-xs font-bold tracking-[0.2em] uppercase
                               hover:bg-[#C9920A] hover:text-white transition-all duration-300"
                  >
                    Xóa bộ lọc
                  </button>
                </>
              )}
            </div>
          )}
        </section>
      </div>
    </div>
  )
}

export default RoomList
