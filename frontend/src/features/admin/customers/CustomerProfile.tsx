import React, { useState, useEffect } from 'react'
import { Link, useParams, useNavigate } from 'react-router-dom'
import { getCustomerProfile, getCustomerBookings } from './customer.api'
import type { CustomerProfile as TCustomerProfile } from './customer.api'
import type { BookingApiRaw } from '@/features/booking/booking.types'
import LoadingSpinner from '@/shared/components/feedback/LoadingSpinner'
import StayJournal from './StayJournal'

const CustomerProfile: React.FC = () => {
  const { email } = useParams<{ email: string }>()
  const navigate = useNavigate()

  const [profile, setProfile] = useState<TCustomerProfile | null>(null)
  const [bookings, setBookings] = useState<BookingApiRaw[]>([])
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    if (!email) {
      navigate('/admin/customers')
      return
    }

    const loadData = async () => {
      setIsLoading(true)
      try {
        const [profData, bookingsData] = await Promise.all([
          getCustomerProfile(email),
          getCustomerBookings(email),
        ])
        setProfile(profData)
        setBookings(bookingsData)
      } catch {
        // fetch error handled silently
      } finally {
        setIsLoading(false)
      }
    }
    loadData()
  }, [email, navigate])

  if (isLoading) {
    return (
      <div className="py-12 flex justify-center">
        <LoadingSpinner size="lg" message="Đang tải hồ sơ khách hàng..." />
      </div>
    )
  }

  if (!profile) {
    return (
      <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center text-gray-500">
        <p className="mb-4">Không tìm thấy thông tin khách hàng.</p>
        <Link to="/admin/customers" className="text-blue-600 hover:underline">
          Quay lại danh sách
        </Link>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <h1 className="text-2xl font-bold text-gray-900">Hồ sơ khách hàng</h1>
        <div className="mt-4 sm:mt-0">
          <Link
            to="/admin/customers"
            className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 shadow-sm"
          >
            Trở lại danh sách
          </Link>
        </div>
      </div>

      <div className="bg-white shadow overflow-hidden sm:rounded-lg border border-gray-200">
        <div className="px-4 py-5 sm:px-6 flex items-center justify-between">
          <div className="flex items-center">
            <div className="h-16 w-16 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-2xl mr-4 shadow-sm">
              {profile.name.charAt(0).toUpperCase()}
            </div>
            <div>
              <h3 className="text-xl leading-6 font-bold text-gray-900">{profile.name}</h3>
              <p className="mt-1 max-w-2xl text-sm text-gray-500">{profile.email}</p>
            </div>
          </div>
          <div className="text-right hidden sm:block">
            <p className="text-sm text-gray-500">Thành viên từ</p>
            <p className="font-semibold text-gray-900">{profile.first_visit.split('T')[0]}</p>
          </div>
        </div>
        <div className="border-t border-gray-200 px-4 py-5 sm:p-0">
          <dl className="sm:divide-y sm:divide-gray-200">
            <div className="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
              <dt className="text-sm font-medium text-gray-500">Tổng lưu trú</dt>
              <dd className="mt-1 text-sm font-bold text-gray-900 sm:mt-0 sm:col-span-2">
                {profile.total_stays} lần ({profile.total_nights || 0} đêm)
              </dd>
            </div>
            <div className="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
              <dt className="text-sm font-medium text-gray-500">Tổng chi tiêu</dt>
              <dd className="mt-1 text-sm font-bold text-green-600 sm:mt-0 sm:col-span-2">
                {new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(
                  Number(profile.total_spent)
                )}
              </dd>
            </div>
            <div className="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
              <dt className="text-sm font-medium text-gray-500">Cơ sở yêu thích</dt>
              <dd className="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                {profile.preferred_location || '---'}
              </dd>
            </div>
            <div className="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
              <dt className="text-sm font-medium text-gray-500">Đánh giá trung bình</dt>
              <dd className="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                {profile.average_rating ? (
                  <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                    ★ {profile.average_rating}/5.0
                  </span>
                ) : (
                  <span className="text-gray-400">Chưa có đánh giá</span>
                )}
              </dd>
            </div>
          </dl>
        </div>
      </div>

      <div className="mt-8">
        <h2 className="text-xl font-bold text-gray-900 mb-4">Nhật ký lưu trú</h2>
        <StayJournal bookings={bookings} />
      </div>
    </div>
  )
}

export default CustomerProfile
