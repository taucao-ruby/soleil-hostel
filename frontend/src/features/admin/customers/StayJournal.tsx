import React from 'react'
import type { BookingDetailRaw } from '@/features/booking/booking.types'
import type { BookingApiRaw } from '@/shared/types/booking.types'
import { STAY_STATUS_CONFIG, type StayStatus } from '@/shared/components/ui/StatusBadge'

interface StayJournalProps {
  bookings: BookingDetailRaw[]
}

function deriveStayStatus(booking: BookingApiRaw): StayStatus {
  const now = new Date()
  const checkIn = new Date(booking.check_in)
  const checkOut = new Date(booking.check_out)

  if (booking.status === 'cancelled') {
    // Cancelled after check-in date passed ≈ no-show
    return checkIn <= now ? 'no_show' : 'checked_out'
  }
  if (
    (booking.status === 'confirmed' || booking.status === 'pending') &&
    now >= checkIn &&
    now < checkOut
  ) {
    return 'in_house'
  }
  if (booking.status === 'confirmed' && now < checkIn) {
    return 'expected'
  }
  return 'checked_out'
}

// ─── Date helpers ─────────────────────────────────────────────────────────────

function fmtDate(iso: string): string {
  if (!iso) return '---'
  try {
    const d = new Date(iso.split('T')[0] + 'T00:00:00')
    return `${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}`
  } catch {
    return iso
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
                  {fmtDate(booking.check_in)}
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
