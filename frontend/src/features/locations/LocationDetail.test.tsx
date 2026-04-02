import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import type { LocationWithRooms } from '@/shared/types/location.types'
import LocationDetail from './LocationDetail'

vi.mock('./location.api', () => ({
  getLocationBySlug: vi.fn(),
}))

import { getLocationBySlug } from './location.api'

const mockedGetLocationBySlug = vi.mocked(getLocationBySlug)

function createLocation(overrides: Partial<LocationWithRooms> = {}): LocationWithRooms {
  return {
    id: 1,
    name: 'Soleil Huế Trung Tâm',
    slug: 'soleil-hue-trung-tam',
    address: {
      full: '12 Nguyễn Huệ, Phường Phú Hội, Tp. Huế',
      street: '12 Nguyễn Huệ',
      ward: 'Phường Phú Hội',
      district: 'Tp. Huế',
      city: 'Huế',
      postal_code: '530000',
    },
    coordinates: null,
    contact: {
      phone: '0234 123 4567',
      email: 'info@soleil.vn',
    },
    description:
      'Một điểm dừng chân ấm áp giữa trung tâm thành phố, thuận tiện để khám phá ẩm thực và văn hóa Huế.',
    amenities: ['wifi', 'air_conditioning', 'breakfast', 'parking'],
    images: [{ url: 'https://example.com/hue.jpg', alt: 'Hue branch', order: 1 }],
    stats: {
      total_rooms: 8,
      available_rooms: 3,
      rooms_count: 8,
    },
    is_active: true,
    created_at: '2026-01-01T00:00:00Z',
    rooms: [
      {
        id: 101,
        name: 'Phòng đôi hướng phố',
        display_name: 'Phòng đôi hướng phố',
        room_number: '101',
        description: 'Không gian yên tĩnh, phù hợp cho cặp đôi hoặc chuyến công tác ngắn ngày.',
        price: 450000,
        max_guests: 2,
        status: 'available',
        location_id: 1,
        lock_version: 1,
        active_bookings_count: 0,
        created_at: '2026-01-01T00:00:00Z',
        updated_at: '2026-01-01T00:00:00Z',
      },
      {
        id: 102,
        name: 'Phòng gia đình',
        display_name: 'Phòng gia đình ban công',
        room_number: '102',
        description: 'Ban công thoáng sáng với sức chứa linh hoạt cho gia đình nhỏ.',
        price: 650000,
        max_guests: 4,
        status: 'available',
        location_id: 1,
        lock_version: 1,
        active_bookings_count: 0,
        created_at: '2026-01-01T00:00:00Z',
        updated_at: '2026-01-01T00:00:00Z',
      },
      {
        id: 103,
        name: 'Suite sân vườn',
        display_name: 'Suite sân vườn',
        room_number: '103',
        description: 'Nghỉ dưỡng riêng tư với khu ngồi thư giãn nhìn ra khoảng xanh.',
        price: 890000,
        max_guests: 3,
        status: 'available',
        location_id: 1,
        lock_version: 1,
        active_bookings_count: 0,
        created_at: '2026-01-01T00:00:00Z',
        updated_at: '2026-01-01T00:00:00Z',
      },
    ],
    ...overrides,
  }
}

function renderLocationDetail(route = '/locations/soleil-hue-trung-tam') {
  return render(
    <MemoryRouter initialEntries={[route]}>
      <Routes>
        <Route path="/locations/:slug" element={<LocationDetail />} />
      </Routes>
    </MemoryRouter>
  )
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('LocationDetail', () => {
  it('shows the default prompt before any availability search is submitted', async () => {
    mockedGetLocationBySlug.mockResolvedValue(createLocation())

    renderLocationDetail()

    await waitFor(() => {
      expect(screen.getByRole('heading', { name: 'Soleil Huế Trung Tâm' })).toBeInTheDocument()
    })

    expect(screen.getByTestId('location-search-prompt')).toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: /Phòng còn trống/i })).not.toBeInTheDocument()
  })

  it('renders searched results and preserves booking query parameters in room links', async () => {
    mockedGetLocationBySlug.mockResolvedValue(createLocation())

    renderLocationDetail(
      '/locations/soleil-hue-trung-tam?check_in=2099-06-10&check_out=2099-06-12&guests=2'
    )

    await waitFor(() => {
      expect(screen.getByRole('heading', { name: /Phòng còn trống \(3\)/i })).toBeInTheDocument()
    })

    expect(mockedGetLocationBySlug).toHaveBeenCalledWith(
      'soleil-hue-trung-tam',
      expect.objectContaining({
        check_in: '2099-06-10',
        check_out: '2099-06-12',
        guests: 2,
      }),
      expect.anything()
    )

    const firstBookingLink = screen.getAllByRole('link', { name: /Đặt ngay/i })[0]
    expect(firstBookingLink.getAttribute('href')).toContain(
      '/booking?room_id=101&check_in=2099-06-10&check_out=2099-06-12&guests=2'
    )
  })

  it('shows the empty state after a search and lets the guest reset the form', async () => {
    mockedGetLocationBySlug.mockImplementation(async (_slug, params) => {
      if (params?.check_in && params?.check_out) {
        return createLocation({
          rooms: [],
          stats: { total_rooms: 8, available_rooms: 0, rooms_count: 8 },
        })
      }

      return createLocation()
    })

    const user = userEvent.setup()
    renderLocationDetail('/locations/soleil-hue-trung-tam?check_in=2099-06-10&check_out=2099-06-12')

    await waitFor(() => {
      expect(screen.getByText('Không có phòng trống cho ngày này')).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: 'Thử chọn ngày khác' }))

    await waitFor(() => {
      expect(screen.getByTestId('location-search-prompt')).toBeInTheDocument()
    })
  })

  it('blocks invalid searches where check-out is not after check-in', async () => {
    mockedGetLocationBySlug.mockResolvedValue(createLocation())

    const user = userEvent.setup()
    renderLocationDetail()

    await waitFor(() => {
      expect(screen.getByTestId('location-search-prompt')).toBeInTheDocument()
    })

    const checkInInput = screen.getByLabelText('Ngày nhận phòng')
    const checkOutInput = screen.getByLabelText('Ngày trả phòng')

    await user.clear(checkInInput)
    await user.type(checkInInput, '2099-06-10')
    await user.clear(checkOutInput)
    await user.type(checkOutInput, '2099-06-10')
    await user.click(screen.getByRole('button', { name: 'Tìm phòng' }))

    expect(screen.getByRole('alert')).toHaveTextContent('Ngày trả phòng phải sau ngày nhận phòng.')
    expect(mockedGetLocationBySlug).toHaveBeenCalledTimes(1)
  })
})
