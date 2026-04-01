import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import DashboardPage from './DashboardPage'

// ── Mocks ───────────────────────────────────────────────

const { mockUseAuth } = vi.hoisted(() => ({
  mockUseAuth: vi.fn(),
}))

vi.mock('@/features/auth/AuthContext', () => ({
  useAuth: mockUseAuth,
}))

// Mock GuestDashboard to avoid its internal hooks
vi.mock('@/features/bookings/GuestDashboard', () => ({
  default: () => <div data-testid="guest-dashboard">GuestDashboard</div>,
}))

// Mock AdminDashboard to avoid its internal hooks
vi.mock('@/features/admin/AdminDashboard', () => ({
  default: () => <div data-testid="admin-dashboard">AdminDashboard</div>,
}))

function renderDashboard() {
  return render(
    <MemoryRouter>
      <DashboardPage />
    </MemoryRouter>
  )
}

// ── Setup ───────────────────────────────────────────────

beforeEach(() => {
  vi.clearAllMocks()
})

// ── Tests ───────────────────────────────────────────────

describe('DashboardPage', () => {
  it('shows loading skeleton when auth is loading', () => {
    mockUseAuth.mockReturnValue({ user: null, loading: true, isAuthenticated: false })

    renderDashboard()
    const skeletons = screen.getAllByRole('status')
    expect(skeletons.length).toBeGreaterThanOrEqual(1)
  })

  it('renders GuestDashboard for regular user', () => {
    mockUseAuth.mockReturnValue({
      user: { id: 1, name: 'Alice', email: 'a@b.com', role: 'user' },
      loading: false,
      isAuthenticated: true,
    })

    renderDashboard()
    expect(screen.getByTestId('guest-dashboard')).toBeInTheDocument()
    expect(screen.queryByTestId('admin-dashboard')).not.toBeInTheDocument()
    expect(screen.getByText('Trang quản lý')).toBeInTheDocument()
    expect(screen.getByText('Quản lý đặt phòng của bạn tại đây.')).toBeInTheDocument()
    expect(screen.getByText('Xin chào, Alice!')).toBeInTheDocument()
  })

  it('renders AdminDashboard for admin user', () => {
    mockUseAuth.mockReturnValue({
      user: { id: 1, name: 'Admin', email: 'admin@b.com', role: 'admin' },
      loading: false,
      isAuthenticated: true,
    })

    renderDashboard()
    expect(screen.getByTestId('admin-dashboard')).toBeInTheDocument()
    expect(screen.queryByTestId('guest-dashboard')).not.toBeInTheDocument()
    expect(screen.getByText('Bảng điều khiển quản trị')).toBeInTheDocument()
  })

  it('renders GuestDashboard for moderator (not admin)', () => {
    mockUseAuth.mockReturnValue({
      user: { id: 1, name: 'Moderator', email: 'mod@b.com', role: 'moderator' },
      loading: false,
      isAuthenticated: true,
    })

    renderDashboard()
    expect(screen.getByTestId('guest-dashboard')).toBeInTheDocument()
    expect(screen.queryByTestId('admin-dashboard')).not.toBeInTheDocument()
    expect(screen.getByText('Xin chào, Moderator!')).toBeInTheDocument()
  })

  it('shows quick actions links on the dashboard shell', () => {
    mockUseAuth.mockReturnValue({
      user: { id: 1, name: 'Admin', email: 'admin@b.com', role: 'admin' },
      loading: false,
      isAuthenticated: true,
    })

    renderDashboard()
    expect(screen.getByRole('link', { name: /Xem phòng/ })).toHaveAttribute('href', '/rooms')
    expect(screen.getByRole('link', { name: /Xem chi nhánh/ })).toHaveAttribute(
      'href',
      '/locations'
    )
  })
})
