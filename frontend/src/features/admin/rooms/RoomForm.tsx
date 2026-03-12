import React, { useState, useEffect } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { createRoom, updateRoom } from './adminRoom.api'
import { validateRoomData } from './adminRoom.validation'
import type { CreateRoomData, UpdateRoomData } from './adminRoom.types'
import api from '@/shared/lib/api'
import LoadingSpinner from '@/shared/components/feedback/LoadingSpinner'

// We need a toast system, so we'll mock it if not available,
// according to instructions we should use assumed @/utils/toast
import * as toast from '@/utils/toast'

const RoomForm: React.FC = () => {
  const { id } = useParams<{ id: string }>()
  const isEditing = Boolean(id)
  const navigate = useNavigate()

  const [isLoading, setIsLoading] = useState(isEditing)
  const [isSaving, setIsSaving] = useState(false)

  const [locations, setLocations] = useState<{ id: number; name: string }[]>([])
  const [errors, setErrors] = useState<Record<string, string>>({})

  // Form State
  const [formData, setFormData] = useState<CreateRoomData | UpdateRoomData>({
    location_id: 0,
    name: '',
    room_number: '',
    description: '',
    price: 0,
    max_guests: 1,
    status: 'available',
  })

  // Optimistic locking
  const [lockVersion, setLockVersion] = useState<number>(0)

  useEffect(() => {
    // Fetch locations for the dropdown
    api.get('/v1/locations').then(res => {
      setLocations(res.data.data)
      if (!isEditing && res.data.data.length > 0) {
        setFormData(prev => ({ ...prev, location_id: res.data.data[0].id }))
      }
    })

    if (isEditing) {
      // Fetch room details
      api
        .get(`/v1/rooms/${id}`)
        .then(res => {
          const room = res.data.data
          setFormData({
            location_id: room.location_id,
            name: room.name,
            room_number: room.room_number || '',
            description: room.description || '',
            price: room.price,
            max_guests: room.max_guests,
            status: room.status,
            ...(isEditing ? { lock_version: room.lock_version } : {}),
          })
          setLockVersion(room.lock_version)
        })
        .catch(() => {
          toast.showToast?.error?.('Không thể tải thông tin phòng')
        })
        .finally(() => {
          setIsLoading(false)
        })
    }
  }, [id, isEditing])

  const handleChange = (
    e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>
  ) => {
    const { name, value, type } = e.target
    const finalValue = type === 'number' ? Number(value) : value
    setFormData(prev => ({ ...prev, [name]: finalValue }))
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()

    const { isValid, errors: validationErrors } = validateRoomData(formData)
    setErrors(validationErrors)

    if (!isValid) {
      toast.showToast?.error?.('Vui lòng kiểm tra lại thông tin')
      return
    }

    setIsSaving(true)
    try {
      if (isEditing) {
        // Send lock_version back
        await updateRoom(Number(id), { ...formData, lock_version: lockVersion } as UpdateRoomData)
        toast.showToast?.success?.('Cập nhật phòng thành công')
      } else {
        await createRoom(formData as CreateRoomData)
        toast.showToast?.success?.('Thêm mới phòng thành công')
      }
      navigate('/admin/rooms')
    } catch (error: unknown) {
      const err = error as {
        response?: { status?: number; data?: { errors?: Record<string, string> } }
      }
      // Handle Optimistic Locking Conflict (409)
      if (err.response?.status === 409) {
        toast.showToast?.error?.('Phòng đã bị thay đổi bởi người khác. Vui lòng tải lại trang.')
        setErrors({ ...errors, form: 'Version conflict. Please refresh.' })
      } else if (err.response?.status === 422) {
        setErrors(err.response.data?.errors || {})
        toast.showToast?.error?.('Dữ liệu không hợp lệ')
      } else {
        toast.showToast?.error?.('Có lỗi xảy ra, vui lòng thử lại')
      }
    } finally {
      setIsSaving(false)
    }
  }

  if (isLoading) {
    return <LoadingSpinner size="lg" message="Đang tải form..." />
  }

  return (
    <div className="max-w-3xl mx-auto py-6 space-y-6">
      <div className="md:flex md:items-center md:justify-between">
        <h1 className="text-2xl font-bold text-gray-900">
          {isEditing ? `Cập nhật phòng: ${formData.name}` : 'Thêm phòng mới'}
        </h1>
        <button
          onClick={() => navigate('/admin/rooms')}
          className="mt-4 md:mt-0 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
        >
          Quay lại
        </button>
      </div>

      <div className="bg-white shadow px-4 py-5 sm:rounded-lg sm:p-6">
        {errors.form && (
          <div
            className="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded relative"
            role="alert"
          >
            <span className="block sm:inline">{errors.form}</span>
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-6">
          <div className="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-2">
            {/* Location */}
            <div className="sm:col-span-2">
              <label htmlFor="location_id" className="block text-sm font-medium text-gray-700">
                Cơ sở <span className="text-red-500">*</span>
              </label>
              <div className="mt-1">
                <select
                  id="location_id"
                  name="location_id"
                  value={formData.location_id}
                  onChange={handleChange}
                  className="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md py-2"
                >
                  <option value={0} disabled>
                    -- Chọn cơ sở --
                  </option>
                  {locations.map(loc => (
                    <option key={loc.id} value={loc.id}>
                      {loc.name}
                    </option>
                  ))}
                </select>
                {errors.location_id && (
                  <p className="mt-2 text-sm text-red-600">{errors.location_id}</p>
                )}
              </div>
            </div>

            {/* Name */}
            <div>
              <label htmlFor="name" className="block text-sm font-medium text-gray-700">
                Tên phòng/Loại phòng <span className="text-red-500">*</span>
              </label>
              <div className="mt-1">
                <input
                  type="text"
                  name="name"
                  id="name"
                  value={formData.name}
                  onChange={handleChange}
                  className="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md py-2 px-3"
                  placeholder="VD: Superior Double Room"
                />
                {errors.name && <p className="mt-2 text-sm text-red-600">{errors.name}</p>}
              </div>
            </div>

            {/* Room Number */}
            <div>
              <label htmlFor="room_number" className="block text-sm font-medium text-gray-700">
                Số phòng
              </label>
              <div className="mt-1">
                <input
                  type="text"
                  name="room_number"
                  id="room_number"
                  value={formData.room_number || ''}
                  onChange={handleChange}
                  className="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md py-2 px-3"
                  placeholder="VD: 101, 202"
                />
              </div>
            </div>

            {/* Price */}
            <div>
              <label htmlFor="price" className="block text-sm font-medium text-gray-700">
                Giá phòng / đêm (VNĐ) <span className="text-red-500">*</span>
              </label>
              <div className="mt-1">
                <input
                  type="number"
                  name="price"
                  id="price"
                  value={formData.price}
                  onChange={handleChange}
                  className="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md py-2 px-3"
                  min="0"
                  step="1000"
                />
                {errors.price && <p className="mt-2 text-sm text-red-600">{errors.price}</p>}
              </div>
            </div>

            {/* Max Guests */}
            <div>
              <label htmlFor="max_guests" className="block text-sm font-medium text-gray-700">
                Sức chứa (người) <span className="text-red-500">*</span>
              </label>
              <div className="mt-1">
                <input
                  type="number"
                  name="max_guests"
                  id="max_guests"
                  value={formData.max_guests}
                  onChange={handleChange}
                  className="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md py-2 px-3"
                  min="1"
                />
                {errors.max_guests && (
                  <p className="mt-2 text-sm text-red-600">{errors.max_guests}</p>
                )}
              </div>
            </div>

            {/* Status */}
            <div className="sm:col-span-2">
              <label htmlFor="status" className="block text-sm font-medium text-gray-700">
                Trạng thái <span className="text-red-500">*</span>
              </label>
              <div className="mt-1">
                <select
                  id="status"
                  name="status"
                  value={formData.status}
                  onChange={handleChange}
                  className="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md py-2 px-3"
                >
                  <option value="available">Trống (Available)</option>
                  <option value="occupied">Có khách (Occupied)</option>
                  <option value="maintenance">Bảo trì/Dọn dẹp (Maintenance)</option>
                </select>
              </div>
            </div>

            {/* Description */}
            <div className="sm:col-span-2">
              <label htmlFor="description" className="block text-sm font-medium text-gray-700">
                Mô tả
              </label>
              <div className="mt-1">
                <textarea
                  id="description"
                  name="description"
                  rows={3}
                  value={formData.description || ''}
                  onChange={handleChange}
                  className="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border border-gray-300 rounded-md py-2 px-3"
                />
              </div>
            </div>
          </div>

          <div className="pt-5 border-t border-gray-200">
            <div className="flex justify-end space-x-3">
              <button
                type="button"
                onClick={() => navigate('/admin/rooms')}
                className="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
              >
                Hủy
              </button>
              <button
                type="submit"
                disabled={isSaving}
                className="inline-flex justify-center flex-shrink-0 bg-blue-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
              >
                {isSaving ? 'Đang lưu...' : isEditing ? 'Lưu thay đổi' : 'Tạo phòng'}
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  )
}

export default RoomForm
