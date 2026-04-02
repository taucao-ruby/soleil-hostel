import React, { useEffect, useState } from 'react'
import { Outlet, useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '@/features/auth/AuthContext'
import AdminSidebar from './AdminSidebar'

function getBreadcrumbOverride(state: unknown): string | null {
  if (typeof state !== 'object' || state === null || !('adminBreadcrumb' in state)) {
    return null
  }

  const breadcrumb = (state as { adminBreadcrumb?: unknown }).adminBreadcrumb
  return typeof breadcrumb === 'string' && breadcrumb.trim() ? breadcrumb : null
}

function getAdminBreadcrumb(pathname: string, state?: unknown): string {
  const override = getBreadcrumbOverride(state)

  if (override) {
    return override
  }

  if (pathname === '/admin') return 'Tổng quan'
  if (pathname.startsWith('/admin/bookings/calendar')) return 'Đặt phòng / Lịch'
  if (pathname.startsWith('/admin/bookings/today')) return 'Đặt phòng / Hôm nay'
  if (/^\/admin\/bookings\/[^/]+/.test(pathname)) return 'Đặt phòng / Chi tiết'
  if (pathname.startsWith('/admin/bookings')) return 'Đặt phòng'
  if (pathname.startsWith('/admin/rooms/new')) return 'Phòng / Thêm phòng mới'
  if (/^\/admin\/rooms\/[^/]+\/edit$/.test(pathname)) return 'Phòng / Sửa phòng'
  if (pathname.startsWith('/admin/rooms')) return 'Phòng'
  if (/^\/admin\/customers\/[^/]+/.test(pathname)) return 'Khách hàng / Hồ sơ'
  if (pathname.startsWith('/admin/customers')) return 'Khách hàng'
  return 'Tổng quan'
}

function getInitials(name: string): string {
  const initials = name
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map(part => part.charAt(0))
    .join('')
    .toUpperCase()

  return initials || 'SA'
}

const AdminLayout: React.FC = () => {
  const { user, logoutHttpOnly } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [isMobileSidebarOpen, setIsMobileSidebarOpen] = useState(false)

  useEffect(() => {
    setIsMobileSidebarOpen(false)
  }, [location.pathname])

  useEffect(() => {
    if (!isMobileSidebarOpen) return

    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        setIsMobileSidebarOpen(false)
      }
    }

    document.addEventListener('keydown', handleKeyDown)
    return () => document.removeEventListener('keydown', handleKeyDown)
  }, [isMobileSidebarOpen])

  useEffect(() => {
    document.body.style.overflow = isMobileSidebarOpen ? 'hidden' : ''

    return () => {
      document.body.style.overflow = ''
    }
  }, [isMobileSidebarOpen])

  const userName = user?.name ?? 'Soleil Admin'
  const roleLabel = user?.role === 'admin' ? 'Quản trị viên' : 'Nhân viên'
  const breadcrumb = `Quản trị / ${getAdminBreadcrumb(location.pathname, location.state)}`

  const handleLogout = async () => {
    setIsMobileSidebarOpen(false)

    try {
      await logoutHttpOnly()
    } finally {
      navigate('/')
    }
  }

  return (
    <div className="min-h-screen bg-[radial-gradient(circle_at_top,_rgba(217,119,6,0.08),_transparent_34%),linear-gradient(180deg,_#faf8f3_0%,_#f4efe6_100%)]">
      <AdminSidebar
        isMobileOpen={isMobileSidebarOpen}
        onCloseMobile={() => setIsMobileSidebarOpen(false)}
        onLogout={handleLogout}
        userInitials={getInitials(userName)}
        userName={userName}
      />

      <div className="min-h-screen md:ml-60">
        <header className="sticky top-0 z-20 flex h-12 items-center justify-between border-b border-gray-100 bg-white/90 px-4 backdrop-blur md:px-6">
          <div className="flex min-w-0 items-center gap-3">
            <button
              type="button"
              aria-label="Mở điều hướng quản trị"
              aria-expanded={isMobileSidebarOpen}
              onClick={() => setIsMobileSidebarOpen(open => !open)}
              className="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 text-stone-700 transition hover:bg-stone-50 md:hidden"
            >
              <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                {isMobileSidebarOpen ? (
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M6 18 18 6M6 6l12 12"
                  />
                ) : (
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M4 7h16M4 12h16M4 17h16"
                  />
                )}
              </svg>
            </button>
            <p className="truncate text-[13px] text-stone-500">{breadcrumb}</p>
          </div>

          <span
            className={`inline-flex items-center rounded-full border px-3 py-1 text-xs font-medium ${
              user?.role === 'admin'
                ? 'border-amber-200 bg-amber-50 text-amber-800'
                : 'border-sky-200 bg-sky-50 text-sky-700'
            }`}
          >
            {roleLabel}
          </span>
        </header>

        <main className="px-4 py-4 sm:px-6 sm:py-6">
          <Outlet />
        </main>
      </div>
    </div>
  )
}

export default AdminLayout
