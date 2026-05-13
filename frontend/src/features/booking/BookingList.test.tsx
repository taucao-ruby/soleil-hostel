import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import BookingList from './BookingList'
import type { BookingApiRaw } from '@/shared/types/booking.types'

const { mockFetchMyBookings, mockCancelBooking } = vi.hoisted(() => ({
  mockFetchMyBookings: vi.fn(),
  mockCancelBooking: vi.fn(),
}))

vi.mock('./booking.api', () => ({
  fetchMyBookings: mockFetchMyBookings,
  cancelBooking: mockCancelBooking,
}))

vi.mock('@/shared/utils/toast', () => ({
  showToast: {
    success: vi.fn(),
    error: vi.fn(),
  },
}))

const booking: BookingApiRaw = {
  id: 33,
  room_id: 5,
  user_id: 9,
  check_in: '2026-06-01',
  check_out: '2026-06-03',
  guest_name: 'Tran Thi B',
  guest_email: 'guest-b@example.com',
  status: 'confirmed',
  status_label: 'Confirmed',
  nights: 2,
  amount: 1800000,
  amount_formatted: '1.800.000₫',
  created_at: '2026-04-01T08:00:00Z',
  updated_at: '2026-04-01T08:00:00Z',
}

function renderBookingList() {
  return render(
    <MemoryRouter>
      <BookingList />
    </MemoryRouter>
  )
}

beforeEach(() => {
  vi.clearAllMocks()
  mockFetchMyBookings.mockResolvedValue([booking])
  mockCancelBooking.mockResolvedValue({ success: true, message: 'ok', data: booking })
})

describe('BookingList', () => {
  it('renders explicit error UI when the bookings request fails', async () => {
    mockFetchMyBookings.mockRejectedValue(new Error('Network failed'))

    renderBookingList()

    expect(
      await screen.findByText('Không thể tải danh sách đặt phòng. Vui lòng thử lại sau.')
    ).toBeInTheDocument()
    expect(screen.queryByText('Bạn chưa có đặt phòng nào ở mục này')).not.toBeInTheDocument()
  })

  it('renders the empty state separately from request failures', async () => {
    mockFetchMyBookings.mockResolvedValue([])

    renderBookingList()

    expect(await screen.findByText('Bạn chưa có đặt phòng nào ở mục này')).toBeInTheDocument()
    expect(
      screen.queryByText('Không thể tải danh sách đặt phòng. Vui lòng thử lại sau.')
    ).not.toBeInTheDocument()
  })
})
