import { describe, it, expect } from 'vitest'
import {
  MAX_STAY_DAYS,
  validateBookingForm,
  calculateNights,
  formatDateForInput,
  getMinCheckOutDate,
  getMaxCheckOutDate,
} from './booking.validation'

describe('Booking Validation', () => {
  const validData = {
    room_id: 1,
    guest_name: 'John Doe',
    guest_email: 'john@example.com',
    check_in: getFutureDateStr(1),
    check_out: getFutureDateStr(3),
    number_of_guests: 2,
  }

  /** Helper: returns YYYY-MM-DD string N days in the future */
  function getFutureDateStr(days: number): string {
    const d = new Date()
    d.setDate(d.getDate() + days)
    return formatDateForInput(d)
  }

  function getPastDateStr(days: number): string {
    const d = new Date()
    d.setDate(d.getDate() - days)
    return formatDateForInput(d)
  }

  describe('validateBookingForm', () => {
    it('returns no errors for valid data', () => {
      const errors = validateBookingForm(validData)
      expect(Object.keys(errors)).toHaveLength(0)
    })

    // Room selection
    it('requires a room selection', () => {
      const errors = validateBookingForm({ ...validData, room_id: null })
      expect(errors.room_id).toBe('Vui lòng chọn phòng')
    })

    it('rejects room_id of 0', () => {
      const errors = validateBookingForm({ ...validData, room_id: 0 })
      expect(errors.room_id).toBe('Vui lòng chọn phòng')
    })

    // Guest name
    it('requires guest name', () => {
      const errors = validateBookingForm({ ...validData, guest_name: '' })
      expect(errors.guest_name).toBe('Vui lòng nhập họ và tên')
    })

    it('requires guest name at least 2 characters', () => {
      const errors = validateBookingForm({ ...validData, guest_name: 'A' })
      expect(errors.guest_name).toBe('Họ và tên phải có ít nhất 2 ký tự')
    })

    // Guest email
    it('requires guest email', () => {
      const errors = validateBookingForm({ ...validData, guest_email: '' })
      expect(errors.guest_email).toBe('Vui lòng nhập địa chỉ email')
    })

    it('validates email format', () => {
      const errors = validateBookingForm({ ...validData, guest_email: 'not-an-email' })
      expect(errors.guest_email).toBe('Email không hợp lệ')
    })

    // Check-in date
    it('requires check-in date', () => {
      const errors = validateBookingForm({ ...validData, check_in: '' })
      expect(errors.check_in).toBe('Vui lòng chọn ngày nhận phòng')
    })

    it('rejects check-in date in the past', () => {
      const errors = validateBookingForm({ ...validData, check_in: getPastDateStr(2) })
      expect(errors.check_in).toBe('Ngày nhận phòng không thể là ngày đã qua')
    })

    // Check-out date
    it('requires check-out date', () => {
      const errors = validateBookingForm({ ...validData, check_out: '' })
      expect(errors.check_out).toBe('Vui lòng chọn ngày trả phòng')
    })

    it('rejects check-out before check-in', () => {
      const errors = validateBookingForm({
        ...validData,
        check_in: getFutureDateStr(5),
        check_out: getFutureDateStr(3),
      })
      expect(errors.check_out).toBe('Ngày trả phòng phải sau ngày nhận phòng')
    })

    it('rejects stay longer than 30 days', () => {
      const errors = validateBookingForm({
        ...validData,
        check_in: getFutureDateStr(1),
        check_out: getFutureDateStr(35),
      })
      expect(errors.check_out).toBe('Thời gian lưu trú tối đa là 30 ngày')
    })

    // Number of guests
    it('requires at least 1 guest', () => {
      const errors = validateBookingForm({ ...validData, number_of_guests: 0 })
      expect(errors.number_of_guests).toBe('Phải có ít nhất 1 khách')
    })

    it('rejects more than 10 guests', () => {
      const errors = validateBookingForm({ ...validData, number_of_guests: 11 })
      expect(errors.number_of_guests).toBe('Tối đa 10 khách mỗi phòng')
    })
  })

  describe('calculateNights', () => {
    it('calculates nights between two dates', () => {
      expect(calculateNights('2026-03-01', '2026-03-04')).toBe(3)
    })

    it('returns 1 for consecutive days', () => {
      expect(calculateNights('2026-03-01', '2026-03-02')).toBe(1)
    })
  })

  describe('formatDateForInput', () => {
    it('formats date as YYYY-MM-DD', () => {
      const date = new Date(2026, 0, 5) // Jan 5, 2026
      expect(formatDateForInput(date)).toBe('2026-01-05')
    })

    it('pads single-digit months and days', () => {
      const date = new Date(2026, 2, 9) // Mar 9, 2026
      expect(formatDateForInput(date)).toBe('2026-03-09')
    })
  })

  describe('getMinCheckOutDate', () => {
    it('returns day after check-in when check-in provided', () => {
      const result = getMinCheckOutDate('2026-03-10')
      expect(result).toBe('2026-03-11')
    })

    it('returns tomorrow when no check-in provided', () => {
      const tomorrow = new Date()
      tomorrow.setDate(tomorrow.getDate() + 1)
      expect(getMinCheckOutDate()).toBe(formatDateForInput(tomorrow))
    })
  })

  describe('getMaxCheckOutDate', () => {
    it('returns max checkout date based on the stay limit', () => {
      const result = getMaxCheckOutDate('2026-03-10')
      expect(result).toBe('2026-04-09')
    })

    it('returns undefined when check-in is missing', () => {
      expect(getMaxCheckOutDate()).toBeUndefined()
    })

    it('exports the configured stay limit', () => {
      expect(MAX_STAY_DAYS).toBe(30)
    })
  })
})
