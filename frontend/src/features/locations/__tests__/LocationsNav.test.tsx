import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import Header from '@/shared/components/layout/Header'

// Mock AuthContext
vi.mock('@/features/auth/AuthContext', () => ({
  useAuth: () => ({
    isAuthenticated: false,
    user: null,
    logoutHttpOnly: vi.fn(),
  }),
}))

function renderHeader(initialRoute: string) {
  return render(
    <MemoryRouter initialEntries={[initialRoute]}>
      <Header />
    </MemoryRouter>
  )
}

describe('Locations nav link', () => {
  it('renders "Locations" link in the nav', () => {
    renderHeader('/')
    const links = screen.getAllByRole('link', { name: 'Locations' })
    expect(links.length).toBeGreaterThanOrEqual(1)
    expect(links[0]).toHaveAttribute('href', '/locations')
  })

  it('has active style on /locations', () => {
    renderHeader('/locations')
    const link = screen.getAllByRole('link', { name: 'Locations' })[0]
    expect(link.className).toContain('text-blue-600')
  })

  it('has active style on /locations/:slug', () => {
    renderHeader('/locations/some-slug')
    const link = screen.getAllByRole('link', { name: 'Locations' })[0]
    expect(link.className).toContain('text-blue-600')
  })
})
