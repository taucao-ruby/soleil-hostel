import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import RoomForm from './RoomForm'

const {
  mockCreateRoom,
  mockGetLocations,
  mockGetRoomById,
  mockUpdateRoom,
  mockToastError,
  mockToastSuccess,
} = vi.hoisted(() => ({
  mockCreateRoom: vi.fn(),
  mockGetLocations: vi.fn(),
  mockGetRoomById: vi.fn(),
  mockUpdateRoom: vi.fn(),
  mockToastError: vi.fn(),
  mockToastSuccess: vi.fn(),
}))

vi.mock('./adminRoom.api', () => ({
  createRoom: mockCreateRoom,
  getRoomById: mockGetRoomById,
  updateRoom: mockUpdateRoom,
}))

vi.mock('@/shared/lib/location.api', () => ({
  getLocations: mockGetLocations,
}))

vi.mock('@/shared/utils/toast', () => ({
  showToast: {
    error: mockToastError,
    success: mockToastSuccess,
  },
  getErrorMessage: (error: unknown) => (error instanceof Error ? error.message : 'Lỗi'),
}))

function renderRoomForm(initialEntry: string) {
  return render(
    <MemoryRouter initialEntries={[initialEntry]}>
      <Routes>
        <Route path="/admin/rooms" element={<div>Room Dashboard</div>} />
        <Route path="/admin/rooms/new" element={<RoomForm />} />
        <Route path="/admin/rooms/:id/edit" element={<RoomForm />} />
      </Routes>
    </MemoryRouter>
  )
}

beforeEach(() => {
  vi.clearAllMocks()

  mockGetLocations.mockResolvedValue([
    { id: 1, name: 'Soleil Hostel' },
    { id: 2, name: 'Soleil House' },
  ])
  mockGetRoomById.mockResolvedValue({
    id: 7,
    location_id: 2,
    name: 'Dormitory 4 giường',
    display_name: 'Dormitory 4 giường',
    room_number: 'D4-201',
    description: 'Phòng tập thể sáng, thoáng và gần khu sinh hoạt chung.',
    price: 420000,
    max_guests: 4,
    room_type_code: 'DORM4',
    room_tier: 1,
    status: 'booked',
    readiness_status: 'inspected',
    lock_version: 5,
    created_at: '2026-04-01T08:00:00Z',
    updated_at: '2026-04-01T08:00:00Z',
  })
  mockCreateRoom.mockResolvedValue({})
  mockUpdateRoom.mockResolvedValue({})
})

describe('RoomForm', () => {
  it('creates a room with slug auto-generation and inline validation', async () => {
    const user = userEvent.setup()

    renderRoomForm('/admin/rooms/new')

    expect(await screen.findByRole('heading', { name: 'Tạo phòng' })).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Lưu phòng' }))

    expect(await screen.findByText('Tên phòng không được để trống')).toBeInTheDocument()
    expect(screen.getByText('Slug không được để trống')).toBeInTheDocument()
    expect(screen.getByText('Vui lòng nhập mô tả phòng')).toBeInTheDocument()
    expect(screen.getByText('Giá phòng phải lớn hơn 0')).toBeInTheDocument()
    expect(mockCreateRoom).not.toHaveBeenCalled()

    await user.type(screen.getByLabelText('Tên phòng'), 'Phòng Dormitory 4 giường')
    expect(screen.getByLabelText('Slug')).toHaveValue('phong-dormitory-4-giuong')

    await user.type(
      screen.getByLabelText('Mô tả'),
      'Phòng tập thể phù hợp nhóm bạn, có rèm riêng và tủ khóa.'
    )
    await user.clear(screen.getByLabelText('Giá mỗi đêm (₫)'))
    await user.type(screen.getByLabelText('Giá mỗi đêm (₫)'), '450000')
    await user.clear(screen.getByLabelText('Sức chứa tối đa'))
    await user.type(screen.getByLabelText('Sức chứa tối đa'), '4')

    await user.click(screen.getByRole('button', { name: 'Lưu phòng' }))

    await waitFor(() => {
      expect(mockCreateRoom).toHaveBeenCalledWith({
        location_id: 1,
        name: 'Phòng Dormitory 4 giường',
        description: 'Phòng tập thể phù hợp nhóm bạn, có rèm riêng và tủ khóa.',
        price: 450000,
        max_guests: 4,
        status: 'available',
        readiness_status: 'ready',
        room_type_code: null,
        room_tier: null,
      })
    })

    expect(await screen.findByText('Room Dashboard')).toBeInTheDocument()
  }, 15000)

  it('loads edit mode with pre-filled room data and classification fields', async () => {
    const user = userEvent.setup()

    renderRoomForm('/admin/rooms/7/edit')

    expect(await screen.findByRole('heading', { name: 'Sửa phòng' })).toBeInTheDocument()
    expect(await screen.findByLabelText('Tên phòng')).toHaveValue('Dormitory 4 giường')
    expect(screen.getByLabelText('Slug')).toHaveValue('dormitory-4-giuong')
    expect(screen.getByLabelText('Chi nhánh')).toHaveValue('2')
    expect(screen.getByLabelText('Mô tả')).toHaveValue(
      'Phòng tập thể sáng, thoáng và gần khu sinh hoạt chung.'
    )
    expect(screen.getByLabelText('Giá mỗi đêm (₫)')).toHaveValue(420000)
    expect(screen.getByLabelText('Sức chứa tối đa')).toHaveValue(4)
    expect(screen.getByLabelText('Trạng thái phòng')).toHaveValue('booked')
    expect(screen.getByLabelText('Readiness')).toHaveValue('inspected')

    expect(await screen.findByLabelText('Room Type Code')).toHaveValue('DORM4')
    expect(screen.getByLabelText('Room Tier')).toHaveValue(1)

    await user.clear(screen.getByLabelText('Room Tier'))
    await user.type(screen.getByLabelText('Room Tier'), '2')
    await user.selectOptions(screen.getByLabelText('Readiness'), 'cleaning')
    await user.click(screen.getByRole('button', { name: 'Lưu phòng' }))

    await waitFor(() => {
      expect(mockUpdateRoom).toHaveBeenCalledWith(7, {
        location_id: 2,
        name: 'Dormitory 4 giường',
        description: 'Phòng tập thể sáng, thoáng và gần khu sinh hoạt chung.',
        price: 420000,
        max_guests: 4,
        status: 'booked',
        readiness_status: 'cleaning',
        room_type_code: 'DORM4',
        room_tier: 2,
        lock_version: 5,
      })
    })
  })
})
