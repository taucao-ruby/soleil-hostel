import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import BookingForm from './BookingForm'

const { mockNavigate, mockSearchParamsRef } = vi.hoisted(() => ({
  mockNavigate: vi.fn(),
  mockSearchParamsRef: { current: new URLSearchParams() },
}))

vi.mock('react-router-dom', () => ({
  useNavigate: () => mockNavigate,
  useSearchParams: () => [mockSearchParamsRef.current],
}))

const mockGetRooms = vi.fn()
vi.mock('../rooms/room.api', () => ({
  getRooms: (...args: unknown[]) => mockGetRooms(...args),
}))

const mockCreateBooking = vi.fn()
vi.mock('./booking.api', () => ({
  createBooking: (...args: unknown[]) => mockCreateBooking(...args),
}))

const mockValidateBookingForm = vi.fn()
const mockGetMinCheckInDate = vi.fn()
const mockGetMinCheckOutDate = vi.fn()
const mockGetMaxCheckOutDate = vi.fn()
const mockCalculateNights = vi.fn()

vi.mock('./booking.validation', () => ({
  MAX_STAY_DAYS: 30,
  validateBookingForm: (...args: unknown[]) => mockValidateBookingForm(...args),
  getMinCheckInDate: (...args: unknown[]) => mockGetMinCheckInDate(...args),
  getMinCheckOutDate: (...args: unknown[]) => mockGetMinCheckOutDate(...args),
  getMaxCheckOutDate: (...args: unknown[]) => mockGetMaxCheckOutDate(...args),
  calculateNights: (...args: unknown[]) => mockCalculateNights(...args),
}))

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
    expect(screen.getByText('Thanh toán tại chỗ · Không cần thẻ')).toBeInTheDocument()

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

    await waitFor(() => {
      expect(screen.getByRole('combobox')).toBeDisabled()
    })

    expect(screen.getByRole('status')).toHaveTextContent('Không có phòng nào còn trống')
  })

  it('renders validation errors returned by the validator', async () => {
    mockValidateBookingForm.mockReturnValue({
      guest_name: 'Vui lòng nhập họ và tên',
      guest_email: 'Vui lòng nhập địa chỉ email',
    })

    render(<BookingForm />)

    const user = userEvent.setup()
    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Xác nhận đặt phòng →' })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: 'Xác nhận đặt phòng →' }))

    expect(screen.getByText('Vui lòng nhập họ và tên')).toBeInTheDocument()
    expect(screen.getByText('Vui lòng nhập địa chỉ email')).toBeInTheDocument()
    expect(mockCreateBooking).not.toHaveBeenCalled()
  })

  it('shows the API error banner when booking creation fails', async () => {
    mockCreateBooking.mockRejectedValue(new Error('conflict'))

    render(<BookingForm />)

    const user = userEvent.setup()
    await waitFor(() => {
      expect(screen.getByLabelText(/Chọn phòng/)).toBeInTheDocument()
    })

    await user.selectOptions(screen.getByLabelText(/Chọn phòng/), '1')
    await user.type(screen.getByLabelText(/Họ và tên/), 'Nguyen Van A')
    await user.type(screen.getByLabelText(/Địa chỉ email/), 'user@example.com')
    fireEvent.change(screen.getByLabelText(/Ngày nhận phòng/), { target: { value: '2026-06-15' } })
    fireEvent.change(screen.getByLabelText(/Ngày trả phòng/), { target: { value: '2026-06-18' } })

    await user.click(screen.getByRole('button', { name: 'Xác nhận đặt phòng →' }))

    expect(await screen.findByTestId('error-message')).toHaveTextContent(
      'Không thể đặt phòng. Phòng này có thể đã được đặt. Vui lòng thử ngày khác.'
    )
  })

  it('shows the success state and redirects after two seconds', async () => {
    mockCreateBooking.mockResolvedValue({
      id: 42,
      room_id: 1,
      guest_name: 'Nguyen Van A',
      guest_email: 'user@example.com',
      check_in: '2026-06-15',
      check_out: '2026-06-18',
      number_of_guests: 2,
      special_requests: null,
      status: 'pending',
      total_price: 1050000,
      created_at: '2026-06-01T10:00:00Z',
      updated_at: '2026-06-01T10:00:00Z',
    })

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
    fireEvent.click(screen.getByRole('button', { name: 'Xác nhận đặt phòng →' }))

    expect(await screen.findByTestId('success-message')).toBeInTheDocument()
    expect(screen.getByTestId('booking-reference')).toHaveTextContent('SOL-2026-0042')
    expect(screen.getByText('Quay về trang quản lý sau 2 giây...')).toBeInTheDocument()

    await waitFor(
      () => {
        expect(mockNavigate).toHaveBeenCalledWith('/dashboard')
      },
      { timeout: 3000 }
    )
  }, 10000)
})
