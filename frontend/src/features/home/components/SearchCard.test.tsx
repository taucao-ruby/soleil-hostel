import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import SearchCard from './SearchCard'

// ── Mocks ───────────────────────────────────────────────

const mockNavigate = vi.fn()

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')
  return { ...actual, useNavigate: () => mockNavigate }
})

vi.mock('@/features/locations/location.api', () => ({
  getLocations: vi.fn(),
}))

import { getLocations } from '@/features/locations/location.api'
import type { Location } from '@/features/locations/location.types'

const mockedGetLocations = vi.mocked(getLocations)

// ── Helpers ─────────────────────────────────────────────

const MOCK_LOCATIONS = [
  {
    id: 1,
    name: 'Soleil Phú Hội',
    slug: 'soleil-phu-hoi',
    address: {
      full: '1 Phu Hoi',
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
  {
    id: 2,
    name: 'Soleil Thành Nội',
    slug: 'soleil-thanh-noi',
    address: {
      full: '2 Thanh Noi',
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
    stats: { total_rooms: 8 },
    is_active: true,
    created_at: '',
  },
]

function renderSearchCard() {
  return render(
    <MemoryRouter>
      <SearchCard />
    </MemoryRouter>
  )
}

// ── Setup ───────────────────────────────────────────────

beforeEach(() => {
  vi.clearAllMocks()
  mockedGetLocations.mockResolvedValue(MOCK_LOCATIONS as Location[])
})

// ── Tests ───────────────────────────────────────────────

describe('SearchCard', () => {
  it('shows loading skeleton while fetching locations', () => {
    // Never resolve to keep loading state
    mockedGetLocations.mockReturnValue(new Promise(() => {}))

    renderSearchCard()
    expect(screen.getByTestId('locations-skeleton')).toBeInTheDocument()
  })

  it('populates select with fetched locations', async () => {
    renderSearchCard()

    await waitFor(() => {
      expect(screen.getByRole('option', { name: 'Soleil Phú Hội' })).toBeInTheDocument()
    })
    expect(screen.getByRole('option', { name: 'Soleil Thành Nội' })).toBeInTheDocument()
  })

  it('navigates to location detail with search params on submit', async () => {
    const user = userEvent.setup()
    renderSearchCard()

    // Wait for locations to load
    await waitFor(() => {
      expect(screen.getByRole('option', { name: 'Soleil Phú Hội' })).toBeInTheDocument()
    })

    // Submit the form (default values: first location, today/tomorrow, 1 guest)
    await user.click(screen.getByRole('button', { name: /Tìm phòng trống/i }))

    expect(mockNavigate).toHaveBeenCalledTimes(1)
    const navArg = mockNavigate.mock.calls[0][0] as string
    expect(navArg).toContain('/locations/soleil-phu-hoi')
    expect(navArg).toContain('check_in=')
    expect(navArg).toContain('check_out=')
  })

  it('navigates with selected location when changed', async () => {
    const user = userEvent.setup()
    renderSearchCard()

    await waitFor(() => {
      expect(screen.getByRole('option', { name: 'Soleil Thành Nội' })).toBeInTheDocument()
    })

    // Change to second location
    await user.selectOptions(screen.getByRole('combobox'), 'soleil-thanh-noi')
    await user.click(screen.getByRole('button', { name: /Tìm phòng trống/i }))

    const navArg = mockNavigate.mock.calls[0][0] as string
    expect(navArg).toContain('/locations/soleil-thanh-noi')
  })

  it('shows validation error when check_out <= check_in', async () => {
    const user = userEvent.setup()
    renderSearchCard()

    await waitFor(() => {
      expect(screen.getByRole('option', { name: 'Soleil Phú Hội' })).toBeInTheDocument()
    })

    // Set check_out to same day as check_in
    const checkInInput = screen.getByLabelText(/Nhận phòng/i)
    const checkOutInput = screen.getByLabelText(/Trả phòng/i)
    const today = new Date().toISOString().slice(0, 10)

    await user.clear(checkInInput)
    await user.type(checkInInput, today)
    await user.clear(checkOutInput)
    await user.type(checkOutInput, today)

    await user.click(screen.getByRole('button', { name: /Tìm phòng trống/i }))

    expect(screen.getByRole('alert')).toHaveTextContent(/Ngày trả phòng phải sau/)
    expect(mockNavigate).not.toHaveBeenCalled()
  })

  it('shows error state with retry when locations fetch fails', async () => {
    const user = userEvent.setup()
    mockedGetLocations.mockRejectedValueOnce(new Error('Network error'))

    renderSearchCard()

    await waitFor(() => {
      expect(screen.getByText(/Lỗi — Thử lại/)).toBeInTheDocument()
    })

    // Retry should re-fetch
    mockedGetLocations.mockResolvedValueOnce(MOCK_LOCATIONS as Location[])
    await user.click(screen.getByText(/Lỗi — Thử lại/))

    await waitFor(() => {
      expect(screen.getByRole('option', { name: 'Soleil Phú Hội' })).toBeInTheDocument()
    })
  })

  it('shows empty state when no locations returned', async () => {
    mockedGetLocations.mockResolvedValueOnce([])

    renderSearchCard()

    await waitFor(() => {
      expect(screen.getByText('Không có chi nhánh')).toBeInTheDocument()
    })

    // Submit button should be disabled
    expect(screen.getByRole('button', { name: /Tìm phòng trống/i })).toBeDisabled()
  })

  it('disables submit button while locations are loading', () => {
    mockedGetLocations.mockReturnValue(new Promise(() => {}))

    renderSearchCard()
    expect(screen.getByRole('button', { name: /Tìm phòng trống/i })).toBeDisabled()
  })
})
