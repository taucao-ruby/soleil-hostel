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
