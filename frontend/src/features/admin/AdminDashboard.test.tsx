import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import AdminDashboard from './AdminDashboard'

function renderAdmin() {
  return render(
    <MemoryRouter>
      <AdminDashboard />
    </MemoryRouter>
  )
}

describe('AdminDashboard', () => {
  it('renders the overview heading', () => {
    renderAdmin()
    expect(screen.getByRole('heading', { name: 'Tổng quan hôm nay' })).toBeInTheDocument()
  })

  it('renders the new booking link', () => {
    renderAdmin()
    const link = screen.getByRole('link', { name: '+ Đặt phòng mới' })
    expect(link).toHaveAttribute('href', '/admin/bookings/new')
  })

  it('renders all four stat cards', () => {
    renderAdmin()
    expect(screen.getByText('Nhận phòng hôm nay')).toBeInTheDocument()
    expect(screen.getByText('Trả phòng hôm nay')).toBeInTheDocument()
    expect(screen.getByText('Phòng đang có khách')).toBeInTheDocument()
    expect(screen.getByText('Đặt phòng mới (Tuần)')).toBeInTheDocument()
  })

  it('renders navigation links in stat cards', () => {
    renderAdmin()
    const detailLinks = screen.getAllByRole('link', { name: 'Xem chi tiết' })
    expect(detailLinks).toHaveLength(2)
    detailLinks.forEach(link => {
      expect(link).toHaveAttribute('href', '/admin/bookings/today')
    })

    expect(screen.getByRole('link', { name: 'Xem sơ đồ phòng' })).toHaveAttribute(
      'href',
      '/admin/rooms'
    )
    expect(screen.getByRole('link', { name: 'Quản lý đặt phòng' })).toHaveAttribute(
      'href',
      '/admin/bookings'
    )
  })

  it('renders the pending tasks section', () => {
    renderAdmin()
    expect(screen.getByRole('heading', { name: 'Công việc cần xử lý' })).toBeInTheDocument()
    expect(
      screen.getByText('Tính năng đang được phát triển theo Requirement V1.')
    ).toBeInTheDocument()
  })
})
