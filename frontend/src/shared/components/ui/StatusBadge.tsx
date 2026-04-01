/* eslint-disable react-refresh/only-export-components */
import React from 'react'

/**
 * StatusBadge — canonical status badge system (PROMPT_SH2).
 *
 * Exports:
 *   BookingStatusBadge   — booking pipeline status (5 states)
 *   RoomStatusBadge      — room availability status (3 states)
 *   RoomReadinessBadge   — housekeeping readiness (6 states)
 *   StayStatusDot        — stay timeline dot + label (6 states)
 *   StatusBadgeGrid      — visual reference grid (dev/docs use)
 *
 * All badge pills: rounded-full border px-2.5 py-0.5 text-xs font-medium inline-flex items-center
 * Design tokens: #C9973A brandAmber | #1C1A17 hueBlack | #E2DDD6 hueBorder
 */

// ─── Types ────────────────────────────────────────────────────────────────────

export type BookingStatus =
  | 'pending'
  | 'confirmed'
  | 'cancelled'
  | 'refund_pending'
  | 'refund_failed'

export type RoomStatus = 'available' | 'booked' | 'maintenance'

export type RoomReadinessStatus =
  | 'ready'
  | 'occupied'
  | 'dirty'
  | 'cleaning'
  | 'inspected'
  | 'out_of_service'

export type StayStatus =
  | 'expected'
  | 'in_house'
  | 'late_checkout'
  | 'checked_out'
  | 'no_show'
  | 'relocated'

// ─── Config maps ──────────────────────────────────────────────────────────────

interface BadgeConfig {
  label: string
  cls: string
}

interface DotConfig {
  label: string
  dotCls: string
}

export const BOOKING_STATUS_CONFIG: Record<BookingStatus, BadgeConfig> = {
  pending: {
    label: 'Chờ xác nhận',
    cls: 'bg-amber-50 text-amber-800 border border-amber-200',
  },
  confirmed: {
    label: 'Đã xác nhận',
    cls: 'bg-green-50 text-green-800 border border-green-200',
  },
  cancelled: {
    label: 'Đã hủy',
    cls: 'bg-gray-100 text-gray-600 border border-gray-200',
  },
  refund_pending: {
    label: 'Hoàn tiền đang xử lý',
    cls: 'bg-blue-50 text-blue-800 border border-blue-200',
  },
  refund_failed: {
    label: 'Hoàn tiền thất bại',
    cls: 'bg-orange-50 text-orange-800 border border-orange-200',
  },
}

export const ROOM_STATUS_CONFIG: Record<RoomStatus, BadgeConfig> = {
  available: {
    label: 'Còn phòng',
    cls: 'bg-green-50 text-green-700 border border-green-200',
  },
  booked: {
    label: 'Đã đặt',
    cls: 'bg-blue-50 text-blue-700 border border-blue-200',
  },
  maintenance: {
    label: 'Bảo trì',
    cls: 'bg-stone-100 text-stone-700 border border-stone-200',
  },
}

export const ROOM_READINESS_CONFIG: Record<RoomReadinessStatus, BadgeConfig> = {
  ready: {
    label: 'Sẵn sàng',
    cls: 'bg-green-50 text-green-700 border border-green-200',
  },
  occupied: {
    label: 'Đang có khách',
    cls: 'bg-blue-50 text-blue-700 border border-blue-200',
  },
  dirty: {
    label: 'Bẩn',
    cls: 'bg-orange-50 text-orange-700 border border-orange-200',
  },
  cleaning: {
    label: 'Đang dọn',
    cls: 'bg-yellow-50 text-yellow-800 border border-yellow-200',
  },
  inspected: {
    label: 'Đã kiểm tra',
    cls: 'bg-teal-50 text-teal-700 border border-teal-200',
  },
  out_of_service: {
    label: 'Ngưng khai thác',
    cls: 'bg-red-50 text-red-700 border border-red-200',
  },
}

export const STAY_STATUS_CONFIG: Record<StayStatus, DotConfig> = {
  expected: {
    label: 'Dự kiến đến',
    dotCls: 'bg-gray-300',
  },
  in_house: {
    label: 'Đang lưu trú',
    dotCls: 'bg-green-500',
  },
  late_checkout: {
    label: 'Trễ trả phòng',
    dotCls: 'bg-amber-500',
  },
  checked_out: {
    label: 'Đã trả phòng',
    dotCls: 'bg-gray-400',
  },
  no_show: {
    label: 'Không đến',
    dotCls: 'bg-red-500',
  },
  relocated: {
    label: 'Chuyển phòng',
    dotCls: 'bg-blue-500',
  },
}

// ─── Shared pill base ─────────────────────────────────────────────────────────

const PILL_BASE = 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium'

// ─── BookingStatusBadge ───────────────────────────────────────────────────────

interface BookingStatusBadgeProps {
  status: string
  className?: string
}

export const BookingStatusBadge: React.FC<BookingStatusBadgeProps> = ({
  status,
  className = '',
}) => {
  const cfg = BOOKING_STATUS_CONFIG[status as BookingStatus] ?? {
    label: status,
    cls: 'bg-gray-100 text-gray-600 border border-gray-200',
  }
  return <span className={`${PILL_BASE} ${cfg.cls} ${className}`.trim()}>{cfg.label}</span>
}

// ─── RoomStatusBadge ─────────────────────────────────────────────────────────

interface RoomStatusBadgeProps {
  status: string
  className?: string
}

export const RoomStatusBadge: React.FC<RoomStatusBadgeProps> = ({ status, className = '' }) => {
  const cfg = ROOM_STATUS_CONFIG[status as RoomStatus] ?? {
    label: status,
    cls: 'bg-gray-100 text-gray-600 border border-gray-200',
  }
  return <span className={`${PILL_BASE} ${cfg.cls} ${className}`.trim()}>{cfg.label}</span>
}

// ─── RoomReadinessBadge ───────────────────────────────────────────────────────

interface RoomReadinessBadgeProps {
  status: string
  className?: string
}

export const RoomReadinessBadge: React.FC<RoomReadinessBadgeProps> = ({
  status,
  className = '',
}) => {
  const cfg = ROOM_READINESS_CONFIG[status as RoomReadinessStatus] ?? {
    label: status,
    cls: 'bg-gray-100 text-gray-600 border border-gray-200',
  }
  return <span className={`${PILL_BASE} ${cfg.cls} ${className}`.trim()}>{cfg.label}</span>
}

// ─── StayStatusDot ────────────────────────────────────────────────────────────

interface StayStatusDotProps {
  status: StayStatus
  /** When true, renders dot + label inline. Default: false (dot only) */
  showLabel?: boolean
  className?: string
}

export const StayStatusDot: React.FC<StayStatusDotProps> = ({
  status,
  showLabel = false,
  className = '',
}) => {
  const cfg = STAY_STATUS_CONFIG[status]
  return (
    <span className={`inline-flex items-center gap-1.5 ${className}`.trim()}>
      <span
        className={`inline-block w-2 h-2 rounded-full flex-shrink-0 ${cfg.dotCls}`}
        aria-label={cfg.label}
      />
      {showLabel && <span className="text-[12px] text-[#6B6760]">{cfg.label}</span>}
    </span>
  )
}

// ─── StatusBadgeGrid — reference sheet ───────────────────────────────────────

/**
 * StatusBadgeGrid — renders a full visual reference for all badge variants.
 * Intended for design docs, Storybook, or a /admin/design-system route.
 */
export const StatusBadgeGrid: React.FC = () => {
  return (
    <div className="space-y-8 p-6 bg-[#F7F3EE] min-h-screen font-sans">
      <h1 className="text-[20px] font-medium text-[#1C1A17]">
        Status Badge Reference — PROMPT_SH2
      </h1>

      {/* ── Booking Status ──────────────────────────────────────────────── */}
      <section>
        <h2 className="text-[13px] font-medium uppercase tracking-wider text-[#6B6760] mb-3">
          Booking Status Badges
        </h2>
        <div className="rounded-2xl border border-[#E2DDD6] bg-white overflow-hidden">
          <table className="w-full text-left text-[13px]">
            <thead>
              <tr className="border-b border-[#E2DDD6]">
                <th className="px-4 py-2.5 text-[12px] font-medium text-[#6B6760] w-40">Key</th>
                <th className="px-4 py-2.5 text-[12px] font-medium text-[#6B6760]">Badge</th>
                <th className="px-4 py-2.5 text-[12px] font-medium text-[#6B6760]">Usage</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[#E2DDD6]">
              {(Object.entries(BOOKING_STATUS_CONFIG) as [BookingStatus, BadgeConfig][]).map(
                ([key]) => (
                  <tr key={key}>
                    <td className="px-4 py-3 font-mono text-[12px] text-[#6B6760]">{key}</td>
                    <td className="px-4 py-3">
                      <BookingStatusBadge status={key} />
                    </td>
                    <td className="px-4 py-3 text-[12px] text-[#6B6760]">
                      {bookingStatusUsage[key]}
                    </td>
                  </tr>
                )
              )}
            </tbody>
          </table>
        </div>
      </section>

      {/* ── Room Status ─────────────────────────────────────────────────── */}
      <section>
        <h2 className="text-[13px] font-medium uppercase tracking-wider text-[#6B6760] mb-3">
          Room Status Badges
        </h2>
        <div className="rounded-2xl border border-[#E2DDD6] bg-white overflow-hidden">
          <table className="w-full text-left text-[13px]">
            <thead>
              <tr className="border-b border-[#E2DDD6]">
                <th className="px-4 py-2.5 text-[12px] font-medium text-[#6B6760] w-40">Key</th>
                <th className="px-4 py-2.5 text-[12px] font-medium text-[#6B6760]">Badge</th>
                <th className="px-4 py-2.5 text-[12px] font-medium text-[#6B6760]">Usage</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[#E2DDD6]">
              {(Object.entries(ROOM_STATUS_CONFIG) as [RoomStatus, BadgeConfig][]).map(([key]) => (
                <tr key={key}>
                  <td className="px-4 py-3 font-mono text-[12px] text-[#6B6760]">{key}</td>
                  <td className="px-4 py-3">
                    <RoomStatusBadge status={key} />
                  </td>
                  <td className="px-4 py-3 text-[12px] text-[#6B6760]">{roomStatusUsage[key]}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>

      {/* ── Room Readiness ──────────────────────────────────────────────── */}
      <section>
        <h2 className="text-[13px] font-medium uppercase tracking-wider text-[#6B6760] mb-3">
          Room Readiness Badges
        </h2>
        <div className="rounded-2xl border border-[#E2DDD6] bg-white overflow-hidden">
          <table className="w-full text-left text-[13px]">
            <thead>
              <tr className="border-b border-[#E2DDD6]">
                <th className="px-4 py-2.5 text-[12px] font-medium text-[#6B6760] w-40">Key</th>
                <th className="px-4 py-2.5 text-[12px] font-medium text-[#6B6760]">Badge</th>
                <th className="px-4 py-2.5 text-[12px] font-medium text-[#6B6760]">Usage</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[#E2DDD6]">
              {(Object.entries(ROOM_READINESS_CONFIG) as [RoomReadinessStatus, BadgeConfig][]).map(
                ([key]) => (
                  <tr key={key}>
                    <td className="px-4 py-3 font-mono text-[12px] text-[#6B6760]">{key}</td>
                    <td className="px-4 py-3">
                      <RoomReadinessBadge status={key} />
                    </td>
                    <td className="px-4 py-3 text-[12px] text-[#6B6760]">
                      {roomReadinessUsage[key]}
                    </td>
                  </tr>
                )
              )}
            </tbody>
          </table>
        </div>
      </section>

      {/* ── Stay Status Dots ────────────────────────────────────────────── */}
      <section>
        <h2 className="text-[13px] font-medium uppercase tracking-wider text-[#6B6760] mb-3">
          Stay Status Dots
        </h2>
        <div className="rounded-2xl border border-[#E2DDD6] bg-white overflow-hidden">
          <table className="w-full text-left text-[13px]">
            <thead>
              <tr className="border-b border-[#E2DDD6]">
                <th className="px-4 py-2.5 text-[12px] font-medium text-[#6B6760] w-40">Key</th>
                <th className="px-4 py-2.5 text-[12px] font-medium text-[#6B6760]">Dot + Label</th>
                <th className="px-4 py-2.5 text-[12px] font-medium text-[#6B6760]">Usage</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[#E2DDD6]">
              {(Object.entries(STAY_STATUS_CONFIG) as [StayStatus, DotConfig][]).map(([key]) => (
                <tr key={key}>
                  <td className="px-4 py-3 font-mono text-[12px] text-[#6B6760]">{key}</td>
                  <td className="px-4 py-3">
                    <StayStatusDot status={key} showLabel />
                  </td>
                  <td className="px-4 py-3 text-[12px] text-[#6B6760]">{stayStatusUsage[key]}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>
    </div>
  )
}

// ─── Usage context strings (for reference grid) ───────────────────────────────

const bookingStatusUsage: Record<BookingStatus, string> = {
  pending: 'Đặt phòng chờ xác nhận từ admin',
  confirmed: 'Đã xác nhận, khách sẽ đến',
  cancelled: 'Đã hủy bởi khách hoặc admin',
  refund_pending: 'Đã hủy, đang xử lý hoàn tiền',
  refund_failed: 'Hoàn tiền thất bại, cần xử lý thủ công',
}

const roomStatusUsage: Record<RoomStatus, string> = {
  available: 'Phòng có thể đặt ngay',
  booked: 'Đã có đặt phòng trong khoảng ngày này',
  maintenance: 'Không thể đặt, đang bảo trì',
}

const roomReadinessUsage: Record<RoomReadinessStatus, string> = {
  ready: 'Đã dọn dẹp, sẵn sàng đón khách',
  occupied: 'Khách đang ở trong phòng',
  dirty: 'Cần dọn dẹp sau khi khách trả phòng',
  cleaning: 'Nhân viên đang dọn phòng',
  inspected: 'Đã kiểm tra sau dọn, chờ xác nhận ready',
  out_of_service: 'Tạm ngưng, không dùng trong hoạt động',
}

const stayStatusUsage: Record<StayStatus, string> = {
  expected: 'Đặt phòng confirmed, chưa đến ngày check-in',
  in_house: 'Khách đang lưu trú (check-in ≤ now < check-out)',
  late_checkout: 'Quá giờ check-out, khách vẫn còn trong phòng',
  checked_out: 'Đã trả phòng hoặc kỳ lưu trú đã kết thúc',
  no_show: 'Cancelled sau khi qua ngày check-in',
  relocated: 'Đã chuyển sang phòng khác',
}

// ─── Default export ───────────────────────────────────────────────────────────

export default BookingStatusBadge
