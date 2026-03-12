import React from 'react'
import { Link } from 'react-router-dom'
import type { BookingDetailRaw } from '@/features/booking/booking.types'

interface StayJournalProps {
  bookings: BookingDetailRaw[]
}

const StayJournal: React.FC<StayJournalProps> = ({ bookings }) => {
  if (bookings.length === 0) {
    return (
      <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-8 text-center text-gray-500">
        Khách hàng chưa có lịch sử lưu trú.
      </div>
    )
  }

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'confirmed':
        return 'bg-green-100 text-green-800'
      case 'pending':
        return 'bg-yellow-100 text-yellow-800'
      case 'cancelled':
      case 'refund_pending':
        return 'bg-gray-100 text-gray-800'
      default:
        return 'bg-blue-100 text-blue-800'
    }
  }

  return (
    <div className="bg-white shadow overflow-hidden sm:rounded-md border border-gray-200">
      <ul className="divide-y divide-gray-200">
        {bookings.map(booking => (
          <li key={booking.id}>
            <div className="px-4 py-4 sm:px-6 hover:bg-gray-50">
              <div className="flex items-center justify-between">
                <div className="flex items-center">
                  <p className="text-sm font-medium text-blue-600 truncate">
                    Mã ĐP:{' '}
                    <Link to={`/admin/bookings/${booking.id}`} className="hover:underline">
                      #{booking.id}
                    </Link>
                  </p>
                  <span
                    className={`ml-3 px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${getStatusColor(booking.status)}`}
                  >
                    {booking.status}
                  </span>
                </div>
                <div className="ml-2 flex-shrink-0 flex">
                  <p className="text-sm font-semibold text-gray-900">
                    {new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(
                      Number(
                        (booking as unknown as Record<string, unknown>).amount ||
                          (booking as unknown as Record<string, unknown>).total_price ||
                          0
                      )
                    )}
                  </p>
                </div>
              </div>
              <div className="mt-2 sm:flex sm:justify-between">
                <div className="sm:flex flex-col">
                  <p className="flex items-center text-sm text-gray-500">
                    <span className="font-semibold text-gray-700 w-16">Phòng:</span>
                    {booking.room?.name || '---'}
                  </p>
                  <p className="flex items-center text-sm text-gray-500 mt-1">
                    <span className="font-semibold text-gray-700 w-16">Kỳ nghỉ:</span>
                    {booking.check_in} &rarr; {booking.check_out} (
                    {Math.max(
                      1,
                      Math.ceil(
                        (new Date(booking.check_out).getTime() -
                          new Date(booking.check_in).getTime()) /
                          (1000 * 3600 * 24)
                      )
                    )}{' '}
                    đêm)
                  </p>
                </div>
                <div className="mt-2 flex items-center text-sm text-gray-500 sm:mt-0">
                  <p>Đặt ngày: {booking.created_at.split('T')[0]}</p>
                </div>
              </div>
            </div>
          </li>
        ))}
      </ul>
    </div>
  )
}

export default StayJournal
