import React, { useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '@/features/auth/AuthContext'

/**
 * Header — dark sticky header for all non-/ non-/admin/* routes (PROMPT_SH1).
 *
 * Desktop (≥ 768px):
 *   Left:   Soleil wordmark (amber)
 *   Center: Trang chủ | Phòng | Chi nhánh
 *   Right:  auth section (unauthenticated: Đăng nhập outline + Đăng ký amber)
 *           (authenticated: Đặt phòng · Bảng điều khiển · Xin chào, Văn A · Đăng xuất)
 *
 * Mobile (< 768px):
 *   Left:  Soleil wordmark
 *   Right: hamburger button (aria-label="Mở menu")
 *   Below: slide-down dark nav card
 */

// ─── Helpers ──────────────────────────────────────────────────────────────────

/** Extract given name: "Nguyễn Văn A" → "Văn A" */
function givenName(fullName: string | null | undefined): string {
  if (!fullName) return ''
  const parts = fullName.trim().split(/\s+/)
  return parts.length > 1 ? parts.slice(1).join(' ') : fullName
}

// ─── Nav links ────────────────────────────────────────────────────────────────

const CENTER_LINKS = [
  { path: '/', label: 'Trang chủ', exact: true },
  { path: '/rooms', label: 'Phòng', exact: false },
  { path: '/locations', label: 'Chi nhánh', exact: false },
]

// ─── Component ────────────────────────────────────────────────────────────────

const Header: React.FC = () => {
  const location = useLocation()
  const navigate = useNavigate()
  const { isAuthenticated, user, logoutHttpOnly } = useAuth()
  const [menuOpen, setMenuOpen] = useState(false)

  const isActive = (path: string, exact: boolean) =>
    exact ? location.pathname === path : location.pathname.startsWith(path)

  const closeMenu = () => setMenuOpen(false)

  const handleLogout = async () => {
    closeMenu()
    try {
      await logoutHttpOnly()
      navigate('/')
    } catch {
      // non-critical
    }
  }

  // ── Desktop center link style ──────────────────────────────────────────────
  const desktopLink = (path: string, exact: boolean, label: string) => {
    const active = isActive(path, exact)
    return (
      <Link
        key={path}
        to={path}
        className={[
          'text-[14px] font-sans transition-colors',
          active ? 'text-[#C9973A]' : 'text-white/80 hover:text-amber-300',
        ].join(' ')}
      >
        {label}
      </Link>
    )
  }

  // ── Mobile link style ──────────────────────────────────────────────────────
  const mobileLink = (path: string, label: string, onClick?: () => void) => (
    <Link
      key={path}
      to={path}
      onClick={onClick ?? closeMenu}
      className="block py-2.5 px-4 text-[15px] text-white/80 hover:text-white hover:bg-white/5 rounded-lg transition-colors"
    >
      {label}
    </Link>
  )

  return (
    <header className="sticky top-0 z-50 bg-[#1C1A17]">
      {/* ── Main bar ──────────────────────────────────────────────────────── */}
      <div className="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
        {/* Left — wordmark */}
        <Link to="/" className="flex items-center gap-1.5 flex-shrink-0">
          <span
            className="text-[#C9973A] font-sans font-medium leading-none"
            style={{ fontSize: '18px' }}
          >
            Soleil
          </span>
          <span className="text-[10px] text-white/40 tracking-widest uppercase mt-0.5">HOSTEL</span>
        </Link>

        {/* Center — desktop nav */}
        <nav className="hidden md:flex items-center gap-7" aria-label="Điều hướng chính">
          {CENTER_LINKS.map(l => desktopLink(l.path, l.exact, l.label))}
        </nav>

        {/* Right — desktop auth */}
        <div className="hidden md:flex items-center gap-3">
          {isAuthenticated ? (
            <>
              <Link
                to="/booking"
                className="text-[14px] font-sans text-white/80 hover:text-white transition-colors"
              >
                Đặt phòng
              </Link>
              <Link
                to="/dashboard"
                className="text-[14px] font-sans text-white/80 hover:text-white transition-colors"
              >
                Bảng điều khiển
              </Link>
              <span className="text-[13px] text-white/50 select-none">
                Xin chào, {givenName(user?.name)}
              </span>
              <button
                onClick={handleLogout}
                className="text-[13px] font-sans text-white/70 hover:text-white transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/40 rounded"
              >
                Đăng xuất
              </button>
            </>
          ) : (
            <>
              <Link
                to="/login"
                className="text-sm font-sans text-white border border-white/50 px-4 py-1.5 rounded-lg hover:border-white hover:text-white transition-colors"
              >
                Đăng nhập
              </Link>
              <Link
                to="/register"
                className="text-sm font-sans text-white bg-[#C9973A] hover:bg-[#B8872A] px-4 py-1.5 rounded-lg transition-colors"
              >
                Đăng ký
              </Link>
            </>
          )}
        </div>

        {/* Mobile — hamburger */}
        <button
          className="md:hidden flex items-center justify-center w-9 h-9 text-white/80 hover:text-white transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/40 rounded-lg"
          aria-label="Mở menu"
          aria-expanded={menuOpen}
          onClick={() => setMenuOpen(o => !o)}
        >
          <svg
            className="w-5 h-5"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
            aria-hidden="true"
          >
            {menuOpen ? (
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M6 18L18 6M6 6l12 12"
              />
            ) : (
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M4 6h16M4 12h16M4 18h16"
              />
            )}
          </svg>
        </button>
      </div>

      {/* ── Slide-down mobile menu ─────────────────────────────────────────── */}
      {menuOpen && (
        <nav
          className="md:hidden border-t border-white/10 bg-[#1C1A17] px-4 py-3 space-y-1"
          aria-label="Điều hướng di động"
        >
          {/* Core links */}
          {CENTER_LINKS.map(l => mobileLink(l.path, l.label))}

          {/* Auth section */}
          <div className="pt-3 mt-3 border-t border-white/10 space-y-1">
            {isAuthenticated ? (
              <>
                {mobileLink('/booking', 'Đặt phòng')}
                {mobileLink('/dashboard', 'Bảng điều khiển')}
                <div className="px-4 py-1.5 text-[13px] text-white/40 select-none">
                  Xin chào, {givenName(user?.name)}
                </div>
                <button
                  onClick={handleLogout}
                  className="w-full text-left block py-2.5 px-4 text-[15px] text-white/80 hover:text-white hover:bg-white/5 rounded-lg transition-colors"
                >
                  Đăng xuất
                </button>
              </>
            ) : (
              <>
                <Link
                  to="/login"
                  onClick={closeMenu}
                  className="block py-2.5 px-4 text-[15px] text-white/80 hover:text-white hover:bg-white/5 rounded-lg transition-colors"
                >
                  Đăng nhập
                </Link>
                <Link
                  to="/register"
                  onClick={closeMenu}
                  className="block py-2.5 px-4 text-[15px] text-white bg-[#C9973A] hover:bg-[#B8872A] rounded-lg transition-colors text-center mt-1"
                >
                  Đăng ký
                </Link>
              </>
            )}
          </div>
        </nav>
      )}
    </header>
  )
}

export default Header
