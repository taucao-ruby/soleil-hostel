import React, { useState } from 'react'
import { Link, useNavigate, useLocation } from 'react-router-dom'
import { useAuth } from '@/features/auth/AuthContext'

/**
 * HeaderMobile — responsive sticky header for the public homepage.
 *
 * Mobile  (<md): Wordmark + "Phòng" shortcut + hamburger drawer
 * Tablet+ (≥md): Full horizontal nav, no hamburger
 *   Left:   Soleil wordmark
 *   Center: Nav links (Trang chủ | Phòng | Chi nhánh)
 *   Right:  Đặt ngay CTA + Đăng nhập / Đăng ký
 */

const NAV_LINKS = [
  { to: '/', label: 'Trang chủ' },
  { to: '/rooms', label: 'Phòng' },
  { to: '/locations', label: 'Chi nhánh' },
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

  const isActive = (to: string) =>
    to === '/' ? location.pathname === '/' : location.pathname.startsWith(to)

  return (
    <>
      <header className="fixed top-0 left-0 right-0 z-50 bg-[#1C1A17]/95 backdrop-blur-md h-14 border-b border-white/[0.08]">
        <div className="flex items-center justify-between h-full px-4 md:px-8 lg:px-12 max-w-7xl mx-auto">
          {/* Wordmark */}
          <Link to="/" onClick={close} className="flex items-center gap-1.5 shrink-0">
            <span className="font-serif font-bold text-[20px] text-[#C9973A] leading-none">
              Soleil
            </span>
            <span className="text-[10px] font-sans tracking-widest uppercase text-white/45 mt-0.5">
              HOSTEL
            </span>
          </Link>

          {/* ── Desktop center nav ──────────────────────────────────── */}
          <nav aria-label="Điều hướng chính" className="hidden md:flex items-center gap-1">
            {NAV_LINKS.map(({ to, label }) => {
              const active = isActive(to)
              return (
                <Link
                  key={to}
                  to={to}
                  className={[
                    'px-4 py-2 rounded-lg text-[14px] font-medium transition-colors duration-150',
                    active ? 'text-[#C9973A]' : 'text-white/65 hover:text-white',
                  ].join(' ')}
                >
                  {label}
                </Link>
              )
            })}
          </nav>

          {/* ── Desktop right: auth + CTA ───────────────────────────── */}
          <div className="hidden md:flex items-center gap-2">
            {isAuthenticated ? (
              <>
                <Link
                  to="/dashboard"
                  className="text-[13px] text-white/65 hover:text-white transition-colors px-3 py-2 rounded-lg"
                >
                  Tài khoản
                </Link>
                <button
                  onClick={handleLogout}
                  className="text-[13px] text-white/65 hover:text-rose-400 transition-colors px-3 py-2 rounded-lg"
                >
                  Đăng xuất
                </button>
              </>
            ) : (
              <>
                <Link
                  to="/login"
                  className="text-[13px] text-white/65 hover:text-white transition-colors px-3 py-2 rounded-lg"
                >
                  Đăng nhập
                </Link>
                <Link
                  to="/register"
                  className="text-[13px] font-medium text-white/65 hover:text-white transition-colors px-3 py-2 rounded-lg"
                >
                  Đăng ký
                </Link>
              </>
            )}
            <Link
              to="/booking"
              className="ml-1 text-[13px] font-semibold bg-[#C9973A] hover:bg-[#b8852e] text-white px-5 py-2 rounded-lg transition-colors duration-150"
            >
              Đặt ngay
            </Link>
          </div>

          {/* ── Mobile right: Phòng + hamburger ──────────────────────── */}
          <div className="flex md:hidden items-center gap-4">
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
                  : 'border-white/20 bg-white/5 hover:border-white/40',
              ].join(' ')}
            >
              <span
                className={[
                  'absolute w-4 h-[2px] rounded-full transition-all duration-300 origin-center',
                  menuOpen ? 'bg-[#C9973A] rotate-45' : 'bg-white/80 -translate-y-[5px]',
                ].join(' ')}
              />
              <span
                className={[
                  'absolute w-4 h-[2px] rounded-full transition-all duration-200',
                  menuOpen ? 'opacity-0 scale-x-0' : 'bg-white/80 opacity-100',
                ].join(' ')}
              />
              <span
                className={[
                  'absolute w-4 h-[2px] rounded-full transition-all duration-300 origin-center',
                  menuOpen ? 'bg-[#C9973A] -rotate-45' : 'bg-white/80 translate-y-[5px]',
                ].join(' ')}
              />
            </button>
          </div>
        </div>
      </header>

      {/* ── Mobile drawer ──────────────────────────────────────────────── */}
      {menuOpen && (
        <>
          <div
            className="fixed inset-0 z-40 bg-black/40 backdrop-blur-[2px] md:hidden"
            onClick={close}
            aria-hidden="true"
          />
          <nav
            aria-label="Menu điều hướng"
            className="fixed top-14 left-0 right-0 z-40 bg-[#1C1A17] border-t border-white/10 shadow-2xl md:hidden"
          >
            <ul className="flex flex-col py-2">
              {[...NAV_LINKS, { to: '/booking', label: 'Đặt phòng' }].map(({ to, label }) => {
                const active = isActive(to)
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
              <li>
                <div className="mx-5 my-2 border-t border-white/10" />
              </li>
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
                      className="flex w-full items-center gap-3 px-5 py-3.5 text-[15px] text-rose-400 hover:text-rose-300 hover:bg-white/5 transition-colors"
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
                      className="block text-center py-2.5 rounded-xl bg-[#C9973A] text-white text-[14px] font-semibold hover:bg-[#b8852e] transition-colors"
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
