import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import AdminRoomDashboard from './AdminRoomDashboard'

const { mockUseAuth, mockGetRoomsByLocation, mockDeleteRoom, mockGetLocations } = vi.hoisted(
  () => ({
    mockUseAuth: vi.fn(),
    mockGetRoomsByLocation: vi.fn(),
    mockDeleteRoom: vi.fn(),
    mockGetLocations: vi.fn(),
  })
)

vi.mock('@/features/auth/AuthContext', () => ({
  useAuth: mockUseAuth,
}))

vi.mock('./adminRoom.api', () => ({
  getRoomsByLocation: mockGetRoomsByLocation,
  deleteRoom: mockDeleteRoom,
}))

vi.mock('@/shared/lib/location.api', () => ({
  getLocations: mockGetLocations,
}))

vi.mock('@/shared/utils/toast', () => ({
  showToast: {
    success: vi.fn(),
    error: vi.fn(),
  },
  getErrorMessage: (error: unknown) => (error instanceof Error ? error.message : 'Lỗi'),
}))

const rooms = [
  {
    id: 1,
    location_id: 1,
    location: { id: 1, name: 'Soleil Hostel', slug: 'soleil-hostel' },
    name: 'Superior Double',
    display_name: 'Superior Double',
    room_number: 'A101',
    description: null,
    price: 900000,
    max_guests: 2,
    status: 'available',
    readiness_status: 'ready',
    lock_version: 1,
    created_at: '2026-04-01T08:00:00Z',
    updated_at: '2026-04-01T08:00:00Z',
  },
  {
    id: 2,
    location_id: 2,
    location: { id: 2, name: 'Soleil House', slug: 'soleil-house' },
    name: 'Family Loft',
    display_name: 'Family Loft',
    room_number: 'B202',
    description: null,
    price: 1400000,
    max_guests: 4,
    status: 'maintenance',
    readiness_status: 'dirty',
    lock_version: 1,
    created_at: '2026-04-01T08:00:00Z',
    updated_at: '2026-04-01T08:00:00Z',
  },
]

function renderDashboard() {
  return render(
    <MemoryRouter>
      <AdminRoomDashboard />
    </MemoryRouter>
  )
}

beforeEach(() => {
  vi.clearAllMocks()

  mockGetLocations.mockResolvedValue([
    { id: 1, name: 'Soleil Hostel' },
    { id: 2, name: 'Soleil House' },
  ])
  mockGetRoomsByLocation.mockResolvedValue(rooms)
  mockDeleteRoom.mockResolvedValue(undefined)
})

describe('AdminRoomDashboard', () => {
  it('renders the admin and moderator variants side by side', async () => {
    mockUseAuth.mockReturnValue({
      user: { id: 1, name: 'Admin User', email: 'admin@example.com', role: 'admin' },
    })

    renderDashboard()

    const adminPanel = await screen.findByTestId('room-variant-admin')
    const moderatorPanel = screen.getByTestId('room-variant-moderator')

    expect(within(adminPanel).getByRole('heading', { name: 'Phòng' })).toBeInTheDocument()
    expect(within(moderatorPanel).getByRole('heading', { name: 'Phòng' })).toBeInTheDocument()
    expect(within(adminPanel).getByRole('link', { name: 'Thêm phòng mới +' })).toHaveAttribute(
      'href',
      '/admin/rooms/new'
    )
    expect(within(moderatorPanel).queryByText('Thêm phòng mới +')).not.toBeInTheDocument()
    expect(within(adminPanel).getAllByRole('link', { name: 'Sửa' })).toHaveLength(rooms.length)
    expect(within(adminPanel).getAllByRole('button', { name: 'Xóa' })).toHaveLength(rooms.length)
    expect(within(moderatorPanel).queryByRole('link', { name: 'Sửa' })).not.toBeInTheDocument()
    expect(within(moderatorPanel).getAllByText('—')).toHaveLength(rooms.length)
    expect(within(adminPanel).getByText('Tất cả chi nhánh')).toBeInTheDocument()
  })

  it('keeps the admin preview non-interactive for moderator users', async () => {
    mockUseAuth.mockReturnValue({
      user: { id: 2, name: 'Moderator', email: 'moderator@example.com', role: 'moderator' },
    })

    renderDashboard()

    const adminPanel = await screen.findByTestId('room-variant-admin')

    expect(
      within(adminPanel).getByText(
        'Bản xem trước admin. Tài khoản moderator vẫn không có quyền CUD.'
      )
    ).toBeInTheDocument()
    expect(within(adminPanel).getByText('Thêm phòng mới +')).toHaveAttribute(
      'aria-disabled',
      'true'
    )

    for (const button of within(adminPanel).getAllByRole('button', { name: 'Xóa' })) {
      expect(button).toBeDisabled()
    }
  })

  it('reloads rooms when the location filter changes', async () => {
    const user = userEvent.setup()
    mockUseAuth.mockReturnValue({
      user: { id: 1, name: 'Admin User', email: 'admin@example.com', role: 'admin' },
    })

    renderDashboard()

    const adminPanel = await screen.findByTestId('room-variant-admin')
    const locationSelect = within(adminPanel).getByLabelText('Lọc theo chi nhánh')

    await waitFor(() => {
      expect(mockGetRoomsByLocation).toHaveBeenCalledWith(undefined, expect.any(AbortSignal))
    })

    await user.selectOptions(locationSelect, '2')

    await waitFor(() => {
      expect(mockGetRoomsByLocation).toHaveBeenLastCalledWith(2, expect.any(AbortSignal))
    })
  })
})
