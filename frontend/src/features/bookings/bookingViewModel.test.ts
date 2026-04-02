import { describe, it, expect } from 'vitest'
import { toBookingViewModel, isUpcoming, isPast } from './bookingViewModel'
import type { BookingApiRaw } from '@/features/booking/booking.types'

function makeRaw(overrides: Partial<BookingApiRaw> = {}): BookingApiRaw {
  return {
    id: 1,
    room_id: 10,
    user_id: 5,
    check_in: '2026-06-01',
    check_out: '2026-06-03',
    guest_name: 'Alice',
    guest_email: 'alice@example.com',
    status: 'pending',
    status_label: 'Pending',
    nights: 2,
    room: {
      id: 10,
      name: 'Dormitory 4 giường',
      display_name: 'Phòng Dormitory 4 giường',
    },
    created_at: '2026-05-20T10:00:00+00:00',
    updated_at: '2026-05-20T10:00:00+00:00',
    ...overrides,
  }
}

describe('toBookingViewModel', () => {
  it('maps pending status to canCancel=true', () => {
    const vm = toBookingViewModel(makeRaw({ status: 'pending' }))
    expect(vm.canCancel).toBe(true)
  })

  it('maps confirmed status to canCancel=true', () => {
    const vm = toBookingViewModel(makeRaw({ status: 'confirmed' }))
    expect(vm.canCancel).toBe(true)
  })

  it('maps cancelled status to canCancel=false', () => {
    const vm = toBookingViewModel(makeRaw({ status: 'cancelled' }))
    expect(vm.canCancel).toBe(false)
  })

  it('maps refund_pending status to canCancel=false', () => {
    const vm = toBookingViewModel(makeRaw({ status: 'refund_pending' }))
    expect(vm.canCancel).toBe(false)
  })

  it('parses check_in and check_out as Date objects', () => {
    const vm = toBookingViewModel(makeRaw({ check_in: '2026-06-01', check_out: '2026-06-03' }))
    expect(vm.checkIn).toBeInstanceOf(Date)
    expect(vm.checkOut).toBeInstanceOf(Date)
  })

  it('uses status as fallback when status_label is null', () => {
    const vm = toBookingViewModel(makeRaw({ status: 'pending', status_label: null }))
    expect(vm.statusLabel).toBe('pending')
  })

  it('prefers the room display_name when available', () => {
    const vm = toBookingViewModel(makeRaw())
    expect(vm.roomName).toBe('Phòng Dormitory 4 giường')
  })

  it('falls back to a generated room label when room data is absent', () => {
    const raw = makeRaw()
    delete raw.room
    const vm = toBookingViewModel(raw)
    expect(vm.roomName).toBe('Phòng #10')
  })

  it('includes amount_formatted when present', () => {
    const vm = toBookingViewModel(makeRaw({ amount: 5000, amount_formatted: '50.000 ₫' }))
    expect(vm.amountFormatted).toBe('50.000₫')
  })

  it('leaves amountFormatted undefined when not present', () => {
    const raw = makeRaw()
    delete raw.amount_formatted
    const vm = toBookingViewModel(raw)
    expect(vm.amountFormatted).toBeUndefined()
  })

  it('formats amount when only numeric amount is present', () => {
    const vm = toBookingViewModel(makeRaw({ amount: 1050000, amount_formatted: undefined }))
    expect(vm.amountFormatted).toBe('1.050.000₫')
  })
})

describe('isUpcoming', () => {
  it('returns true when checkIn is in the future', () => {
    const vm = toBookingViewModel(makeRaw({ check_in: '2099-01-01', check_out: '2099-01-03' }))
    expect(isUpcoming(vm)).toBe(true)
  })

  it('returns false when checkIn is in the past', () => {
    const vm = toBookingViewModel(makeRaw({ check_in: '2020-01-01', check_out: '2020-01-03' }))
    expect(isUpcoming(vm)).toBe(false)
  })
})

describe('isPast', () => {
  it('returns true when checkOut is in the past', () => {
    const vm = toBookingViewModel(makeRaw({ check_in: '2020-01-01', check_out: '2020-01-03' }))
    expect(isPast(vm)).toBe(true)
  })

  it('returns false when checkOut is in the future', () => {
    const vm = toBookingViewModel(makeRaw({ check_in: '2099-01-01', check_out: '2099-01-03' }))
    expect(isPast(vm)).toBe(false)
  })
})
