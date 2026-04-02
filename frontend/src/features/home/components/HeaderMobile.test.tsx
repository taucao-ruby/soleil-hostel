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

function renderHeader(path = '/') {
  return render(
    <MemoryRouter initialEntries={[path]}>
      <HeaderMobile />
    </MemoryRouter>
  )
}

async function openMenu() {
  const user = userEvent.setup()
  const btn = screen.getByRole('button', { name: /mở menu/i })
  await user.click(btn)
  return user
}

// ── Setup ───────────────────────────────────────────────

beforeEach(() => {
  vi.clearAllMocks()
  mockUseAuth.mockReturnValue({
    isAuthenticated: false,
    user: null,
    logoutHttpOnly: vi.fn(),
  })
})

// ── Tests ───────────────────────────────────────────────

describe('HeaderMobile', () => {
  describe('brand logo', () => {
    it('renders Soleil HOSTEL wordmark linking to /', () => {
      renderHeader()
      expect(screen.getByText('Soleil')).toBeInTheDocument()
      expect(screen.getByText('HOSTEL')).toBeInTheDocument()
    })

    it('wordmark is a link to /', () => {
      renderHeader()
      const link = screen.getByRole('link', { name: /soleil/i })
      expect(link).toHaveAttribute('href', '/')
    })
  })

  describe('always-visible header bar', () => {
    it('shows the quick Phòng link', () => {
      renderHeader()
      expect(screen.getByRole('link', { name: 'Phòng' })).toBeInTheDocument()
    })

    it('shows the hamburger button', () => {
      renderHeader()
      expect(screen.getByRole('button', { name: /mở menu/i })).toBeInTheDocument()
    })

    it('hamburger button has aria-expanded=false initially', () => {
      renderHeader()
      const btn = screen.getByRole('button', { name: /mở menu/i })
      expect(btn).toHaveAttribute('aria-expanded', 'false')
    })
  })

  describe('drawer — closed by default', () => {
    it('does not show nav links before menu is opened', () => {
      renderHeader()
      expect(screen.queryByRole('navigation', { name: /menu điều hướng/i })).not.toBeInTheDocument()
    })

    it('does not show Đăng nhập before menu is opened', () => {
      renderHeader()
      expect(screen.queryByText('Đăng nhập')).not.toBeInTheDocument()
    })
  })

  describe('drawer — open state', () => {
    it('opens drawer when hamburger is clicked', async () => {
      renderHeader()
      await openMenu()
      expect(screen.getByRole('navigation', { name: /menu điều hướng/i })).toBeInTheDocument()
    })

    it('button aria-label changes to Đóng menu when open', async () => {
      renderHeader()
      await openMenu()
      expect(screen.getByRole('button', { name: /đóng menu/i })).toBeInTheDocument()
    })

    it('button aria-expanded becomes true when open', async () => {
      renderHeader()
      await openMenu()
      const btn = screen.getByRole('button', { name: /đóng menu/i })
      expect(btn).toHaveAttribute('aria-expanded', 'true')
    })

    it('shows all nav links in drawer', async () => {
      renderHeader()
      await openMenu()
      expect(screen.getByRole('link', { name: 'Trang chủ' })).toBeInTheDocument()
      expect(screen.getAllByRole('link', { name: 'Phòng' }).length).toBeGreaterThanOrEqual(1)
      expect(screen.getByRole('link', { name: 'Chi nhánh' })).toBeInTheDocument()
      expect(screen.getByRole('link', { name: 'Đặt phòng' })).toBeInTheDocument()
    })

    it('closes drawer when backdrop is clicked', async () => {
      renderHeader()
      const user = await openMenu()
      const backdrop = document.querySelector('[aria-hidden="true"]') as HTMLElement
      await user.click(backdrop)
      expect(screen.queryByRole('navigation', { name: /menu điều hướng/i })).not.toBeInTheDocument()
    })

    it('closes drawer when a nav link is clicked', async () => {
      renderHeader()
      await openMenu()
      const user = userEvent.setup()
      await user.click(screen.getByRole('link', { name: 'Chi nhánh' }))
      expect(screen.queryByRole('navigation', { name: /menu điều hướng/i })).not.toBeInTheDocument()
    })
  })

  describe('unauthenticated drawer', () => {
    it('shows Đăng nhập and Đăng ký ngay in drawer', async () => {
      renderHeader()
      await openMenu()
      expect(screen.getByRole('link', { name: 'Đăng nhập' })).toBeInTheDocument()
      expect(screen.getByRole('link', { name: 'Đăng ký ngay' })).toBeInTheDocument()
    })

    it('does not show Bảng điều khiển or Đăng xuất', async () => {
      renderHeader()
      await openMenu()
      expect(screen.queryByText('Bảng điều khiển')).not.toBeInTheDocument()
      expect(screen.queryByText('Đăng xuất')).not.toBeInTheDocument()
    })
  })

  describe('authenticated drawer', () => {
    const mockLogout = vi.fn().mockResolvedValue(undefined)

    beforeEach(() => {
      mockUseAuth.mockReturnValue({
        isAuthenticated: true,
        user: { id: 1, name: 'Nguyễn Văn A', email: 'a@test.com', role: 'user' },
        logoutHttpOnly: mockLogout,
      })
    })

    it('shows Bảng điều khiển and Đăng xuất in drawer', async () => {
      renderHeader()
      await openMenu()
      expect(screen.getByRole('link', { name: 'Bảng điều khiển' })).toBeInTheDocument()
      expect(screen.getByRole('button', { name: 'Đăng xuất' })).toBeInTheDocument()
    })

    it('does not show Đăng nhập or Đăng ký ngay', async () => {
      renderHeader()
      await openMenu()
      expect(screen.queryByText('Đăng nhập')).not.toBeInTheDocument()
      expect(screen.queryByText('Đăng ký ngay')).not.toBeInTheDocument()
    })

    it('calls logoutHttpOnly and closes drawer when Đăng xuất is clicked', async () => {
      renderHeader()
      const user = await openMenu()
      await user.click(screen.getByRole('button', { name: 'Đăng xuất' }))
      expect(mockLogout).toHaveBeenCalledOnce()
      expect(screen.queryByRole('navigation', { name: /menu điều hướng/i })).not.toBeInTheDocument()
    })
  })

  describe('active link indicator', () => {
    it('Trang chủ link has amber indicator when on / route', async () => {
      renderHeader('/')
      await openMenu()
      const homeLink = screen.getByRole('link', { name: 'Trang chủ' })
      expect(homeLink.className).toContain('C9973A')
    })

    it('Phòng link is not highlighted when on / route', async () => {
      renderHeader('/')
      await openMenu()
      // The drawer Phòng link (second one after quick link)
      const links = screen.getAllByRole('link', { name: 'Phòng' })
      const drawerPhong = links[links.length - 1]
      expect(drawerPhong.className).not.toContain('C9973A')
    })
  })
})
