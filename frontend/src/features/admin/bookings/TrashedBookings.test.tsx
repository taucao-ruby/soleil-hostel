import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import type { AdminBookingRaw } from '../admin.types'
import TrashedBookings from './TrashedBookings'

const {
  mockUseAuth,
  mockFetchTrashedBookings,
  mockRestoreBooking,
  mockForceDeleteBooking,
  mockToastSuccess,
  mockToastError,
} = vi.hoisted(() => ({
  mockUseAuth: vi.fn(),
  mockFetchTrashedBookings: vi.fn(),
  mockRestoreBooking: vi.fn(),
  mockForceDeleteBooking: vi.fn(),
  mockToastSuccess: vi.fn(),
  mockToastError: vi.fn(),
}))

vi.mock('@/features/auth/AuthContext', () => ({
  useAuth: mockUseAuth,
}))

vi.mock('../admin.api', () => ({
  fetchTrashedBookings: mockFetchTrashedBookings,
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

function makeTrashed(overrides: Partial<AdminBookingRaw> = {}): AdminBookingRaw {
  return {
    id: 501,
    room_id: 1,
    user_id: 10,
    check_in: '2026-06-10',
    check_out: '2026-06-12',
    guest_name: 'Nguyễn Văn A',
    guest_email: 'vana@example.com',
    number_of_guests: 2,
    special_requests: null,
    status: 'cancelled',
    status_label: 'Đã hủy',
    nights: 2,
    amount: 1800000,
    amount_formatted: '1.800.000₫',
    payment_policy: 'prepaid',
    payment_status: 'refunded',
    room: { id: 1, name: 'Deluxe Double', display_name: 'Deluxe Double' },
    is_trashed: true,
    deleted_at: '2026-06-01T09:00:00Z',
    deleted_by: { id: 2, name: 'Admin B', email: 'admin@example.com' },
    created_at: '2026-05-01T09:00:00Z',
    updated_at: '2026-06-01T09:00:00Z',
    ...overrides,
  }
}

function setRole(role: 'admin' | 'moderator') {
  mockUseAuth.mockReturnValue({ user: { id: 1, name: 'Tester', role } })
}

function renderPage() {
  return render(
    <MemoryRouter>
      <TrashedBookings />
    </MemoryRouter>
  )
}

beforeEach(() => {
  vi.clearAllMocks()
  mockFetchTrashedBookings.mockResolvedValue({
    bookings: [makeTrashed(), makeTrashed({ id: 502, guest_name: 'Trần Thị B' })],
    meta: { current_page: 1, last_page: 1, per_page: 2, total: 2 },
  })
})

describe('TrashedBookings', () => {
  it('lists trashed bookings with restore + force-delete actions for admin', async () => {
    setRole('admin')
    renderPage()

    expect(await screen.findByText('ĐP #501')).toBeInTheDocument()
    expect(screen.getByText('ĐP #502')).toBeInTheDocument()
    expect(screen.getByText('Nguyễn Văn A')).toBeInTheDocument()
    expect(screen.getByText('2 đặt phòng trong thùng rác')).toBeInTheDocument()
    expect(screen.getAllByRole('button', { name: 'Khôi phục' })).toHaveLength(2)
    expect(screen.getAllByRole('button', { name: 'Xóa vĩnh viễn' })).toHaveLength(2)
  })

  it('restores a booking and removes it from the list', async () => {
    setRole('admin')
    mockRestoreBooking.mockResolvedValue(undefined)
    renderPage()

    await screen.findByText('ĐP #501')

    await userEvent.click(screen.getAllByRole('button', { name: 'Khôi phục' })[0])

    await waitFor(() => {
      expect(mockRestoreBooking).toHaveBeenCalledWith(501)
      expect(screen.queryByText('ĐP #501')).not.toBeInTheDocument()
    })
    expect(screen.getByText('ĐP #502')).toBeInTheDocument()
    expect(mockToastSuccess).toHaveBeenCalledWith('Đã khôi phục đặt phòng.')
  })

  it('force-deletes a booking after confirming in the dialog', async () => {
    setRole('admin')
    mockForceDeleteBooking.mockResolvedValue(undefined)
    renderPage()

    await screen.findByText('ĐP #501')

    await userEvent.click(screen.getAllByRole('button', { name: 'Xóa vĩnh viễn' })[0])

    const dialog = await screen.findByRole('dialog')
    expect(within(dialog).getByText('Xóa vĩnh viễn đặt phòng')).toBeInTheDocument()

    await userEvent.click(within(dialog).getByRole('button', { name: 'Xóa vĩnh viễn' }))

    await waitFor(() => {
      expect(mockForceDeleteBooking).toHaveBeenCalledWith(501)
      expect(screen.queryByText('ĐP #501')).not.toBeInTheDocument()
    })
    expect(mockToastSuccess).toHaveBeenCalledWith('Đã xóa vĩnh viễn đặt phòng.')
  })

  it('hides restore + force-delete actions for moderators (admin-only writes)', async () => {
    setRole('moderator')
    renderPage()

    expect(await screen.findByText('ĐP #501')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Khôi phục' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Xóa vĩnh viễn' })).not.toBeInTheDocument()
  })

  it('shows an empty state when there are no trashed bookings', async () => {
    setRole('admin')
    mockFetchTrashedBookings.mockResolvedValue({
      bookings: [],
      meta: { current_page: 1, last_page: 1, per_page: 0, total: 0 },
    })
    renderPage()

    expect(await screen.findByText('Không có đặt phòng nào trong thùng rác.')).toBeInTheDocument()
  })
})

describe('/admin/bookings/trashed route resolution', () => {
  // Guards the fix: the static `bookings/trashed` route must resolve to this
  // page, NOT fall through to the dynamic `bookings/:id` detail modal.
  function renderRoute(path: string) {
    return render(
      <MemoryRouter initialEntries={[path]}>
        <Routes>
          <Route path="/admin/bookings/trashed" element={<TrashedBookings />} />
          <Route
            path="/admin/bookings/:id"
            element={<div data-testid="booking-detail-modal">detail</div>}
          />
        </Routes>
      </MemoryRouter>
    )
  }

  it('renders the trashed page (not the detail modal) for /admin/bookings/trashed', async () => {
    setRole('admin')
    renderRoute('/admin/bookings/trashed')

    expect(await screen.findByRole('heading', { name: 'Đặt phòng đã xóa' })).toBeInTheDocument()
    expect(screen.queryByTestId('booking-detail-modal')).not.toBeInTheDocument()
  })

  it('still renders the detail modal for a numeric id like /admin/bookings/123', () => {
    setRole('admin')
    renderRoute('/admin/bookings/123')

    expect(screen.getByTestId('booking-detail-modal')).toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Đặt phòng đã xóa' })).not.toBeInTheDocument()
  })
})
