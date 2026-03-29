import React, { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { fetchMyBookings } from './booking.api'
import type { BookingApiRaw } from './booking.types'
import LoadingSpinner from '@/shared/components/feedback/LoadingSpinner'
import BookingCancelDialog from './BookingCancelDialog'

const BookingList: React.FC = () => {
  const [bookings, setBookings] = useState<BookingApiRaw[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [filter, setFilter] = useState<'all' | 'upcoming' | 'past' | 'cancelled'>('all')

  // Cancel dialog state
  const [cancelModalBooking, setCancelModalBooking] = useState<BookingApiRaw | null>(null)

  useEffect(() => {
    let active = true
    const loadBookings = async () => {
      setIsLoading(true)
      try {
        const data = await fetchMyBookings()
        if (active) setBookings(data)
      } catch {
        // fetch error handled silently
      } finally {
        if (active) setIsLoading(false)
      }
    }
    loadBookings()
    return () => {
      active = false
    }
  }, [])

  const handleCancelSuccess = (updatedBooking: BookingApiRaw) => {
    setBookings(prev => prev.map(b => (b.id === updatedBooking.id ? updatedBooking : b)))
  }

  // Basic client-side filtering (ideally should be server-side if paginated)
  const today = new Date().toISOString().split('T')[0]
  const filteredBookings = bookings.filter(b => {
    if (filter === 'all') return true
    if (filter === 'cancelled')
      return (
        b.status === 'cancelled' || b.status === 'refund_failed' || b.status === 'refund_pending'
      )
    if (filter === 'upcoming') return b.check_in >= today && b.status !== 'cancelled'
    if (filter === 'past') return b.check_out < today && b.status !== 'cancelled'
    return true
  })

  const getStatusBadge = (status: string) => {
    switch (status) {
      case 'confirmed':
        return (
          <span className="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
            Đã xác nhận
          </span>
        )
      case 'pending':
        return (
          <span className="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
            Chờ duyệt
          </span>
        )
      case 'cancelled':
        return (
          <span className="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
            Đã hủy
          </span>
        )
      case 'refund_pending':
        return (
          <span className="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
            Đang hoàn tiền
          </span>
        )
      case 'refund_failed':
        return (
          <span className="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
            Hoàn tiền thất bại
          </span>
        )
      default:
        return (
          <span className="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
            {status}
          </span>
        )
    }
  }

  return (
    <div className="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
      <div className="mb-8 md:flex md:items-center md:justify-between">
        <h1 className="text-3xl font-bold text-gray-900">Đặt phòng của tôi</h1>
      </div>

      <div className="mb-6 flex space-x-2 overflow-x-auto pb-2">
        {(['all', 'upcoming', 'past', 'cancelled'] as const).map(f => (
          <button
            key={f}
            onClick={() => setFilter(f)}
            className={`px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap ${
              filter === f
                ? 'bg-blue-600 text-white'
                : 'bg-white text-gray-700 hover:bg-gray-50 border border-gray-300'
            }`}
          >
            {f === 'all' && 'Tất cả'}
            {f === 'upcoming' && 'Sắp tới'}
            {f === 'past' && 'Đã qua'}
            {f === 'cancelled' && 'Đã hủy'}
          </button>
        ))}
      </div>

      {isLoading ? (
        <LoadingSpinner size="lg" message="Đang tải danh sách đặt phòng..." />
      ) : filteredBookings.length === 0 ? (
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center text-gray-500">
          <svg
            className="mx-auto h-12 w-12 text-gray-400 mb-4"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth="1"
              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
            />
          </svg>
          <p className="text-lg font-medium">Bạn chưa có đặt phòng nào ở mục này</p>
          <div className="mt-6">
            <Link to="/rooms" className="text-blue-600 hover:text-blue-800 font-medium">
              Xem danh sách phòng và đặt ngay &rarr;
            </Link>
          </div>
        </div>
      ) : (
        <div className="bg-white shadow overflow-hidden sm:rounded-md">
          <ul className="divide-y divide-gray-200">
            {filteredBookings.map(booking => (
              <li key={booking.id}>
                <div className="px-4 py-4 sm:px-6 hover:bg-gray-50">
                  <div className="flex items-center justify-between">
                    <p className="text-sm font-medium text-blue-600 truncate">
                      Mã đặt phòng: #{booking.id}
                    </p>
                    <div className="ml-2 flex-shrink-0 flex">{getStatusBadge(booking.status)}</div>
                  </div>
                  <div className="mt-2 sm:flex sm:justify-between">
                    <div className="sm:flex">
                      <p className="flex items-center text-sm text-gray-500">
                        <svg
                          className="flex-shrink-0 mr-1.5 h-5 w-5 text-gray-400"
                          fill="none"
                          viewBox="0 0 24 24"
                          stroke="currentColor"
                        >
                          <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth="2"
                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                          />
                        </svg>
                        {booking.check_in} &rarr; {booking.check_out}
                      </p>
                    </div>
                    <div className="mt-2 flex items-center text-sm text-gray-500 sm:mt-0 space-x-4">
                      <span className="font-semibold text-gray-900">
                        {new Intl.NumberFormat('vi-VN', {
                          style: 'currency',
                          currency: 'VND',
                        }).format(
                          Number(
                            (booking as unknown as Record<string, unknown>).amount ||
                              (booking as unknown as Record<string, unknown>).total_price ||
                              0
                          )
                        )}
                      </span>
                      <Link
                        to={`/my-bookings/${booking.id}`}
                        className="text-blue-600 hover:text-blue-900"
                      >
                        Xem chi tiết
                      </Link>

                      {['pending', 'confirmed'].includes(booking.status) &&
                        booking.check_in >= today && (
                          <button
                            onClick={() => setCancelModalBooking(booking)}
                            className="text-red-600 hover:text-red-900"
                          >
                            Hủy
                          </button>
                        )}
                    </div>
                  </div>
                </div>
              </li>
            ))}
          </ul>
        </div>
      )}

      {/* Cancel Dialog */}
      <BookingCancelDialog
        booking={cancelModalBooking}
        isOpen={cancelModalBooking !== null}
        onClose={() => setCancelModalBooking(null)}
        onSuccess={handleCancelSuccess}
      />
    </div>
  )
}

export default BookingList
