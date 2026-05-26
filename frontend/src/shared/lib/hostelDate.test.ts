import { describe, expect, it } from 'vitest'
import {
  getHostelToday,
  getHostelTomorrow,
  isDateBeforeHostelToday,
  isDateOnOrBeforeHostelToday,
} from './hostelDate'

describe('hostelDate', () => {
  const utcPreviousDayWindow = new Date('2026-05-25T17:30:00.000Z')

  it('returns Vietnam today during the UTC previous-day window', () => {
    expect(getHostelToday(utcPreviousDayWindow)).toBe('2026-05-26')
  })

  it('derives tomorrow from the hostel-local date', () => {
    expect(getHostelTomorrow(utcPreviousDayWindow)).toBe('2026-05-27')
  })

  it('compares date-only strings against hostel-local today', () => {
    expect(isDateBeforeHostelToday('2026-05-25', utcPreviousDayWindow)).toBe(true)
    expect(isDateBeforeHostelToday('2026-05-26', utcPreviousDayWindow)).toBe(false)
    expect(isDateOnOrBeforeHostelToday('2026-05-26', utcPreviousDayWindow)).toBe(true)
  })
})
