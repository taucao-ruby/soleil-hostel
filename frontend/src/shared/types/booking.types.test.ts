import { describe, expect, it } from 'vitest'
import {
  BOOKING_STATUSES,
  isCancellableBookingStatus,
  isCancelledBookingStatus,
  isBookingStatus,
  parseBookingStatus,
  parseBookingStatusPayload,
  type BookingApiRaw,
} from './booking.types'

type IsExactlyString<T> = string extends T ? (T extends string ? true : false) : false

const bookingApiRawStatusIsNotString: IsExactlyString<BookingApiRaw['status']> = false

describe('booking status contract', () => {
  it('matches the backend BookingStatus values', () => {
    expect(BOOKING_STATUSES).toEqual([
      'pending',
      'confirmed',
      'refund_pending',
      'cancelled',
      'refund_failed',
    ])
  })

  it('accepts refund_pending and refund_failed as valid BookingStatus values', () => {
    expect(isBookingStatus('refund_pending')).toBe(true)
    expect(isBookingStatus('refund_failed')).toBe(true)
    expect(parseBookingStatus('refund_pending')).toBe('refund_pending')
    expect(parseBookingStatus('refund_failed')).toBe('refund_failed')
  })

  it('rejects unknown booking statuses at the parse boundary', () => {
    expect(() => parseBookingStatus('completed')).toThrow('Unknown booking status: completed')
    expect(() => parseBookingStatusPayload({ id: 1, status: 'totally_unknown' })).toThrow(
      'Unknown booking status: totally_unknown'
    )
  })

  it('keeps BookingApiRaw.status narrowed instead of widened to string', () => {
    expect(bookingApiRawStatusIsNotString).toBe(false)
  })

  it('covers cancellation action groupings for every BookingStatus', () => {
    const cancellable = BOOKING_STATUSES.filter(isCancellableBookingStatus)
    const cancelledLike = BOOKING_STATUSES.filter(isCancelledBookingStatus)

    expect(cancellable).toEqual(['pending', 'confirmed', 'refund_failed'])
    expect(cancelledLike).toEqual(['refund_pending', 'cancelled', 'refund_failed'])
  })
})
