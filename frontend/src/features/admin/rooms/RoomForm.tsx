import React, { useEffect, useRef, useState } from 'react'
import { useLocation, useNavigate, useParams } from 'react-router-dom'
import { createRoom, getRoomById, updateRoom } from './adminRoom.api'
import { validateRoomData } from './adminRoom.validation'
import type { RoomReadinessStatus } from './adminRoom.types'
import { getLocations } from '@/shared/lib/location.api'
import LoadingSpinner from '@/shared/components/feedback/LoadingSpinner'
import { getErrorMessage, showToast } from '@/shared/utils/toast'

interface LocationOption {
  id: number
  name: string
}

interface ImagePreview {
  id: string
  file: File
  url: string
}

interface RoomFormState {
  name: string
  slug: string
  location_id: string
  description: string
  price: string
  max_guests: string
  status: 'available' | 'booked' | 'maintenance'
  readiness_status: RoomReadinessStatus
  room_type_code: string
  room_tier: string
}

const defaultFormState: RoomFormState = {
  name: '',
  slug: '',
  location_id: '',
  description: '',
  price: '',
  max_guests: '1',
  status: 'available',
  readiness_status: 'ready',
  room_type_code: '',
  room_tier: '',
}

const roomStatusOptions = [
  { value: 'available', label: 'Còn phòng' },
  { value: 'booked', label: 'Đã đặt' },
  { value: 'maintenance', label: 'Bảo trì' },
] as const

const readinessOptions: Array<{ value: RoomReadinessStatus; label: string }> = [
  { value: 'ready', label: 'Sẵn sàng' },
  { value: 'occupied', label: 'Đang có khách' },
  { value: 'dirty', label: 'Cần dọn' },
  { value: 'cleaning', label: 'Đang dọn' },
  { value: 'inspected', label: 'Đã kiểm tra' },
  { value: 'out_of_service', label: 'Ngừng sử dụng' },
]

const sectionLabelClass =
  'mb-4 text-[11px] font-semibold uppercase tracking-[0.24em] text-stone-500'
const fieldClass =
  'w-full rounded-xl border border-stone-300 bg-white px-4 py-3 text-sm text-stone-900 shadow-sm transition focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-200'

const isAbortError = (error: unknown) => {
  if (error instanceof DOMException) {
    return error.name === 'AbortError'
  }

  return (
    typeof error === 'object' && error !== null && 'code' in error && error.code === 'ERR_CANCELED'
  )
}

const slugify = (value: string) =>
  value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .replace(/-{2,}/g, '-')

const formatBreadcrumbRoomName = (name: string) => {
  const trimmed = name.trim()

  if (!trimmed) {
    return 'Phòng'
  }

  return /^phòng\b/i.test(trimmed) ? trimmed : `Phòng ${trimmed}`
}

const normalizeStatus = (status?: string): RoomFormState['status'] => {
  if (status === 'maintenance') return 'maintenance'
  if (status === 'booked' || status === 'occupied') return 'booked'
  return 'available'
}

const toErrorMap = (errors?: Record<string, string | string[]>): Record<string, string> => {
  if (!errors) {
    return {}
  }

  return Object.fromEntries(
    Object.entries(errors).map(([field, message]) => [
      field,
      Array.isArray(message) ? message[0] : message,
    ])
  )
}

const RoomForm: React.FC = () => {
  const { id } = useParams<{ id: string }>()
  const isEditing = Boolean(id)
  const navigate = useNavigate()
  const location = useLocation()
  const fileInputRef = useRef<HTMLInputElement | null>(null)
  const previewsRef = useRef<ImagePreview[]>([])

  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [isDraggingFiles, setIsDraggingFiles] = useState(false)
  const [isClassificationOpen, setIsClassificationOpen] = useState(false)
  const [isSlugManuallyEdited, setIsSlugManuallyEdited] = useState(false)
  const [locations, setLocations] = useState<LocationOption[]>([])
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [lockVersion, setLockVersion] = useState<number>(1)
  const [imagePreviews, setImagePreviews] = useState<ImagePreview[]>([])
  const [formData, setFormData] = useState<RoomFormState>(defaultFormState)

  previewsRef.current = imagePreviews

  const breadcrumbOverride =
    typeof location.state === 'object' &&
    location.state !== null &&
    'adminBreadcrumb' in location.state &&
    typeof (location.state as { adminBreadcrumb?: unknown }).adminBreadcrumb === 'string'
      ? ((location.state as { adminBreadcrumb?: string }).adminBreadcrumb ?? null)
      : null

  const desiredBreadcrumb = isEditing
    ? formData.name.trim()
      ? `Phòng / Sửa: ${formatBreadcrumbRoomName(formData.name)}`
      : 'Phòng / Sửa phòng'
    : 'Phòng / Thêm phòng mới'

  useEffect(() => {
    if (breadcrumbOverride === desiredBreadcrumb) {
      return
    }

    const nextState =
      typeof location.state === 'object' && location.state !== null
        ? { ...(location.state as Record<string, unknown>), adminBreadcrumb: desiredBreadcrumb }
        : { adminBreadcrumb: desiredBreadcrumb }

    navigate(location.pathname, { replace: true, state: nextState })
  }, [breadcrumbOverride, desiredBreadcrumb, location.pathname, location.state, navigate])

  useEffect(() => {
    return () => {
      previewsRef.current.forEach(preview => URL.revokeObjectURL(preview.url))
    }
  }, [])

  useEffect(() => {
    const controller = new AbortController()

    const loadForm = async () => {
      setIsLoading(true)

      try {
        const [locationItems, room] = await Promise.all([
          getLocations(controller.signal),
          isEditing ? getRoomById(Number(id), controller.signal) : Promise.resolve(null),
        ])

        if (controller.signal.aborted) {
          return
        }

        setLocations(locationItems.map(item => ({ id: item.id, name: item.name })))

        if (room) {
          setFormData({
            name: room.name,
            slug: slugify(room.name),
            location_id: String(room.location_id),
            description: room.description ?? '',
            price: String(Math.round(room.price)),
            max_guests: String(room.max_guests),
            status: normalizeStatus(room.status),
            readiness_status: room.readiness_status ?? 'ready',
            room_type_code: room.room_type_code ?? '',
            room_tier: room.room_tier ? String(room.room_tier) : '',
          })
          setIsClassificationOpen(Boolean(room.room_type_code || room.room_tier))
          setIsSlugManuallyEdited(false)
          setLockVersion(room.lock_version)
        } else {
          setFormData(current => ({
            ...current,
            location_id: current.location_id || String(locationItems[0]?.id ?? ''),
          }))
        }
      } catch (error) {
        if (!isAbortError(error)) {
          showToast.error(
            isEditing ? 'Không thể tải thông tin phòng.' : 'Không thể tải biểu mẫu tạo phòng.'
          )
        }
      } finally {
        if (!controller.signal.aborted) {
          setIsLoading(false)
        }
      }
    }

    loadForm()

    return () => controller.abort()
  }, [id, isEditing])

  const helperSlug = formData.slug || 'phong-dormitory-4-giuong'

  const handleNameChange = (event: React.ChangeEvent<HTMLInputElement>) => {
    const nextName = event.target.value

    setFormData(current => ({
      ...current,
      name: nextName,
      slug: isSlugManuallyEdited ? current.slug : slugify(nextName),
    }))

    setErrors(current => ({ ...current, name: '', slug: '' }))
  }

  const handleSlugChange = (event: React.ChangeEvent<HTMLInputElement>) => {
    setIsSlugManuallyEdited(true)
    setFormData(current => ({ ...current, slug: slugify(event.target.value) }))
    setErrors(current => ({ ...current, slug: '' }))
  }

  const handleFieldChange = (
    event: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>
  ) => {
    const { name, value } = event.target

    setFormData(current => ({
      ...current,
      [name]: value,
    }))

    setErrors(current => ({ ...current, [name]: '' }))
  }

  const appendImageFiles = (files: FileList | File[] | null) => {
    if (!files) {
      return
    }

    const selectedFiles = Array.from(files).filter(file => file.type.startsWith('image/'))

    if (selectedFiles.length === 0) {
      showToast.error('Chỉ có thể xem trước tệp hình ảnh.')
      return
    }

    const nextPreviews = selectedFiles.map(file => ({
      id: `${file.name}-${file.lastModified}-${Math.random().toString(36).slice(2, 8)}`,
      file,
      url: URL.createObjectURL(file),
    }))

    setImagePreviews(current => [...current, ...nextPreviews])
  }

  const removePreview = (previewId: string) => {
    setImagePreviews(current => {
      const target = current.find(preview => preview.id === previewId)

      if (target) {
        URL.revokeObjectURL(target.url)
      }

      return current.filter(preview => preview.id !== previewId)
    })
  }

  const handleDrop = (event: React.DragEvent<HTMLDivElement>) => {
    event.preventDefault()
    setIsDraggingFiles(false)
    appendImageFiles(event.dataTransfer.files)
  }

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault()

    const payload = {
      location_id: Number(formData.location_id),
      name: formData.name.trim(),
      description: formData.description.trim(),
      price: Number(formData.price),
      max_guests: Number(formData.max_guests),
      status: formData.status,
      readiness_status: formData.readiness_status,
      room_type_code: formData.room_type_code.trim() || null,
      room_tier: formData.room_tier ? Number(formData.room_tier) : null,
    }

    const validation = validateRoomData({
      ...payload,
      slug: formData.slug,
    })

    setErrors(validation.errors)

    if (!validation.isValid) {
      showToast.error('Vui lòng kiểm tra lại thông tin bắt buộc.')
      return
    }

    setIsSaving(true)

    try {
      if (isEditing) {
        await updateRoom(Number(id), {
          ...payload,
          lock_version: lockVersion,
        })
        showToast.success('Đã lưu cập nhật phòng.')
      } else {
        await createRoom(payload)
        showToast.success('Đã tạo phòng mới.')
      }

      navigate('/admin/rooms')
    } catch (error) {
      const responseErrors = toErrorMap(
        (error as { response?: { data?: { errors?: Record<string, string | string[]> } } }).response
          ?.data?.errors
      )

      if (Object.keys(responseErrors).length > 0) {
        setErrors(current => ({ ...current, ...responseErrors }))
      }

      if ((error as { response?: { status?: number } }).response?.status === 409) {
        const message = 'Phòng đã bị thay đổi bởi người khác. Vui lòng tải lại trang.'
        setErrors(current => ({ ...current, form: message }))
        showToast.error(message)
      } else {
        showToast.error(getErrorMessage(error))
      }
    } finally {
      setIsSaving(false)
    }
  }

  if (isLoading) {
    return <LoadingSpinner size="lg" message="Đang tải biểu mẫu phòng..." />
  }

  return (
    <div className="mx-auto max-w-2xl space-y-6 pb-24">
      <div className="space-y-1">
        <h1 className="text-2xl font-semibold text-stone-900">
          {isEditing ? 'Sửa phòng' : 'Tạo phòng'}
        </h1>
        <p className="text-sm text-stone-500">
          {isEditing
            ? 'Cập nhật thông tin vận hành và phân loại phòng.'
            : 'Thiết lập một phòng mới cho hệ thống quản trị.'}
        </p>
      </div>

      <div className="overflow-hidden rounded-2xl border border-stone-200 bg-white p-8 shadow-sm shadow-stone-200/50">
        {errors.form ? (
          <div
            className="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
            role="alert"
          >
            {errors.form}
          </div>
        ) : null}

        <form className="space-y-8" onSubmit={handleSubmit}>
          <section>
            <p className={sectionLabelClass}>Thông tin cơ bản</p>
            <div className="space-y-5">
              <div>
                <label htmlFor="name" className="mb-2 block text-sm font-medium text-stone-700">
                  Tên phòng
                </label>
                <input
                  id="name"
                  name="name"
                  type="text"
                  value={formData.name}
                  onChange={handleNameChange}
                  className={fieldClass}
                  placeholder="Phòng Dormitory 4 giường"
                />
                {errors.name ? <p className="mt-2 text-sm text-red-600">{errors.name}</p> : null}
              </div>

              <div>
                <label htmlFor="slug" className="mb-2 block text-sm font-medium text-stone-700">
                  Slug
                </label>
                <input
                  id="slug"
                  name="slug"
                  type="text"
                  value={formData.slug}
                  onChange={handleSlugChange}
                  className={`${fieldClass} font-mono`}
                  placeholder="phong-dormitory-4-giuong"
                />
                <p className="mt-2 text-xs text-stone-500">Dùng trong URL: /rooms/{helperSlug}</p>
                {errors.slug ? <p className="mt-2 text-sm text-red-600">{errors.slug}</p> : null}
              </div>

              <div>
                <label
                  htmlFor="location_id"
                  className="mb-2 block text-sm font-medium text-stone-700"
                >
                  Chi nhánh
                </label>
                <select
                  id="location_id"
                  name="location_id"
                  value={formData.location_id}
                  onChange={handleFieldChange}
                  className={fieldClass}
                >
                  <option value="">Chọn chi nhánh</option>
                  {locations.map(item => (
                    <option key={item.id} value={String(item.id)}>
                      {item.name}
                    </option>
                  ))}
                </select>
                {errors.location_id ? (
                  <p className="mt-2 text-sm text-red-600">{errors.location_id}</p>
                ) : null}
              </div>

              <div>
                <label
                  htmlFor="description"
                  className="mb-2 block text-sm font-medium text-stone-700"
                >
                  Mô tả
                </label>
                <textarea
                  id="description"
                  name="description"
                  rows={4}
                  value={formData.description}
                  onChange={handleFieldChange}
                  className={fieldClass}
                  placeholder="Mô tả ngắn về phòng, tiện nghi và trải nghiệm lưu trú."
                />
                {errors.description ? (
                  <p className="mt-2 text-sm text-red-600">{errors.description}</p>
                ) : null}
              </div>
            </div>
          </section>

          <section>
            <p className={sectionLabelClass}>Giá &amp; Sức chứa</p>
            <div className="grid gap-5 md:grid-cols-2">
              <div>
                <label htmlFor="price" className="mb-2 block text-sm font-medium text-stone-700">
                  Giá mỗi đêm (₫)
                </label>
                <input
                  id="price"
                  name="price"
                  type="number"
                  min="0"
                  step="1000"
                  value={formData.price}
                  onChange={handleFieldChange}
                  className={fieldClass}
                  placeholder="850000"
                />
                <p className="mt-2 text-xs text-stone-500">Chưa bao gồm thuế và phí</p>
                {errors.price ? <p className="mt-2 text-sm text-red-600">{errors.price}</p> : null}
              </div>

              <div>
                <label
                  htmlFor="max_guests"
                  className="mb-2 block text-sm font-medium text-stone-700"
                >
                  Sức chứa tối đa
                </label>
                <input
                  id="max_guests"
                  name="max_guests"
                  type="number"
                  min="1"
                  max="20"
                  value={formData.max_guests}
                  onChange={handleFieldChange}
                  className={fieldClass}
                  placeholder="4"
                />
                {errors.max_guests ? (
                  <p className="mt-2 text-sm text-red-600">{errors.max_guests}</p>
                ) : null}
              </div>
            </div>
          </section>

          <section>
            <p className={sectionLabelClass}>Trạng thái</p>
            <div className="grid gap-5 md:grid-cols-2">
              <div>
                <label htmlFor="status" className="mb-2 block text-sm font-medium text-stone-700">
                  Trạng thái phòng
                </label>
                <select
                  id="status"
                  name="status"
                  value={formData.status}
                  onChange={handleFieldChange}
                  className={fieldClass}
                >
                  {roomStatusOptions.map(option => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
                {errors.status ? (
                  <p className="mt-2 text-sm text-red-600">{errors.status}</p>
                ) : null}
              </div>

              <div>
                <label
                  htmlFor="readiness_status"
                  className="mb-2 block text-sm font-medium text-stone-700"
                >
                  Readiness
                </label>
                <select
                  id="readiness_status"
                  name="readiness_status"
                  value={formData.readiness_status}
                  onChange={handleFieldChange}
                  className={fieldClass}
                >
                  {readinessOptions.map(option => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
                {errors.readiness_status ? (
                  <p className="mt-2 text-sm text-red-600">{errors.readiness_status}</p>
                ) : null}
              </div>
            </div>
          </section>

          <section className="rounded-2xl border border-stone-200 bg-stone-50/60">
            <button
              type="button"
              onClick={() => setIsClassificationOpen(open => !open)}
              className="flex w-full items-center justify-between px-5 py-4 text-left"
            >
              <div>
                <p className={sectionLabelClass}>Phân loại (tùy chọn)</p>
                <p className="text-sm text-stone-500">
                  Dùng để tìm phòng thay thế tương đương khi cần hoán đổi
                </p>
              </div>
              <svg
                className={`h-5 w-5 text-stone-500 transition-transform ${
                  isClassificationOpen ? 'rotate-180' : ''
                }`}
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
                aria-hidden="true"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="m19.5 8.25-7.5 7.5-7.5-7.5"
                />
              </svg>
            </button>

            {isClassificationOpen ? (
              <div className="grid gap-5 border-t border-stone-200 px-5 py-5 md:grid-cols-2">
                <div>
                  <label
                    htmlFor="room_type_code"
                    className="mb-2 block text-sm font-medium text-stone-700"
                  >
                    Room Type Code
                  </label>
                  <input
                    id="room_type_code"
                    name="room_type_code"
                    type="text"
                    value={formData.room_type_code}
                    onChange={handleFieldChange}
                    className={fieldClass}
                    placeholder="DORM4"
                  />
                </div>

                <div>
                  <label
                    htmlFor="room_tier"
                    className="mb-2 block text-sm font-medium text-stone-700"
                  >
                    Room Tier
                  </label>
                  <input
                    id="room_tier"
                    name="room_tier"
                    type="number"
                    min="1"
                    value={formData.room_tier}
                    onChange={handleFieldChange}
                    className={fieldClass}
                    placeholder="1"
                  />
                  <p className="mt-2 text-xs text-stone-500">Số càng cao = hạng phòng càng tốt</p>
                  {errors.room_tier ? (
                    <p className="mt-2 text-sm text-red-600">{errors.room_tier}</p>
                  ) : null}
                </div>
              </div>
            ) : null}
          </section>

          <section>
            <div
              onDragOver={event => {
                event.preventDefault()
                setIsDraggingFiles(true)
              }}
              onDragLeave={event => {
                event.preventDefault()
                setIsDraggingFiles(false)
              }}
              onDrop={handleDrop}
              className={`rounded-xl border-2 border-dashed p-8 text-center transition ${
                isDraggingFiles ? 'border-amber-400 bg-amber-50' : 'border-gray-300 bg-stone-50/30'
              }`}
            >
              <input
                ref={fileInputRef}
                type="file"
                accept="image/*"
                multiple
                className="hidden"
                onChange={event => appendImageFiles(event.target.files)}
              />

              <svg
                className="mx-auto h-8 w-8 text-amber-500"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
                aria-hidden="true"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={1.8}
                  d="M3.75 7.5A2.25 2.25 0 0 1 6 5.25h12A2.25 2.25 0 0 1 20.25 7.5v9A2.25 2.25 0 0 1 18 18.75H6a2.25 2.25 0 0 1-2.25-2.25v-9Zm4.5 0h.008v.008H8.25V7.5Zm1.5 8.25 2.22-2.22a.75.75 0 0 1 1.06 0l1.47 1.47 2.97-2.97a.75.75 0 0 1 1.06 0l1.47 1.47"
                />
              </svg>
              <p className="mt-4 text-sm text-stone-700">Kéo thả ảnh vào đây hoặc</p>
              <button
                type="button"
                onClick={() => fileInputRef.current?.click()}
                className="mt-2 text-sm font-medium text-amber-600 underline underline-offset-4"
              >
                Chọn từ máy tính
              </button>

              {imagePreviews.length > 0 ? (
                <div className="mt-6 flex flex-wrap justify-center gap-3">
                  {imagePreviews.map(preview => (
                    <div key={preview.id} className="relative">
                      <img
                        src={preview.url}
                        alt={preview.file.name}
                        className="h-20 w-[120px] rounded-lg object-cover"
                      />
                      <button
                        type="button"
                        onClick={() => removePreview(preview.id)}
                        className="absolute -right-2 -top-2 inline-flex h-6 w-6 items-center justify-center rounded-full bg-white text-sm text-stone-700 shadow"
                        aria-label={`Xóa ${preview.file.name}`}
                      >
                        ×
                      </button>
                    </div>
                  ))}
                </div>
              ) : null}
            </div>
          </section>

          <div className="sticky bottom-0 -mx-8 flex items-center justify-between border-t border-gray-200 bg-white/95 px-8 pt-6 backdrop-blur">
            <button
              type="button"
              onClick={() => navigate('/admin/rooms')}
              className="inline-flex items-center justify-center rounded-xl border border-stone-300 px-4 py-2.5 text-sm font-medium text-stone-700 transition hover:bg-stone-50"
            >
              Hủy
            </button>

            <button
              type="submit"
              disabled={isSaving}
              className="inline-flex items-center justify-center rounded-xl bg-amber-400 px-5 py-2.5 text-sm font-semibold text-stone-950 transition hover:bg-amber-300 disabled:cursor-not-allowed disabled:opacity-60"
            >
              {isSaving ? 'Đang lưu...' : 'Lưu phòng'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}

export default RoomForm
