import { describe, it, expect, vi, beforeEach } from 'vitest'
import { act, render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import type React from 'react'
import type { BookingApiRaw } from '@/shared/types/booking.types'
import type { CancelBookingResponse } from '@/features/booking/booking.types'
import { showToast } from '@/shared/utils/toast'
import BookingCancelDialog from '@/features/booking/BookingCancelDialog'

const { mockCancelBooking } = vi.hoisted(() => ({
  mockCancelBooking: vi.fn<(id: number) => Promise<CancelBookingResponse>>(),
}))

// BookingCancelDialog imports './booking.api'; the alias resolves to the same module.
vi.mock('@/features/booking/booking.api', () => ({
  cancelBooking: mockCancelBooking,
}))

const successToastSpy = vi.spyOn(showToast, 'success').mockImplementation(() => {})
const errorToastSpy = vi.spyOn(showToast, 'error').mockImplementation(() => {})

function makeRawBooking(overrides: Partial<BookingApiRaw> = {}): BookingApiRaw {
  return {
    id: 42,
    room_id: 7,
    user_id: 3,
    check_in: '2026-07-01',
    check_out: '2026-07-04',
    guest_name: 'Nguyen Van A',
    guest_email: 'user@example.com',
    number_of_guests: 2,
    special_requests: null,
    status: 'confirmed',
    status_label: 'Đã xác nhận',
    nights: 3,
    payment_policy: 'prepaid',
    payment_status: 'paid',
    created_at: '2026-06-01T10:00:00Z',
    updated_at: '2026-06-01T10:00:00Z',
    ...overrides,
  }
}

type DialogProps = React.ComponentProps<typeof BookingCancelDialog>

function renderDialog(overrides: Partial<DialogProps> = {}) {
  const props: DialogProps = {
    booking: makeRawBooking(),
    isOpen: true,
    onClose: vi.fn(),
    onSuccess: vi.fn(),
    ...overrides,
  }
  render(<BookingCancelDialog {...props} />)
  return props
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('BookingCancelDialog', () => {
  it('renders nothing when closed', () => {
    renderDialog({ isOpen: false })

    expect(screen.queryByRole('heading', { name: /Hủy đặt phòng/ })).not.toBeInTheDocument()
  })

  it('renders nothing without a booking', () => {
    renderDialog({ booking: null })

    expect(screen.queryByRole('heading', { name: /Hủy đặt phòng/ })).not.toBeInTheDocument()
  })

  it('shows the Vietnamese confirmation copy for the targeted booking', () => {
    renderDialog()

    expect(screen.getByRole('heading', { name: 'Hủy đặt phòng #42' })).toBeInTheDocument()
    expect(
      screen.getByText(
        'Bạn có chắc chắn muốn hủy đặt phòng này không? Hành động này không thể hoàn tác.'
      )
    ).toBeInTheDocument()
    expect(screen.getByLabelText('Lý do hủy (Không bắt buộc)')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Xác nhận Hủy' })).toBeEnabled()
    expect(screen.getByRole('button', { name: 'Quay lại' })).toBeEnabled()
  })

  it('captures the optional cancellation reason as the guest types', async () => {
    const user = userEvent.setup()
    renderDialog()

    const reason = screen.getByLabelText('Lý do hủy (Không bắt buộc)')
    await user.type(reason, 'Đổi lịch trình')

    expect(reason).toHaveValue('Đổi lịch trình')
  })

  it('cancels the booking, toasts success, and propagates the updated booking', async () => {
    const cancelledBooking = makeRawBooking({ status: 'cancelled', status_label: 'Đã hủy' })
    mockCancelBooking.mockResolvedValueOnce({
      success: true,
      message: 'Đã hủy đặt phòng.',
      data: cancelledBooking,
    })
    const user = userEvent.setup()
    const { onClose, onSuccess } = renderDialog()

    await user.click(screen.getByRole('button', { name: 'Xác nhận Hủy' }))

    await waitFor(() => expect(onClose).toHaveBeenCalledTimes(1))
    expect(mockCancelBooking).toHaveBeenCalledWith(42)
    expect(successToastSpy).toHaveBeenCalledWith('Hủy đặt phòng thành công.')
    expect(onSuccess).toHaveBeenCalledWith(cancelledBooking)
  })

  it('toasts the Vietnamese error and keeps the dialog open when cancellation fails', async () => {
    mockCancelBooking.mockRejectedValueOnce(new Error('boom'))
    const user = userEvent.setup()
    const { onClose, onSuccess } = renderDialog()

    await user.click(screen.getByRole('button', { name: 'Xác nhận Hủy' }))

    await waitFor(() =>
      expect(errorToastSpy).toHaveBeenCalledWith('Không thể hủy đặt phòng lúc này.')
    )
    expect(onSuccess).not.toHaveBeenCalled()
    expect(onClose).not.toHaveBeenCalled()
    expect(screen.getByRole('button', { name: 'Xác nhận Hủy' })).toBeEnabled()
  })

  it('disables both actions and shows pending copy while submitting', async () => {
    let resolveCancel!: (value: CancelBookingResponse) => void
    mockCancelBooking.mockImplementationOnce(
      () =>
        new Promise<CancelBookingResponse>(resolve => {
          resolveCancel = resolve
        })
    )
    const user = userEvent.setup()
    renderDialog()

    await user.click(screen.getByRole('button', { name: 'Xác nhận Hủy' }))

    expect(await screen.findByRole('button', { name: 'Đang xử lý...' })).toBeDisabled()
    expect(screen.getByRole('button', { name: 'Quay lại' })).toBeDisabled()

    await act(async () => {
      resolveCancel({
        success: true,
        message: 'Đã hủy đặt phòng.',
        data: makeRawBooking({ status: 'cancelled' }),
      })
    })
  })

  it('closes via the back button without cancelling', async () => {
    const user = userEvent.setup()
    const { onClose } = renderDialog()

    await user.click(screen.getByRole('button', { name: 'Quay lại' }))

    expect(onClose).toHaveBeenCalledTimes(1)
    expect(mockCancelBooking).not.toHaveBeenCalled()
  })
})
