import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import type React from 'react'
import BookingForm from './BookingForm'

const {
  mockNavigate,
  mockSearchParamsRef,
  mockGetRooms,
  mockCreateBooking,
  mockCreateBookingPaymentIntent,
  mockVerifyBookingPayment,
  mockConfirmPayment,
  mockValidateBookingForm,
  mockGetMinCheckInDate,
  mockGetMinCheckOutDate,
  mockGetMaxCheckOutDate,
  mockCalculateNights,
} = vi.hoisted(() => {
  vi.stubEnv('VITE_STRIPE_PUBLISHABLE_KEY', 'pk_test_unit')

  return {
    mockNavigate: vi.fn(),
    mockSearchParamsRef: { current: new URLSearchParams() },
    mockGetRooms: vi.fn(),
    mockCreateBooking: vi.fn(),
    mockCreateBookingPaymentIntent: vi.fn(),
    mockVerifyBookingPayment: vi.fn(),
    mockConfirmPayment: vi.fn(),
    mockValidateBookingForm: vi.fn(),
    mockGetMinCheckInDate: vi.fn(),
    mockGetMinCheckOutDate: vi.fn(),
    mockGetMaxCheckOutDate: vi.fn(),
    mockCalculateNights: vi.fn(),
  }
})

vi.mock('react-router-dom', () => ({
  useNavigate: () => mockNavigate,
  useSearchParams: () => [mockSearchParamsRef.current],
}))

vi.mock('../rooms/room.api', () => ({
  getRooms: (...args: unknown[]) => mockGetRooms(...args),
}))

vi.mock('./booking.api', () => ({
  createBooking: (...args: unknown[]) => mockCreateBooking(...args),
  createBookingPaymentIntent: (...args: unknown[]) => mockCreateBookingPaymentIntent(...args),
  verifyBookingPayment: (...args: unknown[]) => mockVerifyBookingPayment(...args),
}))

vi.mock('@stripe/stripe-js', () => ({
  loadStripe: vi.fn(() => Promise.resolve({})),
}))

vi.mock('@stripe/react-stripe-js', () => ({
  Elements: ({ children }: { children: React.ReactNode }) => (
    <div data-testid="stripe-elements">{children}</div>
  ),
  PaymentElement: () => <div data-testid="payment-element" />,
  useElements: () => ({}),
  useStripe: () => ({
    confirmPayment: (...args: unknown[]) => mockConfirmPayment(...args),
  }),
}))

vi.mock('./booking.validation', () => ({
  MAX_STAY_DAYS: 30,
  validateBookingForm: (...args: unknown[]) => mockValidateBookingForm(...args),
  getMinCheckInDate: (...args: unknown[]) => mockGetMinCheckInDate(...args),
  getMinCheckOutDate: (...args: unknown[]) => mockGetMinCheckOutDate(...args),
  getMaxCheckOutDate: (...args: unknown[]) => mockGetMaxCheckOutDate(...args),
  calculateNights: (...args: unknown[]) => mockCalculateNights(...args),
}))

function makeAxiosError(response?: { status: number; data?: unknown }) {
  return Object.assign(new Error('Request failed'), {
    isAxiosError: true,
    response,
  })
}

function makeBooking(overrides: Record<string, unknown> = {}) {
  return {
    id: 42,
    room_id: 1,
    user_id: 1,
    guest_name: 'Nguyen Van A',
    guest_email: 'user@example.com',
    check_in: '2026-06-15',
    check_out: '2026-06-18',
    number_of_guests: 2,
    special_requests: null,
    status: 'pending',
    status_label: null,
    nights: 3,
    total_price: 1050000,
    payment_policy: 'prepaid',
    payment_status: 'requires_payment_method',
    payment_currency: 'vnd',
    created_at: '2026-06-01T10:00:00Z',
    updated_at: '2026-06-01T10:00:00Z',
    ...overrides,
  }
}

async function fillValidBookingAndSubmit() {
  await waitFor(() => {
    expect(screen.getByRole('option', { name: /Phòng Dormitory 4 giường/i })).toBeInTheDocument()
  })

  fireEvent.change(screen.getByLabelText(/Chọn phòng/), { target: { value: '1' } })
  fireEvent.change(screen.getByLabelText(/Họ và tên/), { target: { value: 'Nguyen Van A' } })
  fireEvent.change(screen.getByLabelText(/Địa chỉ email/), {
    target: { value: 'user@example.com' },
  })
  fireEvent.change(screen.getByLabelText(/Ngày nhận phòng/), { target: { value: '2026-06-15' } })
  fireEvent.change(screen.getByLabelText(/Ngày trả phòng/), { target: { value: '2026-06-18' } })
  fireEvent.click(screen.getByRole('button', { name: 'Giữ phòng và thanh toán' }))
}

async function fillValidBookingWithSpecialRequestAndSubmit() {
  await waitFor(() => {
    expect(screen.getByRole('option', { name: /Phòng Dormitory 4 giường/i })).toBeInTheDocument()
  })

  fireEvent.change(screen.getByLabelText(/Chọn phòng/), { target: { value: '1' } })
  fireEvent.change(screen.getByLabelText(/Họ và tên/), { target: { value: 'Nguyen Van A' } })
  fireEvent.change(screen.getByLabelText(/Địa chỉ email/), {
    target: { value: 'user@example.com' },
  })
  fireEvent.change(screen.getByLabelText(/Ngày nhận phòng/), { target: { value: '2026-06-15' } })
  fireEvent.change(screen.getByLabelText(/Ngày trả phòng/), { target: { value: '2026-06-18' } })
  fireEvent.change(screen.getByLabelText(/Số khách/), { target: { value: '3' } })
  fireEvent.change(screen.getByLabelText(/Yêu cầu đặc biệt/), {
    target: { value: '  Phòng tầng cao  ' },
  })
  fireEvent.click(screen.getByRole('button', { name: 'Giữ phòng và thanh toán' }))
}

describe('BookingForm', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.useRealTimers()
    mockSearchParamsRef.current = new URLSearchParams()

    mockGetRooms.mockResolvedValue([
      {
        id: 1,
        name: 'Dormitory 4 giường',
        price: 350000,
        status: 'available',
        description: 'Shared room',
        image_url: null,
        created_at: '',
        updated_at: '',
      },
      {
        id: 2,
        name: 'Private Double',
        price: 600000,
        status: 'available',
        description: 'Private room',
        image_url: null,
        created_at: '',
        updated_at: '',
      },
      {
        id: 3,
        name: 'Maintenance Room',
        price: 100000,
        status: 'maintenance',
        description: '',
        image_url: null,
        created_at: '',
        updated_at: '',
      },
    ])
    mockCreateBooking.mockResolvedValue(makeBooking())
    mockCreateBookingPaymentIntent.mockResolvedValue({
      client_secret: 'pi_client_secret_unit',
      payment_policy: 'prepaid',
      payment_status: 'requires_payment_method',
    })
    mockConfirmPayment.mockResolvedValue({})
    mockVerifyBookingPayment.mockResolvedValue(
      makeBooking({
        status: 'confirmed',
        payment_status: 'paid',
      })
    )
    mockValidateBookingForm.mockReturnValue({})
    mockGetMinCheckInDate.mockReturnValue('2026-04-01')
    mockGetMinCheckOutDate.mockReturnValue('2026-04-02')
    mockGetMaxCheckOutDate.mockReturnValue('2026-05-01')
    mockCalculateNights.mockReturnValue(3)
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('renders the booking form and the summary card', async () => {
    render(<BookingForm />)

    expect(screen.getByRole('heading', { name: 'Đặt phòng' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Tóm tắt đặt phòng' })).toBeInTheDocument()
    expect(
      screen.getByText('Thanh toán trực tuyến bảo mật · Xác nhận sau khi thanh toán thành công')
    ).toBeInTheDocument()

    await waitFor(() => {
      expect(screen.getByRole('option', { name: /Phòng Dormitory 4 giường/i })).toBeInTheDocument()
    })
  })

  it('shows a disabled select and inline spinner while rooms are loading', () => {
    mockGetRooms.mockImplementation(() => new Promise(() => {}))

    render(<BookingForm />)

    expect(screen.getByRole('combobox')).toBeDisabled()
    expect(screen.getAllByText('Đang tải danh sách phòng...')).not.toHaveLength(0)
  })

  it('loads only available rooms in the dropdown', async () => {
    render(<BookingForm />)

    await waitFor(() => {
      expect(screen.getByRole('option', { name: /Phòng Dormitory 4 giường/i })).toBeInTheDocument()
      expect(screen.getByRole('option', { name: /Phòng Private Double/i })).toBeInTheDocument()
    })

    expect(screen.queryByRole('option', { name: /Maintenance Room/i })).not.toBeInTheDocument()
  })

  it('pre-fills room, dates, guests, and summary details from URL params', async () => {
    mockSearchParamsRef.current = new URLSearchParams(
      'room_id=1&check_in=2026-06-15&check_out=2026-06-18&guests=3'
    )

    render(<BookingForm />)

    await waitFor(() => {
      expect(screen.getByLabelText(/Chọn phòng/)).toHaveValue('1')
      expect(screen.getByLabelText(/Ngày nhận phòng/)).toHaveValue('2026-06-15')
      expect(screen.getByLabelText(/Ngày trả phòng/)).toHaveValue('2026-06-18')
      expect(screen.getByLabelText(/Số khách/)).toHaveValue(3)
    })

    expect(screen.getByText('15/06/2026 → 18/06/2026')).toBeInTheDocument()
    expect(screen.getByText('3 đêm × 350.000₫')).toBeInTheDocument()
    expect(screen.getAllByText('1.050.000₫')).toHaveLength(2)
  })

  it('shows an empty state when no available rooms remain', async () => {
    mockGetRooms.mockResolvedValue([
      {
        id: 4,
        name: 'Booked Room',
        price: 450000,
        status: 'booked',
        description: '',
        image_url: null,
        created_at: '',
        updated_at: '',
      },
    ])

    render(<BookingForm />)

    expect(await screen.findByRole('status')).toHaveTextContent('Không có phòng nào còn trống')
  })

  it('renders validation errors returned by the validator', async () => {
    mockValidateBookingForm.mockReturnValue({
      guest_name: 'Vui lòng nhập họ và tên',
      guest_email: 'Vui lòng nhập địa chỉ email',
    })

    render(<BookingForm />)

    const user = userEvent.setup()
    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Giữ phòng và thanh toán' })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: 'Giữ phòng và thanh toán' }))

    expect(screen.getByText('Vui lòng nhập họ và tên')).toBeInTheDocument()
    expect(screen.getByText('Vui lòng nhập địa chỉ email')).toBeInTheDocument()
    expect(mockCreateBooking).not.toHaveBeenCalled()
  })

  describe('submit error disambiguation', () => {
    it('shows the overlap-specific message on a 409 conflict', async () => {
      mockCreateBooking.mockRejectedValue(makeAxiosError({ status: 409 }))

      render(<BookingForm />)
      await fillValidBookingAndSubmit()

      expect(await screen.findByTestId('error-message')).toHaveTextContent(
        'Phòng đã có người đặt trong khoảng thời gian này. Vui lòng chọn ngày khác.'
      )
    })

    it('surfaces the backend message on a 422 pending-limit failure', async () => {
      mockCreateBooking.mockRejectedValue(
        makeAxiosError({
          status: 422,
          data: { message: 'Bạn đã đạt giới hạn đơn đặt phòng đang chờ xử lý.' },
        })
      )

      render(<BookingForm />)
      await fillValidBookingAndSubmit()

      expect(await screen.findByTestId('error-message')).toHaveTextContent(
        'Bạn đã đạt giới hạn đơn đặt phòng đang chờ xử lý.'
      )
    })

    it('surfaces the backend message on a 422 max-guests failure', async () => {
      mockCreateBooking.mockRejectedValue(
        makeAxiosError({
          status: 422,
          data: { message: 'Số khách vượt quá sức chứa tối đa của phòng.' },
        })
      )

      render(<BookingForm />)
      await fillValidBookingAndSubmit()

      expect(await screen.findByTestId('error-message')).toHaveTextContent(
        'Số khách vượt quá sức chứa tối đa của phòng.'
      )
    })

    it('shows the validation fallback on a 422 without a usable message', async () => {
      mockCreateBooking.mockRejectedValue(makeAxiosError({ status: 422, data: { message: '   ' } }))

      render(<BookingForm />)
      await fillValidBookingAndSubmit()

      expect(await screen.findByTestId('error-message')).toHaveTextContent(
        'Thông tin đặt phòng không hợp lệ. Vui lòng kiểm tra lại.'
      )
    })

    it('shows the generic message on a 500 server error', async () => {
      mockCreateBooking.mockRejectedValue(makeAxiosError({ status: 500 }))

      render(<BookingForm />)
      await fillValidBookingAndSubmit()

      expect(await screen.findByTestId('error-message')).toHaveTextContent(
        'Không thể tạo đặt phòng lúc này. Vui lòng thử lại sau.'
      )
    })

    it('shows the generic message on a network error with no response', async () => {
      mockCreateBooking.mockRejectedValue(makeAxiosError())

      render(<BookingForm />)
      await fillValidBookingAndSubmit()

      expect(await screen.findByTestId('error-message')).toHaveTextContent(
        'Không thể tạo đặt phòng lúc này. Vui lòng thử lại sau.'
      )
    })

    it('shows the generic message on a non-Axios error', async () => {
      mockCreateBooking.mockRejectedValue(new Error('conflict'))

      render(<BookingForm />)
      await fillValidBookingAndSubmit()

      expect(await screen.findByTestId('error-message')).toHaveTextContent(
        'Không thể tạo đặt phòng lúc này. Vui lòng thử lại sau.'
      )
    })
  })

  it('submits number_of_guests and null special_requests for a blank note', async () => {
    mockCreateBooking.mockResolvedValue(makeBooking())

    render(<BookingForm />)
    await fillValidBookingAndSubmit()

    expect(mockCreateBooking).toHaveBeenCalledWith(
      expect.objectContaining({
        number_of_guests: 1,
        special_requests: null,
      })
    )
  })

  it('trims and submits special_requests when provided', async () => {
    mockCreateBooking.mockResolvedValue(
      makeBooking({
        id: 43,
        number_of_guests: 3,
        special_requests: 'Phòng tầng cao',
      })
    )

    render(<BookingForm />)
    await fillValidBookingWithSpecialRequestAndSubmit()

    expect(mockCreateBooking).toHaveBeenCalledWith(
      expect.objectContaining({
        number_of_guests: 3,
        special_requests: 'Phòng tầng cao',
      })
    )
  })

  it('renders Stripe checkout, verifies payment, and redirects after success', async () => {
    render(<BookingForm />)

    await waitFor(() => {
      expect(screen.getByLabelText(/Chọn phòng/)).toBeInTheDocument()
    })

    fireEvent.change(screen.getByLabelText(/Chọn phòng/), { target: { value: '1' } })
    fireEvent.change(screen.getByLabelText(/Họ và tên/), {
      target: { value: 'Nguyen Van A' },
    })
    fireEvent.change(screen.getByLabelText(/Địa chỉ email/), {
      target: { value: 'user@example.com' },
    })
    fireEvent.change(screen.getByLabelText(/Ngày nhận phòng/), {
      target: { value: '2026-06-15' },
    })
    fireEvent.change(screen.getByLabelText(/Ngày trả phòng/), {
      target: { value: '2026-06-18' },
    })
    fireEvent.click(screen.getByRole('button', { name: 'Giữ phòng và thanh toán' }))

    expect(await screen.findByTestId('payment-step')).toBeInTheDocument()
    expect(screen.getByTestId('payment-element')).toBeInTheDocument()
    expect(mockCreateBookingPaymentIntent).toHaveBeenCalledWith(42)

    fireEvent.click(screen.getByRole('button', { name: 'Thanh toán và xác nhận đặt phòng' }))

    expect(await screen.findByTestId('success-message')).toBeInTheDocument()
    expect(mockConfirmPayment).toHaveBeenCalledWith(
      expect.objectContaining({ redirect: 'if_required' })
    )
    expect(mockVerifyBookingPayment).toHaveBeenCalledWith(42)
    expect(screen.getByTestId('booking-reference')).toHaveTextContent('SOL-2026-0042')
    expect(screen.getByText('Quay về trang quản lý sau 2 giây...')).toBeInTheDocument()

    await waitFor(
      () => {
        expect(mockNavigate).toHaveBeenCalledWith('/dashboard')
      },
      { timeout: 3000 }
    )
  }, 10000)

  it('shows a Vietnamese payment error when Stripe confirmation fails', async () => {
    mockConfirmPayment.mockResolvedValueOnce({
      error: { message: 'Your card was declined.' },
    })

    render(<BookingForm />)
    await fillValidBookingAndSubmit()

    expect(await screen.findByTestId('payment-step')).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Thanh toán và xác nhận đặt phòng' }))

    expect(await screen.findByTestId('payment-error-message')).toHaveTextContent(
      'Thanh toán chưa hoàn tất. Vui lòng kiểm tra thông tin thẻ và thử lại.'
    )
    expect(mockVerifyBookingPayment).not.toHaveBeenCalled()
  })

  it('does not render Stripe checkout for explicit pay-at-property bookings', async () => {
    mockCreateBooking.mockResolvedValue(
      makeBooking({
        payment_policy: 'pay_at_property',
        payment_status: 'offline_due',
      })
    )

    render(<BookingForm />)
    await fillValidBookingAndSubmit()

    expect(await screen.findByTestId('success-message')).toBeInTheDocument()
    expect(screen.queryByTestId('payment-step')).not.toBeInTheDocument()
    expect(mockCreateBookingPaymentIntent).not.toHaveBeenCalled()
  })
})
