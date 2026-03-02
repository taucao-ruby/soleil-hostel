import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import RegisterPage from './RegisterPage'

// ── Hoisted mock state (must be declared before vi.mock factories run) ────
const { mockNavigate, mockRegisterHttpOnly, mockClearError, mockAuthRef } = vi.hoisted(() => ({
  mockNavigate: vi.fn(),
  mockRegisterHttpOnly: vi.fn(),
  mockClearError: vi.fn(),
  mockAuthRef: { current: null as string | null },
}))

// Mock react-router-dom
vi.mock('react-router-dom', () => ({
  useNavigate: () => mockNavigate,
}))

// Mock AuthContext
vi.mock('./AuthContext', () => ({
  useAuth: () => ({
    registerHttpOnly: mockRegisterHttpOnly,
    error: mockAuthRef.current,
    clearError: mockClearError,
  }),
}))

describe('RegisterPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockAuthRef.current = null
  })

  it('renders the registration form', () => {
    render(<RegisterPage />)
    expect(screen.getByRole('heading', { name: 'Create Account' })).toBeInTheDocument()
    expect(screen.getByLabelText('Full Name')).toBeInTheDocument()
    expect(screen.getByLabelText('Email Address')).toBeInTheDocument()
    expect(screen.getByLabelText('Password')).toBeInTheDocument()
    expect(screen.getByLabelText('Confirm Password')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Create Account' })).toBeInTheDocument()
  })

  it('shows validation error when name is empty', async () => {
    const user = userEvent.setup()
    render(<RegisterPage />)

    await user.click(screen.getByRole('button', { name: 'Create Account' }))

    expect(screen.getByText('Name is required')).toBeInTheDocument()
  })

  it('shows validation error when name is too short', async () => {
    const user = userEvent.setup()
    render(<RegisterPage />)

    await user.type(screen.getByLabelText('Full Name'), 'A')
    await user.click(screen.getByRole('button', { name: 'Create Account' }))

    expect(screen.getByText('Name must be at least 2 characters')).toBeInTheDocument()
  })

  it('shows validation error when email is empty', async () => {
    const user = userEvent.setup()
    render(<RegisterPage />)

    await user.type(screen.getByLabelText('Full Name'), 'John Doe')
    await user.click(screen.getByRole('button', { name: 'Create Account' }))

    expect(screen.getByText('Email is required')).toBeInTheDocument()
  })

  it('shows validation error for invalid email format', async () => {
    const user = userEvent.setup()
    render(<RegisterPage />)

    await user.type(screen.getByLabelText('Full Name'), 'John Doe')
    await user.type(screen.getByLabelText('Email Address'), 'not-an-email')
    await user.click(screen.getByRole('button', { name: 'Create Account' }))

    expect(screen.getByText('Invalid email format')).toBeInTheDocument()
  })

  it('shows validation error when password is empty', async () => {
    const user = userEvent.setup()
    render(<RegisterPage />)

    await user.type(screen.getByLabelText('Full Name'), 'John Doe')
    await user.type(screen.getByLabelText('Email Address'), 'john@example.com')
    await user.click(screen.getByRole('button', { name: 'Create Account' }))

    expect(screen.getByText('Password is required')).toBeInTheDocument()
  })

  it('shows validation error when password is too short', async () => {
    const user = userEvent.setup()
    render(<RegisterPage />)

    await user.type(screen.getByLabelText('Full Name'), 'John Doe')
    await user.type(screen.getByLabelText('Email Address'), 'john@example.com')
    await user.type(screen.getByLabelText('Password'), '12345')
    await user.click(screen.getByRole('button', { name: 'Create Account' }))

    expect(screen.getByText('Password must be at least 8 characters')).toBeInTheDocument()
  })

  it('shows validation error when password lacks complexity', async () => {
    const user = userEvent.setup()
    render(<RegisterPage />)

    await user.type(screen.getByLabelText('Full Name'), 'John Doe')
    await user.type(screen.getByLabelText('Email Address'), 'john@example.com')
    await user.type(screen.getByLabelText('Password'), 'alllowercase')
    await user.click(screen.getByRole('button', { name: 'Create Account' }))

    expect(
      screen.getByText('Password must contain uppercase, lowercase, and number')
    ).toBeInTheDocument()
  })

  it('shows validation error when passwords do not match', async () => {
    const user = userEvent.setup()
    render(<RegisterPage />)

    await user.type(screen.getByLabelText('Full Name'), 'John Doe')
    await user.type(screen.getByLabelText('Email Address'), 'john@example.com')
    await user.type(screen.getByLabelText('Password'), 'Password1')
    await user.type(screen.getByLabelText('Confirm Password'), 'Password2')
    await user.click(screen.getByRole('button', { name: 'Create Account' }))

    expect(screen.getByText('Passwords do not match')).toBeInTheDocument()
  })

  it('shows validation error when confirmation is empty', async () => {
    const user = userEvent.setup()
    render(<RegisterPage />)

    await user.type(screen.getByLabelText('Full Name'), 'John Doe')
    await user.type(screen.getByLabelText('Email Address'), 'john@example.com')
    await user.type(screen.getByLabelText('Password'), 'Password1')
    await user.click(screen.getByRole('button', { name: 'Create Account' }))

    expect(screen.getByText('Please confirm your password')).toBeInTheDocument()
  })

  it('calls registerHttpOnly with form data on valid submission', async () => {
    mockRegisterHttpOnly.mockResolvedValue(undefined)
    const user = userEvent.setup()
    render(<RegisterPage />)

    await user.type(screen.getByLabelText('Full Name'), 'John Doe')
    await user.type(screen.getByLabelText('Email Address'), 'john@example.com')
    await user.type(screen.getByLabelText('Password'), 'Password1')
    await user.type(screen.getByLabelText('Confirm Password'), 'Password1')
    await user.click(screen.getByRole('button', { name: 'Create Account' }))

    await waitFor(() => {
      expect(mockRegisterHttpOnly).toHaveBeenCalledWith(
        'John Doe',
        'john@example.com',
        'Password1',
        'Password1'
      )
    })
  })

  it('displays auth error from context', () => {
    mockAuthRef.current = 'Email already taken'
    render(<RegisterPage />)

    expect(screen.getByText('Email already taken')).toBeInTheDocument()
  })

  it('has a link to login page', () => {
    render(<RegisterPage />)
    expect(screen.getByText('Sign in here')).toBeInTheDocument()
  })

  it('navigates to login when sign in link is clicked', async () => {
    const user = userEvent.setup()
    render(<RegisterPage />)

    await user.click(screen.getByText('Sign in here'))
    expect(mockNavigate).toHaveBeenCalledWith('/login')
  })

  it('navigates to home when back button is clicked', async () => {
    const user = userEvent.setup()
    render(<RegisterPage />)

    await user.click(screen.getByText('← Back to home'))
    expect(mockNavigate).toHaveBeenCalledWith('/')
  })

  it('clears field error on input change', async () => {
    const user = userEvent.setup()
    render(<RegisterPage />)

    await user.click(screen.getByRole('button', { name: 'Create Account' }))
    expect(screen.getByText('Name is required')).toBeInTheDocument()

    await user.type(screen.getByLabelText('Full Name'), 'John')
    expect(screen.queryByText('Name is required')).not.toBeInTheDocument()
  })
})
