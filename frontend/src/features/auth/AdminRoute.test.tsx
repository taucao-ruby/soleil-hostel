import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import AdminRoute from './AdminRoute'

// ── Mocks ───────────────────────────────────────────────

const { mockUseAuth } = vi.hoisted(() => ({
  mockUseAuth: vi.fn(),
}))

vi.mock('@/features/auth/AuthContext', () => ({
  useAuth: mockUseAuth,
}))

// ── Helpers ─────────────────────────────────────────────

function renderAdminRoute() {
  return render(
    <MemoryRouter initialEntries={['/admin']}>
      <AdminRoute>
        <div data-testid="admin-content">Admin Protected Content</div>
      </AdminRoute>
    </MemoryRouter>
  )
}

// ── Setup ───────────────────────────────────────────────

beforeEach(() => {
  vi.clearAllMocks()
})

// ── Tests ───────────────────────────────────────────────

describe('AdminRoute', () => {
  it('renders children when user has admin role', () => {
    mockUseAuth.mockReturnValue({
      user: { id: 1, name: 'Admin', email: 'admin@test.com', role: 'admin' },
      loading: false,
    })

    renderAdminRoute()
    expect(screen.getByTestId('admin-content')).toBeInTheDocument()
  })

  it('redirects to /dashboard when user has non-admin role', () => {
    mockUseAuth.mockReturnValue({
      user: { id: 2, name: 'User', email: 'user@test.com', role: 'user' },
      loading: false,
    })

    renderAdminRoute()
    expect(screen.queryByTestId('admin-content')).not.toBeInTheDocument()
  })

  it('redirects moderator to /dashboard (not admin)', () => {
    mockUseAuth.mockReturnValue({
      user: { id: 3, name: 'Mod', email: 'mod@test.com', role: 'moderator' },
      loading: false,
    })

    renderAdminRoute()
    expect(screen.queryByTestId('admin-content')).not.toBeInTheDocument()
  })

  it('renders nothing when loading', () => {
    mockUseAuth.mockReturnValue({
      user: null,
      loading: true,
    })

    const { container } = renderAdminRoute()
    expect(container.innerHTML).toBe('')
  })

  it('redirects when user is null and not loading', () => {
    mockUseAuth.mockReturnValue({
      user: null,
      loading: false,
    })

    renderAdminRoute()
    expect(screen.queryByTestId('admin-content')).not.toBeInTheDocument()
  })
})
