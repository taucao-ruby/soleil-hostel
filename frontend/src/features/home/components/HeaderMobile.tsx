import React, { useState } from 'react'
import { Link, useNavigate, useLocation } from 'react-router-dom'
import { useAuth } from '@/features/auth/AuthContext'

/**
 * HeaderMobile — sticky top header for the public homepage (PROMPT_1A).
 *
 * Design:
 *   - Always dark bg-[#1C1A17], height h-14
 *   - Left: "Soleil" wordmark (amber) + "HOSTEL" label
 *   - Right: Phòng link + hamburger button (☰ / ✕)
 *   - Hamburger opens full slide-down drawer with all nav links
 *   - Drawer closes on link click or backdrop tap
 */

const NAV_LINKS = [
  { to: '/', label: 'Trang chủ' },
  { to: '/rooms', label: 'Phòng' },
  { to: '/locations', label: 'Chi nhánh' },
  { to: '/booking', label: 'Đặt phòng' },
]

const HeaderMobile: React.FC = () => {
  const { isAuthenticated, logoutHttpOnly } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [menuOpen, setMenuOpen] = useState(false)

  const handleLogout = async () => {
    setMenuOpen(false)
    try {
      await logoutHttpOnly()
      navigate('/')
    } catch {
      // non-critical
    }
  }

  const close = () => setMenuOpen(false)

  return (
    <>
      <header className="fixed top-0 left-0 right-0 z-50 bg-[#1C1A17] h-14">
        <div className="flex items-center justify-between px-4 h-full">
          {/* Wordmark */}
          <Link to="/" onClick={close} className="flex items-center gap-1.5">
            <span className="font-serif font-bold text-xl text-[#C9973A] leading-none">Soleil</span>
            <span className="text-[10px] font-sans tracking-widest uppercase text-white/50 mt-0.5">
              HOSTEL
            </span>
          </Link>

          {/* Right: quick Phòng link + hamburger */}
          <div className="flex items-center gap-4">
            <Link
              to="/rooms"
              className="text-[13px] text-white/80 hover:text-white transition-colors"
            >
              Phòng
            </Link>
            <button
              onClick={() => setMenuOpen(v => !v)}
              aria-label={menuOpen ? 'Đóng menu' : 'Mở menu'}
              aria-expanded={menuOpen}
              className={[
                'relative flex flex-col justify-center items-center w-9 h-9 rounded-lg border transition-all duration-200',
                menuOpen
                  ? 'border-[#C9973A]/60 bg-[#C9973A]/10'
                  : 'border-white/20 bg-white/5 hover:border-white/40 hover:bg-white/10',
              ].join(' ')}
            >
              {/* Top line */}
              <span
                className={[
                  'absolute w-4 h-[2px] rounded-full transition-all duration-300 origin-center',
                  menuOpen
                    ? 'bg-[#C9973A] rotate-45 translate-y-0'
                    : 'bg-white/80 -translate-y-[5px]',
                ].join(' ')}
              />
              {/* Middle line */}
              <span
                className={[
                  'absolute w-4 h-[2px] rounded-full transition-all duration-200',
                  menuOpen ? 'bg-[#C9973A] opacity-0 scale-x-0' : 'bg-white/80 opacity-100',
                ].join(' ')}
              />
              {/* Bottom line */}
              <span
                className={[
                  'absolute w-4 h-[2px] rounded-full transition-all duration-300 origin-center',
                  menuOpen
                    ? 'bg-[#C9973A] -rotate-45 translate-y-0'
                    : 'bg-white/80 translate-y-[5px]',
                ].join(' ')}
              />
            </button>
          </div>
        </div>
      </header>

      {/* ── Slide-down drawer ────────────────────────────────────────── */}
      {menuOpen && (
        <>
          {/* Backdrop */}
          <div
            className="fixed inset-0 z-40 bg-black/40 backdrop-blur-[2px]"
            onClick={close}
            aria-hidden="true"
          />

          {/* Drawer panel */}
          <nav
            aria-label="Menu điều hướng"
            className="fixed top-14 left-0 right-0 z-40 bg-[#1C1A17] border-t border-white/10 shadow-xl"
          >
            <ul className="flex flex-col py-2">
              {NAV_LINKS.map(({ to, label }) => {
                const active = location.pathname === to
                return (
                  <li key={to}>
                    <Link
                      to={to}
                      onClick={close}
                      className={[
                        'flex items-center gap-3 px-5 py-3.5 text-[15px] transition-colors',
                        active
                          ? 'text-[#C9973A] bg-white/5'
                          : 'text-white/80 hover:text-white hover:bg-white/5',
                      ].join(' ')}
                    >
                      <span
                        className={`w-1 h-4 rounded-full ${active ? 'bg-[#C9973A]' : 'bg-transparent'}`}
                      />
                      {label}
                    </Link>
                  </li>
                )
              })}

              {/* Divider */}
              <li>
                <div className="mx-5 my-2 border-t border-white/10" />
              </li>

              {/* Auth links */}
              {isAuthenticated ? (
                <>
                  <li>
                    <Link
                      to="/dashboard"
                      onClick={close}
                      className="flex items-center gap-3 px-5 py-3.5 text-[15px] text-white/80 hover:text-white hover:bg-white/5 transition-colors"
                    >
                      <span className="w-1 h-4 rounded-full bg-transparent" />
                      Bảng điều khiển
                    </Link>
                  </li>
                  <li>
                    <button
                      onClick={handleLogout}
                      className="flex items-center gap-3 w-full px-5 py-3.5 text-[15px] text-rose-400 hover:text-rose-300 hover:bg-white/5 transition-colors"
                    >
                      <span className="w-1 h-4 rounded-full bg-transparent" />
                      Đăng xuất
                    </button>
                  </li>
                </>
              ) : (
                <>
                  <li>
                    <Link
                      to="/login"
                      onClick={close}
                      className="flex items-center gap-3 px-5 py-3.5 text-[15px] text-white/80 hover:text-white hover:bg-white/5 transition-colors"
                    >
                      <span className="w-1 h-4 rounded-full bg-transparent" />
                      Đăng nhập
                    </Link>
                  </li>
                  <li className="px-5 py-3">
                    <Link
                      to="/register"
                      onClick={close}
                      className="block text-center py-2.5 rounded-xl bg-[#C9973A] text-white text-[14px] font-medium hover:bg-[#b8852e] transition-colors"
                    >
                      Đăng ký ngay
                    </Link>
                  </li>
                </>
              )}
            </ul>
          </nav>
        </>
      )}
    </>
  )
}

export default HeaderMobile
