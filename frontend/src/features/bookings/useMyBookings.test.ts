import { describe, it, expect, vi, beforeEach } from 'vitest'
import { act, renderHook, waitFor } from '@testing-library/react'
import type { CancelBookingResponse } from '@/features/booking/booking.types'

const { mockCancelBooking } = vi.hoisted(() => ({
  mockCancelBooking: vi.fn<(id: number) => Promise<CancelBookingResponse>>(),
}))

vi.mock('@/features/booking/booking.api', () => ({
  fetchMyBookings: vi.fn(),
  cancelBooking: mockCancelBooking,
}))

import { useCancelBookingMutation } from './useMyBookings'

beforeEach(() => {
  vi.clearAllMocks()
})

describe('useCancelBookingMutation', () => {
  it('returns the backend-localized 4xx message instead of a generic fallback', async () => {
    const backendMessage = 'Không thể hủy đặt phòng trong vòng 24 giờ trước ngày nhận phòng.'
    mockCancelBooking.mockRejectedValueOnce({
      isAxiosError: true,
      message: 'Request failed with status code 422',
      response: { status: 422, data: { message: backendMessage } },
    })

    const { result } = renderHook(() => useCancelBookingMutation())

    let resolved: { ok: boolean; errorMessage: string | null } | undefined
    await act(async () => {
      resolved = await result.current.cancel(42)
    })

    expect(resolved).toEqual({ ok: false, errorMessage: backendMessage })
    await waitFor(() => {
      expect(result.current.error).toBe(backendMessage)
    })
  })

  it('flattens Laravel validation errors into the surfaced message', async () => {
    mockCancelBooking.mockRejectedValueOnce({
      isAxiosError: true,
      message: 'Request failed with status code 422',
      response: {
        status: 422,
        data: {
          errors: {
            booking: ['Không thể hủy đặt phòng đã chuyển sang trạng thái hoàn tiền.'],
          },
        },
      },
    })

    const { result } = renderHook(() => useCancelBookingMutation())

    let resolved: { ok: boolean; errorMessage: string | null } | undefined
    await act(async () => {
      resolved = await result.current.cancel(7)
    })

    expect(resolved?.ok).toBe(false)
    expect(resolved?.errorMessage).toBe(
      'Không thể hủy đặt phòng đã chuyển sang trạng thái hoàn tiền.'
    )
  })

  it('suppresses unsafe 5xx internals from the surfaced message', async () => {
    mockCancelBooking.mockRejectedValueOnce({
      isAxiosError: true,
      message: 'Request failed with status code 500',
      response: {
        status: 500,
        data: {
          message: 'SQLSTATE[23503]: foreign key violation on table bookings',
        },
      },
    })

    const { result } = renderHook(() => useCancelBookingMutation())

    let resolved: { ok: boolean; errorMessage: string | null } | undefined
    await act(async () => {
      resolved = await result.current.cancel(99)
    })

    expect(resolved?.ok).toBe(false)
    expect(resolved?.errorMessage).not.toMatch(/SQLSTATE/)
    expect(resolved?.errorMessage).not.toMatch(/foreign key/)
    expect(resolved?.errorMessage).toBe('Đã có lỗi xảy ra. Vui lòng thử lại.')
  })

  it('returns the Vietnamese network copy when axios reports no response', async () => {
    mockCancelBooking.mockRejectedValueOnce({
      isAxiosError: true,
      code: 'ERR_NETWORK',
      message: 'Network Error',
    })

    const { result } = renderHook(() => useCancelBookingMutation())

    let resolved: { ok: boolean; errorMessage: string | null } | undefined
    await act(async () => {
      resolved = await result.current.cancel(1)
    })

    expect(resolved?.errorMessage).toBe(
      'Không thể kết nối máy chủ. Vui lòng kiểm tra mạng và thử lại.'
    )
  })

  it('returns the Vietnamese rate-limit copy for 429 regardless of payload', async () => {
    mockCancelBooking.mockRejectedValueOnce({
      isAxiosError: true,
      message: 'Request failed with status code 429',
      response: { status: 429, data: { message: 'Too Many Requests' } },
    })

    const { result } = renderHook(() => useCancelBookingMutation())

    let resolved: { ok: boolean; errorMessage: string | null } | undefined
    await act(async () => {
      resolved = await result.current.cancel(1)
    })

    expect(resolved?.errorMessage).toBe('Bạn thao tác quá nhanh. Vui lòng thử lại sau.')
  })

  it('returns the Vietnamese session-expired copy for 401', async () => {
    mockCancelBooking.mockRejectedValueOnce({
      isAxiosError: true,
      message: 'Request failed with status code 401',
      response: { status: 401, data: { message: 'Unauthenticated.' } },
    })

    const { result } = renderHook(() => useCancelBookingMutation())

    let resolved: { ok: boolean; errorMessage: string | null } | undefined
    await act(async () => {
      resolved = await result.current.cancel(1)
    })

    expect(resolved?.errorMessage).toBe('Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.')
  })

  it('resolves ok=true and clears the error on success', async () => {
    mockCancelBooking.mockResolvedValueOnce({
      success: true,
      message: 'Đã hủy đặt phòng.',
      data: {} as never,
    })

    const { result } = renderHook(() => useCancelBookingMutation())

    let resolved: { ok: boolean; errorMessage: string | null } | undefined
    await act(async () => {
      resolved = await result.current.cancel(1)
    })

    expect(resolved).toEqual({ ok: true, errorMessage: null })
    expect(result.current.error).toBeNull()
  })
})

// ── NEW TESTS APPENDED BY COVERAGE-LIFT PR ──────────────────────────────────

describe('useMyBookingsQuery', () => {
  const RAW_BOOKING: import('@/shared/types/booking.types').BookingApiRaw = {
    id: 101,
    room_id: 7,
    user_id: 3,
    check_in: '2026-07-01',
    check_out: '2026-07-04',
    guest_name: 'Tran Thi B',
    guest_email: 'guest@example.com',
    number_of_guests: 2,
    special_requests: null,
    status: 'confirmed',
    status_label: 'Đã xác nhận',
    nights: 3,
    amount: 1050000,
    payment_policy: 'prepaid',
    payment_status: 'paid',
    room: { id: 7, name: 'Dorm 4 giường' },
    created_at: '2026-06-01T10:00:00Z',
    updated_at: '2026-06-01T10:00:00Z',
  }

  // The module factory above replaces fetchMyBookings with a vi.fn(); reach it
  // through the mocked module so this block stays append-only.
  async function importQuery() {
    const api = await import('@/features/booking/booking.api')
    const { useMyBookingsQuery } = await import('./useMyBookings')
    return { mockFetchMyBookings: vi.mocked(api.fetchMyBookings), useMyBookingsQuery }
  }

  it('starts loading, then maps fetched bookings into view models', async () => {
    const { mockFetchMyBookings, useMyBookingsQuery } = await importQuery()
    mockFetchMyBookings.mockResolvedValueOnce([RAW_BOOKING])

    const { result } = renderHook(() => useMyBookingsQuery())

    expect(result.current.isLoading).toBe(true)
    expect(result.current.bookings).toEqual([])

    await waitFor(() => expect(result.current.isLoading).toBe(false))

    expect(mockFetchMyBookings).toHaveBeenCalledTimes(1)
    expect(mockFetchMyBookings).toHaveBeenCalledWith(expect.any(AbortSignal))
    expect(result.current.isError).toBe(false)
    expect(result.current.bookings).toHaveLength(1)
    expect(result.current.bookings[0]).toMatchObject({
      id: 101,
      roomName: 'Dorm 4 giường',
      nights: 3,
      canCancel: true,
    })
  })

  it('flags isError when the fetch fails for a non-cancellation reason', async () => {
    const { mockFetchMyBookings, useMyBookingsQuery } = await importQuery()
    mockFetchMyBookings.mockRejectedValueOnce(new Error('boom'))

    const { result } = renderHook(() => useMyBookingsQuery())

    await waitFor(() => expect(result.current.isLoading).toBe(false))

    expect(result.current.isError).toBe(true)
    expect(result.current.bookings).toEqual([])
  })

  it('treats DOMException AbortError as a cancellation, not an error', async () => {
    const { mockFetchMyBookings, useMyBookingsQuery } = await importQuery()
    mockFetchMyBookings.mockRejectedValueOnce(
      new DOMException('The operation was aborted.', 'AbortError')
    )

    const { result } = renderHook(() => useMyBookingsQuery())

    await waitFor(() => expect(result.current.isLoading).toBe(false))

    expect(result.current.isError).toBe(false)
  })

  it('treats axios ERR_CANCELED as a cancellation, not an error', async () => {
    const { mockFetchMyBookings, useMyBookingsQuery } = await importQuery()
    mockFetchMyBookings.mockRejectedValueOnce(
      Object.assign(new Error('canceled'), { code: 'ERR_CANCELED' })
    )

    const { result } = renderHook(() => useMyBookingsQuery())

    await waitFor(() => expect(result.current.isLoading).toBe(false))

    expect(result.current.isError).toBe(false)
  })

  it('refetch reloads bookings without an abort signal', async () => {
    const { mockFetchMyBookings, useMyBookingsQuery } = await importQuery()
    mockFetchMyBookings.mockResolvedValueOnce([])

    const { result } = renderHook(() => useMyBookingsQuery())
    await waitFor(() => expect(result.current.isLoading).toBe(false))
    expect(result.current.bookings).toHaveLength(0)

    mockFetchMyBookings.mockResolvedValueOnce([RAW_BOOKING])
    act(() => {
      result.current.refetch()
    })

    await waitFor(() => expect(result.current.bookings).toHaveLength(1))
    expect(mockFetchMyBookings).toHaveBeenCalledTimes(2)
    expect(mockFetchMyBookings).toHaveBeenLastCalledWith(undefined)
  })

  it('aborts the in-flight request and skips state updates after unmount', async () => {
    const { mockFetchMyBookings, useMyBookingsQuery } = await importQuery()
    let resolveFetch!: (value: (typeof RAW_BOOKING)[]) => void
    mockFetchMyBookings.mockImplementationOnce(
      () =>
        new Promise(resolve => {
          resolveFetch = resolve
        })
    )

    const { result, unmount } = renderHook(() => useMyBookingsQuery())
    expect(result.current.isLoading).toBe(true)
    const signal = mockFetchMyBookings.mock.calls[0][0]

    unmount()
    expect(signal?.aborted).toBe(true)

    // Resolving after unmount must not update state (mountedRef guard).
    await act(async () => {
      resolveFetch([RAW_BOOKING])
    })
  })
})

describe('useCancelBookingMutation clearError', () => {
  it('clears the error captured by a failed cancel', async () => {
    mockCancelBooking.mockRejectedValueOnce({
      isAxiosError: true,
      message: 'Request failed with status code 422',
      response: { status: 422, data: { message: 'Không thể hủy đặt phòng này.' } },
    })
    const { useCancelBookingMutation } = await import('./useMyBookings')

    const { result } = renderHook(() => useCancelBookingMutation())

    await act(async () => {
      await result.current.cancel(11)
    })
    expect(result.current.error).toBe('Không thể hủy đặt phòng này.')
    expect(result.current.isPending).toBe(false)

    act(() => {
      result.current.clearError()
    })
    expect(result.current.error).toBeNull()
  })
})
