import React, { useState, useEffect, useCallback } from 'react'
import { NavLink, useLocation } from 'react-router-dom'

const navItems = [
  { name: 'Tổng quan', path: '/admin', exact: true },
  { name: 'Quản lý phòng', path: '/admin/rooms' },
  { name: 'Đặt phòng', path: '/admin/bookings' },
  { name: 'Khách hàng', path: '/admin/customers' },
  { name: 'Đánh giá', path: '/admin/reviews' },
  { name: 'Tin nhắn', path: '/admin/messages' },
]

const navLinkClass = ({ isActive }: { isActive: boolean }) =>
  `group flex items-center px-4 py-3 text-sm font-medium rounded-md transition-colors ${
    isActive ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'
  }`

/**
 * Shared nav content rendered in both desktop sidebar and mobile drawer.
 */
const SidebarNav: React.FC<{ onNavigate?: () => void }> = ({ onNavigate }) => (
  <>
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
            className={navLinkClass}
            onClick={onNavigate}
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
        onClick={onNavigate}
      >
        &larr; Về trang chủ
      </NavLink>
    </div>
  </>
)

const AdminSidebar: React.FC = () => {
  const [drawerOpen, setDrawerOpen] = useState(false)
  const location = useLocation()

  // Close drawer on route change
  useEffect(() => {
    setDrawerOpen(false)
  }, [location.pathname])

  // Close on Escape
  useEffect(() => {
    if (!drawerOpen) return
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setDrawerOpen(false)
    }
    document.addEventListener('keydown', handleKeyDown)
    return () => document.removeEventListener('keydown', handleKeyDown)
  }, [drawerOpen])

  // Lock body scroll when drawer is open
  useEffect(() => {
    if (drawerOpen) {
      document.body.style.overflow = 'hidden'
    } else {
      document.body.style.overflow = ''
    }
    return () => {
      document.body.style.overflow = ''
    }
  }, [drawerOpen])

  const closeDrawer = useCallback(() => setDrawerOpen(false), [])

  return (
    <>
      {/* Desktop sidebar — unchanged */}
      <div className="hidden md:flex flex-col w-64 bg-gray-900 overflow-y-auto">
        <SidebarNav />
      </div>

      {/* Mobile hamburger trigger — visible only < md */}
      <button
        aria-label="Mở menu quản trị"
        aria-expanded={drawerOpen}
        onClick={() => setDrawerOpen(o => !o)}
        className="fixed top-3 left-3 z-[60] flex items-center justify-center w-10 h-10 rounded-lg bg-gray-900 text-white shadow-lg md:hidden"
      >
        {drawerOpen ? (
          <svg
            className="w-5 h-5"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
            aria-hidden="true"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M6 18L18 6M6 6l12 12"
            />
          </svg>
        ) : (
          <svg
            className="w-5 h-5"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
            aria-hidden="true"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M4 6h16M4 12h16M4 18h16"
            />
          </svg>
        )}
      </button>

      {/* Mobile slide-over drawer */}
      {drawerOpen && (
        <div
          className="fixed inset-0 z-50 md:hidden"
          role="dialog"
          aria-modal="true"
          aria-label="Menu quản trị"
        >
          {/* Backdrop */}
          <div
            className="absolute inset-0 bg-black/50 transition-opacity"
            onClick={closeDrawer}
            aria-hidden="true"
          />
          {/* Drawer panel */}
          <div className="absolute inset-y-0 left-0 flex flex-col w-64 bg-gray-900 shadow-xl animate-slide-in-left">
            <SidebarNav onNavigate={closeDrawer} />
          </div>
        </div>
      )}
    </>
  )
}

export default AdminSidebar
