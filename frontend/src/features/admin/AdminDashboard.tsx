import React from 'react'
import { Link } from 'react-router-dom'

const AdminDashboard: React.FC = () => {
  return (
    <div className="space-y-6">
      <div className="flex flex-col md:flex-row md:items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900">Tổng quan hôm nay</h1>
        <div className="mt-4 md:mt-0 flex space-x-3">
          <Link
            to="/admin/bookings/new"
            className="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 transition-colors"
          >
            + Đặt phòng mới
          </Link>
        </div>
      </div>

      {/* Stats Grid */}
      <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
        {/* Arrivals */}
        <div className="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-100">
          <div className="p-5">
            <div className="flex items-center">
              <div className="w-0 flex-1">
                <dl>
                  <dt className="text-sm font-medium text-gray-500 truncate">Nhận phòng hôm nay</dt>
                  <dd>
                    <div className="text-2xl font-semibold text-gray-900">-</div>
                  </dd>
                </dl>
              </div>
            </div>
          </div>
          <div className="bg-gray-50 px-5 py-3">
            <div className="text-sm">
              <Link
                to="/admin/bookings/today"
                className="font-medium text-blue-600 hover:text-blue-900"
              >
                Xem chi tiết
              </Link>
            </div>
          </div>
        </div>

        {/* Departures */}
        <div className="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-100">
          <div className="p-5">
            <div className="flex items-center">
              <div className="w-0 flex-1">
                <dl>
                  <dt className="text-sm font-medium text-gray-500 truncate">Trả phòng hôm nay</dt>
                  <dd>
                    <div className="text-2xl font-semibold text-gray-900">-</div>
                  </dd>
                </dl>
              </div>
            </div>
          </div>
          <div className="bg-gray-50 px-5 py-3">
            <div className="text-sm">
              <Link
                to="/admin/bookings/today"
                className="font-medium text-blue-600 hover:text-blue-900"
              >
                Xem chi tiết
              </Link>
            </div>
          </div>
        </div>

        {/* Occupied */}
        <div className="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-100">
          <div className="p-5">
            <div className="flex items-center">
              <div className="w-0 flex-1">
                <dl>
                  <dt className="text-sm font-medium text-gray-500 truncate">
                    Phòng đang có khách
                  </dt>
                  <dd>
                    <div className="text-2xl font-semibold text-gray-900">- / 45</div>
                  </dd>
                </dl>
              </div>
            </div>
          </div>
          <div className="bg-gray-50 px-5 py-3">
            <div className="text-sm">
              <Link to="/admin/rooms" className="font-medium text-blue-600 hover:text-blue-900">
                Xem sơ đồ phòng
              </Link>
            </div>
          </div>
        </div>

        {/* New Bookings */}
        <div className="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-100">
          <div className="p-5">
            <div className="flex items-center">
              <div className="w-0 flex-1">
                <dl>
                  <dt className="text-sm font-medium text-gray-500 truncate">
                    Đặt phòng mới (Tuần)
                  </dt>
                  <dd>
                    <div className="text-2xl font-semibold text-gray-900">-</div>
                  </dd>
                </dl>
              </div>
            </div>
          </div>
          <div className="bg-gray-50 px-5 py-3">
            <div className="text-sm">
              <Link to="/admin/bookings" className="font-medium text-blue-600 hover:text-blue-900">
                Quản lý đặt phòng
              </Link>
            </div>
          </div>
        </div>
      </div>

      <div className="mt-8 bg-white shadow-sm overflow-hidden sm:rounded-lg border border-gray-100">
        <div className="px-4 py-5 sm:px-6 border-b border-gray-200">
          <h3 className="text-lg leading-6 font-medium text-gray-900">Công việc cần xử lý</h3>
        </div>
        <div className="p-8 text-center text-gray-500">
          Tính năng đang được phát triển theo Requirement V1.
        </div>
      </div>
    </div>
  )
}

export default AdminDashboard
