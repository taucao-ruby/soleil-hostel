import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import AdminDashboard from './AdminDashboard'
import type { AdminBookingRaw, ContactMessageRaw } from './admin.types'

// ── Mocks ───────────────────────────────────────────────

vi.mock('./admin.api', () => ({
  fetchAdminBookings: vi.fn(),
  fetchTrashedBookings: vi.fn(),
  fetchContactMessages: vi.fn(),
}))

import { fetchAdminBookings, fetchTrashedBookings, fetchContactMessages } from './admin.api'

const mockedFetchBookings = vi.mocked(fetchAdminBookings)
const mockedFetchTrashed = vi.mocked(fetchTrashedBookings)
const mockedFetchContacts = vi.mocked(fetchContactMessages)

// ── Helpers ─────────────────────────────────────────────

function makeBooking(overrides: Partial<AdminBookingRaw> = {}): AdminBookingRaw {
  return {
    id: 1,
    room_id: 10,
    user_id: 5,
    check_in: '2026-06-01',
    check_out: '2026-06-03',
    guest_name: 'Alice Nguyen',
    guest_email: 'alice@example.com',
    status: 'confirmed',
    status_label: null,
    nights: 2,
    amount_formatted: '500,000₫',
    created_at: '2026-05-20T10:00:00+00:00',
    updated_at: '2026-05-20T10:00:00+00:00',
    ...overrides,
  }
}

function makeContact(overrides: Partial<ContactMessageRaw> = {}): ContactMessageRaw {
  return {
    id: 1,
    name: 'Bob',
    email: 'bob@example.com',
    subject: 'Room inquiry',
    message: 'Hello, I would like to ask about availability.',
    read_at: null,
    created_at: '2026-02-20T10:00:00+00:00',
    updated_at: '2026-02-20T10:00:00+00:00',
    ...overrides,
  }
}

function renderAdmin() {
  return render(
    <MemoryRouter>
      <AdminDashboard />
    </MemoryRouter>
  )
}

// ── Setup ───────────────────────────────────────────────

beforeEach(() => {
  vi.clearAllMocks()
  // Default: never-resolving promises (loading state)
  mockedFetchBookings.mockReturnValue(new Promise(() => {}))
  mockedFetchTrashed.mockReturnValue(new Promise(() => {}))
  mockedFetchContacts.mockReturnValue(new Promise(() => {}))
})

// ── Tests ───────────────────────────────────────────────

describe('AdminDashboard', () => {
  it('renders with "Đặt phòng" tab active by default', () => {
    renderAdmin()
    const bookingsTab = screen.getByRole('tab', { name: 'Đặt phòng' })
    expect(bookingsTab).toHaveAttribute('aria-selected', 'true')
  })

  it('shows loading skeleton for bookings tab', () => {
    renderAdmin()
    expect(screen.getByRole('tabpanel', { name: 'Đặt phòng' })).toBeInTheDocument()
    // Skeletons render as role="status"
    const skeletons = screen.getAllByRole('status')
    expect(skeletons.length).toBeGreaterThanOrEqual(1)
  })

  it('shows bookings data when loaded', async () => {
    mockedFetchBookings.mockResolvedValue([
      makeBooking({ id: 1, guest_name: 'Alice Nguyen', status: 'confirmed' }),
      makeBooking({ id: 2, guest_name: 'Bob Tran', status: 'pending' }),
    ])

    renderAdmin()

    await waitFor(() => {
      expect(screen.getByText('Alice Nguyen')).toBeInTheDocument()
    })
    expect(screen.getByText('Bob Tran')).toBeInTheDocument()
    expect(screen.getByText('Đã xác nhận')).toBeInTheDocument()
    expect(screen.getByText('Chờ xác nhận')).toBeInTheDocument()
  })

  it('shows empty state for bookings', async () => {
    mockedFetchBookings.mockResolvedValue([])

    renderAdmin()

    await waitFor(() => {
      expect(screen.getByText('Chưa có đặt phòng nào.')).toBeInTheDocument()
    })
  })

  it('shows error state with retry for bookings', async () => {
    mockedFetchBookings.mockRejectedValue(new Error('Network error'))

    renderAdmin()

    await waitFor(() => {
      expect(screen.getByText('Không thể tải danh sách đặt phòng.')).toBeInTheDocument()
    })

    // Retry button exists
    expect(screen.getByRole('button', { name: 'Thử lại' })).toBeInTheDocument()
  })

  it('switches to "Đã xóa" tab and shows trashed data', async () => {
    const user = userEvent.setup()
    mockedFetchBookings.mockResolvedValue([])
    mockedFetchTrashed.mockResolvedValue([
      makeBooking({
        id: 10,
        guest_name: 'Deleted Guest',
        status: 'cancelled',
        is_trashed: true,
        deleted_at: '2026-02-15T10:00:00+00:00',
        deleted_by: { id: 1, name: 'Admin', email: 'admin@test.com' },
      }),
    ])

    renderAdmin()

    // Wait for bookings tab to finish loading
    await waitFor(() => {
      expect(screen.getByText('Chưa có đặt phòng nào.')).toBeInTheDocument()
    })

    // Switch tab
    await user.click(screen.getByRole('tab', { name: 'Đã xóa' }))

    await waitFor(() => {
      expect(screen.getByText('Deleted Guest')).toBeInTheDocument()
    })
    expect(screen.getByText(/bởi Admin/)).toBeInTheDocument()
  })

  it('switches to "Liên hệ" tab and shows contacts', async () => {
    const user = userEvent.setup()
    mockedFetchBookings.mockResolvedValue([])
    mockedFetchContacts.mockResolvedValue([
      makeContact({ id: 1, name: 'Bob', subject: 'Room inquiry', read_at: null }),
      makeContact({ id: 2, name: 'Carol', subject: 'Pricing', read_at: '2026-02-21T10:00:00Z' }),
    ])

    renderAdmin()

    await waitFor(() => {
      expect(screen.getByText('Chưa có đặt phòng nào.')).toBeInTheDocument()
    })

    await user.click(screen.getByRole('tab', { name: 'Liên hệ' }))

    await waitFor(() => {
      expect(screen.getByText('Bob')).toBeInTheDocument()
    })
    expect(screen.getByText('Carol')).toBeInTheDocument()
    // Unread badge
    expect(screen.getByText('Mới')).toBeInTheDocument()
  })

  it('shows empty state for contacts tab', async () => {
    const user = userEvent.setup()
    mockedFetchBookings.mockResolvedValue([])
    mockedFetchContacts.mockResolvedValue([])

    renderAdmin()

    await waitFor(() => {
      expect(screen.getByText('Chưa có đặt phòng nào.')).toBeInTheDocument()
    })

    await user.click(screen.getByRole('tab', { name: 'Liên hệ' }))

    await waitFor(() => {
      expect(screen.getByText('Chưa có tin nhắn liên hệ nào.')).toBeInTheDocument()
    })
  })
})
