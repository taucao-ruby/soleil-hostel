import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import type { Location } from '@/shared/types/location.types'
import LocationList from './LocationList'

const { mockNavigate } = vi.hoisted(() => ({
  mockNavigate: vi.fn(),
}))

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')
  return {
    ...actual,
    useNavigate: () => mockNavigate,
  }
})

vi.mock('@/shared/lib/location.api', () => ({
  getLocations: vi.fn(),
}))

import { getLocations } from '@/shared/lib/location.api'

const mockedGetLocations = vi.mocked(getLocations)

const MOCK_LOCATIONS: Location[] = [
  {
    id: 1,
    name: 'Soleil Hostel',
    slug: 'soleil-hostel',
    address: {
      full: 'Tháp B, 62 Tố Hữu, Thành phố Huế',
      street: '62 Tố Hữu',
      ward: null,
      district: 'Huế',
      city: 'Thành phố Huế',
      postal_code: null,
    },
    coordinates: null,
    contact: { phone: null, email: null },
    description: 'Our flagship stay right in the city center.',
    amenities: ['wifi', 'air_conditioning', 'breakfast', 'parking', 'laundry'],
    images: [],
    stats: { total_rooms: 9, available_rooms: 9, rooms_count: 9 },
    is_active: true,
    created_at: '',
  },
  {
    id: 2,
    name: 'Soleil House',
    slug: 'soleil-house',
    address: {
      full: '33 Lý Thường Kiệt, Thành phố Huế',
      street: '33 Lý Thường Kiệt',
      ward: null,
      district: 'Phú Nhuận',
      city: 'Thành phố Huế',
      postal_code: null,
    },
    coordinates: null,
    contact: { phone: null, email: null },
    description: 'A cozy guesthouse tucked into a quiet neighborhood.',
    amenities: ['wifi', 'air_conditioning', 'breakfast', 'garden', 'parking'],
    images: [],
    stats: { total_rooms: 10, available_rooms: 10, rooms_count: 10 },
    is_active: true,
    created_at: '',
  },
  {
    id: 3,
    name: 'Soleil Riverside Villa',
    slug: 'soleil-riverside-villa',
    address: {
      full: 'Quảng Phú, Quảng Điền',
      street: 'Quảng Phú',
      ward: null,
      district: 'Quảng Điền',
      city: 'Quảng Điền',
      postal_code: null,
    },
    coordinates: null,
    contact: { phone: null, email: null },
    description: 'A peaceful riverside escape surrounded by nature.',
    amenities: ['wifi', 'air_conditioning', 'breakfast', 'fishing', 'kayaking'],
    images: [],
    stats: { total_rooms: 8, available_rooms: 8, rooms_count: 8 },
    is_active: true,
    created_at: '',
  },
]

function renderLocationList() {
  return render(
    <MemoryRouter>
      <LocationList />
    </MemoryRouter>
  )
}

beforeEach(() => {
  vi.clearAllMocks()
  mockedGetLocations.mockResolvedValue(MOCK_LOCATIONS)
})

describe('LocationList', () => {
  it('renders the updated heading and shows all branches by default', async () => {
    renderLocationList()

    await waitFor(() => {
      expect(
        screen.getByRole('heading', { name: 'Danh sách chi nhánh thực tế - Soleil Hostel' })
      ).toBeInTheDocument()
    })

    expect(screen.getByText('Soleil Hostel')).toBeInTheDocument()
    expect(screen.getByText('Soleil House')).toBeInTheDocument()
    expect(screen.getByText('Soleil Riverside Villa')).toBeInTheDocument()
    expect(screen.getByText(/Hiển thị 3 chi nhánh/i)).toBeInTheDocument()

    const hostelImage = screen.getByRole('img', { name: 'Soleil Hostel' })
    expect(hostelImage.getAttribute('src')).toContain('images.unsplash.com')
  })

  it('only applies the selected city after the search button is clicked', async () => {
    const user = userEvent.setup()
    renderLocationList()

    await waitFor(() => {
      expect(screen.getByText('Soleil Riverside Villa')).toBeInTheDocument()
    })

    const citySelect = screen.getByLabelText('Thành phố')
    await user.selectOptions(citySelect, 'Quảng Điền')

    expect(screen.getByText('Soleil Hostel')).toBeInTheDocument()
    expect(screen.getByText('Soleil House')).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Tìm kiếm' }))

    await waitFor(() => {
      expect(screen.getByText('Hiển thị 1 chi nhánh tại Quảng Điền')).toBeInTheDocument()
    })

    expect(screen.queryByText('Soleil Hostel')).not.toBeInTheDocument()
    expect(screen.queryByText('Soleil House')).not.toBeInTheDocument()
    expect(screen.getByText('Soleil Riverside Villa')).toBeInTheDocument()
  })

  it('shows the empty state when no branches are returned', async () => {
    mockedGetLocations.mockResolvedValueOnce([])

    renderLocationList()

    await waitFor(() => {
      expect(screen.getByText('Không tìm thấy chi nhánh phù hợp')).toBeInTheDocument()
    })

    expect(screen.getByRole('button', { name: 'Tìm kiếm' })).toBeDisabled()
  })
})
