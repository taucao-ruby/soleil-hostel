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

function renderAdminRoute(minRole?: 'moderator' | 'admin') {
  return render(
    <MemoryRouter initialEntries={['/admin']}>
      <AdminRoute minRole={minRole}>
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
  // ── Default behaviour (minRole='moderator' — allows both admin and moderator) ──

  it('renders children when user has admin role (default minRole)', () => {
    mockUseAuth.mockReturnValue({
      user: { id: 1, name: 'Admin', email: 'admin@test.com', role: 'admin' },
      loading: false,
    })

    renderAdminRoute()
    expect(screen.getByTestId('admin-content')).toBeInTheDocument()
  })

  it('renders children when user has moderator role (default minRole allows moderator)', () => {
    mockUseAuth.mockReturnValue({
      user: { id: 3, name: 'Mod', email: 'mod@test.com', role: 'moderator' },
      loading: false,
    })

    renderAdminRoute()
    expect(screen.getByTestId('admin-content')).toBeInTheDocument()
  })

  it('redirects to /dashboard when user has non-admin/non-moderator role', () => {
    mockUseAuth.mockReturnValue({
      user: { id: 2, name: 'User', email: 'user@test.com', role: 'user' },
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

  // ── minRole="admin" — admin-only routes (e.g., room CUD) ──────────────────

  it('renders children for admin when minRole="admin"', () => {
    mockUseAuth.mockReturnValue({
      user: { id: 1, name: 'Admin', email: 'admin@test.com', role: 'admin' },
      loading: false,
    })

    renderAdminRoute('admin')
    expect(screen.getByTestId('admin-content')).toBeInTheDocument()
  })

  it('redirects moderator to /dashboard when minRole="admin"', () => {
    mockUseAuth.mockReturnValue({
      user: { id: 3, name: 'Mod', email: 'mod@test.com', role: 'moderator' },
      loading: false,
    })

    renderAdminRoute('admin')
    expect(screen.queryByTestId('admin-content')).not.toBeInTheDocument()
  })

  it('redirects non-privilege user when minRole="admin"', () => {
    mockUseAuth.mockReturnValue({
      user: { id: 2, name: 'User', email: 'user@test.com', role: 'user' },
      loading: false,
    })

    renderAdminRoute('admin')
    expect(screen.queryByTestId('admin-content')).not.toBeInTheDocument()
  })

  // ── minRole="moderator" explicit (same as default) ────────────────────────

  it('renders children for moderator when minRole="moderator" is explicit', () => {
    mockUseAuth.mockReturnValue({
      user: { id: 3, name: 'Mod', email: 'mod@test.com', role: 'moderator' },
      loading: false,
    })

    renderAdminRoute('moderator')
    expect(screen.getByTestId('admin-content')).toBeInTheDocument()
  })
})
