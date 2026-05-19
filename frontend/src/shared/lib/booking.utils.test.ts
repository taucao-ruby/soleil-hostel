import { describe, it, expect } from 'vitest'
import {
  getStatusConfig,
  formatDateVN,
  formatDateRangeVN,
  formatDateOnly,
  parseDateOnly,
  todayDateOnly,
} from './booking.utils'
import { BOOKING_STATUSES } from '@/shared/types/booking.types'

describe('getStatusConfig', () => {
  it('covers every canonical booking status', () => {
    for (const status of BOOKING_STATUSES) {
      expect(getStatusConfig(status)).toBeDefined()
    }
  })

  it('returns Vietnamese label for known statuses', () => {
    expect(getStatusConfig('pending').label).toBe('Chờ xác nhận')
    expect(getStatusConfig('confirmed').label).toBe('Đã xác nhận')
    expect(getStatusConfig('cancelled').label).toBe('Đã hủy')
    expect(getStatusConfig('refund_pending').label).toBe('Đang hoàn tiền')
    expect(getStatusConfig('refund_failed').label).toBe('Hoàn tiền thất bại')
  })

  it('returns colorClass for known statuses', () => {
    expect(getStatusConfig('pending').colorClass).toContain('bg-yellow-100')
    expect(getStatusConfig('confirmed').colorClass).toContain('bg-green-100')
  })
})

describe('formatDateVN', () => {
  it('formats a date in dd/MM/yyyy pattern', () => {
    const date = new Date(2026, 5, 1) // June 1, 2026
    const result = formatDateVN(date)
    // Intl output may vary slightly, match pattern
    expect(result).toMatch(/01\/06\/2026/)
  })
})

describe('formatDateRangeVN', () => {
  it('formats a check-in/check-out range', () => {
    const checkIn = new Date(2026, 5, 1)
    const checkOut = new Date(2026, 5, 3)
    const result = formatDateRangeVN(checkIn, checkOut)
    expect(result).toContain('—')
    expect(result).toMatch(/01\/06\/2026/)
    expect(result).toMatch(/03\/06\/2026/)
  })
})

describe('formatDateOnly', () => {
  it('formats a YYYY-MM-DD civil date as dd/MM/yyyy', () => {
    expect(formatDateOnly('2026-06-01')).toBe('01/06/2026')
  })

  it('formats without Date or Intl, so the result is timezone-independent', () => {
    // Direct string rearrangement — no dependency on the runtime timezone.
    // The TZ-pinned vitest runs exercise the rendered path end-to-end.
    expect(formatDateOnly('2026-12-31')).toBe('31/12/2026')
    expect(formatDateOnly('2026-01-05')).toBe('05/01/2026')
  })

  it('renders an em dash for nullish or empty input', () => {
    expect(formatDateOnly(null)).toBe('—')
    expect(formatDateOnly(undefined)).toBe('—')
    expect(formatDateOnly('')).toBe('—')
  })

  it('returns non-date-only strings unchanged without throwing', () => {
    expect(formatDateOnly('invalid')).toBe('invalid')
    expect(formatDateOnly('2026-06-01T10:00:00Z')).toBe('2026-06-01T10:00:00Z')
  })

  // Hostile boundary dates exercised under the TZ env var seen at process
  // startup (UTC, Asia/Bangkok, America/Los_Angeles, Pacific/Auckland,
  // Etc/GMT+12). Output must not depend on the runtime timezone — a regression
  // that routes these through `new Date(...)` would shift the day in
  // negative-offset zones.
  it.each([
    ['2026-01-01', '01/01/2026'],
    ['2026-03-08', '08/03/2026'],
    ['2026-03-29', '29/03/2026'],
    ['2026-05-20', '20/05/2026'],
    ['2026-12-31', '31/12/2026'],
  ])('formats hostile boundary date %s as %s', (input, expected) => {
    expect(formatDateOnly(input)).toBe(expected)
  })
})

describe('parseDateOnly', () => {
  it('parses a YYYY-MM-DD string to a UTC-midnight Date', () => {
    const d = parseDateOnly('2026-06-01')
    expect(d).toBeInstanceOf(Date)
    expect(d.toISOString()).toBe('2026-06-01T00:00:00.000Z')
  })

  it('round-trips to the same calendar date when formatted with timeZone: UTC', () => {
    const formatted = new Intl.DateTimeFormat('vi-VN', {
      timeZone: 'UTC',
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
    }).format(parseDateOnly('2026-06-01'))
    expect(formatted).toMatch(/01\/06\/2026/)
  })
})

describe('todayDateOnly', () => {
  it('returns today as a YYYY-MM-DD string', () => {
    expect(todayDateOnly()).toMatch(/^\d{4}-\d{2}-\d{2}$/)
  })
})
