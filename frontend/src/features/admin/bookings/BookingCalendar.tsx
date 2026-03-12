import React, { useState, useEffect, useMemo } from 'react'
import { Link } from 'react-router-dom'
import api from '@/shared/lib/api'
import type { BookingDetailRaw } from '@/features/booking/booking.types'

// Note: In a real implementation we would fetch rooms and bookings, then map them together.
const BookingCalendar: React.FC = () => {
  const [currentDate, setCurrentDate] = useState(new Date())
  const [locations, setLocations] = useState<{ id: number; name: string }[]>([])
  const [selectedLocationId, setSelectedLocationId] = useState<number | ''>('')

  // Dummy data arrays for rooms and bookings to demonstrate UI structure
  const [rooms, setRooms] = useState<{ id: number; name: string }[]>([])
  const [bookings, setBookings] = useState<BookingDetailRaw[]>([])

  const year = currentDate.getFullYear()
  const month = currentDate.getMonth()

  // Generate days in month
  const daysInMonth = useMemo(() => {
    const end = new Date(year, month + 1, 0).getDate()
    return Array.from({ length: end }, (_, i) => i + 1)
  }, [year, month])

  // Month navigation
  const prevMonth = () => setCurrentDate(new Date(year, month - 1, 1))
  const nextMonth = () => setCurrentDate(new Date(year, month + 1, 1))
  const today = () => setCurrentDate(new Date())

  // Fake fetch logic
  useEffect(() => {
    api.get('/v1/locations').then(res => {
      setLocations(res.data.data)
      if (res.data.data.length > 0) setSelectedLocationId(res.data.data[0].id)
    })
  }, [])

  useEffect(() => {
    if (!selectedLocationId) return

    // In a real app we would:
    // 1. Fetch rooms for this location
    // 2. Fetch bookings for this location overlapping with current month
    // Here we'll just mock rooms up
    api
      .get('/v1/rooms', { params: { location_id: selectedLocationId } })
      .then(res => setRooms(res.data.data || []))

    api
      .get('/v1/admin/bookings', { params: { location_id: selectedLocationId } })
      .then(res => setBookings(res.data.data || []))
  }, [selectedLocationId, year, month])

  // Helper to check if a booking overlaps a specific day
  const getBookingForDay = (roomId: number, day: number) => {
    const targetDate = new Date(Date.UTC(year, month, day)).toISOString().split('T')[0]

    return bookings.find(b => {
      // Half-open interval [check_in, check_out)
      // If check-out is exactly today, it doesn't count as occupying the night.
      return b.room_id === roomId && b.check_in <= targetDate && b.check_out > targetDate
    })
  }

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'confirmed':
        return 'bg-green-400 border-green-500 text-white'
      case 'pending':
        return 'bg-yellow-400 border-yellow-500 text-yellow-900'
      case 'cancelled':
      case 'refund_pending':
        return 'bg-gray-400 border-gray-500 text-white opacity-50'
      default:
        return 'bg-blue-400 border-blue-500 text-white'
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <h1 className="text-2xl font-bold text-gray-900">Lịch phòng</h1>
        <div className="mt-4 sm:mt-0">
          <Link
            to="/admin/bookings"
            className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
          >
            Trở lại danh sách
          </Link>
        </div>
      </div>

      <div className="bg-white p-4 shadow-sm rounded-lg border border-gray-200 flex flex-col md:flex-row justify-between items-center">
        <div className="flex items-center space-x-4 mb-4 md:mb-0">
          <select
            value={selectedLocationId}
            onChange={e => setSelectedLocationId(Number(e.target.value))}
            className="block w-full md:w-64 pl-3 pr-10 py-2 border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 rounded-md sm:text-sm"
          >
            <option value="">-- Chọn cơ sở --</option>
            {locations.map(loc => (
              <option key={loc.id} value={loc.id}>
                {loc.name}
              </option>
            ))}
          </select>
        </div>

        <div className="flex items-center space-x-3">
          <button onClick={prevMonth} className="p-2 border rounded-md hover:bg-gray-50">
            &larr;
          </button>
          <button
            onClick={today}
            className="px-3 py-2 border rounded-md hover:bg-gray-50 text-sm font-medium"
          >
            Hôm nay
          </button>
          <span className="px-4 py-2 font-bold text-gray-900 border rounded-md bg-gray-50">
            Tháng {month + 1}, {year}
          </span>
          <button onClick={nextMonth} className="p-2 border rounded-md hover:bg-gray-50">
            &rarr;
          </button>
        </div>
      </div>

      <div className="bg-white border text-sm rounded shadow-sm overflow-x-auto">
        <div className="inline-block min-w-full align-middle">
          {/* Calendar Grid container using CSS Grid */}
          <div
            className="grid border-b border-gray-200"
            style={{
              gridTemplateColumns: `150px repeat(${daysInMonth.length}, minmax(40px, 1fr))`,
            }}
          >
            {/* Header Row */}
            <div className="bg-gray-100 p-2 font-semibold text-gray-700 sticky left-0 z-10 border-r border-gray-300">
              Phòng
            </div>
            {daysInMonth.map(day => (
              <div
                key={day}
                className="bg-gray-100 p-2 text-center text-xs font-semibold text-gray-500 border-r border-gray-200"
              >
                {day}
              </div>
            ))}

            {/* Room Rows */}
            {rooms.map(room => (
              <React.Fragment key={room.id}>
                {/* Room Label */}
                <div
                  className="bg-white p-2 font-medium text-gray-900 border-t border-r border-gray-200 sticky left-0 z-10 truncate"
                  title={room.name}
                >
                  {room.name}
                </div>

                {/* Days Cells */}
                {daysInMonth.map(day => {
                  const booking = getBookingForDay(room.id, day)

                  return (
                    <div
                      key={day}
                      className={`border-t border-r border-gray-100 p-1 relative h-12 flex flex-col justify-center`}
                    >
                      {booking ? (
                        <div
                          className={`absolute inset-x-0 ${getStatusColor(booking.status)} mx-0 flex items-center px-1 text-[10px] leading-tight overflow-hidden rounded-sm cursor-pointer z-0 whitespace-nowrap z-0 shadow-sm transition-all hover:scale-105`}
                          style={{ height: 'calc(100% - 4px)' }}
                          title={`${booking.guest_name} (${booking.check_in} - ${booking.check_out})`}
                          onClick={() => (window.location.href = `/admin/bookings/${booking.id}`)}
                        >
                          <span className="truncate">{booking.guest_name}</span>
                        </div>
                      ) : null}
                    </div>
                  )
                })}
              </React.Fragment>
            ))}
          </div>

          {rooms.length === 0 && (
            <div className="p-8 text-center text-gray-500">
              Vui lòng chọn cơ sở có phòng để xem lịch.
            </div>
          )}
        </div>
      </div>

      {/* Legend */}
      <div className="flex flex-wrap gap-4 text-sm mt-4">
        <div className="flex items-center">
          <span className="w-3 h-3 bg-yellow-400 mr-2 rounded-sm border border-yellow-500"></span>{' '}
          Chờ duyệt
        </div>
        <div className="flex items-center">
          <span className="w-3 h-3 bg-green-400 mr-2 rounded-sm border border-green-500"></span> Xác
          nhận
        </div>
        <div className="flex items-center">
          <span className="w-3 h-3 bg-gray-400 opacity-50 mr-2 rounded-sm border border-gray-500"></span>{' '}
          Đã hủy
        </div>
      </div>
    </div>
  )
}

export default BookingCalendar
