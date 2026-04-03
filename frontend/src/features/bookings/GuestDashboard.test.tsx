import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import GuestDashboard from './GuestDashboard'
import { toBookingViewModel } from './bookingViewModel'
import type { BookingApiRaw } from '@/features/booking/booking.types'

const mockRefetch = vi.fn()
const mockCancel = vi.fn<(id: number) => Promise<boolean>>()

const { mockUseAuth } = vi.hoisted(() => ({
  mockUseAuth: vi.fn(),
}))

const { mockShowToast } = vi.hoisted(() => ({
  mockShowToast: {
    success: vi.fn(),
    error: vi.fn(),
    warning: vi.fn(),
    info: vi.fn(),
    promise: vi.fn(),
  },
}))

const { mockApiPost } = vi.hoisted(() => ({
  mockApiPost: vi.fn(),
}))

vi.mock('@/features/auth/AuthContext', () => ({
  useAuth: mockUseAuth,
}))

vi.mock('@/shared/lib/api', () => ({
  default: {
    post: mockApiPost,
  },
}))

vi.mock('./useMyBookings', () => ({
  useMyBookingsQuery: vi.fn(),
  useCancelBookingMutation: vi.fn(),
}))

vi.mock('@/shared/utils/toast', () => ({
  showToast: mockShowToast,
  getErrorMessage: (err: unknown) => (typeof err === 'string' ? err : 'Error'),
}))

const verifiedUser = {
  id: 1,
  name: 'Nguyễn Văn A',
  email: 'test@example.com',
  role: 'user' as const,
  email_verified_at: '2026-01-01T00:00:00.000Z',
  created_at: '2026-01-01T00:00:00.000Z',
  updated_at: '2026-01-01T00:00:00.000Z',
}

import { useMyBookingsQuery, useCancelBookingMutation } from './useMyBookings'

const mockedQuery = vi.mocked(useMyBookingsQuery)
const mockedMutation = vi.mocked(useCancelBookingMutation)

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
    amount_formatted: '1.050.000₫',
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

beforeEach(() => {
  vi.clearAllMocks()
  mockApiPost.mockResolvedValue({ data: { success: true } })
  mockUseAuth.mockReturnValue({
    user: verifiedUser,
    isAuthenticated: true,
    loading: false,
    error: null,
    loginHttpOnly: vi.fn(),
    registerHttpOnly: vi.fn(),
    logoutHttpOnly: vi.fn(),
    me: vi.fn(),
    clearError: vi.fn(),
  })
  mockedMutation.mockReturnValue({
    cancel: mockCancel,
    isPending: false,
    error: null,
    clearError: vi.fn(),
  })
})

describe('GuestDashboard', () => {
  it('shows loading skeleton when isLoading is true', () => {
    mockedQuery.mockReturnValue({
      bookings: [],
      isLoading: true,
      isError: false,
      refetch: mockRefetch,
    })

    renderDashboard()
    const skeletons = screen.getAllByRole('status', { name: 'Đang tải danh sách đặt phòng' })
    expect(skeletons).toHaveLength(3)
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

    await userEvent.click(screen.getByRole('button', { name: 'Thử lại' }))
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
    expect(screen.getByText('Bạn chưa có đặt phòng nào')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Đặt phòng ngay →' })).toHaveAttribute('href', '/rooms')
  })

  it('renders booking cards with all dashboard status variants', () => {
    const bookings = [
      toViewModel(makeRaw({ id: 1, status: 'pending' })),
      toViewModel(makeRaw({ id: 2, status: 'confirmed' })),
      toViewModel(
        makeRaw({ id: 3, status: 'cancelled', check_in: '2020-01-01', check_out: '2020-01-03' })
      ),
      toViewModel(makeRaw({ id: 4, status: 'refund_pending' })),
      toViewModel(makeRaw({ id: 5, status: 'refund_failed' })),
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
    expect(screen.getByText('Hoàn tiền đang xử lý')).toBeInTheDocument()
    expect(screen.getByText('Hoàn tiền thất bại')).toBeInTheDocument()
    expect(screen.getAllByText('Phòng Dormitory 4 giường').length).toBeGreaterThan(0)
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
    expect(screen.getAllByRole('button', { name: /Hủy đặt phòng/ })).toHaveLength(1)
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

    await userEvent.click(screen.getByRole('button', { name: /Hủy đặt phòng/ }))

    expect(screen.getByRole('dialog')).toBeInTheDocument()
    expect(screen.getByText('Hủy đặt phòng #SOL-2026-0042')).toBeInTheDocument()

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
      expect(mockShowToast.error).toHaveBeenCalledWith('Không thể hủy đặt phòng. Vui lòng thử lại.')
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
    expect(screen.getByText(/01\/06\/2026/)).toBeInTheDocument()
    expect(screen.getByText(/03\/06\/2026/)).toBeInTheDocument()
  })

  it('shows email verification banner when email_verified_at is null and hides the generic load error', () => {
    mockUseAuth.mockReturnValue({
      user: { ...verifiedUser, email_verified_at: null },
      isAuthenticated: true,
      loading: false,
      error: null,
      loginHttpOnly: vi.fn(),
      registerHttpOnly: vi.fn(),
      logoutHttpOnly: vi.fn(),
      me: vi.fn(),
      clearError: vi.fn(),
    })
    mockedQuery.mockReturnValue({
      bookings: [],
      isLoading: false,
      isError: true,
      refetch: mockRefetch,
    })

    renderDashboard()
    expect(
      screen.getByText(
        'Email của bạn chưa được xác minh. Vui lòng kiểm tra hộp thư đến để lấy mã xác minh.'
      )
    ).toBeInTheDocument()
    expect(screen.queryByText('Không thể tải danh sách đặt phòng.')).not.toBeInTheDocument()
  })

  it('resends the verification email from the banner action', async () => {
    mockUseAuth.mockReturnValue({
      user: { ...verifiedUser, email_verified_at: null },
      isAuthenticated: true,
      loading: false,
      error: null,
      loginHttpOnly: vi.fn(),
      registerHttpOnly: vi.fn(),
      logoutHttpOnly: vi.fn(),
      me: vi.fn(),
      clearError: vi.fn(),
    })
    mockedQuery.mockReturnValue({
      bookings: [],
      isLoading: false,
      isError: true,
      refetch: mockRefetch,
    })

    renderDashboard()

    await userEvent.click(screen.getByRole('button', { name: 'Gửi lại mã →' }))

    await waitFor(() => {
      expect(mockApiPost).toHaveBeenCalledWith('/email/send-code')
      expect(mockShowToast.success).toHaveBeenCalledWith('Đã gửi mã xác minh đến email của bạn.')
    })
  })
})
