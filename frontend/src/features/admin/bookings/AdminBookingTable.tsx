import React, { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import type { BookingDetailRaw } from '@/features/booking/booking.types'
import { getStatusConfig } from '@/shared/lib/booking.utils'
import { confirmBooking, adminCancelBooking } from './adminBooking.api'
import * as toast from '@/shared/utils/toast'

interface AdminBookingTableProps {
  bookings: BookingDetailRaw[]
  onRefresh: () => void
}

const AdminBookingTable: React.FC<AdminBookingTableProps> = ({ bookings, onRefresh }) => {
  const navigate = useNavigate()
  const [isProcessing, setIsProcessing] = useState<number | null>(null)

  const handleConfirm = async (id: number) => {
    setIsProcessing(id)
    try {
      await confirmBooking(id)
      toast.showToast?.success?.('Đã xác nhận đặt phòng')
      onRefresh()
    } catch {
      toast.showToast?.error?.('Lỗi: không thể xác nhận')
    } finally {
      setIsProcessing(null)
    }
  }

  const handleCancel = async (id: number) => {
    const reason = window.prompt('Nhập lý do hủy (bắt buộc):')
    if (!reason || reason.trim() === '') return

    setIsProcessing(id)
    try {
      await adminCancelBooking(id, reason)
      toast.showToast?.success?.('Đã hủy đặt phòng')
      onRefresh()
    } catch {
      toast.showToast?.error?.('Lỗi: không thể hủy')
    } finally {
      setIsProcessing(null)
    }
  }

  return (
    <div className="flex flex-col">
      <div className="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
        <div className="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
          <div className="shadow overflow-hidden border-b border-gray-200 sm:rounded-lg">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th
                    scope="col"
                    className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                  >
                    Mã ĐP
                  </th>
                  <th
                    scope="col"
                    className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                  >
                    Khách (Email)
                  </th>
                  <th
                    scope="col"
                    className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                  >
                    Phòng
                  </th>
                  <th
                    scope="col"
                    className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                  >
                    Thời gian
                  </th>
                  <th
                    scope="col"
                    className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                  >
                    Trạng thái
                  </th>
                  <th scope="col" className="relative px-6 py-3">
                    <span className="sr-only">Hành động</span>
                  </th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {bookings.map(booking => {
                  const statusConfig = getStatusConfig(booking.status)
                  return (
                    <tr key={booking.id} className="hover:bg-gray-50">
                      <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600">
                        <Link to={`/admin/bookings/${booking.id}`}>#{booking.id}</Link>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="text-sm font-medium text-gray-900">
                          {booking.guest_name}
                        </div>
                        <div className="text-sm text-gray-500">{booking.guest_email}</div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="text-sm text-gray-900">{booking.room?.name || '---'}</div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="text-sm text-gray-900">{booking.check_in}</div>
                        <div className="text-xs text-gray-500">đến {booking.check_out}</div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <span
                          className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusConfig.colorClass}`}
                        >
                          {statusConfig.label}
                        </span>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-3">
                        <button
                          onClick={() => navigate(`/admin/bookings/${booking.id}`)}
                          className="text-gray-600 hover:text-gray-900"
                        >
                          Chi tiết
                        </button>

                        {booking.status === 'pending' && (
                          <button
                            disabled={isProcessing === booking.id}
                            onClick={() => handleConfirm(booking.id)}
                            className="text-green-600 hover:text-green-900 disabled:opacity-50"
                          >
                            Duyệt
                          </button>
                        )}

                        {['pending', 'confirmed'].includes(booking.status) && (
                          <button
                            disabled={isProcessing === booking.id}
                            onClick={() => handleCancel(booking.id)}
                            className="text-red-600 hover:text-red-900 disabled:opacity-50"
                          >
                            Hủy
                          </button>
                        )}
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  )
}

export default AdminBookingTable
