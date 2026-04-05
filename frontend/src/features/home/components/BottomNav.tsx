import React from 'react'
import { Link, useLocation } from 'react-router-dom'
import { useAuth } from '@/features/auth/AuthContext'

/**
 * BottomNav — 4-tab sticky bottom navigation for mobile.
 *
 * Route-driven: active tab derived from `useLocation`, not local state.
 * Auth-aware: "Tài khoản" links to /dashboard when authenticated, /login otherwise.
 *
 * Defect fixes:
 *   H-01: Labels are navigation destinations only. "Cuộn xuống" (or any action verb)
 *         is FORBIDDEN. Labels: Trang chủ | Phòng | Đặt phòng | Tài khoản.
 *   M-02: Tabs now use <Link> and useLocation instead of dead useState.
 */

interface Tab {
  id: string
  label: string
  path: string
  /** Alternate path when user is authenticated */
  authPath?: string
  icon: React.ReactNode
}

const TABS: Tab[] = [
  {
    id: 'home',
    label: 'Trang chủ',
    path: '/',
    icon: (
      <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        className="w-6 h-6"
        fill="none"
        stroke="currentColor"
        strokeWidth={2}
        aria-hidden="true"
      >
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"
        />
      </svg>
    ),
  },
  {
    id: 'rooms',
    label: 'Phòng',
    path: '/rooms',
    icon: (
      <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        className="w-6 h-6"
        fill="none"
        stroke="currentColor"
        strokeWidth={2}
        aria-hidden="true"
      >
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
        />
      </svg>
    ),
  },
  {
    id: 'booking',
    label: 'Đặt phòng',
    path: '/booking',
    icon: (
      <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        className="w-6 h-6"
        fill="none"
        stroke="currentColor"
        strokeWidth={2}
        aria-hidden="true"
      >
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
        />
      </svg>
    ),
  },
  {
    id: 'account',
    label: 'Tài khoản',
    path: '/login',
    authPath: '/dashboard',
    icon: (
      <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        className="w-6 h-6"
        fill="none"
        stroke="currentColor"
        strokeWidth={2}
        aria-hidden="true"
      >
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
        />
      </svg>
    ),
  },
]

/**
 * Match current location pathname to a tab id.
 * Exact match for "/" to avoid false positives on "/" prefix.
 */
function getActiveTabId(pathname: string): string {
  if (pathname === '/') return 'home'
  if (pathname.startsWith('/rooms')) return 'rooms'
  if (pathname.startsWith('/booking')) return 'booking'
  if (
    pathname.startsWith('/dashboard') ||
    pathname.startsWith('/login') ||
    pathname.startsWith('/register')
  )
    return 'account'
  return ''
}

const BottomNav: React.FC = () => {
  const location = useLocation()
  const { isAuthenticated } = useAuth()
  const activeTabId = getActiveTabId(location.pathname)

  return (
    <nav
      className="md:hidden fixed bottom-0 left-0 right-0 z-50 bg-[#FDFAF3] border-t border-[#E2D5C3]"
      style={{ paddingBottom: 'env(safe-area-inset-bottom)' }}
      aria-label="Điều hướng chính"
    >
      <div className="grid grid-cols-4 h-14">
        {TABS.map(tab => {
          const isActive = activeTabId === tab.id
          const href = tab.authPath && isAuthenticated ? tab.authPath : tab.path
          return (
            <Link
              key={tab.id}
              to={href}
              aria-label={tab.label}
              aria-current={isActive ? 'page' : undefined}
              className={[
                'relative flex flex-col items-center justify-center gap-0.5',
                'text-xs font-sans font-medium',
                'transition-colors duration-150',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#D4622A]',
                isActive ? 'text-[#D4622A]' : 'text-[#5C3D1E]/40 hover:text-[#5C3D1E]',
              ].join(' ')}
            >
              {/* Active indicator — top edge bar */}
              {isActive && (
                <span
                  aria-hidden="true"
                  className="absolute top-0 left-1/2 -translate-x-1/2 w-8 h-0.5 bg-[#D4622A] rounded-full"
                />
              )}
              {tab.icon}
              {tab.label}
            </Link>
          )
        })}
      </div>
    </nav>
  )
}

export default BottomNav
