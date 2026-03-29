import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor, fireEvent } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import type { BookingDetailRaw } from '@/features/booking/booking.types'

// ── Hoist mock before vi.mock() factory ──────────────────────
const { mockGetBookingById, mockSubmitReview } = vi.hoisted(() => ({
  mockGetBookingById: vi.fn<(id: number, signal?: AbortSignal) => Promise<BookingDetailRaw>>(),
  mockSubmitReview: vi.fn(),
}))

vi.mock('@/features/booking/booking.api', () => ({
  getBookingById: mockGetBookingById,
  submitReview: mockSubmitReview,
}))

import BookingDetailPanel from './BookingDetailPanel'

// ── Factory ──────────────────────────────────────────────────
function makeDetail(overrides: Partial<BookingDetailRaw> = {}): BookingDetailRaw {
  return {
    id: 1,
    room_id: 10,
    user_id: 5,
    check_in: '2099-06-01',
    check_out: '2099-06-03',
    guest_name: 'Nguyễn Văn A',
    guest_email: 'guest@example.com',
    status: 'pending',
    status_label: 'Chờ xác nhận',
    nights: 2,
    amount_formatted: '200.000 ₫',
    created_at: '2026-05-20T10:00:00+00:00',
    updated_at: '2026-05-20T10:00:00+00:00',
    room: {
      id: 10,
      name: 'Phòng Deluxe',
      display_name: null,
      room_number: '101',
      max_guests: 2,
      price: 20000,
    },
    ...overrides,
  }
}

// ── Render helper ────────────────────────────────────────────
function renderPanel(props: Partial<React.ComponentProps<typeof BookingDetailPanel>> = {}) {
  const defaults = {
    bookingId: 1,
    open: true,
    onClose: vi.fn(),
  }
  return render(<BookingDetailPanel {...defaults} {...props} />)
}

// ── Setup ────────────────────────────────────────────────────
beforeEach(() => {
  vi.clearAllMocks()
})

// ── Tests ────────────────────────────────────────────────────
describe('BookingDetailPanel', () => {
  it('renders nothing when closed', () => {
    mockGetBookingById.mockResolvedValue(makeDetail())
    const { container } = renderPanel({ open: false })
    expect(container).toBeEmptyDOMElement()
  })

  it('does not call API when closed', () => {
    mockGetBookingById.mockResolvedValue(makeDetail())
    renderPanel({ open: false })
    expect(mockGetBookingById).not.toHaveBeenCalled()
  })

  it('shows loading skeleton while fetching', () => {
    // Never resolves — stays in loading state
    mockGetBookingById.mockReturnValue(new Promise(() => {}))
    renderPanel()
    // Skeleton components have role="status"
    expect(screen.getAllByRole('status').length).toBeGreaterThan(0)
  })

  it('renders booking details on success', async () => {
    mockGetBookingById.mockResolvedValue(makeDetail())
    renderPanel()

    await waitFor(() => {
      expect(screen.getByText('Phòng Deluxe (#101)')).toBeInTheDocument()
      expect(screen.getByText('Chờ xác nhận')).toBeInTheDocument()
      expect(screen.getByText('200.000 ₫')).toBeInTheDocument()
      expect(screen.getByText('Nguyễn Văn A')).toBeInTheDocument()
      expect(screen.getByText('guest@example.com')).toBeInTheDocument()
      expect(screen.getByText('2 đêm')).toBeInTheDocument()
    })
  })

  it('shows error state when fetch fails', async () => {
    mockGetBookingById.mockRejectedValue(new Error('Network error'))
    renderPanel()

    await waitFor(() => {
      expect(screen.getByText('Không thể tải chi tiết đặt phòng.')).toBeInTheDocument()
      expect(screen.getByRole('button', { name: 'Thử lại' })).toBeInTheDocument()
    })
  })

  it('retries fetch when Thử lại is clicked', async () => {
    mockGetBookingById
      .mockRejectedValueOnce(new Error('Network error'))
      .mockResolvedValue(makeDetail())

    renderPanel()

    await waitFor(() => {
      expect(screen.getByText('Không thể tải chi tiết đặt phòng.')).toBeInTheDocument()
    })

    await userEvent.click(screen.getByRole('button', { name: 'Thử lại' }))

    await waitFor(() => {
      expect(screen.getByText('Phòng Deluxe (#101)')).toBeInTheDocument()
    })

    expect(mockGetBookingById).toHaveBeenCalledTimes(2)
  })

  it('calls onClose when Escape key is pressed', () => {
    mockGetBookingById.mockReturnValue(new Promise(() => {}))
    const onClose = vi.fn()
    renderPanel({ onClose })

    fireEvent.keyDown(document, { key: 'Escape' })
    expect(onClose).toHaveBeenCalledTimes(1)
  })

  it('calls onClose when backdrop is clicked', async () => {
    mockGetBookingById.mockReturnValue(new Promise(() => {}))
    const onClose = vi.fn()
    renderPanel({ onClose })

    // The backdrop is the outermost div with role="dialog"
    const dialog = screen.getByRole('dialog')
    await userEvent.click(dialog)
    expect(onClose).toHaveBeenCalledTimes(1)
  })

  it('calls onClose when Đóng button is clicked', async () => {
    mockGetBookingById.mockReturnValue(new Promise(() => {}))
    const onClose = vi.fn()
    renderPanel({ onClose })

    await userEvent.click(screen.getByRole('button', { name: 'Đóng' }))
    expect(onClose).toHaveBeenCalledTimes(1)
  })

  it('shows cancelled_at date when booking is cancelled', async () => {
    mockGetBookingById.mockResolvedValue(
      makeDetail({
        status: 'cancelled',
        status_label: 'Đã hủy',
        cancelled_at: '2026-05-01T00:00:00+00:00',
      })
    )
    renderPanel()

    await waitFor(() => {
      expect(screen.getByText('Đã hủy')).toBeInTheDocument()
      // cancelled_at formatted as Vietnamese date dd/MM/yyyy
      expect(screen.getByText(/01\/05\/2026/)).toBeInTheDocument()
    })
  })

  it('does not show cancelled_at section for non-cancelled bookings', async () => {
    mockGetBookingById.mockResolvedValue(makeDetail({ status: 'pending', cancelled_at: undefined }))
    renderPanel()

    await waitFor(() => {
      expect(screen.getByText('Chờ xác nhận')).toBeInTheDocument()
    })

    expect(screen.queryByText('Hủy lúc')).not.toBeInTheDocument()
  })

  it('shows refund amount when present', async () => {
    mockGetBookingById.mockResolvedValue(
      makeDetail({
        status: 'cancelled',
        status_label: 'Đã hủy',
        refund_amount_formatted: '150.000 ₫',
      })
    )
    renderPanel()

    await waitFor(() => {
      expect(screen.getByText('150.000 ₫')).toBeInTheDocument()
      expect(screen.getByText('Hoàn tiền')).toBeInTheDocument()
    })
  })

  it('does not call API again when reopened with same bookingId', async () => {
    mockGetBookingById.mockResolvedValue(makeDetail())
    const { rerender } = renderPanel({ open: true })

    await waitFor(() => expect(screen.getByText('Phòng Deluxe (#101)')).toBeInTheDocument())
    expect(mockGetBookingById).toHaveBeenCalledTimes(1)

    // Close and reopen with the same bookingId (open changes, bookingId stays the same)
    rerender(<BookingDetailPanel bookingId={1} open={false} onClose={vi.fn()} />)
    rerender(<BookingDetailPanel bookingId={1} open={true} onClose={vi.fn()} />)

    await waitFor(() => expect(mockGetBookingById).toHaveBeenCalledTimes(2))
  })

  // ── PR-4B.1: Review form eligibility ─────────────────────

  it('shows review form trigger when booking is confirmed and check_out is past', async () => {
    mockGetBookingById.mockResolvedValue(
      makeDetail({
        status: 'confirmed',
        status_label: 'Đã xác nhận',
        check_in: '2020-01-01',
        check_out: '2020-01-03',
      })
    )
    renderPanel()

    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Mở form viết đánh giá' })).toBeInTheDocument()
    })
  })

  it('hides review form when booking is confirmed but check_out is in the future', async () => {
    mockGetBookingById.mockResolvedValue(
      makeDetail({
        status: 'confirmed',
        status_label: 'Đã xác nhận',
        check_in: '2099-06-01',
        check_out: '2099-06-03',
      })
    )
    renderPanel()

    await waitFor(() => {
      expect(screen.getByText('Đã xác nhận')).toBeInTheDocument()
    })

    expect(screen.queryByRole('button', { name: 'Mở form viết đánh giá' })).not.toBeInTheDocument()
  })

  it('hides review form when booking is not confirmed', async () => {
    mockGetBookingById.mockResolvedValue(
      makeDetail({
        status: 'cancelled',
        status_label: 'Đã hủy',
        check_in: '2020-01-01',
        check_out: '2020-01-03',
      })
    )
    renderPanel()

    await waitFor(() => {
      expect(screen.getByText('Đã hủy')).toBeInTheDocument()
    })

    expect(screen.queryByRole('button', { name: 'Mở form viết đánh giá' })).not.toBeInTheDocument()
  })

  it('expands review form when trigger button is clicked', async () => {
    mockGetBookingById.mockResolvedValue(
      makeDetail({
        status: 'confirmed',
        check_in: '2020-01-01',
        check_out: '2020-01-03',
      })
    )
    renderPanel()

    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Mở form viết đánh giá' })).toBeInTheDocument()
    })

    await userEvent.click(screen.getByRole('button', { name: 'Mở form viết đánh giá' }))

    expect(screen.getByText('Viết đánh giá')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Gửi đánh giá' })).toBeInTheDocument()
  })

  it('shows success state after review is submitted', async () => {
    mockGetBookingById.mockResolvedValue(
      makeDetail({
        status: 'confirmed',
        check_in: '2020-01-01',
        check_out: '2020-01-03',
      })
    )
    mockSubmitReview.mockResolvedValue({ success: true, message: '...' })
    renderPanel()

    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Mở form viết đánh giá' })).toBeInTheDocument()
    })

    // Open form
    await userEvent.click(screen.getByRole('button', { name: 'Mở form viết đánh giá' }))

    // Select a star rating
    await userEvent.click(screen.getByRole('radio', { name: '4 sao' }))

    // Fill title
    await userEvent.type(screen.getByRole('textbox', { name: /Tiêu đề/ }), 'Rất tốt')

    // Fill content
    await userEvent.type(
      screen.getByRole('textbox', { name: /Nội dung/ }),
      'Không gian sạch sẽ, thân thiện.'
    )

    // Submit
    await userEvent.click(screen.getByRole('button', { name: 'Gửi đánh giá' }))

    await waitFor(() => {
      expect(screen.getByText('Đánh giá của bạn đã được gửi thành công.')).toBeInTheDocument()
    })

    expect(mockSubmitReview).toHaveBeenCalledWith({
      booking_id: 1,
      title: 'Rất tốt',
      content: 'Không gian sạch sẽ, thân thiện.',
      rating: 4,
    })
  })

  it('shows validation error from backend on 422', async () => {
    mockGetBookingById.mockResolvedValue(
      makeDetail({
        status: 'confirmed',
        check_in: '2020-01-01',
        check_out: '2020-01-03',
      })
    )
    const err = Object.assign(new Error('Validation'), {
      response: { status: 422, data: { errors: { title: ['The title field is required.'] } } },
    })
    mockSubmitReview.mockRejectedValue(err)
    renderPanel()

    await waitFor(() => screen.getByRole('button', { name: 'Mở form viết đánh giá' }))
    await userEvent.click(screen.getByRole('button', { name: 'Mở form viết đánh giá' }))
    await userEvent.click(screen.getByRole('radio', { name: '3 sao' }))
    await userEvent.click(screen.getByRole('button', { name: 'Gửi đánh giá' }))

    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent('The title field is required.')
    })
  })

  it('shows policy denial message on 403 (duplicate review)', async () => {
    mockGetBookingById.mockResolvedValue(
      makeDetail({
        status: 'confirmed',
        check_in: '2020-01-01',
        check_out: '2020-01-03',
      })
    )
    const err = Object.assign(new Error('Forbidden'), {
      response: {
        status: 403,
        data: { message: 'Review already exists for this booking.' },
      },
    })
    mockSubmitReview.mockRejectedValue(err)
    renderPanel()

    await waitFor(() => screen.getByRole('button', { name: 'Mở form viết đánh giá' }))
    await userEvent.click(screen.getByRole('button', { name: 'Mở form viết đánh giá' }))
    await userEvent.click(screen.getByRole('radio', { name: '5 sao' }))
    await userEvent.type(screen.getByRole('textbox', { name: /Tiêu đề/ }), 'Test')
    await userEvent.type(screen.getByRole('textbox', { name: /Nội dung/ }), 'Test content')
    await userEvent.click(screen.getByRole('button', { name: 'Gửi đánh giá' }))

    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent('Review already exists for this booking.')
    })
  })

  it('shows error when rating is 0 on submit', async () => {
    mockGetBookingById.mockResolvedValue(
      makeDetail({
        status: 'confirmed',
        check_in: '2020-01-01',
        check_out: '2020-01-03',
      })
    )
    renderPanel()

    await waitFor(() => screen.getByRole('button', { name: 'Mở form viết đánh giá' }))
    await userEvent.click(screen.getByRole('button', { name: 'Mở form viết đánh giá' }))
    // Do NOT select a star, just submit
    await userEvent.click(screen.getByRole('button', { name: 'Gửi đánh giá' }))

    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent('Vui lòng chọn số sao đánh giá.')
    })

    expect(mockSubmitReview).not.toHaveBeenCalled()
  })

  // ── PR-4B.2: refund_failed support notice ─────────────────

  it('shows refund_failed support notice for refund_failed bookings', async () => {
    mockGetBookingById.mockResolvedValue(
      makeDetail({
        status: 'refund_failed',
        status_label: 'Hoàn tiền thất bại',
        check_in: '2020-01-01',
        check_out: '2020-01-03',
      })
    )
    renderPanel()

    await waitFor(() => {
      expect(screen.getByRole('alert')).toBeInTheDocument()
      expect(screen.getByRole('alert')).toHaveTextContent('Hoàn tiền thất bại')
      expect(screen.getByRole('alert')).toHaveTextContent('đội ngũ sẽ xử lý hoàn tiền thủ công')
    })
  })

  it('does NOT show refund_failed notice for cancelled bookings', async () => {
    mockGetBookingById.mockResolvedValue(
      makeDetail({
        status: 'cancelled',
        status_label: 'Đã hủy',
        check_in: '2020-01-01',
        check_out: '2020-01-03',
      })
    )
    renderPanel()

    await waitFor(() => {
      expect(screen.getByText('Đã hủy')).toBeInTheDocument()
    })

    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('does NOT show refund_failed notice for refund_pending bookings', async () => {
    mockGetBookingById.mockResolvedValue(
      makeDetail({
        status: 'refund_pending',
        status_label: 'Đang hoàn tiền',
        check_in: '2020-01-01',
        check_out: '2020-01-03',
      })
    )
    renderPanel()

    await waitFor(() => {
      expect(screen.getByText('Đang hoàn tiền')).toBeInTheDocument()
    })

    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })
})
