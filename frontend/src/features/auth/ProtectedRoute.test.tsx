import { describe, expect, it, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import ProtectedRoute from './ProtectedRoute'

const { mockUseAuth } = vi.hoisted(() => ({
  mockUseAuth: vi.fn(),
}))

vi.mock('@/features/auth/AuthContext', () => ({
  useAuth: mockUseAuth,
}))

function renderProtectedRoute() {
  return render(
    <MemoryRouter initialEntries={['/dashboard']}>
      <ProtectedRoute>
        <div>Protected content</div>
      </ProtectedRoute>
    </MemoryRouter>
  )
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('ProtectedRoute', () => {
  it('renders Vietnamese auth-checking copy while loading', () => {
    mockUseAuth.mockReturnValue({
      isAuthenticated: false,
      loading: true,
    })

    renderProtectedRoute()

    expect(screen.getByText('Đang kiểm tra phiên đăng nhập...')).toBeInTheDocument()
    expect(screen.queryByText('Checking authentication...')).not.toBeInTheDocument()
  })
})

// ── NEW TESTS APPENDED BY COVERAGE-LIFT PR ──────────────────────────────────

describe('ProtectedRoute redirect and render branches', () => {
  it('redirects unauthenticated users to the login route', async () => {
    mockUseAuth.mockReturnValue({
      isAuthenticated: false,
      loading: false,
    })

    // A real /login route must exist so the redirect unmounts ProtectedRoute;
    // a bare <Navigate state={...}> under MemoryRouter re-fires every commit
    // (fresh state object each render) and never settles.
    const { Routes, Route } = await import('react-router-dom')
    render(
      <MemoryRouter initialEntries={['/dashboard']}>
        <Routes>
          <Route
            path="/dashboard"
            element={
              <ProtectedRoute>
                <div>Protected content</div>
              </ProtectedRoute>
            }
          />
          <Route path="/login" element={<div>Trang đăng nhập</div>} />
        </Routes>
      </MemoryRouter>
    )

    expect(await screen.findByText('Trang đăng nhập')).toBeInTheDocument()
    expect(screen.queryByText('Protected content')).not.toBeInTheDocument()
    expect(screen.queryByText('Đang kiểm tra phiên đăng nhập...')).not.toBeInTheDocument()
  })

  it('renders children when authenticated', () => {
    mockUseAuth.mockReturnValue({
      isAuthenticated: true,
      loading: false,
    })

    renderProtectedRoute()

    expect(screen.getByText('Protected content')).toBeInTheDocument()
  })
})
