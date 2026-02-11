import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import BookingForm from './BookingForm'

// Mock react-router-dom
const mockNavigate = vi.fn()
vi.mock('react-router-dom', () => ({
  useNavigate: () => mockNavigate,
  useSearchParams: () => [new URLSearchParams()],
}))

// Mock room API
vi.mock('../rooms/room.api', () => ({
  getRooms: vi.fn().mockResolvedValue([
    { id: 1, name: 'Deluxe Room', price: 150, status: 'available', description: 'Nice room', image_url: null, created_at: '', updated_at: '' },
    { id: 2, name: 'Suite Room', price: 250, status: 'available', description: 'Luxury', image_url: null, created_at: '', updated_at: '' },
    { id: 3, name: 'Maintenance Room', price: 100, status: 'maintenance', description: '', image_url: null, created_at: '', updated_at: '' },
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
  })

  it('renders the booking form', async () => {
    render(<BookingForm />)

    expect(screen.getByText('Book Your Stay')).toBeInTheDocument()
    expect(screen.getByText('Fill in the details to reserve your room')).toBeInTheDocument()
  })

  it('loads and displays available rooms in dropdown', async () => {
    render(<BookingForm />)

    await waitFor(() => {
      expect(screen.getByText('Deluxe Room - $150/night')).toBeInTheDocument()
      expect(screen.getByText('Suite Room - $250/night')).toBeInTheDocument()
    })

    // Maintenance room should be filtered out
    expect(screen.queryByText('Maintenance Room - $100/night')).not.toBeInTheDocument()
  })

  it('shows loading state while fetching rooms', () => {
    render(<BookingForm />)
    expect(screen.getByText('Loading rooms...')).toBeInTheDocument()
  })

  it('renders date inputs for check-in and check-out', async () => {
    render(<BookingForm />)

    await waitFor(() => {
      expect(screen.getByLabelText(/Check-in Date/)).toBeInTheDocument()
      expect(screen.getByLabelText(/Check-out Date/)).toBeInTheDocument()
    })
  })

  it('renders guest name and email inputs', async () => {
    render(<BookingForm />)

    await waitFor(() => {
      expect(screen.getByLabelText(/Guest Name/)).toBeInTheDocument()
      expect(screen.getByLabelText(/Email Address/)).toBeInTheDocument()
    })
  })

  it('renders number of guests input', async () => {
    render(<BookingForm />)

    await waitFor(() => {
      expect(screen.getByLabelText(/Number of Guests/)).toBeInTheDocument()
    })
  })

  it('renders special requests textarea', async () => {
    render(<BookingForm />)

    await waitFor(() => {
      expect(screen.getByLabelText(/Special Requests/)).toBeInTheDocument()
    })
  })

  it('has a submit button', async () => {
    render(<BookingForm />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Complete Booking' })).toBeInTheDocument()
    })
  })

  it('has a back button', async () => {
    render(<BookingForm />)

    expect(screen.getByText('← Back')).toBeInTheDocument()
  })

  it('navigates back when back button is clicked', async () => {
    render(<BookingForm />)

    const user = userEvent.setup()
    await user.click(screen.getByText('← Back'))

    expect(mockNavigate).toHaveBeenCalledWith(-1)
  })
})
