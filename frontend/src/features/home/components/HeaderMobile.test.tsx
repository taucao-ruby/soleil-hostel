import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import HeaderMobile from './HeaderMobile'

// ── Mocks ───────────────────────────────────────────────

const { mockUseAuth } = vi.hoisted(() => ({
  mockUseAuth: vi.fn(),
}))

vi.mock('@/features/auth/AuthContext', () => ({
  useAuth: mockUseAuth,
}))

// ── Helpers ─────────────────────────────────────────────

function renderHeader() {
  return render(
    <MemoryRouter initialEntries={['/']}>
      <HeaderMobile />
    </MemoryRouter>
  )
}

// ── Setup ───────────────────────────────────────────────

beforeEach(() => {
  vi.clearAllMocks()
})

// ── Tests ───────────────────────────────────────────────

describe('HeaderMobile', () => {
  describe('brand logo', () => {
    it('renders Soleil Hostel logo linking to /', () => {
      mockUseAuth.mockReturnValue({
        isAuthenticated: false,
        user: null,
        logoutHttpOnly: vi.fn(),
      })

      renderHeader()
      expect(screen.getByText('Soleil')).toBeInTheDocument()
      expect(screen.getByText('Hostel')).toBeInTheDocument()
    })
  })

  describe('unauthenticated state (M-03 fix)', () => {
    beforeEach(() => {
      mockUseAuth.mockReturnValue({
        isAuthenticated: false,
        user: null,
        logoutHttpOnly: vi.fn(),
      })
    })

    it('shows Đăng nhập and Đăng ký links when menu is open', async () => {
      renderHeader()
      const user = userEvent.setup()

      await user.click(screen.getByRole('button', { name: 'Mở menu' }))

      expect(screen.getByText('Đăng nhập')).toBeInTheDocument()
      expect(screen.getByText('Đăng ký')).toBeInTheDocument()
      expect(screen.queryByText('Bảng điều khiển')).not.toBeInTheDocument()
      expect(screen.queryByText('Đăng xuất')).not.toBeInTheDocument()
    })

    it('shows Xem phòng link in menu', async () => {
      renderHeader()
      const user = userEvent.setup()

      await user.click(screen.getByRole('button', { name: 'Mở menu' }))

      expect(screen.getByText('Xem phòng')).toBeInTheDocument()
    })
  })

  describe('authenticated state (M-03 fix)', () => {
    const mockLogout = vi.fn().mockResolvedValue(undefined)

    beforeEach(() => {
      mockUseAuth.mockReturnValue({
        isAuthenticated: true,
        user: { id: 1, name: 'Nguyễn Văn A', email: 'a@test.com', role: 'user' },
        logoutHttpOnly: mockLogout,
      })
    })

    it('shows Bảng điều khiển and Đăng xuất when menu is open', async () => {
      renderHeader()
      const user = userEvent.setup()

      await user.click(screen.getByRole('button', { name: 'Mở menu' }))

      expect(screen.getByText('Bảng điều khiển')).toBeInTheDocument()
      expect(screen.getByText('Đăng xuất')).toBeInTheDocument()
      expect(screen.queryByText('Đăng nhập')).not.toBeInTheDocument()
      expect(screen.queryByText('Đăng ký')).not.toBeInTheDocument()
    })

    it('displays user name in menu', async () => {
      renderHeader()
      const user = userEvent.setup()

      await user.click(screen.getByRole('button', { name: 'Mở menu' }))

      expect(screen.getByText('Nguyễn Văn A')).toBeInTheDocument()
    })

    it('falls back to email when name is null', async () => {
      mockUseAuth.mockReturnValue({
        isAuthenticated: true,
        user: { id: 1, name: null, email: 'fallback@test.com', role: 'user' },
        logoutHttpOnly: mockLogout,
      })

      renderHeader()
      const user = userEvent.setup()

      await user.click(screen.getByRole('button', { name: 'Mở menu' }))

      expect(screen.getByText('fallback@test.com')).toBeInTheDocument()
    })

    it('calls logoutHttpOnly when Đăng xuất is clicked', async () => {
      renderHeader()
      const user = userEvent.setup()

      await user.click(screen.getByRole('button', { name: 'Mở menu' }))
      await user.click(screen.getByText('Đăng xuất'))

      expect(mockLogout).toHaveBeenCalledOnce()
    })
  })

  describe('hamburger toggle', () => {
    beforeEach(() => {
      mockUseAuth.mockReturnValue({
        isAuthenticated: false,
        user: null,
        logoutHttpOnly: vi.fn(),
      })
    })

    it('toggles menu open and closed', async () => {
      renderHeader()
      const user = userEvent.setup()
      const hamburger = screen.getByRole('button', { name: 'Mở menu' })

      expect(hamburger).toHaveAttribute('aria-expanded', 'false')

      await user.click(hamburger)
      expect(hamburger).toHaveAttribute('aria-expanded', 'true')
      expect(screen.getByText('Xem phòng')).toBeInTheDocument()

      await user.click(hamburger)
      expect(hamburger).toHaveAttribute('aria-expanded', 'false')
      expect(screen.queryByText('Xem phòng')).not.toBeInTheDocument()
    })
  })
})
