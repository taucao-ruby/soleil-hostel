import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import type React from 'react'
import BookingForm from './BookingForm'

// This suite deliberately does NOT provide VITE_STRIPE_PUBLISHABLE_KEY, so the
// module-level `stripePromise` resolves to null. It proves MoMo is offered as a
// standalone payment method even when Stripe is not configured.
const {
  mockNavigate,
  mockSearchParamsRef,
  mockGetRooms,
  mockCreateBooking,
  mockCreateBookingPaymentIntent,
  mockCreateMoMoPayment,
  mockGetBookingById,
  mockValidateBookingForm,
  mockGetMinCheckInDate,
  mockGetMinCheckOutDate,
  mockGetMaxCheckOutDate,
  mockCalculateNights,
} = vi.hoisted(() => {
  vi.stubEnv('VITE_STRIPE_PUBLISHABLE_KEY', '')

  return {
    mockNavigate: vi.fn(),
    mockSearchParamsRef: { current: new URLSearchParams() },
    mockGetRooms: vi.fn(),
    mockCreateBooking: vi.fn(),
    mockCreateBookingPaymentIntent: vi.fn(),
    mockCreateMoMoPayment: vi.fn(),
    mockGetBookingById: vi.fn(),
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
  createMoMoPayment: (...args: unknown[]) => mockCreateMoMoPayment(...args),
  getBookingById: (...args: unknown[]) => mockGetBookingById(...args),
  verifyBookingPayment: vi.fn(),
}))

vi.mock('qrcode.react', () => ({
  QRCodeSVG: ({ value }: { value: string }) => <div data-testid="qr-svg" data-value={value} />,
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
  useStripe: () => ({ confirmPayment: vi.fn() }),
}))

vi.mock('./booking.validation', () => ({
  MAX_STAY_DAYS: 30,
  validateBookingForm: (...args: unknown[]) => mockValidateBookingForm(...args),
  getMinCheckInDate: (...args: unknown[]) => mockGetMinCheckInDate(...args),
  getMinCheckOutDate: (...args: unknown[]) => mockGetMinCheckOutDate(...args),
  getMaxCheckOutDate: (...args: unknown[]) => mockGetMaxCheckOutDate(...args),
  calculateNights: (...args: unknown[]) => mockCalculateNights(...args),
}))

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

describe('BookingForm (no Stripe configured)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
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
    ])
    mockCreateBooking.mockResolvedValue(makeBooking())
    mockCreateMoMoPayment.mockResolvedValue({
      payUrl: 'https://test-payment.momo.vn/pay/soleil-42-abc123',
      qrCodeUrl: null,
      deeplink: null,
      orderId: 'soleil-42-abc123',
    })
    mockGetBookingById.mockResolvedValue(makeBooking())
    mockValidateBookingForm.mockReturnValue({})
    mockGetMinCheckInDate.mockReturnValue('2026-04-01')
    mockGetMinCheckOutDate.mockReturnValue('2026-04-02')
    mockGetMaxCheckOutDate.mockReturnValue('2026-05-01')
    mockCalculateNights.mockReturnValue(3)
  })

  it('offers MoMo standalone and never calls the Stripe payment intent', async () => {
    render(<BookingForm />)
    await fillValidBookingAndSubmit()

    expect(await screen.findByTestId('momo-payment-option')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Thanh toán qua ví MoMo' })).toBeInTheDocument()

    // Stripe is absent: no payment intent call, no Stripe card step.
    expect(mockCreateBookingPaymentIntent).not.toHaveBeenCalled()
    expect(screen.queryByTestId('payment-step')).not.toBeInTheDocument()
  })

  it('shows the in-app QR (falling back to payUrl) when chosen', async () => {
    render(<BookingForm />)
    await fillValidBookingAndSubmit()

    fireEvent.click(await screen.findByRole('button', { name: 'Thanh toán qua ví MoMo' }))

    expect(await screen.findByTestId('momo-qr')).toBeInTheDocument()
    expect(mockCreateMoMoPayment).toHaveBeenCalledWith(42)
    // qrCodeUrl is null here, so the QR encodes the hosted payUrl instead.
    expect(screen.getByTestId('qr-svg')).toHaveAttribute(
      'data-value',
      'https://test-payment.momo.vn/pay/soleil-42-abc123'
    )
    // No deeplink in this response → no "open app" button, but the hosted-page link shows.
    expect(screen.queryByTestId('momo-deeplink')).not.toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Hoặc mở trang thanh toán MoMo' })).toBeInTheDocument()
  })
})
