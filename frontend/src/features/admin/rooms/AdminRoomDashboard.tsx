import React, { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { deleteRoom, getRoomsByLocation } from './adminRoom.api'
import type { AdminRoom } from './adminRoom.types'
import RoomStatusBoard from './RoomStatusBoard'
import RoomTable from './RoomTable'
import { useAuth } from '@/features/auth/AuthContext'
import LoadingSpinner from '@/shared/components/feedback/LoadingSpinner'
import { getLocations } from '@/shared/lib/location.api'
import { getErrorMessage, showToast } from '@/shared/utils/toast'

interface LocationOption {
  id: number
  name: string
}

interface RoomDashboardVariantProps {
  variant: 'admin' | 'moderator'
  canManage: boolean
  deletingRoomId: number | null
  isLoading: boolean
  locations: LocationOption[]
  onDelete: (room: AdminRoom) => void | Promise<void>
  onLocationChange: (value: string) => void
  rooms: AdminRoom[]
  selectedLocationId: string
}

const isAbortError = (error: unknown) => {
  if (error instanceof DOMException) {
    return error.name === 'AbortError'
  }

  return (
    typeof error === 'object' && error !== null && 'code' in error && error.code === 'ERR_CANCELED'
  )
}

const RoomDashboardVariant: React.FC<RoomDashboardVariantProps> = ({
  variant,
  canManage,
  deletingRoomId,
  isLoading,
  locations,
  onDelete,
  onLocationChange,
  rooms,
  selectedLocationId,
}) => {
  const isAdminVariant = variant === 'admin'
  const filterId = `${variant}-location-filter`

  return (
    <section
      data-testid={`room-variant-${variant}`}
      className="rounded-[28px] border border-stone-200 bg-white/95 p-5 shadow-lg shadow-stone-200/50"
    >
      <div className="space-y-5">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div className="space-y-2">
            <span
              className={`inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] ${
                isAdminVariant ? 'bg-amber-100 text-amber-900' : 'bg-sky-100 text-sky-800'
              }`}
            >
              {isAdminVariant ? 'Admin · Toàn quyền CUD' : 'Moderator · Chỉ xem'}
            </span>
            <h1 className="text-2xl font-semibold text-stone-900">Phòng</h1>
            {isAdminVariant && !canManage ? (
              <p className="text-xs text-amber-700">
                Bản xem trước admin. Tài khoản moderator vẫn không có quyền CUD.
              </p>
            ) : null}
          </div>

          {isAdminVariant ? (
            canManage ? (
              <Link
                to="/admin/rooms/new"
                className="inline-flex items-center justify-center rounded-xl bg-amber-400 px-4 py-2 text-sm font-semibold text-stone-950 transition hover:bg-amber-300"
              >
                Thêm phòng mới +
              </Link>
            ) : (
              <span
                aria-disabled="true"
                className="inline-flex items-center justify-center rounded-xl bg-amber-200 px-4 py-2 text-sm font-semibold text-stone-600"
              >
                Thêm phòng mới +
              </span>
            )
          ) : null}
        </div>

        <div className="space-y-3">
          <label htmlFor={filterId} className="sr-only">
            Lọc theo chi nhánh
          </label>
          <select
            id={filterId}
            aria-label="Lọc theo chi nhánh"
            value={selectedLocationId}
            onChange={event => onLocationChange(event.target.value)}
            className="w-full rounded-xl border border-stone-300 bg-white px-3 py-2 text-sm text-stone-700 shadow-sm focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-200 md:w-48"
          >
            <option value="all">Tất cả chi nhánh</option>
            {locations.map(location => (
              <option key={location.id} value={String(location.id)}>
                {location.name}
              </option>
            ))}
          </select>
        </div>

        {isLoading ? (
          <div className="flex justify-center py-16">
            <LoadingSpinner size="lg" message="Đang tải danh sách phòng..." />
          </div>
        ) : rooms.length === 0 ? (
          <div className="rounded-2xl border border-dashed border-stone-300 bg-stone-50/70 p-10 text-center text-sm text-stone-500">
            Chưa có phòng nào khớp với bộ lọc hiện tại.
          </div>
        ) : (
          <>
            <RoomStatusBoard rooms={rooms} />
            <RoomTable
              rooms={rooms}
              showActions={isAdminVariant}
              actionsEnabled={isAdminVariant && canManage}
              deletingRoomId={deletingRoomId}
              onDelete={onDelete}
            />
          </>
        )}
      </div>
    </section>
  )
}

const AdminRoomDashboard: React.FC = () => {
  const { user } = useAuth()
  const isAdmin = user?.role === 'admin'
  const [locations, setLocations] = useState<LocationOption[]>([])
  const [selectedLocationId, setSelectedLocationId] = useState('all')
  const [rooms, setRooms] = useState<AdminRoom[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [deletingRoomId, setDeletingRoomId] = useState<number | null>(null)

  useEffect(() => {
    const controller = new AbortController()

    const fetchLocations = async () => {
      try {
        const items = await getLocations(controller.signal)

        if (!controller.signal.aborted) {
          setLocations(items.map(location => ({ id: location.id, name: location.name })))
        }
      } catch (error) {
        if (!isAbortError(error)) {
          showToast.error('Không thể tải danh sách chi nhánh.')
        }
      }
    }

    fetchLocations()

    return () => controller.abort()
  }, [])

  useEffect(() => {
    const controller = new AbortController()

    const fetchRooms = async () => {
      setIsLoading(true)

      try {
        const data = await getRoomsByLocation(
          selectedLocationId === 'all' ? undefined : Number(selectedLocationId),
          controller.signal
        )

        if (!controller.signal.aborted) {
          setRooms(data)
        }
      } catch (error) {
        if (!isAbortError(error)) {
          showToast.error('Không thể tải danh sách phòng.')
        }
      } finally {
        if (!controller.signal.aborted) {
          setIsLoading(false)
        }
      }
    }

    fetchRooms()

    return () => controller.abort()
  }, [selectedLocationId])

  const handleDeleteRoom = async (room: AdminRoom) => {
    if (!isAdmin) {
      return
    }

    const roomLabel = room.room_number || room.display_name || room.name

    if (!window.confirm(`Bạn có chắc muốn xóa ${roomLabel}?`)) {
      return
    }

    setDeletingRoomId(room.id)

    try {
      await deleteRoom(room.id)
      setRooms(currentRooms => currentRooms.filter(currentRoom => currentRoom.id !== room.id))
      showToast.success('Đã xóa phòng.')
    } catch (error) {
      showToast.error(getErrorMessage(error))
    } finally {
      setDeletingRoomId(null)
    }
  }

  if (isLoading && rooms.length === 0 && locations.length === 0) {
    return <LoadingSpinner fullScreen />
  }

  return (
    <div className="grid gap-6 xl:grid-cols-2">
      <RoomDashboardVariant
        variant="admin"
        canManage={isAdmin}
        deletingRoomId={deletingRoomId}
        isLoading={isLoading}
        locations={locations}
        onDelete={handleDeleteRoom}
        onLocationChange={setSelectedLocationId}
        rooms={rooms}
        selectedLocationId={selectedLocationId}
      />
      <RoomDashboardVariant
        variant="moderator"
        canManage={false}
        deletingRoomId={null}
        isLoading={isLoading}
        locations={locations}
        onDelete={handleDeleteRoom}
        onLocationChange={setSelectedLocationId}
        rooms={rooms}
        selectedLocationId={selectedLocationId}
      />
    </div>
  )
}

export default AdminRoomDashboard
