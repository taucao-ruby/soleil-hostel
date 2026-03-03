import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import BookingForm from './BookingForm'

// ── Hoisted mock state (must be declared before vi.mock factories run) ────
const { mockNavigate, mockSearchParamsRef } = vi.hoisted(() => ({
  mockNavigate: vi.fn(),
  mockSearchParamsRef: { current: new URLSearchParams() },
}))

// Mock react-router-dom
vi.mock('react-router-dom', () => ({
  useNavigate: () => mockNavigate,
  useSearchParams: () => [mockSearchParamsRef.current],
}))

// Mock room API
vi.mock('../rooms/room.api', () => ({
  getRooms: vi.fn().mockResolvedValue([
    {
      id: 1,
      name: 'Deluxe Room',
      price: 150,
      status: 'available',
      description: 'Nice room',
      image_url: null,
      created_at: '',
      updated_at: '',
    },
    {
      id: 2,
      name: 'Suite Room',
      price: 250,
      status: 'available',
      description: 'Luxury',
      image_url: null,
      created_at: '',
      updated_at: '',
    },
    {
      id: 3,
      name: 'Maintenance Room',
      price: 100,
      status: 'maintenance',
      description: '',
      image_url: null,
      created_at: '',
      updated_at: '',
    },
  ]),
}))

// Mock booking API
const mockCreateBooking = vi.fn()
vi.mock('./booking.api', () => ({
  createBooking: (...args: unknown[]) => mockCreateBooking(...args),
}))

// Mock validation
vi.mock('./booking.validation', () => ({
  validateBookingForm: vi.fn().mockReturnValue({}),
  getMinCheckInDate: vi.fn().mockReturnValue('2026-02-12'),
  getMinCheckOutDate: vi.fn().mockReturnValue('2026-02-13'),
  calculateNights: vi.fn().mockReturnValue(2),
}))

describe('BookingForm', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockSearchParamsRef.current = new URLSearchParams()
  })

  it('renders the booking form', async () => {
    render(<BookingForm />)

    expect(screen.getByRole('heading', { name: 'Đặt phòng' })).toBeInTheDocument()
    expect(screen.getByText('Vui lòng điền thông tin để đặt phòng')).toBeInTheDocument()
  })

  it('loads and displays available rooms in dropdown', async () => {
    render(<BookingForm />)

    await waitFor(() => {
      expect(screen.getByText(/Deluxe Room.*\/đêm/)).toBeInTheDocument()
      expect(screen.getByText(/Suite Room.*\/đêm/)).toBeInTheDocument()
    })

    // Maintenance room should be filtered out
    expect(screen.queryByText(/Maintenance Room.*\/đêm/)).not.toBeInTheDocument()
  })

  it('shows loading state while fetching rooms', () => {
    render(<BookingForm />)
    expect(screen.getByText('Đang tải phòng...')).toBeInTheDocument()
  })

  it('renders date inputs for check-in and check-out', async () => {
    render(<BookingForm />)

    await waitFor(() => {
      expect(screen.getByLabelText(/Ngày nhận phòng/)).toBeInTheDocument()
      expect(screen.getByLabelText(/Ngày trả phòng/)).toBeInTheDocument()
    })
  })

  it('renders guest name and email inputs', async () => {
    render(<BookingForm />)

    await waitFor(() => {
      expect(screen.getByLabelText(/Tên khách/)).toBeInTheDocument()
      expect(screen.getByLabelText(/Địa chỉ email/)).toBeInTheDocument()
    })
  })

  it('renders number of guests input', async () => {
    render(<BookingForm />)

    await waitFor(() => {
      expect(screen.getByLabelText(/Số khách/)).toBeInTheDocument()
    })
  })

  it('renders special requests textarea', async () => {
    render(<BookingForm />)

    await waitFor(() => {
      expect(screen.getByLabelText(/Yêu cầu đặc biệt/)).toBeInTheDocument()
    })
  })

  it('has a submit button', async () => {
    render(<BookingForm />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Đặt phòng' })).toBeInTheDocument()
    })
  })

  it('has a back button', async () => {
    render(<BookingForm />)

    expect(screen.getByText('← Quay lại')).toBeInTheDocument()
  })

  it('navigates back when back button is clicked', async () => {
    render(<BookingForm />)

    const user = userEvent.setup()
    await user.click(screen.getByText('← Quay lại'))

    expect(mockNavigate).toHaveBeenCalledWith(-1)
  })

  it('pre-fills check_in, check_out, guests from URL params', async () => {
    mockSearchParamsRef.current = new URLSearchParams(
      'room_id=1&check_in=2026-03-10&check_out=2026-03-12&guests=3'
    )

    render(<BookingForm />)

    await waitFor(() => {
      expect(screen.getByLabelText(/Ngày nhận phòng/)).toHaveValue('2026-03-10')
      expect(screen.getByLabelText(/Ngày trả phòng/)).toHaveValue('2026-03-12')
      expect(screen.getByLabelText(/Số khách/)).toHaveValue(3)
    })
  })

  it('defaults guests to 1 when URL param is invalid', async () => {
    mockSearchParamsRef.current = new URLSearchParams('guests=abc')

    render(<BookingForm />)

    await waitFor(() => {
      expect(screen.getByLabelText(/Số khách/)).toHaveValue(1)
    })
  })
})
