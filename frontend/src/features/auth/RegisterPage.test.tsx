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
    expect(screen.getByRole('heading', { name: 'Tạo tài khoản' })).toBeInTheDocument()
    expect(screen.getByLabelText('Họ và tên')).toBeInTheDocument()
    expect(screen.getByLabelText('Địa chỉ email')).toBeInTheDocument()
    expect(screen.getByLabelText('Mật khẩu')).toBeInTheDocument()
    expect(screen.getByLabelText('Xác nhận mật khẩu')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Tạo tài khoản' })).toBeInTheDocument()
  })

  it('shows validation error when name is empty', async () => {
    const user = userEvent.setup()
    render(<RegisterPage />)

    await user.click(screen.getByRole('button', { name: 'Tạo tài khoản' }))

    expect(screen.getByText('Vui lòng nhập họ tên')).toBeInTheDocument()
  })

  it('shows validation error when name is too short', async () => {
    const user = userEvent.setup()
    render(<RegisterPage />)

    await user.type(screen.getByLabelText('Họ và tên'), 'A')
    await user.click(screen.getByRole('button', { name: 'Tạo tài khoản' }))

    expect(screen.getByText('Họ tên phải có ít nhất 2 ký tự')).toBeInTheDocument()
  })

  it('shows validation error when email is empty', async () => {
    const user = userEvent.setup()
    render(<RegisterPage />)

    await user.type(screen.getByLabelText('Họ và tên'), 'John Doe')
    await user.click(screen.getByRole('button', { name: 'Tạo tài khoản' }))

    expect(screen.getByText('Vui lòng nhập email')).toBeInTheDocument()
  })

  it('shows validation error for invalid email format', async () => {
    const user = userEvent.setup()
    render(<RegisterPage />)

    await user.type(screen.getByLabelText('Họ và tên'), 'John Doe')
    await user.type(screen.getByLabelText('Địa chỉ email'), 'not-an-email')
    await user.click(screen.getByRole('button', { name: 'Tạo tài khoản' }))

    expect(screen.getByText('Email không hợp lệ')).toBeInTheDocument()
  })

  it('shows validation error when password is empty', async () => {
    const user = userEvent.setup()
    render(<RegisterPage />)

    await user.type(screen.getByLabelText('Họ và tên'), 'John Doe')
    await user.type(screen.getByLabelText('Địa chỉ email'), 'john@example.com')
    await user.click(screen.getByRole('button', { name: 'Tạo tài khoản' }))

    expect(screen.getByText('Vui lòng nhập mật khẩu')).toBeInTheDocument()
  })

  it('shows validation error when password is too short', async () => {
    const user = userEvent.setup()
    render(<RegisterPage />)

    await user.type(screen.getByLabelText('Họ và tên'), 'John Doe')
    await user.type(screen.getByLabelText('Địa chỉ email'), 'john@example.com')
    await user.type(screen.getByLabelText('Mật khẩu'), '12345')
    await user.click(screen.getByRole('button', { name: 'Tạo tài khoản' }))

    expect(screen.getByText('Mật khẩu phải có ít nhất 8 ký tự')).toBeInTheDocument()
  })

  it('shows validation error when password lacks complexity', async () => {
    const user = userEvent.setup()
    render(<RegisterPage />)

    await user.type(screen.getByLabelText('Họ và tên'), 'John Doe')
    await user.type(screen.getByLabelText('Địa chỉ email'), 'john@example.com')
    await user.type(screen.getByLabelText('Mật khẩu'), 'alllowercase')
    await user.click(screen.getByRole('button', { name: 'Tạo tài khoản' }))

    expect(screen.getByText('Mật khẩu phải chứa chữ hoa, chữ thường và số')).toBeInTheDocument()
  })

  it('shows validation error when passwords do not match', async () => {
    const user = userEvent.setup()
    render(<RegisterPage />)

    await user.type(screen.getByLabelText('Họ và tên'), 'John Doe')
    await user.type(screen.getByLabelText('Địa chỉ email'), 'john@example.com')
    await user.type(screen.getByLabelText('Mật khẩu'), 'Password1')
    await user.type(screen.getByLabelText('Xác nhận mật khẩu'), 'Password2')
    await user.click(screen.getByRole('button', { name: 'Tạo tài khoản' }))

    expect(screen.getByText('Mật khẩu không khớp')).toBeInTheDocument()
  })

  it('shows validation error when confirmation is empty', async () => {
    const user = userEvent.setup()
    render(<RegisterPage />)

    await user.type(screen.getByLabelText('Họ và tên'), 'John Doe')
    await user.type(screen.getByLabelText('Địa chỉ email'), 'john@example.com')
    await user.type(screen.getByLabelText('Mật khẩu'), 'Password1')
    await user.click(screen.getByRole('button', { name: 'Tạo tài khoản' }))

    expect(screen.getByText('Vui lòng xác nhận mật khẩu')).toBeInTheDocument()
  })

  it('calls registerHttpOnly with form data on valid submission', async () => {
    mockRegisterHttpOnly.mockResolvedValue(undefined)
    const user = userEvent.setup()
    render(<RegisterPage />)

    await user.type(screen.getByLabelText('Họ và tên'), 'John Doe')
    await user.type(screen.getByLabelText('Địa chỉ email'), 'john@example.com')
    await user.type(screen.getByLabelText('Mật khẩu'), 'Password1')
    await user.type(screen.getByLabelText('Xác nhận mật khẩu'), 'Password1')
    await user.click(screen.getByRole('button', { name: 'Tạo tài khoản' }))

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
    expect(screen.getByText('Đăng nhập tại đây')).toBeInTheDocument()
  })

  it('navigates to login when sign in link is clicked', async () => {
    const user = userEvent.setup()
    render(<RegisterPage />)

    await user.click(screen.getByText('Đăng nhập tại đây'))
    expect(mockNavigate).toHaveBeenCalledWith('/login')
  })

  it('navigates to home when back button is clicked', async () => {
    const user = userEvent.setup()
    render(<RegisterPage />)

    await user.click(screen.getByText('← Về trang chủ'))
    expect(mockNavigate).toHaveBeenCalledWith('/')
  })

  it('clears field error on input change', async () => {
    const user = userEvent.setup()
    render(<RegisterPage />)

    await user.click(screen.getByRole('button', { name: 'Tạo tài khoản' }))
    expect(screen.getByText('Vui lòng nhập họ tên')).toBeInTheDocument()

    await user.type(screen.getByLabelText('Họ và tên'), 'John')
    expect(screen.queryByText('Vui lòng nhập họ tên')).not.toBeInTheDocument()
  })
})
