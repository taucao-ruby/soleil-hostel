import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import AdminDashboard from './AdminDashboard'
import type { AdminBookingRaw, ContactMessageRaw, PaginationMeta } from './admin.types'

// ── Hoisted mocks (Vitest 2.x safe) ─────────────────────

const { mockShowToast } = vi.hoisted(() => ({
  mockShowToast: {
    success: vi.fn(),
    error: vi.fn(),
    warning: vi.fn(),
    info: vi.fn(),
    promise: vi.fn(),
  },
}))

vi.mock('./admin.api', () => ({
  fetchAdminBookings: vi.fn(),
  fetchTrashedBookings: vi.fn(),
  fetchContactMessages: vi.fn(),
  restoreBooking: vi.fn(),
  forceDeleteBooking: vi.fn(),
}))

vi.mock('@/utils/toast', () => ({
  showToast: mockShowToast,
  getErrorMessage: (err: unknown) => (typeof err === 'string' ? err : 'Có lỗi xảy ra'),
}))

import {
  fetchAdminBookings,
  fetchTrashedBookings,
  fetchContactMessages,
  restoreBooking,
  forceDeleteBooking,
} from './admin.api'

const mockedFetchBookings = vi.mocked(fetchAdminBookings)
const mockedFetchTrashed = vi.mocked(fetchTrashedBookings)
const mockedFetchContacts = vi.mocked(fetchContactMessages)
const mockedRestore = vi.mocked(restoreBooking)
const mockedForceDelete = vi.mocked(forceDeleteBooking)

// ── Helpers ─────────────────────────────────────────────

function makeMeta(overrides: Partial<PaginationMeta> = {}): PaginationMeta {
  return {
    current_page: 1,
    last_page: 1,
    per_page: 50,
    total: 0,
    ...overrides,
  }
}

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

// ── Existing behavior tests ─────────────────────────────

describe('AdminDashboard', () => {
  it('renders with "Đặt phòng" tab active by default', () => {
    renderAdmin()
    const bookingsTab = screen.getByRole('tab', { name: 'Đặt phòng' })
    expect(bookingsTab).toHaveAttribute('aria-selected', 'true')
  })

  it('shows loading skeleton for bookings tab', () => {
    renderAdmin()
    expect(screen.getByRole('tabpanel', { name: 'Đặt phòng' })).toBeInTheDocument()
    const skeletons = screen.getAllByRole('status')
    expect(skeletons.length).toBeGreaterThanOrEqual(1)
  })

  it('shows bookings data when loaded', async () => {
    mockedFetchBookings.mockResolvedValue({
      bookings: [
        makeBooking({ id: 1, guest_name: 'Alice Nguyen', status: 'confirmed' }),
        makeBooking({ id: 2, guest_name: 'Bob Tran', status: 'pending' }),
      ],
      meta: makeMeta({ total: 2 }),
    })

    renderAdmin()

    await waitFor(() => {
      expect(screen.getByText('Alice Nguyen')).toBeInTheDocument()
    })
    expect(screen.getByText('Bob Tran')).toBeInTheDocument()
    expect(screen.getByText('Đã xác nhận')).toBeInTheDocument()
    expect(screen.getByText('Chờ xác nhận')).toBeInTheDocument()
  })

  it('shows empty state for bookings', async () => {
    mockedFetchBookings.mockResolvedValue({
      bookings: [],
      meta: makeMeta(),
    })

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

    expect(screen.getByRole('button', { name: 'Thử lại' })).toBeInTheDocument()
  })

  it('switches to "Đã xóa" tab and shows trashed data', async () => {
    const user = userEvent.setup()
    mockedFetchBookings.mockResolvedValue({
      bookings: [],
      meta: makeMeta(),
    })
    mockedFetchTrashed.mockResolvedValue({
      bookings: [
        makeBooking({
          id: 10,
          guest_name: 'Deleted Guest',
          status: 'cancelled',
          is_trashed: true,
          deleted_at: '2026-02-15T10:00:00+00:00',
          deleted_by: { id: 1, name: 'Admin', email: 'admin@test.com' },
        }),
      ],
      meta: makeMeta({ total: 1 }),
    })

    renderAdmin()

    await waitFor(() => {
      expect(screen.getByText('Chưa có đặt phòng nào.')).toBeInTheDocument()
    })

    await user.click(screen.getByRole('tab', { name: 'Đã xóa' }))

    await waitFor(() => {
      expect(screen.getByText('Deleted Guest')).toBeInTheDocument()
    })
    expect(screen.getByText(/bởi Admin/)).toBeInTheDocument()
  })

  it('switches to "Liên hệ" tab and shows contacts', async () => {
    const user = userEvent.setup()
    mockedFetchBookings.mockResolvedValue({
      bookings: [],
      meta: makeMeta(),
    })
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
    expect(screen.getByText('Mới')).toBeInTheDocument()
  })

  it('shows empty state for contacts tab', async () => {
    const user = userEvent.setup()
    mockedFetchBookings.mockResolvedValue({
      bookings: [],
      meta: makeMeta(),
    })
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

  // ── FE-002: Trashed actions ─────────────────────────────

  describe('Trashed tab actions', () => {
    async function setupTrashedTab() {
      const user = userEvent.setup()
      mockedFetchBookings.mockResolvedValue({
        bookings: [],
        meta: makeMeta(),
      })
      mockedFetchTrashed.mockResolvedValue({
        bookings: [
          makeBooking({
            id: 42,
            guest_name: 'Trashed Guest',
            status: 'cancelled',
            is_trashed: true,
            deleted_at: '2026-02-10T10:00:00+00:00',
          }),
        ],
        meta: makeMeta({ total: 1 }),
      })

      renderAdmin()

      await waitFor(() => {
        expect(screen.getByText('Chưa có đặt phòng nào.')).toBeInTheDocument()
      })

      await user.click(screen.getByRole('tab', { name: 'Đã xóa' }))

      await waitFor(() => {
        expect(screen.getByText('Trashed Guest')).toBeInTheDocument()
      })

      return user
    }

    it('shows Restore and Force Delete buttons on trashed cards', async () => {
      await setupTrashedTab()

      expect(screen.getByRole('button', { name: 'Khôi phục' })).toBeInTheDocument()
      expect(screen.getByRole('button', { name: 'Xóa vĩnh viễn' })).toBeInTheDocument()
    })

    it('calls restoreBooking and shows success toast on restore', async () => {
      const user = await setupTrashedTab()
      mockedRestore.mockResolvedValue(undefined)
      // After restore, refetch returns empty
      mockedFetchTrashed.mockResolvedValue({
        bookings: [],
        meta: makeMeta({ total: 0 }),
      })

      await user.click(screen.getByRole('button', { name: 'Khôi phục' }))

      await waitFor(() => {
        expect(mockedRestore).toHaveBeenCalledWith(42)
      })
      await waitFor(() => {
        expect(mockShowToast.success).toHaveBeenCalledWith('Đã khôi phục đặt phòng.')
      })
    })

    it('shows error toast when restore fails', async () => {
      const user = await setupTrashedTab()
      mockedRestore.mockRejectedValue(new Error('Server error'))

      await user.click(screen.getByRole('button', { name: 'Khôi phục' }))

      await waitFor(() => {
        expect(mockShowToast.error).toHaveBeenCalled()
      })
    })

    it('opens confirm dialog when Force Delete is clicked', async () => {
      const user = await setupTrashedTab()

      await user.click(screen.getByRole('button', { name: 'Xóa vĩnh viễn' }))

      await waitFor(() => {
        expect(screen.getByRole('dialog')).toBeInTheDocument()
      })
      expect(screen.getByText('Xóa vĩnh viễn đặt phòng?')).toBeInTheDocument()
      expect(
        screen.getByText(
          'Hành động này không thể hoàn tác. Dữ liệu đặt phòng sẽ bị xóa hoàn toàn khỏi hệ thống.'
        )
      ).toBeInTheDocument()
    })

    it('calls forceDeleteBooking when confirm dialog is confirmed', async () => {
      const user = await setupTrashedTab()
      mockedForceDelete.mockResolvedValue(undefined)
      mockedFetchTrashed.mockResolvedValue({
        bookings: [],
        meta: makeMeta({ total: 0 }),
      })

      await user.click(screen.getByRole('button', { name: 'Xóa vĩnh viễn' }))

      await waitFor(() => {
        expect(screen.getByRole('dialog')).toBeInTheDocument()
      })

      // Click the confirm button inside the dialog (labeled "Xóa vĩnh viễn")
      const dialogConfirmBtn = screen.getAllByRole('button', { name: 'Xóa vĩnh viễn' })
      // The second "Xóa vĩnh viễn" button is the dialog confirm button
      await user.click(dialogConfirmBtn[dialogConfirmBtn.length - 1])

      await waitFor(() => {
        expect(mockedForceDelete).toHaveBeenCalledWith(42)
      })
      await waitFor(() => {
        expect(mockShowToast.success).toHaveBeenCalledWith('Đã xóa vĩnh viễn.')
      })
    })

    it('closes confirm dialog without calling API when cancel is clicked', async () => {
      const user = await setupTrashedTab()

      await user.click(screen.getByRole('button', { name: 'Xóa vĩnh viễn' }))

      await waitFor(() => {
        expect(screen.getByRole('dialog')).toBeInTheDocument()
      })

      await user.click(screen.getByRole('button', { name: 'Quay lại' }))

      await waitFor(() => {
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
      })
      expect(mockedForceDelete).not.toHaveBeenCalled()
    })
  })

  // ── FE-003: Pagination ──────────────────────────────────

  describe('Pagination', () => {
    it('shows pagination controls when last_page > 1', async () => {
      mockedFetchBookings.mockResolvedValue({
        bookings: [makeBooking({ id: 1 })],
        meta: makeMeta({ current_page: 1, last_page: 3, total: 150 }),
      })

      renderAdmin()

      await waitFor(() => {
        expect(screen.getByText('Alice Nguyen')).toBeInTheDocument()
      })

      expect(screen.getByText('Trang 1 / 3')).toBeInTheDocument()
      expect(screen.getByRole('button', { name: 'Trước' })).toBeDisabled()
      expect(screen.getByRole('button', { name: 'Sau' })).toBeEnabled()
    })

    it('does not show pagination when last_page is 1', async () => {
      mockedFetchBookings.mockResolvedValue({
        bookings: [makeBooking({ id: 1 })],
        meta: makeMeta({ current_page: 1, last_page: 1, total: 1 }),
      })

      renderAdmin()

      await waitFor(() => {
        expect(screen.getByText('Alice Nguyen')).toBeInTheDocument()
      })

      expect(screen.queryByText(/Trang/)).not.toBeInTheDocument()
    })

    it('calls fetchAdminBookings with page 2 when Next is clicked', async () => {
      const user = userEvent.setup()
      mockedFetchBookings.mockResolvedValue({
        bookings: [makeBooking({ id: 1, guest_name: 'Page 1 Guest' })],
        meta: makeMeta({ current_page: 1, last_page: 3, total: 150 }),
      })

      renderAdmin()

      await waitFor(() => {
        expect(screen.getByText('Page 1 Guest')).toBeInTheDocument()
      })

      // Setup page 2 response
      mockedFetchBookings.mockResolvedValue({
        bookings: [makeBooking({ id: 51, guest_name: 'Page 2 Guest' })],
        meta: makeMeta({ current_page: 2, last_page: 3, total: 150 }),
      })

      await user.click(screen.getByRole('button', { name: 'Sau' }))

      await waitFor(() => {
        expect(screen.getByText('Page 2 Guest')).toBeInTheDocument()
      })
      expect(screen.getByText('Trang 2 / 3')).toBeInTheDocument()
      expect(screen.getByRole('button', { name: 'Trước' })).toBeEnabled()
    })

    it('disables Next button on last page', async () => {
      mockedFetchBookings.mockResolvedValue({
        bookings: [makeBooking({ id: 1 })],
        meta: makeMeta({ current_page: 3, last_page: 3, total: 150 }),
      })

      renderAdmin()

      await waitFor(() => {
        expect(screen.getByText('Trang 3 / 3')).toBeInTheDocument()
      })

      expect(screen.getByRole('button', { name: 'Sau' })).toBeDisabled()
      expect(screen.getByRole('button', { name: 'Trước' })).toBeEnabled()
    })
  })
})
