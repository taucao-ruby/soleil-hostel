import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import AdminSidebar from './AdminSidebar'

const baseProps = {
  isMobileOpen: false,
  onCloseMobile: vi.fn(),
  onLogout: vi.fn(),
  userInitials: 'NA',
  userName: 'Nguyen Admin',
}

function renderSidebar(overrides: Partial<typeof baseProps> = {}) {
  const props = { ...baseProps, ...overrides }

  return render(
    <MemoryRouter initialEntries={['/admin']}>
      <AdminSidebar {...props} />
    </MemoryRouter>
  )
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('AdminSidebar', () => {
  it('renders the shared admin navigation and account footer', () => {
    renderSidebar()

    expect(screen.getByText('Soleil Admin')).toBeInTheDocument()
    expect(screen.getAllByText('Tổng quan').length).toBeGreaterThanOrEqual(1)
    expect(screen.getAllByText('Đặt phòng').length).toBeGreaterThanOrEqual(1)
    expect(screen.getAllByText('Phòng').length).toBeGreaterThanOrEqual(1)
    expect(screen.getAllByText('Khách hàng').length).toBeGreaterThanOrEqual(1)
    expect(screen.getByText('Nguyen Admin')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Đăng xuất' })).toBeInTheDocument()
  })

  it('renders a mobile drawer and closes it from the backdrop button', async () => {
    const user = userEvent.setup()
    const onCloseMobile = vi.fn()

    renderSidebar({ isMobileOpen: true, onCloseMobile })

    const drawer = screen.getByRole('dialog', { name: 'Điều hướng quản trị' })
    expect(drawer).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Đóng điều hướng quản trị' }))
    expect(onCloseMobile).toHaveBeenCalledTimes(1)
  })

  it('closes the mobile drawer when a nav item is selected', async () => {
    const user = userEvent.setup()
    const onCloseMobile = vi.fn()

    renderSidebar({ isMobileOpen: true, onCloseMobile })

    const drawer = screen.getByRole('dialog', { name: 'Điều hướng quản trị' })
    await user.click(within(drawer).getByRole('link', { name: 'Khách hàng' }))

    expect(onCloseMobile).toHaveBeenCalledTimes(1)
  })

  it('calls logout from the footer action', async () => {
    const user = userEvent.setup()
    const onLogout = vi.fn()

    renderSidebar({ onLogout })

    await user.click(screen.getByRole('button', { name: 'Đăng xuất' }))
    expect(onLogout).toHaveBeenCalledTimes(1)
  })
})
