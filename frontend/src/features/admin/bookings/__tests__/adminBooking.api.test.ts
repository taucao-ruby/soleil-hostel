import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { BookingStatus } from '@/shared/types/booking.types'

const { mockGet } = vi.hoisted(() => ({
  mockGet: vi.fn(),
}))

vi.mock('@/shared/lib/api', () => ({
  default: {
    get: mockGet,
  },
}))

import { getAllBookings, getTodayArrivals, getTodayDepartures } from '../adminBooking.api'

// Minimal raw booking as it arrives on the wire (status is `unknown` until parsed).
function makeRawBooking(overrides: Record<string, unknown> = {}) {
  return {
    id: 101,
    room_id: 7,
    user_id: 23,
    check_in: '2026-06-15',
    check_out: '2026-06-17',
    guest_name: 'Nguyen Van A',
    guest_email: 'guest@example.com',
    number_of_guests: null,
    special_requests: null,
    status: 'confirmed' as BookingStatus,
    status_label: 'Confirmed',
    nights: 2,
    payment_policy: 'prepaid',
    payment_status: 'paid',
    created_at: '2026-04-01T08:00:00Z',
    updated_at: '2026-04-01T08:00:00Z',
    ...overrides,
  }
}

const meta = { current_page: 1, last_page: 1, per_page: 50, total: 1 }

// GET /v1/admin/bookings → ApiResponse envelope wrapping the paginated payload:
// { data: { data: { bookings, meta } } }
function adminBookingsResponse(bookings: unknown[]) {
  return { data: { data: { bookings, meta } } }
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('getAllBookings', () => {
  it('unwraps data.data into a normalized { bookings, meta } with a validated status', async () => {
    mockGet.mockResolvedValue(adminBookingsResponse([makeRawBooking()]))

    const result = await getAllBookings({ location_id: 1 })

    expect(Array.isArray(result.bookings)).toBe(true)
    expect(result.bookings).toHaveLength(1)
    expect(result.bookings[0].id).toBe(101)
    expect(result.bookings[0].status).toBe('confirmed')
    expect(result.meta).toEqual(meta)
  })

  it('forwards the filters as params and threads the abort signal through', async () => {
    mockGet.mockResolvedValue(adminBookingsResponse([]))
    const controller = new AbortController()

    await getAllBookings({ location_id: 5, status: 'pending' }, controller.signal)

    expect(mockGet).toHaveBeenCalledWith('/v1/admin/bookings', {
      params: { location_id: 5, status: 'pending' },
      signal: controller.signal,
    })
  })

  it('throws when the wire status is not a known booking status', async () => {
    mockGet.mockResolvedValue(adminBookingsResponse([makeRawBooking({ status: 'bogus' })]))

    await expect(getAllBookings()).rejects.toThrow(/Unknown booking status/)
  })
})

// F-53 regression guard: these endpoints return { bookings, meta } (not a bare
// array), so the helper MUST read response.data.data.bookings. A shallow read
// returns undefined at runtime; these tests assert a real array comes back.
describe('getTodayArrivals (endpoint returns { bookings, meta })', () => {
  it('extracts the bookings array from data.data.bookings', async () => {
    mockGet.mockResolvedValue(adminBookingsResponse([makeRawBooking({ id: 201 })]))

    const result = await getTodayArrivals(3)

    expect(Array.isArray(result)).toBe(true)
    expect(result).toHaveLength(1)
    expect(result[0].id).toBe(201)
    expect(result[0].status).toBe('confirmed')
  })

  it('queries /v1/admin/bookings filtered to confirmed check-ins and forwards the signal', async () => {
    mockGet.mockResolvedValue(adminBookingsResponse([]))
    const controller = new AbortController()

    await getTodayArrivals(3, controller.signal)

    expect(mockGet).toHaveBeenCalledWith(
      '/v1/admin/bookings',
      expect.objectContaining({
        params: expect.objectContaining({ location_id: 3, status: 'confirmed' }),
        signal: controller.signal,
      })
    )
  })
})

describe('getTodayDepartures (endpoint returns { bookings, meta })', () => {
  it('extracts the bookings array from data.data.bookings', async () => {
    mockGet.mockResolvedValue(adminBookingsResponse([makeRawBooking({ id: 202 })]))

    const result = await getTodayDepartures(3)

    expect(Array.isArray(result)).toBe(true)
    expect(result).toHaveLength(1)
    expect(result[0].id).toBe(202)
  })

  it('queries /v1/admin/bookings filtered by check-out window and confirmed status', async () => {
    mockGet.mockResolvedValue(adminBookingsResponse([]))

    await getTodayDepartures(9)

    expect(mockGet).toHaveBeenCalledWith(
      '/v1/admin/bookings',
      expect.objectContaining({
        params: expect.objectContaining({ location_id: 9, status: 'confirmed' }),
      })
    )
  })
})
