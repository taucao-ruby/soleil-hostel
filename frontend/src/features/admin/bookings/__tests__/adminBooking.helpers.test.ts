import { describe, expect, it } from 'vitest'
import {
  buildBookingReference,
  formatShortBookingDate,
  formatAdminBookingAmount,
  getAdminBookingStatusConfig,
  normalizeAdminBookingSearch,
} from '../adminBooking.helpers'
import type { BookingDetailRaw } from '@/features/booking/booking.types'

// ─── Fixtures ────────────────────────────────────────────────────────────────

function makeBooking(overrides: Partial<BookingDetailRaw> = {}): BookingDetailRaw {
  return {
    id: 42,
    room_id: 10,
    user_id: 1,
    check_in: '2026-06-01',
    check_out: '2026-06-03',
    guest_name: 'Nguyễn Văn A',
    guest_email: 'nva@example.com',
    status: 'confirmed',
    status_label: 'Đã xác nhận',
    nights: 2,
    created_at: '2026-05-01T10:00:00+07:00',
    updated_at: '2026-05-01T10:00:00+07:00',
    ...overrides,
  }
}

// ─── buildBookingReference ────────────────────────────────────────────────────

describe('buildBookingReference', () => {
  it('formats as SOL-YYYY-NNNN using created_at year and padded id', () => {
    const booking = makeBooking({ id: 42, created_at: '2026-05-01T10:00:00+07:00' })
    expect(buildBookingReference(booking)).toBe('SOL-2026-0042')
  })

  it('pads id to 4 digits', () => {
    expect(buildBookingReference(makeBooking({ id: 1 }))).toBe('SOL-2026-0001')
    expect(buildBookingReference(makeBooking({ id: 999 }))).toBe('SOL-2026-0999')
  })

  it('does not truncate ids longer than 4 digits', () => {
    expect(buildBookingReference(makeBooking({ id: 12345 }))).toBe('SOL-2026-12345')
  })

  it('extracts the correct year from ISO timestamp', () => {
    const booking = makeBooking({ id: 7, created_at: '2025-12-31T23:59:00+07:00' })
    expect(buildBookingReference(booking)).toBe('SOL-2025-0007')
  })

  it('falls back to current year when created_at is invalid', () => {
    const currentYear = new Date().getFullYear()
    const booking = makeBooking({ id: 5, created_at: 'not-a-date' })
    expect(buildBookingReference(booking)).toBe(`SOL-${currentYear}-0005`)
  })

  it('handles plain date string (YYYY-MM-DD) without time component', () => {
    const booking = makeBooking({ id: 3, created_at: '2026-03-15' })
    expect(buildBookingReference(booking)).toBe('SOL-2026-0003')
  })
})

// ─── formatShortBookingDate ───────────────────────────────────────────────────

describe('formatShortBookingDate', () => {
  // Intl.DateTimeFormat separator varies by environment (jsdom → '-', browser → '/').
  // Tests assert day + month values are present, not the exact separator.

  it('formats YYYY-MM-DD and includes correct day and month digits', () => {
    const result = formatShortBookingDate('2026-06-01')
    expect(result).toMatch(/01/) // day
    expect(result).toMatch(/06/) // month
  })

  it('formats month correctly (zero-padded)', () => {
    const result = formatShortBookingDate('2026-01-05')
    expect(result).toMatch(/05/) // day
    expect(result).toMatch(/01/) // month
  })

  it('handles end of year date', () => {
    const result = formatShortBookingDate('2026-12-31')
    expect(result).toMatch(/31/) // day
    expect(result).toMatch(/12/) // month
  })

  it('returns a non-empty string', () => {
    const result = formatShortBookingDate('2026-06-01')
    expect(typeof result).toBe('string')
    expect(result.length).toBeGreaterThan(0)
  })

  it('handles ISO timestamp (falls back to Date parsing)', () => {
    const result = formatShortBookingDate('2026-06-15T14:30:00+07:00')
    expect(result).toBeTruthy()
    expect(typeof result).toBe('string')
  })
})

// ─── formatAdminBookingAmount ─────────────────────────────────────────────────

describe('formatAdminBookingAmount', () => {
  // formatVND uses Intl.NumberFormat('vi-VN') — Vietnamese locale uses '.' as
  // thousands separator and ',' as decimal. 25000 → '25.000' → '25.000₫'.

  it('formats numeric amount using VND locale and appends ₫', () => {
    const result = formatAdminBookingAmount({ amount: 25000, amount_formatted: undefined })
    expect(result).toContain('₫')
    expect(result).toContain('25')
    // No trailing space before ₫
    expect(result).not.toMatch(/\s₫/)
  })

  it('formats amount of 0 and appends ₫', () => {
    const result = formatAdminBookingAmount({ amount: 0, amount_formatted: undefined })
    expect(result).toContain('₫')
    expect(result).not.toMatch(/\s₫/)
  })

  it('prefers numeric amount over amount_formatted string', () => {
    // When amount is present it always uses formatVND(amount), ignoring amount_formatted
    const result = formatAdminBookingAmount({
      amount: 10000,
      amount_formatted: '$999.00 ₫',
    })
    expect(result).toContain('₫')
    // Must NOT include the fallback value
    expect(result).not.toContain('999')
  })

  it('falls back to amount_formatted when amount is undefined', () => {
    const result = formatAdminBookingAmount({
      amount: undefined,
      amount_formatted: '$50.00 ₫',
    })
    expect(result).toBe('$50.00₫')
  })

  it('strips trailing space before ₫ from amount_formatted', () => {
    const result = formatAdminBookingAmount({
      amount: undefined,
      amount_formatted: '200.00 ₫',
    })
    expect(result).toBe('200.00₫')
  })

  it('returns em dash when both amount and amount_formatted are missing', () => {
    const result = formatAdminBookingAmount({ amount: undefined, amount_formatted: undefined })
    expect(result).toBe('—')
  })
})

// ─── getAdminBookingStatusConfig ──────────────────────────────────────────────

describe('getAdminBookingStatusConfig', () => {
  it('returns config for pending status', () => {
    const cfg = getAdminBookingStatusConfig('pending')
    expect(cfg.label).toBe('Chờ xác nhận')
    expect(cfg.className).toContain('amber')
  })

  it('returns config for confirmed status', () => {
    const cfg = getAdminBookingStatusConfig('confirmed')
    expect(cfg.label).toBe('Đã xác nhận')
    expect(cfg.className).toContain('emerald')
  })

  it('returns config for cancelled status', () => {
    const cfg = getAdminBookingStatusConfig('cancelled')
    expect(cfg.label).toBe('Đã hủy')
    expect(cfg.className).toContain('gray')
  })

  it('returns config for refund_pending status', () => {
    const cfg = getAdminBookingStatusConfig('refund_pending')
    expect(cfg.label).toBe('Hoàn tiền đang xử lý')
    expect(cfg.className).toContain('sky')
  })

  it('returns config for refund_failed status', () => {
    const cfg = getAdminBookingStatusConfig('refund_failed')
    expect(cfg.label).toBe('Hoàn tiền thất bại')
    expect(cfg.className).toContain('rose')
  })

  it('returns fallback config for unknown status', () => {
    const cfg = getAdminBookingStatusConfig('unknown_status')
    expect(cfg.label).toBe('Không xác định')
    expect(cfg.className).toContain('gray')
  })

  it('config className includes border class', () => {
    const cfg = getAdminBookingStatusConfig('pending')
    expect(cfg.className).toContain('border')
  })

  it('config className includes bg class', () => {
    const cfg = getAdminBookingStatusConfig('confirmed')
    expect(cfg.className).toMatch(/bg-/)
  })
})

// ─── normalizeAdminBookingSearch ──────────────────────────────────────────────

describe('normalizeAdminBookingSearch', () => {
  it('returns trimmed string unchanged when not a SOL code', () => {
    expect(normalizeAdminBookingSearch('  Nguyễn Văn A  ')).toBe('Nguyễn Văn A')
  })

  it('extracts numeric booking id from SOL-YYYY-NNNN format', () => {
    expect(normalizeAdminBookingSearch('SOL-2026-0042')).toBe('42')
  })

  it('strips leading zeros from extracted booking id', () => {
    expect(normalizeAdminBookingSearch('SOL-2026-0001')).toBe('1')
  })

  it('handles lower-case sol- prefix (case insensitive)', () => {
    expect(normalizeAdminBookingSearch('sol-2026-0099')).toBe('99')
  })

  it('handles mixed-case SOL code', () => {
    expect(normalizeAdminBookingSearch('Sol-2026-0007')).toBe('7')
  })

  it('returns trimmed value unchanged for partial SOL string', () => {
    expect(normalizeAdminBookingSearch('SOL-2026')).toBe('SOL-2026')
  })

  it('returns trimmed value unchanged for plain number string', () => {
    expect(normalizeAdminBookingSearch('  123  ')).toBe('123')
  })

  it('returns trimmed value unchanged for email search', () => {
    expect(normalizeAdminBookingSearch('guest@example.com')).toBe('guest@example.com')
  })

  it('handles large booking ids without truncation', () => {
    expect(normalizeAdminBookingSearch('SOL-2026-12345')).toBe('12345')
  })

  it('returns trimmed string when id part is not numeric', () => {
    // edge case: malformed code — NaN path
    expect(normalizeAdminBookingSearch('SOL-2026-XXXX')).toBe('SOL-2026-XXXX')
  })
})
