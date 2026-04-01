import { act, fireEvent, render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest'
import { MemoryRouter } from 'react-router-dom'
import LoginPage from './LoginPage'

const { mockNavigate, mockLoginHttpOnly, mockClearError, mockUseAuth } = vi.hoisted(() => ({
  mockNavigate: vi.fn(),
  mockLoginHttpOnly: vi.fn(),
  mockClearError: vi.fn(),
  mockUseAuth: vi.fn(),
}))

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')
  return {
    ...actual,
    useNavigate: () => mockNavigate,
  }
})

vi.mock('./AuthContext', () => ({
  useAuth: mockUseAuth,
}))

function buildAuthState(overrides: Record<string, unknown> = {}) {
  return {
    isAuthenticated: false,
    loginHttpOnly: mockLoginHttpOnly,
    error: null,
    clearError: mockClearError,
    ...overrides,
  }
}

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
    vi.useRealTimers()
    mockUseAuth.mockReturnValue(buildAuthState())
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('renders the login form with Vietnamese copy and footer links', () => {
    renderLoginPage()

    expect(screen.getByText('Đăng nhập tài khoản')).toBeInTheDocument()
    expect(screen.getByLabelText('Địa chỉ email')).toBeInTheDocument()
    expect(screen.getByLabelText('Mật khẩu')).toBeInTheDocument()
    expect(
      screen.getByRole('checkbox', { name: 'Ghi nhớ đăng nhập trong 30 ngày' })
    ).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Đăng nhập' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Đăng ký ngay' })).toHaveAttribute('href', '/register')
    expect(screen.getByRole('link', { name: '← Quay về trang chủ' })).toHaveAttribute('href', '/')
  })

  it('still renders the form when the user is already authenticated', () => {
    mockUseAuth.mockReturnValue(buildAuthState({ isAuthenticated: true }))

    renderLoginPage()

    expect(screen.getByText('Đăng nhập tài khoản')).toBeInTheDocument()
    expect(mockNavigate).not.toHaveBeenCalled()
  })

  it('shows validation errors when required fields are empty', async () => {
    const user = userEvent.setup()
    renderLoginPage()

    await user.click(screen.getByRole('button', { name: 'Đăng nhập' }))

    expect(mockLoginHttpOnly).not.toHaveBeenCalled()
    expect(screen.getByText('Vui lòng nhập email')).toBeInTheDocument()
    expect(screen.getByText('Vui lòng nhập mật khẩu')).toBeInTheDocument()
  })

  it('shows a validation error for an invalid email address', async () => {
    const user = userEvent.setup()
    renderLoginPage()

    await user.type(screen.getByLabelText('Địa chỉ email'), 'invalid-email')
    await user.type(screen.getByLabelText('Mật khẩu'), 'password123')
    await user.click(screen.getByRole('button', { name: 'Đăng nhập' }))

    expect(screen.getByText('Email không hợp lệ')).toBeInTheDocument()
  })

  it('toggles password visibility', async () => {
    const user = userEvent.setup()
    renderLoginPage()

    const passwordInput = screen.getByLabelText('Mật khẩu')
    expect(passwordInput).toHaveAttribute('type', 'password')

    await user.click(screen.getByRole('button', { name: 'Hiện mật khẩu' }))
    expect(passwordInput).toHaveAttribute('type', 'text')

    await user.click(screen.getByRole('button', { name: 'Ẩn mật khẩu' }))
    expect(passwordInput).toHaveAttribute('type', 'password')
  })

  it('calls loginHttpOnly with trimmed email and remember me selection', async () => {
    mockLoginHttpOnly.mockResolvedValue(undefined)
    const user = userEvent.setup()
    renderLoginPage()

    await user.type(screen.getByLabelText('Địa chỉ email'), '  user@example.com  ')
    await user.type(screen.getByLabelText('Mật khẩu'), 'password123')
    await user.click(screen.getByRole('checkbox', { name: 'Ghi nhớ đăng nhập trong 30 ngày' }))
    await user.click(screen.getByRole('button', { name: 'Đăng nhập' }))

    await waitFor(() => {
      expect(mockClearError).toHaveBeenCalled()
      expect(mockLoginHttpOnly).toHaveBeenCalledWith('user@example.com', 'password123', true)
    })
  })

  it('shows the loading state while submission is in progress', async () => {
    mockLoginHttpOnly.mockImplementation(
      () =>
        new Promise<void>(() => {
          return
        })
    )
    const user = userEvent.setup()
    renderLoginPage()

    await user.type(screen.getByLabelText('Địa chỉ email'), 'user@example.com')
    await user.type(screen.getByLabelText('Mật khẩu'), 'password123')
    await user.click(screen.getByRole('button', { name: 'Đăng nhập' }))

    const submitButton = screen.getByRole('button', { name: 'Đang đăng nhập...' })
    expect(submitButton).toBeDisabled()
    expect(submitButton).toHaveAttribute('aria-busy', 'true')
  })

  it('displays the auth error from context', () => {
    mockUseAuth.mockReturnValue(buildAuthState({ error: 'Sai email hoặc mật khẩu' }))

    renderLoginPage()

    expect(screen.getByRole('alert')).toHaveTextContent('Sai email hoặc mật khẩu')
  })

  it('shows the Vietnamese fallback error when submission fails without an auth message', async () => {
    mockLoginHttpOnly.mockRejectedValue(new Error('request failed'))
    const user = userEvent.setup()
    renderLoginPage()

    await user.type(screen.getByLabelText('Địa chỉ email'), 'user@example.com')
    await user.type(screen.getByLabelText('Mật khẩu'), 'password123')
    await user.click(screen.getByRole('button', { name: 'Đăng nhập' }))

    expect(await screen.findByText('Đăng nhập thất bại. Vui lòng thử lại.')).toBeInTheDocument()
  })

  it('navigates to the dashboard after a 500ms delay on success', async () => {
    vi.useFakeTimers()
    mockLoginHttpOnly.mockResolvedValue(undefined)
    renderLoginPage()

    fireEvent.change(screen.getByLabelText('Địa chỉ email'), {
      target: { value: 'user@example.com' },
    })
    fireEvent.change(screen.getByLabelText('Mật khẩu'), {
      target: { value: 'password123' },
    })
    fireEvent.click(screen.getByRole('button', { name: 'Đăng nhập' }))

    await act(async () => {
      await Promise.resolve()
    })

    expect(mockLoginHttpOnly).toHaveBeenCalledWith('user@example.com', 'password123', false)
    expect(mockNavigate).not.toHaveBeenCalled()

    act(() => {
      vi.advanceTimersByTime(499)
    })
    expect(mockNavigate).not.toHaveBeenCalled()

    act(() => {
      vi.advanceTimersByTime(1)
    })

    expect(mockNavigate).toHaveBeenCalledWith('/dashboard')
  })
})
