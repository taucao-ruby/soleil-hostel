import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import HomePage from './HomePage'

// Mock react-router-dom
const mockNavigate = vi.fn()
vi.mock('react-router-dom', () => ({
  useNavigate: () => mockNavigate,
}))

// Mock AuthContext
let mockIsAuthenticated = false
vi.mock('@/features/auth/AuthContext', () => ({
  useAuth: () => ({
    isAuthenticated: mockIsAuthenticated,
  }),
}))

// Mock room API
vi.mock('@/features/rooms/room.api', () => ({
  getRooms: vi.fn().mockResolvedValue([
    { id: 1, name: 'Deluxe Room', price: 150, status: 'available', description: 'Nice room', image_url: null },
    { id: 2, name: 'Suite Room', price: 250, status: 'available', description: 'Luxury suite', image_url: null },
  ]),
}))

// Mock UI components
vi.mock('@/shared/components/ui/Button', () => ({
  default: ({ children, onClick, ...props }: { children: React.ReactNode; onClick?: () => void; [key: string]: unknown }) => (
    <button onClick={onClick} {...props}>
      {children}
    </button>
  ),
}))

vi.mock('@/shared/components/ui/Card', () => {
  const Card = ({ children, ...props }: { children: React.ReactNode; [key: string]: unknown }) => (
    <div {...props}>{children}</div>
  )
  Card.Content = ({ children, ...props }: { children: React.ReactNode; [key: string]: unknown }) => (
    <div {...props}>{children}</div>
  )
  return { default: Card }
})

vi.mock('@/shared/components/ui/Skeleton', () => ({
  SkeletonCard: () => <div data-testid="skeleton-card" />,
}))

describe('HomePage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockIsAuthenticated = false
  })

  it('renders the hero section', () => {
    render(<HomePage />)
    expect(screen.getByText('Soleil Hostel')).toBeInTheDocument()
  })

  it('renders the welcome message', () => {
    render(<HomePage />)
    expect(screen.getByText(/Welcome to/)).toBeInTheDocument()
  })

  it('renders the Explore Our Rooms button', () => {
    render(<HomePage />)
    expect(screen.getByText('Explore Our Rooms')).toBeInTheDocument()
  })

  it('shows Get Started button when not authenticated', () => {
    mockIsAuthenticated = false
    render(<HomePage />)
    expect(screen.getByText('Get Started')).toBeInTheDocument()
  })

  it('shows Book Your Stay button when authenticated', () => {
    mockIsAuthenticated = true
    render(<HomePage />)
    expect(screen.getByText('Book Your Stay')).toBeInTheDocument()
  })

  it('navigates to /rooms when Explore Our Rooms is clicked', async () => {
    const user = userEvent.setup()
    render(<HomePage />)

    await user.click(screen.getByText('Explore Our Rooms'))
    expect(mockNavigate).toHaveBeenCalledWith('/rooms')
  })

  it('navigates to /register when Get Started is clicked (unauthenticated)', async () => {
    mockIsAuthenticated = false
    const user = userEvent.setup()
    render(<HomePage />)

    await user.click(screen.getByText('Get Started'))
    expect(mockNavigate).toHaveBeenCalledWith('/register')
  })

  it('navigates to /booking when Book Your Stay is clicked (authenticated)', async () => {
    mockIsAuthenticated = true
    const user = userEvent.setup()
    render(<HomePage />)

    await user.click(screen.getByText('Book Your Stay'))
    expect(mockNavigate).toHaveBeenCalledWith('/booking')
  })

  it('renders Featured Rooms section', () => {
    render(<HomePage />)
    expect(screen.getByText('Featured Rooms')).toBeInTheDocument()
  })

  it('loads and displays featured rooms', async () => {
    render(<HomePage />)

    await waitFor(() => {
      expect(screen.getByText('Deluxe Room')).toBeInTheDocument()
      expect(screen.getByText('Suite Room')).toBeInTheDocument()
    })
  })

  it('renders Why Choose Us section', () => {
    render(<HomePage />)
    expect(screen.getByText('Why Choose Us')).toBeInTheDocument()
  })

  it('renders feature highlights', () => {
    render(<HomePage />)
    expect(screen.getByText('Comfortable Rooms')).toBeInTheDocument()
    expect(screen.getByText('Prime Location')).toBeInTheDocument()
    expect(screen.getByText('Affordable Prices')).toBeInTheDocument()
  })

  it('renders Guest Reviews section', () => {
    render(<HomePage />)
    expect(screen.getByText('Guest Reviews')).toBeInTheDocument()
  })

  it('renders CTA section', () => {
    render(<HomePage />)
    expect(screen.getByText('Ready to Book Your Stay?')).toBeInTheDocument()
  })

  it('renders View All Rooms button', () => {
    render(<HomePage />)
    expect(screen.getByText('View All Rooms')).toBeInTheDocument()
  })

  it('navigates to /rooms when View All Rooms is clicked', async () => {
    const user = userEvent.setup()
    render(<HomePage />)

    await user.click(screen.getByText('View All Rooms'))
    expect(mockNavigate).toHaveBeenCalledWith('/rooms')
  })

  it('shows Get Started Today in CTA when not authenticated', () => {
    mockIsAuthenticated = false
    render(<HomePage />)
    expect(screen.getByText('Get Started Today')).toBeInTheDocument()
  })

  it('shows Book Now in CTA when authenticated', () => {
    mockIsAuthenticated = true
    render(<HomePage />)
    expect(screen.getByText('Book Now')).toBeInTheDocument()
  })
})
