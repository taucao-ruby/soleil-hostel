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
