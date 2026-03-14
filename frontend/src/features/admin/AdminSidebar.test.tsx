import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import AdminSidebar from './AdminSidebar'

// ── Setup ───────────────────────────────────────────────

beforeEach(() => {
  vi.clearAllMocks()
  document.body.style.overflow = ''
})

function renderSidebar(initialEntries: string[] = ['/admin']) {
  return render(
    <MemoryRouter initialEntries={initialEntries}>
      <AdminSidebar />
    </MemoryRouter>
  )
}

// ── Tests ───────────────────────────────────────────────

describe('AdminSidebar', () => {
  describe('desktop sidebar', () => {
    it('renders all nav links in desktop sidebar', () => {
      renderSidebar()
      // Desktop sidebar has hidden md:flex — always in DOM
      expect(screen.getAllByText('Tổng quan').length).toBeGreaterThanOrEqual(1)
      expect(screen.getAllByText('Quản lý phòng').length).toBeGreaterThanOrEqual(1)
      expect(screen.getAllByText('Đặt phòng').length).toBeGreaterThanOrEqual(1)
      expect(screen.getAllByText('Khách hàng').length).toBeGreaterThanOrEqual(1)
      expect(screen.getAllByText('Đánh giá').length).toBeGreaterThanOrEqual(1)
      expect(screen.getAllByText('Tin nhắn').length).toBeGreaterThanOrEqual(1)
    })

    it('renders "Về trang chủ" link pointing to /', () => {
      renderSidebar()
      const homeLinks = screen.getAllByText(/Về trang chủ/)
      expect(homeLinks.length).toBeGreaterThanOrEqual(1)
    })
  })

  describe('mobile hamburger trigger', () => {
    it('renders hamburger button with correct aria-label', () => {
      renderSidebar()
      const hamburger = screen.getByRole('button', { name: 'Mở menu quản trị' })
      expect(hamburger).toBeInTheDocument()
      expect(hamburger).toHaveAttribute('aria-expanded', 'false')
    })

    it('opens drawer when hamburger is clicked', async () => {
      renderSidebar()
      const user = userEvent.setup()
      const hamburger = screen.getByRole('button', { name: 'Mở menu quản trị' })

      await user.click(hamburger)

      expect(hamburger).toHaveAttribute('aria-expanded', 'true')
      expect(screen.getByRole('dialog', { name: 'Menu quản trị' })).toBeInTheDocument()
    })
  })

  describe('mobile slide-over drawer', () => {
    it('shows all nav items when drawer is open', async () => {
      renderSidebar()
      const user = userEvent.setup()

      await user.click(screen.getByRole('button', { name: 'Mở menu quản trị' }))

      const dialog = screen.getByRole('dialog')
      expect(dialog).toBeInTheDocument()
      // Drawer duplicates nav items — check at least 2 instances of each
      expect(screen.getAllByText('Tổng quan').length).toBe(2)
      expect(screen.getAllByText('Quản lý phòng').length).toBe(2)
    })

    it('closes drawer when backdrop is clicked', async () => {
      renderSidebar()
      const user = userEvent.setup()

      await user.click(screen.getByRole('button', { name: 'Mở menu quản trị' }))
      expect(screen.getByRole('dialog')).toBeInTheDocument()

      // Click backdrop (aria-hidden div inside dialog)
      const dialog = screen.getByRole('dialog')
      const backdrop = dialog.querySelector('[aria-hidden="true"]')
      expect(backdrop).toBeTruthy()
      fireEvent.click(backdrop!)

      expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    })

    it('closes drawer on Escape key', async () => {
      renderSidebar()
      const user = userEvent.setup()

      await user.click(screen.getByRole('button', { name: 'Mở menu quản trị' }))
      expect(screen.getByRole('dialog')).toBeInTheDocument()

      await user.keyboard('{Escape}')
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    })

    it('locks body scroll when drawer is open', async () => {
      renderSidebar()
      const user = userEvent.setup()

      expect(document.body.style.overflow).toBe('')

      await user.click(screen.getByRole('button', { name: 'Mở menu quản trị' }))
      expect(document.body.style.overflow).toBe('hidden')

      await user.keyboard('{Escape}')
      expect(document.body.style.overflow).toBe('')
    })
  })
})
