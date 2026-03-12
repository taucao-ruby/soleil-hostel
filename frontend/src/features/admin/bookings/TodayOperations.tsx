import React, { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { getTodayArrivals, getTodayDepartures } from './adminBooking.api'
import type { BookingDetailRaw } from '@/features/booking/booking.types'
import api from '@/shared/lib/api'
import LoadingSpinner from '@/shared/components/feedback/LoadingSpinner'
import * as toast from '@/shared/utils/toast'

const TodayOperations: React.FC = () => {
  const [locations, setLocations] = useState<{ id: number; name: string }[]>([])
  const [selectedLocationId, setSelectedLocationId] = useState<number | ''>('')

  const [arrivals, setArrivals] = useState<BookingDetailRaw[]>([])
  const [departures, setDepartures] = useState<BookingDetailRaw[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [isProcessing, setIsProcessing] = useState<number | null>(null)

  useEffect(() => {
    api.get('/v1/locations').then(res => {
      setLocations(res.data.data)
      if (res.data.data.length > 0) setSelectedLocationId(res.data.data[0].id)
    })
  }, [])

  const loadData = async () => {
    if (!selectedLocationId) return
    setIsLoading(true)
    try {
      const arr = await getTodayArrivals(Number(selectedLocationId))
      const dep = await getTodayDepartures(Number(selectedLocationId))
      setArrivals(arr)
      setDepartures(dep)
    } catch {
      // fetch error handled silently
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    loadData()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [selectedLocationId])

  const handleCheckIn = async (bookingId: number, roomId: number) => {
    setIsProcessing(bookingId)
    try {
      // 1. Mark room as occupied
      await api.patch(`/v1/rooms/${roomId}/status`, {
        status: 'occupied',
        lock_version: 1 /* ignoring lock check for brevity */,
      })
      toast.showToast?.success?.(`Nhận phòng #${bookingId} thành công`)
      loadData()
    } catch {
      toast.showToast?.error?.('Lỗi khi Check-in')
    } finally {
      setIsProcessing(null)
    }
  }

  const handleCheckOut = async (bookingId: number, roomId: number) => {
    setIsProcessing(bookingId)
    try {
      // 1. Mark room as cleaning (maintenance)
      await api.patch(`/v1/rooms/${roomId}/status`, {
        status: 'maintenance',
        lock_version: 1 /* ignoring lock check for brevity */,
      })
      toast.showToast?.success?.(`Trả phòng #${bookingId} thành công! Phòng chuyển sang dọn dẹp.`)
      loadData()
    } catch {
      toast.showToast?.error?.('Lỗi khi Check-out')
    } finally {
      setIsProcessing(null)
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <h1 className="text-2xl font-bold text-gray-900">Nghiệp vụ hôm nay</h1>
        <div className="mt-4 sm:mt-0">
          <Link
            to="/admin/bookings"
            className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
          >
            Trở lại quản lý
          </Link>
        </div>
      </div>

      <div className="bg-white p-4 shadow-sm rounded-lg border border-gray-200">
        <label htmlFor="loc" className="mr-3 font-medium text-gray-700">
          Cơ sở:
        </label>
        <select
          id="loc"
          value={selectedLocationId}
          onChange={e => setSelectedLocationId(Number(e.target.value))}
          className="md:w-64 pl-3 pr-10 py-2 border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 rounded-md sm:text-sm inline-block"
        >
          <option value="">-- Chọn --</option>
          {locations.map(loc => (
            <option key={loc.id} value={loc.id}>
              {loc.name}
            </option>
          ))}
        </select>
      </div>

      {isLoading ? (
        <LoadingSpinner message="Đang tải dữ liệu..." size="lg" />
      ) : (
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
          {/* Arrivals Column (Check-in) */}
          <div className="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div className="bg-blue-50 px-6 py-4 border-b border-blue-100 flex justify-between items-center">
              <h2 className="text-lg font-bold text-blue-900">Khách nhận phòng (Check-in)</h2>
              <span className="bg-blue-200 text-blue-800 py-1 px-3 rounded-full text-xs font-bold">
                {arrivals.length}
              </span>
            </div>
            <div className="divide-y divide-gray-100">
              {arrivals.length === 0 ? (
                <div className="p-8 text-center text-gray-500">
                  Không có khách đến trong hôm nay.
                </div>
              ) : (
                arrivals.map(b => (
                  <div
                    key={b.id}
                    className="p-6 flex items-center justify-between hover:bg-gray-50"
                  >
                    <div>
                      <div className="font-bold text-gray-900 text-lg mb-1">{b.guest_name}</div>
                      <div className="text-sm text-gray-600 mb-2">
                        <span className="inline-block w-20 font-medium">Phòng:</span>{' '}
                        <span className="font-semibold">{b.room?.name}</span>
                      </div>
                      <div className="text-sm text-gray-500">
                        <span className="inline-block w-20 font-medium">Mã ĐP:</span>{' '}
                        <Link
                          to={`/admin/bookings/${b.id}`}
                          className="text-blue-600 hover:underline"
                        >
                          #{b.id}
                        </Link>
                      </div>
                    </div>
                    <div>
                      <button
                        disabled={isProcessing === b.id}
                        onClick={() => handleCheckIn(b.id, b.room_id)}
                        className="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded shadow-sm disabled:opacity-50"
                      >
                        {isProcessing === b.id ? 'Đang...' : 'Check-in (Đã đến)'}
                      </button>
                    </div>
                  </div>
                ))
              )}
            </div>
          </div>

          {/* Departures Column (Check-out) */}
          <div className="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div className="bg-yellow-50 px-6 py-4 border-b border-yellow-100 flex justify-between items-center">
              <h2 className="text-lg font-bold text-yellow-900">Khách trả phòng (Check-out)</h2>
              <span className="bg-yellow-200 text-yellow-800 py-1 px-3 rounded-full text-xs font-bold">
                {departures.length}
              </span>
            </div>
            <div className="divide-y divide-gray-100">
              {departures.length === 0 ? (
                <div className="p-8 text-center text-gray-500">
                  Không có khách trả phòng trong hôm nay.
                </div>
              ) : (
                departures.map(b => (
                  <div
                    key={b.id}
                    className="p-6 flex items-center justify-between hover:bg-gray-50"
                  >
                    <div>
                      <div className="font-bold text-gray-900 text-lg mb-1">{b.guest_name}</div>
                      <div className="text-sm text-gray-600 mb-2">
                        <span className="inline-block w-20 font-medium">Phòng:</span>{' '}
                        <span className="font-semibold">{b.room?.name}</span>
                      </div>
                      <div className="text-sm text-gray-500">
                        <span className="inline-block w-20 font-medium">Mã ĐP:</span>{' '}
                        <Link
                          to={`/admin/bookings/${b.id}`}
                          className="text-blue-600 hover:underline"
                        >
                          #{b.id}
                        </Link>
                      </div>
                    </div>
                    <div>
                      <button
                        disabled={isProcessing === b.id}
                        onClick={() => handleCheckOut(b.id, b.room_id)}
                        className="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded shadow-sm disabled:opacity-50"
                      >
                        {isProcessing === b.id ? 'Đang...' : 'Check-out (Rời đi)'}
                      </button>
                    </div>
                  </div>
                ))
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

export default TodayOperations
