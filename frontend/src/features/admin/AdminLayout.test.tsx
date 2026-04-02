import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import AdminLayout from './AdminLayout'

const { mockUseAuth, mockLogoutHttpOnly } = vi.hoisted(() => ({
  mockUseAuth: vi.fn(),
  mockLogoutHttpOnly: vi.fn(),
}))

vi.mock('@/features/auth/AuthContext', () => ({
  useAuth: mockUseAuth,
}))

function renderLayout(initialEntry: string | { pathname: string; state?: unknown } = '/admin') {
  return render(
    <MemoryRouter initialEntries={[initialEntry]}>
      <Routes>
        <Route path="/" element={<div>Home Page</div>} />
        <Route path="/admin" element={<AdminLayout />}>
          <Route index element={<div>Overview Content</div>} />
          <Route path="customers" element={<div>Customers Content</div>} />
          <Route path="rooms/new" element={<div>New Room Content</div>} />
          <Route path="rooms/:id/edit" element={<div>Edit Room Content</div>} />
        </Route>
      </Routes>
    </MemoryRouter>
  )
}

beforeEach(() => {
  vi.clearAllMocks()
  mockLogoutHttpOnly.mockResolvedValue(undefined)
})

describe('AdminLayout', () => {
  it('renders the breadcrumb and admin role badge', () => {
    mockUseAuth.mockReturnValue({
      user: { id: 1, name: 'Admin User', email: 'admin@example.com', role: 'admin' },
      logoutHttpOnly: mockLogoutHttpOnly,
    })

    renderLayout('/admin/customers')

    expect(screen.getByText('Quản trị / Khách hàng')).toBeInTheDocument()
    expect(screen.getByText('Quản trị viên')).toBeInTheDocument()
    expect(screen.getByText('Customers Content')).toBeInTheDocument()
  })

  it('renders the moderator badge label for moderator users', () => {
    mockUseAuth.mockReturnValue({
      user: { id: 2, name: 'Staff User', email: 'staff@example.com', role: 'moderator' },
      logoutHttpOnly: mockLogoutHttpOnly,
    })

    renderLayout('/admin')

    expect(screen.getByText('Nhân viên')).toBeInTheDocument()
    expect(screen.getByText('Overview Content')).toBeInTheDocument()
  })

  it('prefers the breadcrumb override from route state for room edit pages', () => {
    mockUseAuth.mockReturnValue({
      user: { id: 1, name: 'Admin User', email: 'admin@example.com', role: 'admin' },
      logoutHttpOnly: mockLogoutHttpOnly,
    })

    renderLayout({
      pathname: '/admin/rooms/42/edit',
      state: { adminBreadcrumb: 'Phòng / Sửa: Phòng Dormitory 4 giường' },
    })

    expect(screen.getByText('Quản trị / Phòng / Sửa: Phòng Dormitory 4 giường')).toBeInTheDocument()
    expect(screen.getByText('Edit Room Content')).toBeInTheDocument()
  })

  it('opens the mobile drawer from the header button', async () => {
    const user = userEvent.setup()
    mockUseAuth.mockReturnValue({
      user: { id: 1, name: 'Admin User', email: 'admin@example.com', role: 'admin' },
      logoutHttpOnly: mockLogoutHttpOnly,
    })

    renderLayout('/admin')

    const toggle = screen.getByRole('button', { name: 'Mở điều hướng quản trị' })
    expect(toggle).toHaveAttribute('aria-expanded', 'false')

    await user.click(toggle)

    expect(toggle).toHaveAttribute('aria-expanded', 'true')
    expect(screen.getByRole('dialog', { name: 'Điều hướng quản trị' })).toBeInTheDocument()
  })

  it('logs out from the sidebar footer and navigates home', async () => {
    const user = userEvent.setup()
    mockUseAuth.mockReturnValue({
      user: { id: 1, name: 'Admin User', email: 'admin@example.com', role: 'admin' },
      logoutHttpOnly: mockLogoutHttpOnly,
    })

    renderLayout('/admin')

    await user.click(screen.getByRole('button', { name: 'Đăng xuất' }))

    await waitFor(() => {
      expect(mockLogoutHttpOnly).toHaveBeenCalledTimes(1)
    })
    expect(screen.getByText('Home Page')).toBeInTheDocument()
  })
})
