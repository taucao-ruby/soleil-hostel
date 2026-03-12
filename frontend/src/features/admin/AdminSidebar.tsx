import React from 'react'
import { NavLink } from 'react-router-dom'

const navItems = [
  { name: 'Tổng quan', path: '/admin', exact: true },
  { name: 'Quản lý phòng', path: '/admin/rooms' },
  { name: 'Đặt phòng', path: '/admin/bookings' },
  { name: 'Khách hàng', path: '/admin/customers' },
  { name: 'Đánh giá', path: '/admin/reviews' },
  { name: 'Tin nhắn', path: '/admin/messages' },
]

const AdminSidebar: React.FC = () => {
  return (
    <div className="hidden md:flex flex-col w-64 bg-gray-900 overflow-y-auto">
      <div className="flex items-center justify-center h-16 bg-gray-900 border-b border-gray-800">
        <span className="text-white font-bold text-lg uppercase tracking-wider">Soleil Hostel</span>
      </div>
      <div className="flex flex-col flex-1 overflow-y-auto w-full pt-4">
        <nav className="flex-1 px-2 py-4 space-y-1">
          {navItems.map(item => (
            <NavLink
              key={item.name}
              to={item.path}
              end={item.exact}
              className={({ isActive }) =>
                `group flex items-center px-4 py-3 text-sm font-medium rounded-md transition-colors ${
                  isActive
                    ? 'bg-gray-800 text-white'
                    : 'text-gray-300 hover:bg-gray-700 hover:text-white'
                }`
              }
            >
              <span className="truncate">{item.name}</span>
            </NavLink>
          ))}
        </nav>
      </div>
      <div className="flex-shrink-0 p-4 border-t border-gray-800">
        <NavLink
          to="/"
          className="group flex flex-col w-full px-4 py-2 text-sm font-medium text-gray-400 hover:text-white transition-colors"
        >
          &larr; Về trang chủ
        </NavLink>
      </div>
    </div>
  )
}

export default AdminSidebar
