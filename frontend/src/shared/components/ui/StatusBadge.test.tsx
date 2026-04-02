import { describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import {
  BookingStatusBadge,
  RoomStatusBadge,
  RoomReadinessBadge,
  StayStatusDot,
  BOOKING_STATUS_CONFIG,
  ROOM_STATUS_CONFIG,
  ROOM_READINESS_CONFIG,
  STAY_STATUS_CONFIG,
  type BookingStatus,
  type RoomStatus,
  type RoomReadinessStatus,
  type StayStatus,
} from './StatusBadge'

// ─── BookingStatusBadge ───────────────────────────────────────────────────────

describe('BookingStatusBadge', () => {
  const statuses: BookingStatus[] = [
    'pending',
    'confirmed',
    'cancelled',
    'refund_pending',
    'refund_failed',
  ]

  it.each(statuses)('renders correct Vietnamese label for "%s"', status => {
    render(<BookingStatusBadge status={status} />)
    const expected = BOOKING_STATUS_CONFIG[status].label
    expect(screen.getByText(expected)).toBeTruthy()
  })

  it('renders as a <span> element', () => {
    const { container } = render(<BookingStatusBadge status="pending" />)
    expect(container.querySelector('span')).toBeTruthy()
  })

  it('applies rounded-full pill class', () => {
    const { container } = render(<BookingStatusBadge status="confirmed" />)
    expect(container.firstChild).toHaveClass('rounded-full')
  })

  it('applies amber classes for pending status', () => {
    const { container } = render(<BookingStatusBadge status="pending" />)
    const el = container.firstChild as HTMLElement
    expect(el.className).toContain('amber')
  })

  it('applies green classes for confirmed status', () => {
    const { container } = render(<BookingStatusBadge status="confirmed" />)
    const el = container.firstChild as HTMLElement
    expect(el.className).toContain('green')
  })

  it('applies gray classes for cancelled status', () => {
    const { container } = render(<BookingStatusBadge status="cancelled" />)
    const el = container.firstChild as HTMLElement
    expect(el.className).toContain('gray')
  })

  it('renders unknown status key as label with gray fallback', () => {
    render(<BookingStatusBadge status="totally_unknown" />)
    expect(screen.getByText('totally_unknown')).toBeTruthy()
    const el = screen.getByText('totally_unknown')
    expect(el.className).toContain('gray')
  })

  it('accepts optional className and merges it', () => {
    const { container } = render(
      <BookingStatusBadge status="pending" className="my-custom-class" />
    )
    const el = container.firstChild as HTMLElement
    expect(el.className).toContain('my-custom-class')
  })
})

// ─── RoomStatusBadge ─────────────────────────────────────────────────────────

describe('RoomStatusBadge', () => {
  const statuses: RoomStatus[] = ['available', 'booked', 'maintenance']

  it.each(statuses)('renders correct Vietnamese label for "%s"', status => {
    render(<RoomStatusBadge status={status} />)
    const expected = ROOM_STATUS_CONFIG[status].label
    expect(screen.getByText(expected)).toBeTruthy()
  })

  it('renders "Còn phòng" for available', () => {
    render(<RoomStatusBadge status="available" />)
    expect(screen.getByText('Còn phòng')).toBeTruthy()
  })

  it('renders "Đã đặt" for booked', () => {
    render(<RoomStatusBadge status="booked" />)
    expect(screen.getByText('Đã đặt')).toBeTruthy()
  })

  it('renders "Bảo trì" for maintenance', () => {
    render(<RoomStatusBadge status="maintenance" />)
    expect(screen.getByText('Bảo trì')).toBeTruthy()
  })

  it('applies green classes for available', () => {
    const { container } = render(<RoomStatusBadge status="available" />)
    expect((container.firstChild as HTMLElement).className).toContain('green')
  })

  it('falls back gracefully for unknown status', () => {
    render(<RoomStatusBadge status="unknown_room_status" />)
    expect(screen.getByText('unknown_room_status')).toBeTruthy()
  })
})

// ─── RoomReadinessBadge ───────────────────────────────────────────────────────

describe('RoomReadinessBadge', () => {
  const statuses: RoomReadinessStatus[] = [
    'ready',
    'occupied',
    'dirty',
    'cleaning',
    'inspected',
    'out_of_service',
  ]

  it.each(statuses)('renders correct Vietnamese label for "%s"', status => {
    render(<RoomReadinessBadge status={status} />)
    const expected = ROOM_READINESS_CONFIG[status].label
    expect(screen.getByText(expected)).toBeTruthy()
  })

  it('renders "Sẵn sàng" for ready', () => {
    render(<RoomReadinessBadge status="ready" />)
    expect(screen.getByText('Sẵn sàng')).toBeTruthy()
  })

  it('renders "Ngưng khai thác" for out_of_service', () => {
    render(<RoomReadinessBadge status="out_of_service" />)
    expect(screen.getByText('Ngưng khai thác')).toBeTruthy()
  })

  it('applies red classes for out_of_service', () => {
    const { container } = render(<RoomReadinessBadge status="out_of_service" />)
    expect((container.firstChild as HTMLElement).className).toContain('red')
  })

  it('applies yellow classes for cleaning', () => {
    const { container } = render(<RoomReadinessBadge status="cleaning" />)
    expect((container.firstChild as HTMLElement).className).toContain('yellow')
  })

  it('applies teal classes for inspected', () => {
    const { container } = render(<RoomReadinessBadge status="inspected" />)
    expect((container.firstChild as HTMLElement).className).toContain('teal')
  })

  it('falls back gracefully for unknown readiness status', () => {
    render(<RoomReadinessBadge status="unknown_readiness" />)
    expect(screen.getByText('unknown_readiness')).toBeTruthy()
  })
})

// ─── StayStatusDot ────────────────────────────────────────────────────────────

describe('StayStatusDot', () => {
  const statuses: StayStatus[] = [
    'expected',
    'in_house',
    'late_checkout',
    'checked_out',
    'no_show',
    'relocated',
  ]

  it.each(statuses)('renders dot with aria-label for "%s"', status => {
    render(<StayStatusDot status={status} />)
    const expected = STAY_STATUS_CONFIG[status].label
    const dot = document.querySelector(`[aria-label="${expected}"]`)
    expect(dot).toBeTruthy()
  })

  it('does NOT render label text when showLabel is omitted', () => {
    render(<StayStatusDot status="in_house" />)
    expect(screen.queryByText('Đang lưu trú')).toBeNull()
  })

  it('renders label text when showLabel is true', () => {
    render(<StayStatusDot status="in_house" showLabel />)
    expect(screen.getByText('Đang lưu trú')).toBeTruthy()
  })

  it('renders label text for all statuses when showLabel is true', () => {
    statuses.forEach(status => {
      const { unmount } = render(<StayStatusDot status={status} showLabel />)
      const expected = STAY_STATUS_CONFIG[status].label
      expect(screen.getByText(expected)).toBeTruthy()
      unmount()
    })
  })

  it('applies green dot for in_house', () => {
    const { container } = render(<StayStatusDot status="in_house" />)
    const dot = container.querySelector('[aria-label]') as HTMLElement
    expect(dot.className).toContain('bg-green-500')
  })

  it('applies red dot for no_show', () => {
    const { container } = render(<StayStatusDot status="no_show" />)
    const dot = container.querySelector('[aria-label]') as HTMLElement
    expect(dot.className).toContain('bg-red-500')
  })

  it('applies amber dot for late_checkout', () => {
    const { container } = render(<StayStatusDot status="late_checkout" />)
    const dot = container.querySelector('[aria-label]') as HTMLElement
    expect(dot.className).toContain('bg-amber-500')
  })

  it('applies blue dot for relocated', () => {
    const { container } = render(<StayStatusDot status="relocated" />)
    const dot = container.querySelector('[aria-label]') as HTMLElement
    expect(dot.className).toContain('bg-blue-500')
  })

  it('accepts optional className and applies it to wrapper', () => {
    const { container } = render(<StayStatusDot status="in_house" className="custom-dot-class" />)
    expect((container.firstChild as HTMLElement).className).toContain('custom-dot-class')
  })

  it('dot element has rounded-full class', () => {
    const { container } = render(<StayStatusDot status="checked_out" />)
    const dot = container.querySelector('[aria-label]') as HTMLElement
    expect(dot.className).toContain('rounded-full')
  })
})

// ─── Config map completeness ──────────────────────────────────────────────────

describe('config map completeness', () => {
  it('BOOKING_STATUS_CONFIG has all 5 booking statuses', () => {
    const keys = Object.keys(BOOKING_STATUS_CONFIG)
    expect(keys).toContain('pending')
    expect(keys).toContain('confirmed')
    expect(keys).toContain('cancelled')
    expect(keys).toContain('refund_pending')
    expect(keys).toContain('refund_failed')
    expect(keys).toHaveLength(5)
  })

  it('ROOM_STATUS_CONFIG has all 3 room statuses', () => {
    const keys = Object.keys(ROOM_STATUS_CONFIG)
    expect(keys).toHaveLength(3)
  })

  it('ROOM_READINESS_CONFIG has all 6 readiness statuses', () => {
    const keys = Object.keys(ROOM_READINESS_CONFIG)
    expect(keys).toHaveLength(6)
  })

  it('STAY_STATUS_CONFIG has all 6 stay statuses', () => {
    const keys = Object.keys(STAY_STATUS_CONFIG)
    expect(keys).toContain('expected')
    expect(keys).toContain('in_house')
    expect(keys).toContain('late_checkout')
    expect(keys).toContain('checked_out')
    expect(keys).toContain('no_show')
    expect(keys).toContain('relocated')
    expect(keys).toHaveLength(6)
  })

  it('every BOOKING_STATUS_CONFIG entry has label and cls', () => {
    Object.values(BOOKING_STATUS_CONFIG).forEach(cfg => {
      expect(cfg.label).toBeTruthy()
      expect(cfg.cls).toBeTruthy()
    })
  })

  it('every STAY_STATUS_CONFIG entry has label and dotCls', () => {
    Object.values(STAY_STATUS_CONFIG).forEach(cfg => {
      expect(cfg.label).toBeTruthy()
      expect(cfg.dotCls).toBeTruthy()
    })
  })
})
