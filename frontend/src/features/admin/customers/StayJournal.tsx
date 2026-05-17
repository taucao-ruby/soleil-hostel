import React from 'react'
import {
  assertNever,
  type BookingApiRaw,
  type BookingDetailRaw,
} from '@/shared/types/booking.types'
import { STAY_STATUS_CONFIG, type StayStatus } from '@/shared/components/ui/StatusBadge'
import { formatDateOnly, todayDateOnly } from '@/shared/lib/booking.utils'

interface StayJournalProps {
  bookings: BookingDetailRaw[]
}

// check_in/check_out are zoneless civil dates — compared as YYYY-MM-DD strings
// against today's civil date so stay-status derivation stays stable across
// timezones. Routing them through `new Date()` anchors them to UTC midnight and
// skews the comparison in off-UTC zones.
function deriveStayStatus(booking: BookingApiRaw): StayStatus {
  const today = todayDateOnly()
  const checkIn = booking.check_in
  const checkOut = booking.check_out

  switch (booking.status) {
    case 'cancelled':
    case 'refund_pending':
    case 'refund_failed':
      return checkIn <= today ? 'no_show' : 'checked_out'
    case 'pending':
      return today >= checkIn && today < checkOut ? 'in_house' : 'checked_out'
    case 'confirmed':
      if (today >= checkIn && today < checkOut) return 'in_house'
      if (today < checkIn) return 'expected'
      return 'checked_out'
    default:
      return assertNever(booking.status)
  }
}

// ─── Component ───────────────────────────────────────────────────────────────

/**
 * StayJournal — vertical timeline of a guest's stay history.
 *
 * Spec (PROMPT_5C):
 *   - border-l-2 border-gray-200 vertical line on left
 *   - Each node: 6px circle dot (colored by stay status) + content right
 *   - Date bold 13px · Room 13px · Duration muted
 *   - Stay status: in_house → green | checked_out → gray | no_show → red | relocated → blue
 */
const StayJournal: React.FC<StayJournalProps> = ({ bookings }) => {
  if (bookings.length === 0) {
    return (
      <div className="rounded-2xl border border-[#E2DDD6] bg-white p-8 text-center text-[13px] text-[#6B6760]">
        Chưa có nhật ký lưu trú.
      </div>
    )
  }

  return (
    <div className="rounded-2xl border border-[#E2DDD6] bg-white px-4 py-4">
      {/* Timeline container — vertical line is border-l on the inner list */}
      <ul className="relative border-l-2 border-gray-200 ml-2 space-y-0">
        {bookings.map((booking, idx) => {
          const stayStatus = deriveStayStatus(booking)
          const dot = STAY_STATUS_CONFIG[stayStatus]
          const roomName = booking.room?.name ?? '---'
          const nights = booking.nights ?? 1
          const isLast = idx === bookings.length - 1

          return (
            <li key={booking.id} className={`relative pl-5 ${isLast ? 'pb-0' : 'pb-5'}`}>
              {/* Timeline dot — 6px circle, positioned on the vertical line */}
              <span
                aria-label={dot.label}
                className={`absolute -left-[5px] top-[5px] w-2.5 h-2.5 rounded-full border-2 border-white ${dot.dotCls}`}
              />

              {/* Content */}
              <div>
                {/* Date row */}
                <p className="text-[13px] font-medium text-[#1C1A17] leading-snug">
                  {formatDateOnly(booking.check_in)}
                </p>

                {/* Room name */}
                <p className="text-[13px] text-[#1C1A17] mt-0.5">{roomName}</p>

                {/* Duration + stay status */}
                <div className="flex items-center gap-2 mt-0.5">
                  <span className="text-[12px] text-[#6B6760]">{nights} đêm</span>
                  <span
                    className={`inline-block w-1.5 h-1.5 rounded-full ${dot.dotCls}`}
                    aria-hidden="true"
                  />
                  <span className="text-[12px] text-[#6B6760]">{dot.label}</span>
                </div>
              </div>
            </li>
          )
        })}
      </ul>
    </div>
  )
}

export default StayJournal
