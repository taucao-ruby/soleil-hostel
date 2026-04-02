import type {
  CreateRoomData,
  RoomReadinessStatus,
  RoomStatus,
  UpdateRoomData,
} from './adminRoom.types'

export type RoomFormValidationInput = Omit<
  Partial<CreateRoomData>,
  'location_id' | 'price' | 'max_guests'
> &
  Omit<Partial<UpdateRoomData>, 'location_id' | 'price' | 'max_guests'> & {
    slug?: string
    location_id?: number | string
    price?: number | null
    max_guests?: number | null
    room_tier?: number | null
    status?: RoomStatus
    readiness_status?: RoomReadinessStatus
  }

export const validateRoomData = (data: RoomFormValidationInput) => {
  const errors: Record<string, string> = {}

  if (!data.name || data.name.trim() === '') {
    errors.name = 'Tên phòng không được để trống'
  }

  if (!data.slug || data.slug.trim() === '') {
    errors.slug = 'Slug không được để trống'
  }

  if (!data.location_id || Number(data.location_id) <= 0) {
    errors.location_id = 'Vui lòng chọn cơ sở'
  }

  if (!data.description || data.description.trim() === '') {
    errors.description = 'Vui lòng nhập mô tả phòng'
  }

  if (typeof data.price !== 'number' || Number.isNaN(data.price) || data.price <= 0) {
    errors.price = 'Giá phòng phải lớn hơn 0'
  }

  if (
    typeof data.max_guests !== 'number' ||
    Number.isNaN(data.max_guests) ||
    data.max_guests < 1 ||
    data.max_guests > 20
  ) {
    errors.max_guests = 'Sức chứa tối đa phải trong khoảng 1 đến 20'
  }

  if (!data.status) {
    errors.status = 'Vui lòng chọn trạng thái phòng'
  }

  if (!data.readiness_status) {
    errors.readiness_status = 'Vui lòng chọn readiness'
  }

  if (typeof data.room_tier === 'number' && !Number.isNaN(data.room_tier) && data.room_tier < 1) {
    errors.room_tier = 'Hạng phòng phải lớn hơn hoặc bằng 1'
  }

  return {
    isValid: Object.keys(errors).length === 0,
    errors,
  }
}
