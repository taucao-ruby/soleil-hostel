import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import LoginPage from './LoginPage'

// ── Hoisted mock state (must be declared before vi.mock factories run) ────
const { mockNavigate, mockLoginHttpOnly, mockClearError, mockAuthRef } = vi.hoisted(() => ({
  mockNavigate: vi.fn(),
  mockLoginHttpOnly: vi.fn(),
  mockClearError: vi.fn(),
  mockAuthRef: { current: null as string | null },
}))

// Partial mock — keep real Router components, override useNavigate
vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')
  return {
    ...actual,
    useNavigate: () => mockNavigate,
  }
})

// Mock AuthContext
vi.mock('./AuthContext', () => ({
  useAuth: () => ({
    loginHttpOnly: mockLoginHttpOnly,
    error: mockAuthRef.current,
    clearError: mockClearError,
  }),
}))

function renderLoginPage() {
  return render(
    <MemoryRouter>
      <LoginPage />
    </MemoryRouter>
  )
}

describe('LoginPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockAuthRef.current = null
  })

  it('renders the login form', () => {
    renderLoginPage()
    expect(screen.getByText('Chào mừng trở lại')).toBeInTheDocument()
    expect(screen.getByLabelText('Địa chỉ email')).toBeInTheDocument()
    expect(screen.getByLabelText('Mật khẩu')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Đăng nhập' })).toBeInTheDocument()
  })

  it('shows validation error when email is empty', async () => {
    const user = userEvent.setup()
    renderLoginPage()

    await user.click(screen.getByRole('button', { name: 'Đăng nhập' }))

    expect(screen.getByText('Vui lòng nhập email')).toBeInTheDocument()
  })

  it('shows validation error for invalid email format', async () => {
    const user = userEvent.setup()
    renderLoginPage()

    await user.type(screen.getByLabelText('Địa chỉ email'), 'invalid-email')
    await user.click(screen.getByRole('button', { name: 'Đăng nhập' }))

    expect(screen.getByText('Email không hợp lệ')).toBeInTheDocument()
  })

  it('shows validation error when password is empty', async () => {
    const user = userEvent.setup()
    renderLoginPage()

    await user.type(screen.getByLabelText('Địa chỉ email'), 'user@example.com')
    await user.click(screen.getByRole('button', { name: 'Đăng nhập' }))

    expect(screen.getByText('Vui lòng nhập mật khẩu')).toBeInTheDocument()
  })

  it('shows validation error when password is too short', async () => {
    const user = userEvent.setup()
    renderLoginPage()

    await user.type(screen.getByLabelText('Địa chỉ email'), 'user@example.com')
    await user.type(screen.getByLabelText('Mật khẩu'), '12345')
    await user.click(screen.getByRole('button', { name: 'Đăng nhập' }))

    expect(screen.getByText('Mật khẩu phải có ít nhất 8 ký tự')).toBeInTheDocument()
  })

  it('calls loginHttpOnly with form data on valid submission', async () => {
    mockLoginHttpOnly.mockResolvedValue(undefined)
    const user = userEvent.setup()
    renderLoginPage()

    await user.type(screen.getByLabelText('Địa chỉ email'), 'user@example.com')
    await user.type(screen.getByLabelText('Mật khẩu'), 'password123')
    await user.click(screen.getByRole('button', { name: 'Đăng nhập' }))

    await waitFor(() => {
      expect(mockLoginHttpOnly).toHaveBeenCalledWith('user@example.com', 'password123', false)
    })
  })

  it('displays auth error from context', () => {
    mockAuthRef.current = 'Invalid credentials'
    renderLoginPage()

    expect(screen.getByText('Invalid credentials')).toBeInTheDocument()
  })

  it('has a link to registration page', () => {
    renderLoginPage()
    expect(screen.getByText('Đăng ký tại đây')).toBeInTheDocument()
  })

  it('navigates to register when register link is clicked', async () => {
    const user = userEvent.setup()
    renderLoginPage()

    await user.click(screen.getByText('Đăng ký tại đây'))
    expect(mockNavigate).toHaveBeenCalledWith('/register')
  })
})
