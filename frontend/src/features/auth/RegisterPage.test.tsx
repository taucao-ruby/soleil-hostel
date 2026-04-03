import { act, fireEvent, render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { MemoryRouter } from 'react-router-dom'
import RegisterPage from './RegisterPage'

const { mockNavigate, mockRegisterHttpOnly, mockClearError, mockUseAuth } = vi.hoisted(() => ({
  mockNavigate: vi.fn(),
  mockRegisterHttpOnly: vi.fn(),
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
    registerHttpOnly: mockRegisterHttpOnly,
    error: null,
    clearError: mockClearError,
    ...overrides,
  }
}

function renderRegisterPage() {
  return render(
    <MemoryRouter>
      <RegisterPage />
    </MemoryRouter>
  )
}

describe('RegisterPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.useRealTimers()
    mockUseAuth.mockReturnValue(buildAuthState())
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('renders the register form with the requested Vietnamese copy', () => {
    renderRegisterPage()

    expect(screen.getByRole('heading', { name: 'Tạo tài khoản' })).toBeInTheDocument()
    expect(screen.getByText('Đặt phòng nhanh hơn với tài khoản Soleil')).toBeInTheDocument()
    expect(screen.getByLabelText('Họ và tên')).toBeInTheDocument()
    expect(screen.getByLabelText('Email')).toBeInTheDocument()
    expect(screen.getByLabelText('Mật khẩu')).toBeInTheDocument()
    expect(screen.getByLabelText('Xác nhận mật khẩu')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Tạo tài khoản' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Đăng nhập' })).toHaveAttribute('href', '/login')
  })

  it('shows the field validation messages when submitted empty', async () => {
    const user = userEvent.setup()
    renderRegisterPage()

    await user.click(screen.getByRole('button', { name: 'Tạo tài khoản' }))

    expect(mockRegisterHttpOnly).not.toHaveBeenCalled()
    expect(screen.getByText('Tên cần ít nhất 2 ký tự')).toBeInTheDocument()
    expect(screen.getByText('Địa chỉ email không hợp lệ')).toBeInTheDocument()
    expect(
      screen.getByText(
        'Mật khẩu cần ít nhất 8 ký tự, 1 chữ hoa, 1 chữ thường, 1 số và 1 ký tự đặc biệt'
      )
    ).toBeInTheDocument()
    expect(screen.getByText('Mật khẩu xác nhận không khớp')).toBeInTheDocument()
  })

  it('updates the password strength bar from weak to strong', async () => {
    const user = userEvent.setup()
    renderRegisterPage()

    const passwordInput = screen.getByLabelText('Mật khẩu')

    await user.type(passwordInput, 'pass')
    expect(screen.getByTestId('password-strength-segment-0')).toHaveClass('bg-red-500')
    expect(screen.getByTestId('password-strength-segment-1')).toHaveClass('bg-stone-200')
    expect(screen.getByText('Yếu')).toBeInTheDocument()

    await user.clear(passwordInput)
    await user.type(passwordInput, 'Password')
    expect(screen.getByTestId('password-strength-segment-0')).toHaveClass('bg-red-500')
    expect(screen.getByTestId('password-strength-segment-1')).toHaveClass('bg-red-500')
    expect(screen.getByTestId('password-strength-segment-2')).toHaveClass('bg-stone-200')
    expect(screen.getByText('Yếu')).toBeInTheDocument()

    await user.clear(passwordInput)
    await user.type(passwordInput, 'Password1')
    expect(screen.getByTestId('password-strength-segment-0')).toHaveClass('bg-amber-400')
    expect(screen.getByTestId('password-strength-segment-1')).toHaveClass('bg-amber-400')
    expect(screen.getByTestId('password-strength-segment-2')).toHaveClass('bg-amber-400')
    expect(screen.getByTestId('password-strength-segment-3')).toHaveClass('bg-stone-200')
    expect(screen.getByText('Trung bình')).toBeInTheDocument()

    await user.clear(passwordInput)
    await user.type(passwordInput, 'Password1!')
    expect(screen.getByTestId('password-strength-segment-0')).toHaveClass('bg-emerald-500')
    expect(screen.getByTestId('password-strength-segment-1')).toHaveClass('bg-emerald-500')
    expect(screen.getByTestId('password-strength-segment-2')).toHaveClass('bg-emerald-500')
    expect(screen.getByTestId('password-strength-segment-3')).toHaveClass('bg-emerald-500')
    expect(screen.getByText('Mạnh')).toBeInTheDocument()
  })

  it('shows the email and password confirmation validation messages', async () => {
    const user = userEvent.setup()
    renderRegisterPage()

    await user.type(screen.getByLabelText('Họ và tên'), 'Nguyen Van A')
    await user.type(screen.getByLabelText('Email'), 'not-an-email')
    await user.type(screen.getByLabelText('Mật khẩu'), 'Password1')
    await user.type(screen.getByLabelText('Xác nhận mật khẩu'), 'Password2')
    await user.click(screen.getByRole('button', { name: 'Tạo tài khoản' }))

    expect(screen.getByText('Địa chỉ email không hợp lệ')).toBeInTheDocument()
    expect(screen.getByText('Mật khẩu xác nhận không khớp')).toBeInTheDocument()
  })

  it('clears a field error as the user types again', async () => {
    const user = userEvent.setup()
    renderRegisterPage()

    await user.click(screen.getByRole('button', { name: 'Tạo tài khoản' }))
    expect(screen.getByText('Tên cần ít nhất 2 ký tự')).toBeInTheDocument()

    await user.type(screen.getByLabelText('Họ và tên'), 'Ng')
    expect(screen.queryByText('Tên cần ít nhất 2 ký tự')).not.toBeInTheDocument()
  })

  it('calls registerHttpOnly with trimmed values on valid submission', async () => {
    mockRegisterHttpOnly.mockResolvedValue(undefined)
    const user = userEvent.setup()
    renderRegisterPage()

    await user.type(screen.getByLabelText('Họ và tên'), '  Nguyen Van A  ')
    await user.type(screen.getByLabelText('Email'), '  user@example.com  ')
    await user.type(screen.getByLabelText('Mật khẩu'), 'Password1!')
    await user.type(screen.getByLabelText('Xác nhận mật khẩu'), 'Password1!')
    await user.click(screen.getByRole('button', { name: 'Tạo tài khoản' }))

    await waitFor(() => {
      expect(mockClearError).toHaveBeenCalled()
      expect(mockRegisterHttpOnly).toHaveBeenCalledWith(
        'Nguyen Van A',
        'user@example.com',
        'Password1!',
        'Password1!'
      )
    })
  })

  it('shows the loading state while registration is in progress', async () => {
    mockRegisterHttpOnly.mockImplementation(
      () =>
        new Promise<void>(() => {
          return
        })
    )
    const user = userEvent.setup()
    renderRegisterPage()

    await user.type(screen.getByLabelText('Họ và tên'), 'Nguyen Van A')
    await user.type(screen.getByLabelText('Email'), 'user@example.com')
    await user.type(screen.getByLabelText('Mật khẩu'), 'Password1!')
    await user.type(screen.getByLabelText('Xác nhận mật khẩu'), 'Password1!')
    await user.click(screen.getByRole('button', { name: 'Tạo tài khoản' }))

    const submitButton = screen.getByRole('button', { name: 'Đang tạo tài khoản...' })
    expect(submitButton).toBeDisabled()
    expect(submitButton).toHaveAttribute('aria-busy', 'true')
  })

  it('displays the auth error from context in the shared alert banner', () => {
    mockUseAuth.mockReturnValue(buildAuthState({ error: 'Email này đã được đăng ký' }))

    renderRegisterPage()

    expect(screen.getByRole('alert')).toHaveTextContent('Email này đã được đăng ký')
  })

  it('shows the Vietnamese fallback error when submission fails without an auth message', async () => {
    mockRegisterHttpOnly.mockRejectedValue(new Error('request failed'))
    const user = userEvent.setup()
    renderRegisterPage()

    await user.type(screen.getByLabelText('Họ và tên'), 'Nguyen Van A')
    await user.type(screen.getByLabelText('Email'), 'user@example.com')
    await user.type(screen.getByLabelText('Mật khẩu'), 'Password1!')
    await user.type(screen.getByLabelText('Xác nhận mật khẩu'), 'Password1!')
    await user.click(screen.getByRole('button', { name: 'Tạo tài khoản' }))

    expect(await screen.findByText('Đăng ký thất bại. Vui lòng thử lại.')).toBeInTheDocument()
  })

  it('shows the success message and navigates to the dashboard after 1000ms', async () => {
    vi.useFakeTimers()
    mockRegisterHttpOnly.mockResolvedValue(undefined)
    renderRegisterPage()

    fireEvent.change(screen.getByLabelText('Họ và tên'), {
      target: { value: 'Nguyen Van A' },
    })
    fireEvent.change(screen.getByLabelText('Email'), {
      target: { value: 'user@example.com' },
    })
    fireEvent.change(screen.getByLabelText('Mật khẩu'), {
      target: { value: 'Password1!' },
    })
    fireEvent.change(screen.getByLabelText('Xác nhận mật khẩu'), {
      target: { value: 'Password1!' },
    })
    fireEvent.click(screen.getByRole('button', { name: 'Tạo tài khoản' }))

    await act(async () => {
      await Promise.resolve()
    })

    expect(mockRegisterHttpOnly).toHaveBeenCalledWith(
      'Nguyen Van A',
      'user@example.com',
      'Password1!',
      'Password1!'
    )
    expect(screen.getByRole('status')).toHaveTextContent(
      'Tài khoản đã được tạo! Đang chuyển hướng...'
    )
    expect(mockNavigate).not.toHaveBeenCalled()

    act(() => {
      vi.advanceTimersByTime(999)
    })
    expect(mockNavigate).not.toHaveBeenCalled()

    act(() => {
      vi.advanceTimersByTime(1)
    })

    expect(mockNavigate).toHaveBeenCalledWith('/email/verify')
  })
})
