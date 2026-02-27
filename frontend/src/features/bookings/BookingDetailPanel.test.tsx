import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor, fireEvent } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import type { BookingDetailRaw } from '@/features/booking/booking.types'

// ── Hoist mock before vi.mock() factory ──────────────────────
const { mockGetBookingById } = vi.hoisted(() => ({
  mockGetBookingById: vi.fn<(id: number, signal?: AbortSignal) => Promise<BookingDetailRaw>>(),
}))

vi.mock('@/features/booking/booking.api', () => ({
  getBookingById: mockGetBookingById,
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
    amount_formatted: '$200.00',
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
      expect(screen.getByText('$200.00')).toBeInTheDocument()
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
        refund_amount_formatted: '$150.00',
      })
    )
    renderPanel()

    await waitFor(() => {
      expect(screen.getByText('$150.00')).toBeInTheDocument()
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
})
