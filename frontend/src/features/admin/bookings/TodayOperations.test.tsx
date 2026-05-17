import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { act, render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import TodayOperations from './TodayOperations'
import type { BookingDetailRaw } from '@/shared/types/booking.types'

const { mockGetLocations, mockGetTodayArrivals, mockGetTodayDepartures, mockApiGet, mockApiPatch } =
  vi.hoisted(() => ({
    mockGetLocations: vi.fn(),
    mockGetTodayArrivals: vi.fn(),
    mockGetTodayDepartures: vi.fn(),
    mockApiGet: vi.fn(),
    mockApiPatch: vi.fn(),
  }))

vi.mock('@/shared/lib/location.api', () => ({
  getLocations: mockGetLocations,
}))

vi.mock('./adminBooking.api', () => ({
  getTodayArrivals: mockGetTodayArrivals,
  getTodayDepartures: mockGetTodayDepartures,
}))

vi.mock('@/shared/lib/api', () => ({
  default: {
    get: mockApiGet,
    patch: mockApiPatch,
  },
}))

vi.mock('@/shared/utils/toast', () => ({
  showToast: {
    success: vi.fn(),
    error: vi.fn(),
  },
}))

const arrival: BookingDetailRaw = {
  id: 101,
  room_id: 7,
  user_id: 23,
  check_in: '2026-04-29',
  check_out: '2026-04-30',
  guest_name: 'Nguyen Van A',
  guest_email: 'guest@example.com',
  status: 'confirmed',
  status_label: 'Confirmed',
  nights: 1,
  amount: 900000,
  amount_formatted: '900.000₫',
  room: {
    id: 7,
    name: 'Superior 7',
    display_name: 'Superior 7',
    room_number: 'A7',
    max_guests: 2,
    price: 900000,
  },
  created_at: '2026-04-01T08:00:00Z',
  updated_at: '2026-04-01T08:00:00Z',
}

const roomResponse = {
  data: {
    data: {
      id: 7,
      location_id: 1,
      name: 'Superior 7',
      description: 'Quiet room',
      price: 900000,
      max_guests: 2,
      status: 'available',
      readiness_status: 'ready',
      room_type_code: 'superior',
      room_tier: 2,
      lock_version: 9,
    },
  },
}

function renderTodayOperations() {
  return render(
    <MemoryRouter>
      <TodayOperations />
    </MemoryRouter>
  )
}

beforeEach(() => {
  vi.clearAllMocks()
  mockGetLocations.mockResolvedValue([{ id: 1, name: 'Soleil Da Nang' }])
  mockGetTodayArrivals.mockResolvedValue([arrival])
  mockGetTodayDepartures.mockResolvedValue([])
  mockApiGet.mockResolvedValue(roomResponse)
  mockApiPatch.mockResolvedValue(roomResponse)
})

afterEach(() => {
  vi.useRealTimers()
})

describe('TodayOperations', () => {
  it('aborts the location fetch on unmount without setting state afterward', async () => {
    vi.useFakeTimers()
    let capturedSignal: AbortSignal | undefined
    const consoleErrorSpy = vi.spyOn(console, 'error').mockImplementation(() => undefined)

    mockGetLocations.mockImplementation((signal?: AbortSignal) => {
      capturedSignal = signal
      return new Promise(resolve => {
        window.setTimeout(() => resolve([{ id: 1, name: 'Soleil Da Nang' }]), 50)
      })
    })

    const { unmount } = renderTodayOperations()

    unmount()

    expect(capturedSignal?.aborted).toBe(true)

    await act(async () => {
      await vi.advanceTimersByTimeAsync(50)
    })

    expect(consoleErrorSpy).not.toHaveBeenCalled()
    consoleErrorSpy.mockRestore()
  })

  it('calls the live room patch route without the deprecated /status suffix', async () => {
    const user = userEvent.setup()
    mockApiPatch.mockImplementation((url: string) => {
      if (url.endsWith('/status')) {
        return Promise.reject(
          Object.assign(new Error('Unhandled route'), { response: { status: 404 } })
        )
      }

      return Promise.resolve(roomResponse)
    })

    renderTodayOperations()

    await user.click(await screen.findByRole('button', { name: 'Check-in (Đã đến)' }))

    await waitFor(() => {
      expect(mockApiPatch).toHaveBeenCalledWith(
        '/v1/rooms/7',
        expect.objectContaining({ readiness_status: 'occupied' })
      )
    })
    expect(mockApiPatch).not.toHaveBeenCalledWith(
      '/v1/rooms/7/status',
      expect.objectContaining({ readiness_status: 'occupied' })
    )
  })

  it('does not send lock_version when updating room readiness', async () => {
    const user = userEvent.setup()
    renderTodayOperations()

    await user.click(await screen.findByRole('button', { name: 'Check-in (Đã đến)' }))

    await waitFor(() => {
      expect(mockApiPatch).toHaveBeenCalled()
    })

    const [, payload] = mockApiPatch.mock.calls[0] as [string, Record<string, unknown>]
    expect(payload).not.toHaveProperty('lock_version')
    expect(payload).toMatchObject({ readiness_status: 'occupied' })
  })
})
