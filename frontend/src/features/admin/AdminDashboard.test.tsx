import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import AdminDashboard from './AdminDashboard'

const {
  mockUseAuth,
  mockFetchAdminBookings,
  mockFetchTrashedBookings,
  mockFetchContactMessages,
  mockRestoreBooking,
  mockForceDeleteBooking,
  mockToastSuccess,
  mockToastError,
} = vi.hoisted(() => ({
  mockUseAuth: vi.fn(),
  mockFetchAdminBookings: vi.fn(),
  mockFetchTrashedBookings: vi.fn(),
  mockFetchContactMessages: vi.fn(),
  mockRestoreBooking: vi.fn(),
  mockForceDeleteBooking: vi.fn(),
  mockToastSuccess: vi.fn(),
  mockToastError: vi.fn(),
}))

vi.mock('@/features/auth/AuthContext', () => ({
  useAuth: mockUseAuth,
}))

vi.mock('./admin.api', () => ({
  fetchAdminBookings: mockFetchAdminBookings,
  fetchTrashedBookings: mockFetchTrashedBookings,
  fetchContactMessages: mockFetchContactMessages,
  restoreBooking: mockRestoreBooking,
  forceDeleteBooking: mockForceDeleteBooking,
}))

vi.mock('@/shared/utils/toast', () => ({
  getErrorMessage: (error: unknown) => (error instanceof Error ? error.message : 'Lỗi'),
  showToast: {
    success: mockToastSuccess,
    error: mockToastError,
  },
}))

const bookingsPageOne = {
  bookings: [
    {
      id: 101,
      room_id: 1,
      user_id: 10,
      check_in: '2026-04-05',
      check_out: '2026-04-07',
      guest_name: 'Nguyen Van A',
      guest_email: 'a@example.com',
      status: 'pending',
      status_label: 'Chờ xác nhận',
      nights: 2,
      amount: 1800000,
      amount_formatted: '1.800.000₫',
      room: { id: 1, name: 'Deluxe Double', display_name: 'Deluxe Double' },
      created_at: '2026-04-01T09:00:00Z',
      updated_at: '2026-04-01T09:00:00Z',
    },
    {
      id: 102,
      room_id: 2,
      user_id: 11,
      check_in: '2026-04-06',
      check_out: '2026-04-08',
      guest_name: 'Tran Thi B',
      guest_email: 'b@example.com',
      status: 'confirmed',
      status_label: 'Đã xác nhận',
      nights: 2,
      amount: 2400000,
      amount_formatted: '2.400.000₫',
      room: { id: 2, name: 'Suite Ocean', display_name: 'Suite Ocean' },
      created_at: '2026-04-01T10:30:00Z',
      updated_at: '2026-04-01T10:30:00Z',
    },
  ],
  meta: {
    current_page: 1,
    last_page: 2,
    per_page: 2,
    total: 3,
  },
}

const bookingsPageTwo = {
  bookings: [
    {
      id: 103,
      room_id: 3,
      user_id: 12,
      check_in: '2026-04-10',
      check_out: '2026-04-12',
      guest_name: 'Le Thi C',
      guest_email: 'c@example.com',
      status: 'confirmed',
      status_label: 'Đã xác nhận',
      nights: 2,
      amount: 3000000,
      amount_formatted: '3.000.000₫',
      room: { id: 3, name: 'Family Loft', display_name: 'Family Loft' },
      created_at: '2026-04-02T08:45:00Z',
      updated_at: '2026-04-02T08:45:00Z',
    },
  ],
  meta: {
    current_page: 2,
    last_page: 2,
    per_page: 2,
    total: 3,
  },
}

const trashedBookings = {
  bookings: [
    {
      id: 201,
      room_id: 4,
      user_id: 22,
      check_in: '2026-04-12',
      check_out: '2026-04-13',
      guest_name: 'Pham Van D',
      guest_email: 'd@example.com',
      status: 'cancelled',
      status_label: 'Đã hủy',
      nights: 1,
      amount: 900000,
      amount_formatted: '900.000₫',
      room: { id: 4, name: 'Garden Twin', display_name: 'Garden Twin' },
      created_at: '2026-04-01T12:00:00Z',
      updated_at: '2026-04-01T12:00:00Z',
      is_trashed: true,
      deleted_at: '2026-04-01T13:15:00Z',
      deleted_by: { id: 7, name: 'Admin User', email: 'admin@example.com' },
    },
  ],
  meta: {
    current_page: 1,
    last_page: 1,
    per_page: 1,
    total: 1,
  },
}

const contactMessages = [
  {
    id: 1,
    name: 'Lan Nguyen',
    email: 'lan@example.com',
    subject: 'Giữ hành lý sau checkout',
    message: 'Cho mình hỏi hostel có hỗ trợ giữ hành lý đến buổi tối không?',
    read_at: null,
    created_at: '2026-04-01T08:15:00Z',
    updated_at: '2026-04-01T08:15:00Z',
  },
]

function renderDashboard() {
  return render(
    <MemoryRouter>
      <AdminDashboard />
    </MemoryRouter>
  )
}

beforeEach(() => {
  vi.clearAllMocks()

  mockFetchAdminBookings.mockImplementation((page?: number) =>
    Promise.resolve(page === 2 ? bookingsPageTwo : bookingsPageOne)
  )
  mockFetchTrashedBookings.mockResolvedValue(trashedBookings)
  mockFetchContactMessages.mockResolvedValue(contactMessages)
  mockRestoreBooking.mockResolvedValue(undefined)
  mockForceDeleteBooking.mockResolvedValue(undefined)
})

describe('AdminDashboard', () => {
  it('renders the admin metrics and all three tabs for admin users', async () => {
    mockUseAuth.mockReturnValue({
      user: { id: 1, name: 'Admin User', email: 'admin@example.com', role: 'admin' },
    })

    renderDashboard()

    expect(await screen.findByText('ĐP #101')).toBeInTheDocument()
    expect(screen.getByText('Đặt phòng hôm nay')).toBeInTheDocument()
    expect(screen.getByText('28.400.000₫')).toBeInTheDocument()
    expect(screen.getByRole('tab', { name: 'Đặt phòng' })).toBeInTheDocument()
    expect(screen.getByRole('tab', { name: 'Đã xóa' })).toBeInTheDocument()
    expect(screen.getByRole('tab', { name: 'Liên hệ' })).toBeInTheDocument()
  })

  it('renders only the bookings tab for moderator users', async () => {
    mockUseAuth.mockReturnValue({
      user: { id: 2, name: 'Moderator', email: 'moderator@example.com', role: 'moderator' },
    })

    renderDashboard()

    expect(await screen.findByText('ĐP #101')).toBeInTheDocument()
    expect(screen.getByRole('tab', { name: 'Đặt phòng' })).toBeInTheDocument()
    expect(screen.queryByRole('tab', { name: 'Đã xóa' })).not.toBeInTheDocument()
    expect(screen.queryByRole('tab', { name: 'Liên hệ' })).not.toBeInTheDocument()
    expect(mockFetchTrashedBookings).not.toHaveBeenCalled()
    expect(mockFetchContactMessages).not.toHaveBeenCalled()
  })

  it('supports booking pagination in the recent bookings tab', async () => {
    const user = userEvent.setup()
    mockUseAuth.mockReturnValue({
      user: { id: 1, name: 'Admin User', email: 'admin@example.com', role: 'admin' },
    })

    renderDashboard()

    expect(await screen.findByText('ĐP #101')).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Trang sau' }))

    expect(await screen.findByText('ĐP #103')).toBeInTheDocument()
    expect(mockFetchAdminBookings).toHaveBeenCalledWith(2, expect.any(AbortSignal))
  })

  it('shows admin-only trashed booking actions and restores a booking', async () => {
    const user = userEvent.setup()
    mockUseAuth.mockReturnValue({
      user: { id: 1, name: 'Admin User', email: 'admin@example.com', role: 'admin' },
    })

    renderDashboard()

    expect(await screen.findByText('ĐP #101')).toBeInTheDocument()
    await user.click(screen.getByRole('tab', { name: 'Đã xóa' }))

    expect(await screen.findByText('Pham Van D')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Khôi phục' }))

    await waitFor(() => {
      expect(mockRestoreBooking).toHaveBeenCalledWith(201)
      expect(mockToastSuccess).toHaveBeenCalledWith('Đã khôi phục đặt phòng.')
    })
  })

  it('shows contact messages with a new badge for unread items', async () => {
    const user = userEvent.setup()
    mockUseAuth.mockReturnValue({
      user: { id: 1, name: 'Admin User', email: 'admin@example.com', role: 'admin' },
    })

    renderDashboard()

    expect(await screen.findByText('ĐP #101')).toBeInTheDocument()
    await user.click(screen.getByRole('tab', { name: 'Liên hệ' }))

    expect(await screen.findByText('Giữ hành lý sau checkout')).toBeInTheDocument()
    expect(screen.getByText('Mới')).toBeInTheDocument()
    expect(
      screen.getByText('Cho mình hỏi hostel có hỗ trợ giữ hành lý đến buổi tối không?')
    ).toBeInTheDocument()
  })
})
