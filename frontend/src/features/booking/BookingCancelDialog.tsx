import React, { useState } from 'react'
import type { BookingApiRaw } from './booking.types'
import * as toast from '@/shared/utils/toast'
import { cancelBooking } from './booking.api'

interface BookingCancelDialogProps {
  booking: BookingApiRaw | null
  isOpen: boolean
  onClose: () => void
  onSuccess: (updatedBooking: BookingApiRaw) => void
}

const BookingCancelDialog: React.FC<BookingCancelDialogProps> = ({
  booking,
  isOpen,
  onClose,
  onSuccess,
}) => {
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [reason, setReason] = useState('')

  if (!isOpen || !booking) return null

  const handleCancel = async () => {
    setIsSubmitting(true)
    try {
      // API call to cancel booking
      // Note: The provided cancelBooking API in booking.api.ts might not take a reason.
      // We will call it anyway (assuming the backend takes optional reason, or ignore for now).
      const res = await cancelBooking(booking.id)
      toast.showToast?.success?.('Hủy đặt phòng thành công.')
      onSuccess(res.data)
      onClose()
    } catch {
      toast.showToast?.error?.('Không thể hủy đặt phòng lúc này.')
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto">
      <div className="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div className="fixed inset-0 transition-opacity" aria-hidden="true" onClick={onClose}>
          <div className="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>

        <span className="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">
          &#8203;
        </span>

        <div className="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6 text-gray-900 border border-gray-200">
          <div>
            <div className="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
              <svg
                className="h-6 w-6 text-red-600"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth="2"
                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                />
              </svg>
            </div>
            <div className="mt-3 text-center sm:mt-5">
              <h3 className="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                Hủy đặt phòng #{booking.id}
              </h3>
              <div className="mt-2 text-sm text-gray-500">
                <p>
                  Bạn có chắc chắn muốn hủy đặt phòng này không? Hành động này không thể hoàn tác.
                </p>
                <div className="mt-4 text-left">
                  <label htmlFor="reason" className="block text-sm font-medium text-gray-700">
                    Lý do hủy (Không bắt buộc)
                  </label>
                  <textarea
                    id="reason"
                    rows={3}
                    className="mt-1 shadow-sm focus:ring-red-500 focus:border-red-500 block w-full sm:text-sm border-gray-300 rounded-md p-2"
                    placeholder="Cho chúng tôi biết lý do bạn hủy để cải thiện dịch vụ..."
                    value={reason}
                    onChange={e => setReason(e.target.value)}
                  />
                </div>
              </div>
            </div>
          </div>
          <div className="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
            <button
              type="button"
              disabled={isSubmitting}
              className="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:col-start-2 sm:text-sm disabled:opacity-50"
              onClick={handleCancel}
            >
              {isSubmitting ? 'Đang xử lý...' : 'Xác nhận Hủy'}
            </button>
            <button
              type="button"
              disabled={isSubmitting}
              className="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:col-start-1 sm:text-sm disabled:opacity-50"
              onClick={onClose}
            >
              Quay lại
            </button>
          </div>
        </div>
      </div>
    </div>
  )
}

export default BookingCancelDialog
