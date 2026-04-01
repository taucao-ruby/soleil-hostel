import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'

// ── Hoist mock before vi.mock() factory ──────────────────────
const { mockSubmitReview } = vi.hoisted(() => ({
  mockSubmitReview: vi.fn(),
}))

vi.mock('@/features/booking/booking.api', () => ({
  submitReview: mockSubmitReview,
}))

import ReviewForm from './ReviewForm'

// ── Render helper ────────────────────────────────────────────
function renderForm(props: Partial<React.ComponentProps<typeof ReviewForm>> = {}) {
  return render(<ReviewForm bookingId={1} {...props} />)
}

// ── Setup ────────────────────────────────────────────────────
beforeEach(() => {
  vi.clearAllMocks()
})

// ── Tests ────────────────────────────────────────────────────
describe('ReviewForm', () => {
  it('renders collapsed trigger button by default', () => {
    renderForm()
    expect(screen.getByRole('button', { name: 'Mở form viết đánh giá' })).toBeInTheDocument()
    // Form fields and submit action are not visible when collapsed
    expect(screen.queryByRole('button', { name: 'Gửi đánh giá' })).not.toBeInTheDocument()
    expect(screen.queryByRole('radiogroup')).not.toBeInTheDocument()
  })

  it('expands the form when trigger is clicked', async () => {
    renderForm()
    await userEvent.click(screen.getByRole('button', { name: 'Mở form viết đánh giá' }))

    expect(screen.getByText('Viết đánh giá')).toBeInTheDocument()
    expect(screen.getByRole('radiogroup', { name: 'Xếp hạng sao' })).toBeInTheDocument()
    expect(screen.getByRole('textbox', { name: /Tiêu đề/ })).toBeInTheDocument()
    expect(screen.getByRole('textbox', { name: /Nội dung/ })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Gửi đánh giá' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Hủy' })).toBeInTheDocument()
  })

  it('collapses back when Hủy is clicked', async () => {
    renderForm()
    await userEvent.click(screen.getByRole('button', { name: 'Mở form viết đánh giá' }))

    expect(screen.getByRole('button', { name: 'Gửi đánh giá' })).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: 'Hủy' }))

    // Submit button is gone; trigger button is back
    expect(screen.queryByRole('button', { name: 'Gửi đánh giá' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Mở form viết đánh giá' })).toBeInTheDocument()
  })

  it('shows error if submitting without selecting a star', async () => {
    renderForm()
    await userEvent.click(screen.getByRole('button', { name: 'Mở form viết đánh giá' }))
    await userEvent.type(screen.getByRole('textbox', { name: /Tiêu đề/ }), 'Test')
    await userEvent.type(screen.getByRole('textbox', { name: /Nội dung/ }), 'Test content')
    await userEvent.click(screen.getByRole('button', { name: 'Gửi đánh giá' }))

    expect(screen.getByRole('alert')).toHaveTextContent('Vui lòng chọn số sao đánh giá.')
    expect(mockSubmitReview).not.toHaveBeenCalled()
  })

  it('selects and highlights stars on click', async () => {
    renderForm()
    await userEvent.click(screen.getByRole('button', { name: 'Mở form viết đánh giá' }))

    const star3 = screen.getByRole('radio', { name: '3 sao' })
    await userEvent.click(star3)

    expect(star3).toHaveAttribute('aria-checked', 'true')
    expect(screen.getByRole('radio', { name: '1 sao' })).toHaveAttribute('aria-checked', 'false')
    expect(screen.getByRole('radio', { name: '5 sao' })).toHaveAttribute('aria-checked', 'false')
  })

  it('shows success state after successful submission', async () => {
    mockSubmitReview.mockResolvedValue({ success: true, message: 'OK' })
    renderForm({ bookingId: 42 })

    await userEvent.click(screen.getByRole('button', { name: 'Mở form viết đánh giá' }))
    await userEvent.click(screen.getByRole('radio', { name: '5 sao' }))
    await userEvent.type(screen.getByRole('textbox', { name: /Tiêu đề/ }), 'Tuyệt vời')
    await userEvent.type(screen.getByRole('textbox', { name: /Nội dung/ }), 'Phòng sạch, giá tốt.')
    await userEvent.click(screen.getByRole('button', { name: 'Gửi đánh giá' }))

    await waitFor(() => {
      expect(screen.getByText('Đánh giá của bạn đã được gửi thành công.')).toBeInTheDocument()
    })

    expect(mockSubmitReview).toHaveBeenCalledWith({
      booking_id: 42,
      title: 'Tuyệt vời',
      content: 'Phòng sạch, giá tốt.',
      rating: 5,
    })

    // Trigger button and form should no longer be visible
    expect(screen.queryByRole('button', { name: 'Mở form viết đánh giá' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Gửi đánh giá' })).not.toBeInTheDocument()
  })

  it('shows validation error from 422 response', async () => {
    const err = Object.assign(new Error('Validation'), {
      response: {
        status: 422,
        data: { errors: { rating: ['Rating must be between 1-5.'] } },
      },
    })
    mockSubmitReview.mockRejectedValue(err)
    renderForm()

    await userEvent.click(screen.getByRole('button', { name: 'Mở form viết đánh giá' }))
    await userEvent.click(screen.getByRole('radio', { name: '2 sao' }))
    await userEvent.type(screen.getByRole('textbox', { name: /Tiêu đề/ }), 'OK')
    await userEvent.type(screen.getByRole('textbox', { name: /Nội dung/ }), 'Content')
    await userEvent.click(screen.getByRole('button', { name: 'Gửi đánh giá' }))

    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent('Rating must be between 1-5.')
    })
  })

  it('shows message error from 403 response', async () => {
    const err = Object.assign(new Error('Forbidden'), {
      response: {
        status: 403,
        data: { message: 'Bạn không được phép viết đánh giá cho đặt phòng này.' },
      },
    })
    mockSubmitReview.mockRejectedValue(err)
    renderForm()

    await userEvent.click(screen.getByRole('button', { name: 'Mở form viết đánh giá' }))
    await userEvent.click(screen.getByRole('radio', { name: '4 sao' }))
    await userEvent.type(screen.getByRole('textbox', { name: /Tiêu đề/ }), 'Title')
    await userEvent.type(screen.getByRole('textbox', { name: /Nội dung/ }), 'Content here')
    await userEvent.click(screen.getByRole('button', { name: 'Gửi đánh giá' }))

    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent(
        'Bạn không được phép viết đánh giá cho đặt phòng này.'
      )
    })
  })

  it('shows generic error for unknown errors', async () => {
    mockSubmitReview.mockRejectedValue(new Error('Network error'))
    renderForm()

    await userEvent.click(screen.getByRole('button', { name: 'Mở form viết đánh giá' }))
    await userEvent.click(screen.getByRole('radio', { name: '3 sao' }))
    await userEvent.type(screen.getByRole('textbox', { name: /Tiêu đề/ }), 'Title')
    await userEvent.type(screen.getByRole('textbox', { name: /Nội dung/ }), 'Content')
    await userEvent.click(screen.getByRole('button', { name: 'Gửi đánh giá' }))

    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent(
        'Không thể gửi đánh giá. Vui lòng thử lại.'
      )
    })
  })

  it('clears error when form is collapsed and reopened', async () => {
    const err = Object.assign(new Error('Forbidden'), {
      response: { status: 403, data: { message: 'Not allowed.' } },
    })
    mockSubmitReview.mockRejectedValue(err)
    renderForm()

    await userEvent.click(screen.getByRole('button', { name: 'Mở form viết đánh giá' }))
    await userEvent.click(screen.getByRole('radio', { name: '1 sao' }))
    await userEvent.type(screen.getByRole('textbox', { name: /Tiêu đề/ }), 'T')
    await userEvent.type(screen.getByRole('textbox', { name: /Nội dung/ }), 'C')
    await userEvent.click(screen.getByRole('button', { name: 'Gửi đánh giá' }))

    await waitFor(() => expect(screen.getByRole('alert')).toBeInTheDocument())

    // Collapse
    await userEvent.click(screen.getByRole('button', { name: 'Hủy' }))
    // Reopen
    await userEvent.click(screen.getByRole('button', { name: 'Mở form viết đánh giá' }))

    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('renders the detail variant expanded by default', () => {
    renderForm({ variant: 'detail', defaultOpen: true })

    expect(screen.queryByRole('button', { name: 'Mở form viết đánh giá' })).not.toBeInTheDocument()
    expect(screen.getByRole('radiogroup', { name: 'Xếp hạng sao' })).toBeInTheDocument()
    expect(screen.queryByRole('textbox', { name: /Tiêu đề/ })).not.toBeInTheDocument()
    expect(screen.getByRole('textbox', { name: /Chia sẻ trải nghiệm của bạn/ })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Gửi đánh giá' })).toBeInTheDocument()
  })

  it('auto-generates a title in the detail variant when submitting', async () => {
    mockSubmitReview.mockResolvedValue({ success: true, message: 'OK' })
    renderForm({ bookingId: 9, variant: 'detail', defaultOpen: true })

    await userEvent.click(screen.getByRole('radio', { name: '5 sao' }))
    await userEvent.type(
      screen.getByRole('textbox', { name: /Chia sẻ trải nghiệm của bạn/ }),
      'Phòng sạch và nhân viên hỗ trợ rất nhiệt tình.'
    )
    await userEvent.click(screen.getByRole('button', { name: 'Gửi đánh giá' }))

    await waitFor(() => {
      expect(mockSubmitReview).toHaveBeenCalledWith({
        booking_id: 9,
        title: 'Xuất sắc',
        content: 'Phòng sạch và nhân viên hỗ trợ rất nhiệt tình.',
        rating: 5,
      })
    })
  })
})
