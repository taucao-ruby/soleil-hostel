import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import GuestDashboard from './GuestDashboard'
import { toBookingViewModel } from './bookingViewModel'
import type { BookingApiRaw } from '@/features/booking/booking.types'

// ── Mocks ───────────────────────────────────────────────

const mockRefetch = vi.fn()
const mockCancel = vi.fn<(id: number) => Promise<boolean>>()

const { mockShowToast } = vi.hoisted(() => ({
  mockShowToast: {
    success: vi.fn(),
    error: vi.fn(),
    warning: vi.fn(),
    info: vi.fn(),
    promise: vi.fn(),
  },
}))

vi.mock('./useMyBookings', () => ({
  useMyBookingsQuery: vi.fn(),
  useCancelBookingMutation: vi.fn(),
}))

vi.mock('@/utils/toast', () => ({
  showToast: mockShowToast,
  getErrorMessage: (err: unknown) => (typeof err === 'string' ? err : 'Error'),
}))

import { useMyBookingsQuery, useCancelBookingMutation } from './useMyBookings'

const mockedQuery = vi.mocked(useMyBookingsQuery)
const mockedMutation = vi.mocked(useCancelBookingMutation)

// ── Helpers ─────────────────────────────────────────────

function makeRaw(overrides: Partial<BookingApiRaw> = {}): BookingApiRaw {
  return {
    id: 1,
    room_id: 10,
    user_id: 5,
    check_in: '2099-06-01',
    check_out: '2099-06-03',
    guest_name: 'Alice',
    guest_email: 'alice@example.com',
    status: 'pending',
    status_label: null,
    nights: 2,
    created_at: '2026-05-20T10:00:00+00:00',
    updated_at: '2026-05-20T10:00:00+00:00',
    ...overrides,
  }
}

function toViewModel(raw: BookingApiRaw) {
  return toBookingViewModel(raw)
}

function renderDashboard() {
  return render(
    <MemoryRouter>
      <GuestDashboard />
    </MemoryRouter>
  )
}

// ── Setup ───────────────────────────────────────────────

beforeEach(() => {
  vi.clearAllMocks()
  mockedMutation.mockReturnValue({
    cancel: mockCancel,
    isPending: false,
    error: null,
    clearError: vi.fn(),
  })
})

// ── Tests ───────────────────────────────────────────────

describe('GuestDashboard', () => {
  it('shows loading skeleton when isLoading is true', () => {
    mockedQuery.mockReturnValue({
      bookings: [],
      isLoading: true,
      isError: false,
      refetch: mockRefetch,
    })

    renderDashboard()
    const skeletons = screen.getAllByRole('status')
    expect(skeletons.length).toBeGreaterThanOrEqual(1)
  })

  it('shows error state with retry button', async () => {
    mockedQuery.mockReturnValue({
      bookings: [],
      isLoading: false,
      isError: true,
      refetch: mockRefetch,
    })

    renderDashboard()
    expect(screen.getByText('Không thể tải danh sách đặt phòng.')).toBeInTheDocument()

    const retryBtn = screen.getByRole('button', { name: 'Thử lại' })
    await userEvent.click(retryBtn)
    expect(mockRefetch).toHaveBeenCalledTimes(1)
  })

  it('shows empty state with CTA link', () => {
    mockedQuery.mockReturnValue({
      bookings: [],
      isLoading: false,
      isError: false,
      refetch: mockRefetch,
    })

    renderDashboard()
    expect(screen.getByText('Bạn chưa có đặt phòng nào.')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Đặt phòng ngay' })).toHaveAttribute('href', '/rooms')
  })

  it('renders booking cards with Vietnamese status labels', () => {
    const bookings = [
      toViewModel(makeRaw({ id: 1, status: 'pending' })),
      toViewModel(makeRaw({ id: 2, status: 'confirmed' })),
      toViewModel(
        makeRaw({ id: 3, status: 'cancelled', check_in: '2020-01-01', check_out: '2020-01-03' })
      ),
    ]

    mockedQuery.mockReturnValue({
      bookings,
      isLoading: false,
      isError: false,
      refetch: mockRefetch,
    })

    renderDashboard()
    expect(screen.getByText('Chờ xác nhận')).toBeInTheDocument()
    expect(screen.getByText('Đã xác nhận')).toBeInTheDocument()
    expect(screen.getByText('Đã hủy')).toBeInTheDocument()
  })

  it('shows cancel button only for cancellable bookings', () => {
    const bookings = [
      toViewModel(makeRaw({ id: 1, status: 'pending' })),
      toViewModel(
        makeRaw({ id: 2, status: 'cancelled', check_in: '2020-01-01', check_out: '2020-01-03' })
      ),
    ]

    mockedQuery.mockReturnValue({
      bookings,
      isLoading: false,
      isError: false,
      refetch: mockRefetch,
    })

    renderDashboard()
    const cancelButtons = screen.getAllByRole('button', { name: /Hủy đặt phòng/ })
    // Only the pending booking should have a cancel button
    expect(cancelButtons).toHaveLength(1)
  })

  it('opens confirm dialog and triggers cancel + toast on success', async () => {
    const bookings = [toViewModel(makeRaw({ id: 42, status: 'pending' }))]
    mockCancel.mockResolvedValue(true)

    mockedQuery.mockReturnValue({
      bookings,
      isLoading: false,
      isError: false,
      refetch: mockRefetch,
    })

    renderDashboard()

    // Click cancel button
    await userEvent.click(screen.getByRole('button', { name: /Hủy đặt phòng/ }))

    // Confirm dialog should appear
    expect(screen.getByRole('dialog')).toBeInTheDocument()
    expect(screen.getByText('Hủy đặt phòng')).toBeInTheDocument()

    // Click confirm
    await userEvent.click(screen.getByRole('button', { name: 'Xác nhận hủy' }))

    await waitFor(() => {
      expect(mockCancel).toHaveBeenCalledWith(42)
      expect(mockShowToast.success).toHaveBeenCalledWith('Đã hủy đặt phòng thành công.')
      expect(mockRefetch).toHaveBeenCalled()
    })
  })

  it('shows error toast when cancel fails', async () => {
    const bookings = [toViewModel(makeRaw({ id: 42, status: 'pending' }))]
    mockCancel.mockResolvedValue(false)

    mockedMutation.mockReturnValue({
      cancel: mockCancel,
      isPending: false,
      error: 'Cancel failed',
      clearError: vi.fn(),
    })

    mockedQuery.mockReturnValue({
      bookings,
      isLoading: false,
      isError: false,
      refetch: mockRefetch,
    })

    renderDashboard()
    await userEvent.click(screen.getByRole('button', { name: /Hủy đặt phòng/ }))
    await userEvent.click(screen.getByRole('button', { name: 'Xác nhận hủy' }))

    await waitFor(() => {
      expect(mockShowToast.error).toHaveBeenCalled()
    })
  })

  it('shows Vietnamese date format in booking cards', () => {
    const bookings = [toViewModel(makeRaw({ check_in: '2026-06-01', check_out: '2026-06-03' }))]

    mockedQuery.mockReturnValue({
      bookings,
      isLoading: false,
      isError: false,
      refetch: mockRefetch,
    })

    renderDashboard()
    // Vietnamese date format: dd/MM/yyyy
    expect(screen.getByText(/01\/06\/2026/)).toBeInTheDocument()
    expect(screen.getByText(/03\/06\/2026/)).toBeInTheDocument()
  })
})
