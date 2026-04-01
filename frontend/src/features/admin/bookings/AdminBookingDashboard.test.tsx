import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import AdminBookingDashboard from './AdminBookingDashboard'

const { mockGetAllBookings, mockApiGet } = vi.hoisted(() => ({
  mockGetAllBookings: vi.fn(),
  mockApiGet: vi.fn(),
}))

vi.mock('./adminBooking.api', () => ({
  getAllBookings: mockGetAllBookings,
}))

vi.mock('@/shared/lib/api', () => ({
  default: {
    get: mockApiGet,
  },
}))

const locationsResponse = {
  data: {
    data: [
      { id: 1, name: 'Soleil Da Nang' },
      { id: 2, name: 'Soleil Hoi An' },
    ],
  },
}

const bookingsPageOne = {
  bookings: [
    {
      id: 42,
      room_id: 3,
      user_id: 10,
      check_in: '2026-06-15',
      check_out: '2026-06-18',
      guest_name: 'Nguyen Van A',
      guest_email: 'guest-a@example.com',
      status: 'pending',
      status_label: 'Pending',
      nights: 3,
      amount: 2400000,
      amount_formatted: '2.400.000₫',
      room: { id: 3, name: 'Deluxe Garden', display_name: 'Deluxe Garden' },
      created_at: '2026-04-01T09:00:00Z',
      updated_at: '2026-04-01T09:00:00Z',
    },
    {
      id: 87,
      room_id: 4,
      user_id: 11,
      check_in: '2026-06-20',
      check_out: '2026-06-22',
      guest_name: 'Tran Thi B',
      guest_email: 'guest-b@example.com',
      status: 'confirmed',
      status_label: 'Confirmed',
      nights: 2,
      amount: 3200000,
      amount_formatted: '3.200.000₫',
      room: { id: 4, name: 'Suite Ocean', display_name: 'Suite Ocean' },
      created_at: '2026-04-01T10:00:00Z',
      updated_at: '2026-04-01T10:00:00Z',
    },
  ],
  meta: {
    current_page: 1,
    last_page: 3,
    per_page: 2,
    total: 24,
  },
}

const bookingsPageTwo = {
  bookings: [
    {
      id: 103,
      room_id: 8,
      user_id: 19,
      check_in: '2026-07-02',
      check_out: '2026-07-04',
      guest_name: 'Le Thi C',
      guest_email: 'guest-c@example.com',
      status: 'refund_pending',
      status_label: 'Refund Processing',
      nights: 2,
      amount: 1800000,
      amount_formatted: '1.800.000₫',
      room: { id: 8, name: 'Family Loft', display_name: 'Family Loft' },
      created_at: '2026-04-02T08:30:00Z',
      updated_at: '2026-04-02T08:30:00Z',
    },
  ],
  meta: {
    current_page: 2,
    last_page: 3,
    per_page: 2,
    total: 24,
  },
}

function renderDashboard() {
  return render(
    <MemoryRouter>
      <AdminBookingDashboard />
    </MemoryRouter>
  )
}

beforeEach(() => {
  vi.clearAllMocks()
  mockApiGet.mockResolvedValue(locationsResponse)
  mockGetAllBookings.mockResolvedValue(bookingsPageOne)
})

describe('AdminBookingDashboard', () => {
  it('renders the page header, filter panel, bookings table, and pagination', async () => {
    renderDashboard()

    expect(await screen.findByText('SOL-2026-0042')).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Đặt phòng' })).toBeInTheDocument()
    expect(screen.getByText('Tổng quan / Đặt phòng')).toBeInTheDocument()
    expect(screen.getByPlaceholderText('Tìm theo tên, email, mã...')).toBeInTheDocument()
    expect(screen.getByText('24 kết quả')).toBeInTheDocument()
    expect(screen.getAllByText('Suite Ocean').length).toBeGreaterThan(0)
    expect(screen.getByText('Trang 1 / 3')).toBeInTheDocument()
    expect(screen.getAllByRole('link', { name: 'Xem chi tiết →' }).length).toBeGreaterThan(0)
  })

  it('applies filters with the expected API params and resets them', async () => {
    const user = userEvent.setup()
    renderDashboard()

    expect(await screen.findByText('SOL-2026-0042')).toBeInTheDocument()

    await user.clear(screen.getByLabelText('Tìm kiếm đặt phòng'))
    await user.type(screen.getByLabelText('Tìm kiếm đặt phòng'), 'SOL-2026-0042')
    await user.selectOptions(screen.getByLabelText('Trạng thái'), 'confirmed')
    await user.selectOptions(screen.getByLabelText('Chi nhánh'), '2')
    fireEvent.change(screen.getByLabelText('Nhận phòng từ'), { target: { value: '2026-06-01' } })
    fireEvent.change(screen.getAllByLabelText('đến')[0], { target: { value: '2026-06-30' } })
    fireEvent.change(screen.getByLabelText('Trả phòng từ'), {
      target: { value: '2026-06-05' },
    })
    fireEvent.change(screen.getAllByLabelText('đến')[1], { target: { value: '2026-07-01' } })

    await user.click(screen.getByRole('button', { name: 'Áp dụng bộ lọc' }))

    await waitFor(() => {
      expect(mockGetAllBookings).toHaveBeenLastCalledWith(
        {
          search: '42',
          status: 'confirmed',
          location_id: 2,
          check_in_start: '2026-06-01',
          check_in_end: '2026-06-30',
          check_out_start: '2026-06-05',
          check_out_end: '2026-07-01',
          page: 1,
        },
        expect.any(AbortSignal)
      )
    })

    await user.click(screen.getByRole('button', { name: 'Xóa bộ lọc' }))

    await waitFor(() => {
      expect(mockGetAllBookings).toHaveBeenLastCalledWith({ page: 1 }, expect.any(AbortSignal))
    })

    expect(screen.getByLabelText('Tìm kiếm đặt phòng')).toHaveValue('')
    expect(screen.getByLabelText('Trạng thái')).toHaveValue('')
    expect(screen.getByLabelText('Chi nhánh')).toHaveValue('')
  })

  it('supports pagination controls', async () => {
    const user = userEvent.setup()
    mockGetAllBookings.mockResolvedValueOnce(bookingsPageOne).mockResolvedValueOnce(bookingsPageTwo)

    renderDashboard()

    expect(await screen.findByText('SOL-2026-0042')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Trang sau →' }))

    await waitFor(() => {
      expect(mockGetAllBookings).toHaveBeenLastCalledWith({ page: 2 }, expect.any(AbortSignal))
    })
    expect(await screen.findByText('SOL-2026-0103')).toBeInTheDocument()
    expect(screen.getByText('Trang 2 / 3')).toBeInTheDocument()
  })

  it('shows a five-row loading skeleton while bookings are loading', async () => {
    let resolveBookings: ((value: typeof bookingsPageOne) => void) | undefined
    mockGetAllBookings.mockReturnValue(
      new Promise(resolve => {
        resolveBookings = resolve
      })
    )

    renderDashboard()

    expect(screen.getByLabelText('Đang tải danh sách đặt phòng')).toBeInTheDocument()
    expect(screen.getAllByTestId('admin-booking-skeleton-row')).toHaveLength(5)

    resolveBookings?.(bookingsPageOne)

    expect(await screen.findByText('SOL-2026-0042')).toBeInTheDocument()
  })

  it('renders the empty state when no bookings match the active filters', async () => {
    mockGetAllBookings.mockResolvedValue({
      bookings: [],
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0,
      },
    })

    renderDashboard()

    expect(await screen.findByText('Không tìm thấy đặt phòng nào')).toBeInTheDocument()
    expect(screen.getByText('Thử điều chỉnh từ khóa hoặc khoảng ngày.')).toBeInTheDocument()
  })
})
