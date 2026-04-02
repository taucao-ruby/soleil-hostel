import React from 'react'
import { NavLink } from 'react-router-dom'

interface AdminSidebarProps {
  isMobileOpen: boolean
  onCloseMobile: () => void
  onLogout: () => void | Promise<void>
  userInitials: string
  userName: string
}

interface AdminNavItem {
  label: string
  path: string
  exact?: boolean
  icon: React.ReactNode
}

const iconClassName = 'h-4 w-4 flex-none'

const navItems: AdminNavItem[] = [
  {
    label: 'Tổng quan',
    path: '/admin',
    exact: true,
    icon: (
      <svg className={iconClassName} fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth={1.8}
          d="M3 12h7V3H3v9Zm0 9h7v-5H3v5Zm11 0h7v-9h-7v9Zm0-13h7V3h-7v5Z"
        />
      </svg>
    ),
  },
  {
    label: 'Đặt phòng',
    path: '/admin/bookings',
    icon: (
      <svg className={iconClassName} fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth={1.8}
          d="M8 7V3m8 4V3m-9 8h10m-12 9h14a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2Z"
        />
      </svg>
    ),
  },
  {
    label: 'Phòng',
    path: '/admin/rooms',
    icon: (
      <svg className={iconClassName} fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth={1.8}
          d="M4 12V8.5A2.5 2.5 0 0 1 6.5 6h11A2.5 2.5 0 0 1 20 8.5V12M4 12v5m0-5h16m0 0v5M8 10h.01M16 10h.01M6 17h12"
        />
      </svg>
    ),
  },
  {
    label: 'Khách hàng',
    path: '/admin/customers',
    icon: (
      <svg className={iconClassName} fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth={1.8}
          d="M15 19v-1a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v1m18 0v-1a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75M13 7a4 4 0 1 1-8 0a4 4 0 0 1 8 0Z"
        />
      </svg>
    ),
  },
]

const navLinkClassName = ({ isActive }: { isActive: boolean }) =>
  [
    'mx-2 flex items-center gap-3 rounded-lg border-l-2 px-3 py-2.5 text-sm transition-colors',
    isActive
      ? 'border-amber-400 bg-amber-900/40 text-amber-300'
      : 'border-transparent text-gray-400 hover:bg-white/5 hover:text-white',
  ].join(' ')

const SidebarContent: React.FC<{
  onLogout: () => void | Promise<void>
  onNavigate?: () => void
  userInitials: string
  userName: string
}> = ({ onLogout, onNavigate, userInitials, userName }) => {
  const handleLogoutClick = () => {
    onNavigate?.()
    void onLogout()
  }

  return (
    <div className="flex h-full flex-col pb-6">
      <div className="flex items-center gap-3 px-4 pt-6">
        <div className="flex h-8 w-8 items-center justify-center rounded-full bg-amber-400 text-[13px] font-semibold text-[#1A1916]">
          S
        </div>
        <p className="text-[13px] font-medium text-white">Soleil Admin</p>
      </div>

      <nav className="mt-8 flex flex-1 flex-col gap-1" aria-label="Điều hướng quản trị">
        {navItems.map(item => (
          <NavLink
            key={item.path}
            to={item.path}
            end={item.exact}
            className={navLinkClassName}
            onClick={onNavigate}
          >
            <span aria-hidden="true">{item.icon}</span>
            <span>{item.label}</span>
          </NavLink>
        ))}
      </nav>

      <div className="mt-auto px-3">
        <div className="flex items-center gap-3 rounded-xl border border-white/10 bg-white/[0.04] px-3 py-3">
          <div className="flex h-8 w-8 items-center justify-center rounded-full bg-white/10 text-xs font-semibold text-white">
            {userInitials}
          </div>
          <div className="min-w-0 flex-1">
            <p className="truncate text-[13px] font-medium text-white">{userName}</p>
            <button
              type="button"
              onClick={handleLogoutClick}
              className="mt-1 text-[13px] text-gray-400 transition-colors hover:text-white"
            >
              Đăng xuất
            </button>
          </div>
        </div>
      </div>
    </div>
  )
}

const AdminSidebar: React.FC<AdminSidebarProps> = ({
  isMobileOpen,
  onCloseMobile,
  onLogout,
  userInitials,
  userName,
}) => {
  return (
    <>
      <aside className="fixed inset-y-0 left-0 z-30 hidden w-60 bg-[#1A1916] md:flex md:flex-col">
        <SidebarContent onLogout={onLogout} userInitials={userInitials} userName={userName} />
      </aside>

      {isMobileOpen && (
        <div
          className="fixed inset-0 z-50 bg-black/50 md:hidden"
          role="dialog"
          aria-modal="true"
          aria-label="Điều hướng quản trị"
        >
          <button
            type="button"
            className="absolute inset-0 cursor-default"
            onClick={onCloseMobile}
            aria-label="Đóng điều hướng quản trị"
          />

          <aside className="relative flex h-full w-60 flex-col bg-[#1A1916] shadow-2xl">
            <SidebarContent
              onLogout={onLogout}
              onNavigate={onCloseMobile}
              userInitials={userInitials}
              userName={userName}
            />
          </aside>
        </div>
      )}
    </>
  )
}

export default AdminSidebar
