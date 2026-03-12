import React, { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { getRoomsByLocation } from './adminRoom.api'
import type { AdminRoom } from './adminRoom.types'
import RoomTable from './RoomTable'
import RoomStatusBoard from './RoomStatusBoard'
import api from '@/shared/lib/api'
import LoadingSpinner from '@/shared/components/feedback/LoadingSpinner'

// Minimal Location type for the selector
interface LocationOption {
  id: number
  name: string
}

const AdminRoomDashboard: React.FC = () => {
  const [locations, setLocations] = useState<LocationOption[]>([])
  const [selectedLocationId, setSelectedLocationId] = useState<number | ''>('')
  const [rooms, setRooms] = useState<AdminRoom[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [viewMode, setViewMode] = useState<'table' | 'board'>('board')

  // Fetch locations on mount
  useEffect(() => {
    const fetchLocations = async () => {
      try {
        const response = await api.get('/v1/locations')
        const items = response.data.data
        setLocations(items)
        if (items.length > 0) {
          setSelectedLocationId(items[0].id)
        }
      } catch {
        // fetch error handled silently
      } finally {
        setIsLoading(false)
      }
    }
    fetchLocations()
  }, [])

  // Fetch rooms when location changes
  useEffect(() => {
    if (selectedLocationId === '') return

    const fetchRooms = async () => {
      setIsLoading(true)
      try {
        const data = await getRoomsByLocation(Number(selectedLocationId))
        setRooms(data)
      } catch {
        // fetch error handled silently
      } finally {
        setIsLoading(false)
      }
    }
    fetchRooms()
  }, [selectedLocationId])

  // Calculate stats
  const totalRooms = rooms.length
  const availableRooms = rooms.filter(r => r.status === 'available').length
  const occupiedRooms = rooms.filter(r => r.status === 'occupied').length
  const maintenanceRooms = rooms.filter(r => r.status === 'maintenance').length

  if (isLoading && locations.length === 0) {
    return <LoadingSpinner fullScreen />
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <h1 className="text-2xl font-bold text-gray-900">Quản lý phòng</h1>
        <div className="flex mt-4 space-x-3 sm:mt-0">
          <Link
            to="/admin/rooms/new"
            className="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md shadow-sm hover:bg-blue-700"
          >
            + Thêm phòng mới
          </Link>
        </div>
      </div>

      {/* Controls Bar */}
      <div className="flex flex-col items-center justify-between p-4 space-y-4 bg-white border border-gray-200 rounded-lg shadow-sm md:flex-row md:space-y-0">
        <div className="flex items-center w-full space-x-4 md:w-auto">
          <label
            htmlFor="locationSelect"
            className="text-sm font-medium text-gray-700 whitespace-nowrap"
          >
            Cơ sở:
          </label>
          <select
            id="locationSelect"
            className="block w-full py-2 pl-3 pr-10 text-base border-gray-300 rounded-md md:w-64 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
            value={selectedLocationId}
            onChange={e => setSelectedLocationId(Number(e.target.value))}
          >
            {locations.map(loc => (
              <option key={loc.id} value={loc.id}>
                {loc.name}
              </option>
            ))}
          </select>
        </div>

        <div className="flex w-full p-1 space-x-2 overflow-hidden bg-gray-100 rounded-lg md:w-auto">
          <button
            onClick={() => setViewMode('board')}
            className={`flex-1 md:flex-none px-4 py-2 text-sm font-medium rounded-md transition-colors ${
              viewMode === 'board'
                ? 'bg-white shadow text-gray-900'
                : 'text-gray-500 hover:text-gray-700'
            }`}
          >
            Sơ đồ phòng
          </button>
          <button
            onClick={() => setViewMode('table')}
            className={`flex-1 md:flex-none px-4 py-2 text-sm font-medium rounded-md transition-colors ${
              viewMode === 'table'
                ? 'bg-white shadow text-gray-900'
                : 'text-gray-500 hover:text-gray-700'
            }`}
          >
            Danh sách
          </button>
        </div>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div className="p-4 overflow-hidden bg-white border border-gray-200 rounded-lg shadow-sm">
          <dt className="text-sm font-medium text-gray-500 truncate">Tổng số phòng</dt>
          <dd className="mt-1 text-2xl font-semibold text-gray-900">{totalRooms}</dd>
        </div>
        <div className="p-4 overflow-hidden bg-white border border-gray-200 rounded-lg shadow-sm">
          <dt className="text-sm font-medium text-green-600 truncate">Phòng trống</dt>
          <dd className="mt-1 text-2xl font-semibold text-gray-900">{availableRooms}</dd>
        </div>
        <div className="p-4 overflow-hidden bg-white border border-red-200 rounded-lg shadow-sm bg-red-50">
          <dt className="text-sm font-medium text-red-600 truncate">Đã có khách</dt>
          <dd className="mt-1 text-2xl font-semibold text-gray-900">{occupiedRooms}</dd>
        </div>
        <div className="p-4 overflow-hidden bg-white border border-yellow-200 rounded-lg shadow-sm bg-yellow-50">
          <dt className="text-sm font-medium text-yellow-600 truncate">Đang bảo trì/dọn</dt>
          <dd className="mt-1 text-2xl font-semibold text-gray-900">{maintenanceRooms}</dd>
        </div>
      </div>

      {/* Main Content Area */}
      {isLoading ? (
        <div className="flex justify-center py-12">
          <LoadingSpinner size="lg" message="Đang tải dữ liệu phòng..." />
        </div>
      ) : rooms.length === 0 ? (
        <div className="p-12 text-center text-gray-500 bg-white border border-gray-200 rounded-lg shadow-sm">
          Chưa có phòng nào được cấu hình cho cơ sở này.
        </div>
      ) : viewMode === 'table' ? (
        <RoomTable rooms={rooms} />
      ) : (
        <RoomStatusBoard rooms={rooms} />
      )}
    </div>
  )
}

export default AdminRoomDashboard
