import { describe, it, expect } from 'vitest'
import { getStatusConfig, formatDateVN, formatDateRangeVN } from './booking.utils'
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
