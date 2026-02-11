import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import LoginPage from './LoginPage'

// Mock react-router-dom
const mockNavigate = vi.fn()
vi.mock('react-router-dom', () => ({
  useNavigate: () => mockNavigate,
}))

// Mock AuthContext
const mockLoginHttpOnly = vi.fn()
const mockClearError = vi.fn()
let mockAuthError: string | null = null

vi.mock('./AuthContext', () => ({
  useAuth: () => ({
    loginHttpOnly: mockLoginHttpOnly,
    error: mockAuthError,
    clearError: mockClearError,
  }),
}))

describe('LoginPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockAuthError = null
  })

  it('renders the login form', () => {
    render(<LoginPage />)
    expect(screen.getByText('Welcome Back')).toBeInTheDocument()
    expect(screen.getByLabelText('Email Address')).toBeInTheDocument()
    expect(screen.getByLabelText('Password')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Sign In' })).toBeInTheDocument()
  })

  it('shows validation error when email is empty', async () => {
    const user = userEvent.setup()
    render(<LoginPage />)

    await user.click(screen.getByRole('button', { name: 'Sign In' }))

    expect(screen.getByText('Email is required')).toBeInTheDocument()
  })

  it('shows validation error for invalid email format', async () => {
    const user = userEvent.setup()
    render(<LoginPage />)

    await user.type(screen.getByLabelText('Email Address'), 'invalid-email')
    await user.click(screen.getByRole('button', { name: 'Sign In' }))

    expect(screen.getByText('Invalid email format')).toBeInTheDocument()
  })

  it('shows validation error when password is empty', async () => {
    const user = userEvent.setup()
    render(<LoginPage />)

    await user.type(screen.getByLabelText('Email Address'), 'user@example.com')
    await user.click(screen.getByRole('button', { name: 'Sign In' }))

    expect(screen.getByText('Password is required')).toBeInTheDocument()
  })

  it('shows validation error when password is too short', async () => {
    const user = userEvent.setup()
    render(<LoginPage />)

    await user.type(screen.getByLabelText('Email Address'), 'user@example.com')
    await user.type(screen.getByLabelText('Password'), '12345')
    await user.click(screen.getByRole('button', { name: 'Sign In' }))

    expect(screen.getByText('Password must be at least 8 characters')).toBeInTheDocument()
  })

  it('calls loginHttpOnly with form data on valid submission', async () => {
    mockLoginHttpOnly.mockResolvedValue(undefined)
    const user = userEvent.setup()
    render(<LoginPage />)

    await user.type(screen.getByLabelText('Email Address'), 'user@example.com')
    await user.type(screen.getByLabelText('Password'), 'password123')
    await user.click(screen.getByRole('button', { name: 'Sign In' }))

    await waitFor(() => {
      expect(mockLoginHttpOnly).toHaveBeenCalledWith('user@example.com', 'password123', false)
    })
  })

  it('displays auth error from context', () => {
    mockAuthError = 'Invalid credentials'
    render(<LoginPage />)

    expect(screen.getByText('Invalid credentials')).toBeInTheDocument()
  })

  it('has a link to registration page', () => {
    render(<LoginPage />)
    expect(screen.getByText('Register here')).toBeInTheDocument()
  })

  it('navigates to register when register link is clicked', async () => {
    const user = userEvent.setup()
    render(<LoginPage />)

    await user.click(screen.getByText('Register here'))
    expect(mockNavigate).toHaveBeenCalledWith('/register')
  })
})
