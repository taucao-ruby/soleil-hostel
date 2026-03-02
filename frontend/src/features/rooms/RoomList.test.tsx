import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import RoomList from './RoomList'

// ── Hoisted mock state ────
const { mockNavigate, mockGetRooms } = vi.hoisted(() => ({
  mockNavigate: vi.fn(),
  mockGetRooms: vi.fn(),
}))

vi.mock('react-router-dom', () => ({
  useNavigate: () => mockNavigate,
}))

vi.mock('./room.api', () => ({
  getRooms: (...args: unknown[]) => mockGetRooms(...args),
}))

const mockRooms = [
  {
    id: 1,
    name: 'Deluxe Room',
    description: 'A nice room',
    price: 150,
    status: 'available' as const,
    image_url: null,
    created_at: '',
    updated_at: '',
  },
  {
    id: 2,
    name: 'Suite Room',
    description: 'Luxury suite',
    price: 250,
    status: 'booked' as const,
    image_url: 'https://example.com/suite.jpg',
    created_at: '',
    updated_at: '',
  },
  {
    id: 3,
    name: 'Maintenance Room',
    description: '',
    price: 100,
    status: 'maintenance' as const,
    image_url: null,
    created_at: '',
    updated_at: '',
  },
]

describe('RoomList', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockGetRooms.mockResolvedValue(mockRooms)
  })

  it('renders loading skeleton while fetching rooms', () => {
    mockGetRooms.mockReturnValue(new Promise(() => {})) // never resolves
    render(<RoomList />)

    const skeletons = document.querySelectorAll('[class*="animate-pulse"]')
    expect(skeletons.length).toBeGreaterThan(0)
  })

  it('renders room cards when API returns data', async () => {
    render(<RoomList />)

    await waitFor(() => {
      expect(screen.getByText('Deluxe Room')).toBeInTheDocument()
      expect(screen.getByText('Suite Room')).toBeInTheDocument()
      expect(screen.getByText('Maintenance Room')).toBeInTheDocument()
    })
  })

  it('renders empty state when API returns empty array', async () => {
    mockGetRooms.mockResolvedValue([])
    render(<RoomList />)

    await waitFor(() => {
      expect(screen.getByText('No rooms available')).toBeInTheDocument()
    })
  })

  it('renders error state when API rejects', async () => {
    mockGetRooms.mockRejectedValue(new Error('Network error'))
    render(<RoomList />)

    await waitFor(() => {
      expect(screen.getByText('Failed to load rooms. Please try again later.')).toBeInTheDocument()
    })
  })

  it('displays room prices', async () => {
    render(<RoomList />)

    await waitFor(() => {
      const prices = screen.getAllByTestId('room-price')
      expect(prices).toHaveLength(3)
    })
  })

  it('shows Book Now button only for available rooms', async () => {
    render(<RoomList />)

    await waitFor(() => {
      const bookButtons = screen.getAllByRole('button', { name: 'Book Now' })
      expect(bookButtons).toHaveLength(1) // only Deluxe Room is available
    })
  })

  it('displays status badges for each room', async () => {
    render(<RoomList />)

    await waitFor(() => {
      expect(screen.getByText('available')).toBeInTheDocument()
      expect(screen.getByText('booked')).toBeInTheDocument()
      expect(screen.getByText('maintenance')).toBeInTheDocument()
    })
  })

  it('renders room images when image_url is provided', async () => {
    render(<RoomList />)

    await waitFor(() => {
      const img = screen.getByAltText('Suite Room')
      expect(img).toHaveAttribute('src', 'https://example.com/suite.jpg')
    })
  })
})
