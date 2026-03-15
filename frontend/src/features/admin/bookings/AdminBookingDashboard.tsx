import React, { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { getAllBookings } from './adminBooking.api'
import type { BookingDetailRaw } from '@/features/booking/booking.types'
import type { AdminBookingFilters } from './adminBooking.api'
import AdminBookingTable from './AdminBookingTable'
import LoadingSpinner from '@/shared/components/feedback/LoadingSpinner'
import api from '@/shared/lib/api'

interface LocationOption {
  id: number
  name: string
}

const AdminBookingDashboard: React.FC = () => {
  const [locations, setLocations] = useState<LocationOption[]>([])
  const [selectedLocationId, setSelectedLocationId] = useState<number | ''>('')

  const [bookings, setBookings] = useState<BookingDetailRaw[]>([])
  const [isLoading, setIsLoading] = useState(true)

  // Filters
  const [statusFilter, setStatusFilter] = useState<string>('')
  const [search, setSearch] = useState<string>('')

  // Fetch locations
  useEffect(() => {
    api
      .get('/v1/locations')
      .then(res => setLocations(res.data.data))
      .catch(() => {
        /* ignored */
      })
  }, [])

  // Fetch bookings whenever filters change
  const fetchBookings = async () => {
    setIsLoading(true)
    try {
      const filters: AdminBookingFilters = {}
      if (selectedLocationId) filters.location_id = Number(selectedLocationId)
      if (statusFilter) filters.status = statusFilter
      if (search) filters.search = search

      const response = await getAllBookings(filters)
      // Backend returns { bookings: [...], meta: {...} } via ApiResponse wrapper
      setBookings(response.bookings)
    } catch {
      // fetch error handled silently
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    // Basic debounce for search can be added later, for now we let it fire or use form submit
    const debouncedFetch = setTimeout(fetchBookings, 500)
    return () => clearTimeout(debouncedFetch)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [selectedLocationId, statusFilter, search])

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <h1 className="text-2xl font-bold text-gray-900">Quản lý đặt phòng</h1>
        <div className="mt-4 sm:mt-0 flex space-x-3">
          <Link
            to="/admin/bookings/calendar"
            className="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
          >
            Xem lịch
          </Link>
          <Link
            to="/admin/bookings/today"
            className="inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 shadow-sm"
          >
            Lễ tân hôm nay
          </Link>
        </div>
      </div>

      {/* Filter Bar */}
      <div className="bg-white p-4 shadow-sm rounded-lg border border-gray-200 flex flex-col md:flex-row md:items-center space-y-4 md:space-y-0 md:space-x-6">
        <div className="flex-1 max-w-xs">
          <label htmlFor="search" className="sr-only">
            Tìm kiếm
          </label>
          <input
            type="text"
            id="search"
            value={search}
            onChange={e => setSearch(e.target.value)}
            className="block w-full pl-3 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
            placeholder="Mã ĐP, email hoặc tên khách..."
          />
        </div>

        <div className="flex items-center space-x-4">
          <select
            value={selectedLocationId}
            onChange={e =>
              setSelectedLocationId(e.target.value === '' ? '' : Number(e.target.value))
            }
            className="block w-full md:w-48 pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md"
          >
            <option value="">Tất cả cơ sở</option>
            {locations.map(loc => (
              <option key={loc.id} value={loc.id}>
                {loc.name}
              </option>
            ))}
          </select>

          <select
            value={statusFilter}
            onChange={e => setStatusFilter(e.target.value)}
            className="block w-full md:w-48 pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md"
          >
            <option value="">Tất cả trạng thái</option>
            <option value="pending">Chờ xác nhận</option>
            <option value="confirmed">Đã xác nhận</option>
            <option value="cancelled">Đã hủy</option>
            <option value="refund_pending">Đang hoàn tiền</option>
          </select>
        </div>
      </div>

      {/* Table Content */}
      {isLoading ? (
        <div className="py-12 flex justify-center">
          <LoadingSpinner size="lg" message="Đang tải danh sách đặt phòng..." />
        </div>
      ) : bookings.length === 0 ? (
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center text-gray-500">
          Không tìm thấy đặt phòng nào phù hợp với bộ lọc.
        </div>
      ) : (
        <AdminBookingTable bookings={bookings} onRefresh={fetchBookings} />
      )}
    </div>
  )
}

export default AdminBookingDashboard
