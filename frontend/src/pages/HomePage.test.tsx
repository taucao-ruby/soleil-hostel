/**
 * HomePage regression + functional tests
 *
 * Prevents recurrence of 7 documented defects (C-01…M-03).
 * Uses MemoryRouter because components use Link/useNavigate.
 * Uses vitest + @testing-library/react, semantic queries only.
 */
import { describe, test, expect, vi, beforeEach } from 'vitest'
import { render, screen, within, fireEvent } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import HomePage from './HomePage'
import BottomNav from '@/features/home/components/BottomNav'

// BottomNav + HeaderMobile use useAuth — provide default unauthenticated state
const { mockUseAuth } = vi.hoisted(() => ({
  mockUseAuth: vi.fn(),
}))

vi.mock('@/features/auth/AuthContext', () => ({
  useAuth: mockUseAuth,
}))

// SearchCard now fetches locations on mount — mock to prevent unhandled requests
vi.mock('@/shared/lib/location.api', () => ({
  getLocations: vi.fn().mockResolvedValue([
    {
      id: 1,
      name: 'Soleil Phú Hội',
      slug: 'soleil-phu-hoi',
      address: {
        full: '',
        street: '',
        ward: null,
        district: null,
        city: 'Huế',
        postal_code: null,
      },
      coordinates: null,
      contact: { phone: null, email: null },
      description: null,
      amenities: [],
      images: [],
      stats: { total_rooms: 10 },
      is_active: true,
      created_at: '',
    },
  ]),
}))

// jsdom does not implement window.matchMedia — minimal stub
beforeEach(() => {
  // Default: unauthenticated user
  mockUseAuth.mockReturnValue({
    user: null,
    isAuthenticated: false,
    loading: false,
    logoutHttpOnly: vi.fn(),
  })

  Object.defineProperty(window, 'matchMedia', {
    writable: true,
    value: vi.fn().mockImplementation((query: string) => ({
      matches: false,
      media: query,
      onchange: null,
      addListener: vi.fn(),
      removeListener: vi.fn(),
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
      dispatchEvent: vi.fn(),
    })),
  })
})

const renderHomePage = () =>
  render(
    <MemoryRouter initialEntries={['/']}>
      <HomePage />
    </MemoryRouter>
  )

// ─── REGRESSION TESTS ──────────────────────────────────────────────────────

describe('Regression: C-01 — no watermark text in hero', () => {
  test('hero section does NOT contain "Soleil" or "Hostel" as text content', () => {
    renderHomePage()
    const heroSection = screen.getByTestId('hero-section')
    // Verify brand text does not appear as DOM text in the hero
    // (placehold.co image text was the root cause of this defect)
    expect(heroSection).not.toHaveTextContent(/Soleil/i)
    expect(heroSection).not.toHaveTextContent(/Hostel/i)
  })
})

describe('Regression: C-02 — hero has a real photo', () => {
  test('hero section contains an <img> element (not a flat colour)', () => {
    renderHomePage()
    const heroSection = screen.getByTestId('hero-section')
    const img = within(heroSection).getByRole('img', { hidden: true })
    expect(img).toBeInTheDocument()
    expect(img).toHaveAttribute('src')
    // Must be a real URL, not a placehold.co text-watermark URL
    const src = img.getAttribute('src') ?? ''
    expect(src).not.toContain('placehold.co')
  })
})

describe('Regression: C-03 — search card present in DOM', () => {
  test('search form with role="search" is in the document', () => {
    renderHomePage()
    expect(screen.getByRole('search')).toBeInTheDocument()
  })
})

describe('Regression: H-01 — no forbidden "Cuộn xuống" text in page', () => {
  test('"Cuộn xuống" is absent from the full page DOM', () => {
    renderHomePage()
    // Root cause: Hero.tsx scroll indicator text leaked into DOM and appeared
    // over BottomNav. Verify it is fully absent.
    expect(screen.queryByText('Cuộn xuống')).not.toBeInTheDocument()
    expect(screen.queryByText(/cuộn xuống/i)).not.toBeInTheDocument()
  })
})

describe('Regression: H-02 — "Tìm phòng trống" appears exactly once', () => {
  test('exactly one element matches /Tìm phòng trống/i', () => {
    renderHomePage()
    const matches = screen.getAllByText(/Tìm phòng trống/i)
    expect(matches).toHaveLength(1)
  })
})

// ─── CORE FUNCTIONALITY TESTS ──────────────────────────────────────────────

describe('Hero content', () => {
  test('renders correct H1 heading', () => {
    renderHomePage()
    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent(
      'Khám phá Huế theo cách của bạn'
    )
  })

  test('renders subtitle text', () => {
    renderHomePage()
    expect(screen.getByText('Đặt phòng nhanh — không cần thẻ tín dụng')).toBeInTheDocument()
  })
})

describe('FilterChips', () => {
  test('clicking inactive amenity chip sets it active (aria-pressed + amber active class)', () => {
    renderHomePage()

    // Second chip (Điều hòa) is initially inactive; first chip (WiFi) is active
    const acChip = screen.getByRole('button', { name: /Điều hòa/i })
    expect(acChip).toHaveAttribute('aria-pressed', 'false')

    fireEvent.click(acChip)

    // Now active — aria-pressed and bg-amber-100 class
    expect(acChip).toHaveAttribute('aria-pressed', 'true')
    expect(acChip.className).toMatch(/bg-amber-100/)

    // Previous active chip (WiFi) is deactivated
    const wifiChip = screen.getByRole('button', { name: /WiFi/i })
    expect(wifiChip.className).not.toMatch(/bg-amber-100/)
  })
})

describe('Room cards', () => {
  test('renders at least 2 room cards each with "Đặt ngay" button', () => {
    renderHomePage()
    const bookButtons = screen.getAllByRole('button', { name: /Đặt ngay/i })
    expect(bookButtons.length).toBeGreaterThanOrEqual(2)
  })

  test('renders availability badges on room cards', () => {
    renderHomePage()
    const availabilityBadges = screen.getAllByText('Còn phòng')
    expect(availabilityBadges.length).toBeGreaterThanOrEqual(2)
  })
})

describe('BottomNav — standalone (route-driven)', () => {
  const renderBottomNav = (initialEntries: string[] = ['/']) =>
    render(
      <MemoryRouter initialEntries={initialEntries}>
        <BottomNav />
      </MemoryRouter>
    )

  test('renders exactly 4 tabs with correct Vietnamese labels (H-01 regression)', () => {
    renderBottomNav()
    const nav = screen.getByRole('navigation', { name: /điều hướng/i })
    const tabs = within(nav).getAllByRole('link')
    expect(tabs).toHaveLength(4)
    expect(screen.getByRole('link', { name: /Trang chủ/i })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Phòng' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /Đặt phòng/i })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /Tài khoản/i })).toBeInTheDocument()
  })

  test('home tab is active when on "/" (aria-current="page")', () => {
    renderBottomNav(['/'])
    const homeTab = screen.getByRole('link', { name: /Trang chủ/i })
    expect(homeTab).toHaveAttribute('aria-current', 'page')
  })

  test('rooms tab is active when on "/rooms"', () => {
    renderBottomNav(['/rooms'])
    expect(screen.getByRole('link', { name: 'Phòng' })).toHaveAttribute('aria-current', 'page')
    expect(screen.getByRole('link', { name: /Trang chủ/i })).not.toHaveAttribute(
      'aria-current',
      'page'
    )
  })

  test('account tab links to /login when not authenticated', () => {
    renderBottomNav()
    expect(screen.getByRole('link', { name: /Tài khoản/i })).toHaveAttribute('href', '/login')
  })

  test('account tab links to /dashboard when authenticated', () => {
    mockUseAuth.mockReturnValue({
      user: { id: 1, name: 'Test', email: 'test@test.com', role: 'user' },
      isAuthenticated: true,
      loading: false,
      logoutHttpOnly: vi.fn(),
    })
    renderBottomNav()
    expect(screen.getByRole('link', { name: /Tài khoản/i })).toHaveAttribute('href', '/dashboard')
  })
})
