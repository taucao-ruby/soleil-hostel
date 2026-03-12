import type { CreateRoomData, UpdateRoomData } from './adminRoom.types'

export const validateRoomData = (data: Partial<CreateRoomData | UpdateRoomData>) => {
  const errors: Record<string, string> = {}

  if (!data.name || data.name.trim() === '') {
    errors.name = 'Tên phòng không được để trống'
  }

  if (!data.location_id) {
    errors.location_id = 'Vui lòng chọn cơ sở'
  }

  if (typeof data.price === 'undefined' || data.price <= 0) {
    errors.price = 'Giá phòng phải lớn hơn 0'
  }

  if (typeof data.max_guests === 'undefined' || data.max_guests <= 0) {
    errors.max_guests = 'Số khách tối đa phải lớn hơn 0'
  }

  return {
    isValid: Object.keys(errors).length === 0,
    errors,
  }
}
