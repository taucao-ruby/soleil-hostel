import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { AuthProvider, useAuth } from './AuthContext'
import api from '@/shared/lib/api'

// Mock api module
vi.mock('@/shared/lib/api', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
  },
}))

// Mock csrf utils
vi.mock('@/shared/utils/csrf', () => ({
  setCsrfToken: vi.fn(),
  clearCsrfToken: vi.fn(),
}))

const mockGet = vi.mocked(api.get)
const mockPost = vi.mocked(api.post)

// Test component that exposes auth context
function TestConsumer() {
  const auth = useAuth()
  return (
    <div>
      <span data-testid="loading">{String(auth.loading)}</span>
      <span data-testid="authenticated">{String(auth.isAuthenticated)}</span>
      <span data-testid="user">{auth.user ? auth.user.name : 'null'}</span>
      <span data-testid="error">{auth.error ?? 'null'}</span>
      <button
        data-testid="login"
        onClick={() => auth.loginHttpOnly('test@example.com', 'password123').catch(() => {})}
      >
        Login
      </button>
      <button data-testid="logout" onClick={() => auth.logoutHttpOnly()}>
        Logout
      </button>
      <button data-testid="clear-error" onClick={() => auth.clearError()}>
        Clear
      </button>
    </div>
  )
}

describe('AuthContext', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    sessionStorage.clear()
  })

  it('provides default unauthenticated state when no csrf token', async () => {
    render(
      <AuthProvider>
        <TestConsumer />
      </AuthProvider>
    )

    await waitFor(() => {
      expect(screen.getByTestId('loading').textContent).toBe('false')
    })

    expect(screen.getByTestId('authenticated').textContent).toBe('false')
    expect(screen.getByTestId('user').textContent).toBe('null')
  })

  it('validates token on mount when csrf_token exists in sessionStorage', async () => {
    sessionStorage.setItem('csrf_token', 'test-csrf')
    mockGet.mockResolvedValue({
      data: {
        user: { id: 1, name: 'Test User', email: 'test@example.com', role: 'guest' },
      },
    })

    render(
      <AuthProvider>
        <TestConsumer />
      </AuthProvider>
    )

    await waitFor(() => {
      expect(screen.getByTestId('user').textContent).toBe('Test User')
    })

    expect(screen.getByTestId('authenticated').textContent).toBe('true')
    expect(mockGet).toHaveBeenCalledWith('/auth/me-httponly')
  })

  it('clears user on failed token validation', async () => {
    sessionStorage.setItem('csrf_token', 'expired-csrf')
    mockGet.mockRejectedValue({ response: { status: 401 } })

    render(
      <AuthProvider>
        <TestConsumer />
      </AuthProvider>
    )

    await waitFor(() => {
      expect(screen.getByTestId('loading').textContent).toBe('false')
    })

    expect(screen.getByTestId('authenticated').textContent).toBe('false')
    expect(screen.getByTestId('user').textContent).toBe('null')
  })

  it('logs in successfully and sets user', async () => {
    mockPost.mockResolvedValue({
      data: {
        user: { id: 1, name: 'Logged In', email: 'test@example.com', role: 'guest' },
        csrf_token: 'new-csrf',
      },
    })

    render(
      <AuthProvider>
        <TestConsumer />
      </AuthProvider>
    )

    await waitFor(() => {
      expect(screen.getByTestId('loading').textContent).toBe('false')
    })

    const user = userEvent.setup()
    await user.click(screen.getByTestId('login'))

    await waitFor(() => {
      expect(screen.getByTestId('user').textContent).toBe('Logged In')
    })

    expect(mockPost).toHaveBeenCalledWith('/auth/login-httponly', {
      email: 'test@example.com',
      password: 'password123',
      remember_me: false,
    })
  })

  it('sets error on login failure', async () => {
    mockPost.mockRejectedValue({
      response: { data: { message: 'Invalid credentials' } },
    })

    render(
      <AuthProvider>
        <TestConsumer />
      </AuthProvider>
    )

    await waitFor(() => {
      expect(screen.getByTestId('loading').textContent).toBe('false')
    })

    const user = userEvent.setup()
    await user.click(screen.getByTestId('login'))

    await waitFor(() => {
      expect(screen.getByTestId('error').textContent).toBe('Invalid credentials')
    })
  })

  it('logs out and clears user state', async () => {
    mockPost.mockResolvedValueOnce({
      data: {
        user: { id: 1, name: 'Logged In', email: 'test@example.com', role: 'guest' },
        csrf_token: 'csrf',
      },
    })

    render(
      <AuthProvider>
        <TestConsumer />
      </AuthProvider>
    )

    await waitFor(() => {
      expect(screen.getByTestId('loading').textContent).toBe('false')
    })

    const user = userEvent.setup()
    await user.click(screen.getByTestId('login'))

    await waitFor(() => {
      expect(screen.getByTestId('authenticated').textContent).toBe('true')
    })

    mockPost.mockResolvedValueOnce({})
    await user.click(screen.getByTestId('logout'))

    await waitFor(() => {
      expect(screen.getByTestId('authenticated').textContent).toBe('false')
      expect(screen.getByTestId('user').textContent).toBe('null')
    })
  })

  it('clears error when clearError is called', async () => {
    mockPost.mockRejectedValue({
      response: { data: { message: 'Some error' } },
    })

    render(
      <AuthProvider>
        <TestConsumer />
      </AuthProvider>
    )

    await waitFor(() => {
      expect(screen.getByTestId('loading').textContent).toBe('false')
    })

    const user = userEvent.setup()

    await user.click(screen.getByTestId('login'))

    await waitFor(() => {
      expect(screen.getByTestId('error').textContent).toBe('Some error')
    })

    await user.click(screen.getByTestId('clear-error'))
    expect(screen.getByTestId('error').textContent).toBe('null')
  })

  it('throws error when useAuth is used outside AuthProvider', () => {
    const spy = vi.spyOn(console, 'error').mockImplementation(() => {})
    expect(() => render(<TestConsumer />)).toThrow(
      'useAuth must be used within an AuthProvider'
    )
    spy.mockRestore()
  })
})
