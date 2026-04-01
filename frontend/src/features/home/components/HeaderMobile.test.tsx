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
    it('renders Soleil HOSTEL wordmark linking to /', () => {
      mockUseAuth.mockReturnValue({
        isAuthenticated: false,
        user: null,
        logoutHttpOnly: vi.fn(),
      })

      renderHeader()
      expect(screen.getByText('Soleil')).toBeInTheDocument()
      expect(screen.getByText('HOSTEL')).toBeInTheDocument()
    })
  })

  describe('unauthenticated state', () => {
    beforeEach(() => {
      mockUseAuth.mockReturnValue({
        isAuthenticated: false,
        user: null,
        logoutHttpOnly: vi.fn(),
      })
    })

    it('shows Phòng, Đăng nhập, and Đăng ký as inline links (no hamburger)', () => {
      renderHeader()
      expect(screen.getByRole('link', { name: 'Phòng' })).toBeInTheDocument()
      expect(screen.getByRole('link', { name: 'Đăng nhập' })).toBeInTheDocument()
      expect(screen.getByRole('link', { name: 'Đăng ký' })).toBeInTheDocument()
    })

    it('does not show Bảng điều khiển or Đăng xuất', () => {
      renderHeader()
      expect(screen.queryByText('Bảng điều khiển')).not.toBeInTheDocument()
      expect(screen.queryByText('Đăng xuất')).not.toBeInTheDocument()
    })

    it('has no hamburger button', () => {
      renderHeader()
      expect(screen.queryByRole('button', { name: /mở menu/i })).not.toBeInTheDocument()
    })
  })

  describe('authenticated state', () => {
    const mockLogout = vi.fn().mockResolvedValue(undefined)

    beforeEach(() => {
      mockUseAuth.mockReturnValue({
        isAuthenticated: true,
        user: { id: 1, name: 'Nguyễn Văn A', email: 'a@test.com', role: 'user' },
        logoutHttpOnly: mockLogout,
      })
    })

    it('shows Phòng, Bảng điều khiển, and Đăng xuất', () => {
      renderHeader()
      expect(screen.getByRole('link', { name: 'Phòng' })).toBeInTheDocument()
      expect(screen.getByRole('link', { name: 'Bảng điều khiển' })).toBeInTheDocument()
      expect(screen.getByRole('button', { name: 'Đăng xuất' })).toBeInTheDocument()
    })

    it('does not show Đăng nhập or Đăng ký', () => {
      renderHeader()
      expect(screen.queryByText('Đăng nhập')).not.toBeInTheDocument()
      expect(screen.queryByText('Đăng ký')).not.toBeInTheDocument()
    })

    it('calls logoutHttpOnly when Đăng xuất is clicked', async () => {
      renderHeader()
      const user = userEvent.setup()
      await user.click(screen.getByRole('button', { name: 'Đăng xuất' }))
      expect(mockLogout).toHaveBeenCalledOnce()
    })
  })
})
