import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import type { BookingDetailRaw } from '@/features/booking/booking.types'

const { mockGetBookingById, mockCancelBooking } = vi.hoisted(() => ({
  mockGetBookingById: vi.fn(),
  mockCancelBooking: vi.fn(),
}))

const { mockShowToast } = vi.hoisted(() => ({
  mockShowToast: {
    success: vi.fn(),
    error: vi.fn(),
  },
}))

vi.mock('@/features/booking/booking.api', () => ({
  getBookingById: mockGetBookingById,
  cancelBooking: mockCancelBooking,
}))

vi.mock('@/shared/utils/toast', () => ({
  showToast: mockShowToast,
  getErrorMessage: (error: unknown) => (typeof error === 'string' ? error : 'Unexpected error'),
}))

vi.mock('./BookingDetailPanel', () => ({
  default: ({ bookingId, open }: { bookingId: number | null; open: boolean }) => (
    <div data-testid="admin-booking-detail-panel">
      {String(bookingId)}|{String(open)}
    </div>
  ),
}))

vi.mock('./ReviewForm', () => ({
  default: ({
    bookingId,
    variant,
    defaultOpen,
  }: {
    bookingId: number
    variant?: string
    defaultOpen?: boolean
  }) => (
    <div data-testid="review-form">
      {bookingId}|{variant ?? 'compact'}|{String(defaultOpen)}
    </div>
  ),
}))

import BookingDetailPage from './BookingDetailPage'

function makeDetail(overrides: Partial<BookingDetailRaw> = {}): BookingDetailRaw {
  return {
    id: 42,
    room_id: 10,
    user_id: 5,
    check_in: '2026-06-15',
    check_out: '2026-06-18',
    guest_name: 'Nguyễn Văn A',
    guest_email: 'vana@example.com',
    status: 'confirmed',
    status_label: 'Đã xác nhận',
    nights: 3,
    amount: 1050000,
    amount_formatted: '1.050.000 ₫',
    created_at: '2026-05-01T10:30:00+07:00',
    updated_at: '2026-05-01T10:30:00+07:00',
    room: {
      id: 10,
      name: 'Phòng Dormitory 4 giường',
      display_name: 'Phòng Dormitory 4 giường',
      room_number: '204',
      max_guests: 4,
      price: 350000,
    },
    ...overrides,
  }
}

function renderDetail(initialPath: string) {
  return render(
    <MemoryRouter initialEntries={[initialPath]}>
      <Routes>
        <Route path="/my-bookings/:id" element={<BookingDetailPage />} />
        <Route path="/admin/bookings/:id" element={<BookingDetailPage />} />
      </Routes>
    </MemoryRouter>
  )
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('BookingDetailPage', () => {
  it('renders the guest booking details page for /my-bookings/:id', async () => {
    mockGetBookingById.mockResolvedValue(
      makeDetail({
        check_in: '2020-06-15',
        check_out: '2020-06-18',
      })
    )

    renderDetail('/my-bookings/42')

    await waitFor(() => {
      expect(screen.getByText('Chi tiết đặt phòng')).toBeInTheDocument()
    })

    expect(screen.getByText('SOL-2026-0042')).toBeInTheDocument()
    expect(screen.getByText('Phòng Dormitory 4 giường (#204)')).toBeInTheDocument()
    expect(screen.getByText('Nguyễn Văn A')).toBeInTheDocument()
    expect(screen.getByText('vana@example.com')).toBeInTheDocument()
    expect(screen.getByText('1.050.000đ')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Hủy đặt phòng' })).toBeInTheDocument()
    expect(screen.getByTestId('review-form')).toHaveTextContent('42|detail|true')
  })

  it('opens the cancel dialog, cancels the booking, and refetches details', async () => {
    mockGetBookingById.mockResolvedValue(makeDetail())
    mockCancelBooking.mockResolvedValue({
      success: true,
      message: 'Đã hủy',
      data: makeDetail({ status: 'cancelled', status_label: 'Đã hủy' }),
    })

    renderDetail('/my-bookings/42')

    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Hủy đặt phòng' })).toBeInTheDocument()
    })

    await userEvent.click(screen.getByRole('button', { name: 'Hủy đặt phòng' }))
    expect(screen.getByRole('dialog')).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: 'Xác nhận hủy' }))

    await waitFor(() => {
      expect(mockCancelBooking).toHaveBeenCalledWith(42)
      expect(mockGetBookingById).toHaveBeenCalledTimes(2)
      expect(mockShowToast.success).toHaveBeenCalledWith('Đã hủy đặt phòng thành công.')
    })
  })

  it('keeps the admin booking route on the existing panel flow', () => {
    renderDetail('/admin/bookings/99')

    expect(screen.getByTestId('admin-booking-detail-panel')).toHaveTextContent('99|true')
    expect(mockGetBookingById).not.toHaveBeenCalled()
  })
})
