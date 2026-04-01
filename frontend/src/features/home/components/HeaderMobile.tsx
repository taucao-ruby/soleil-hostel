import React from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '@/features/auth/AuthContext'

/**
 * HeaderMobile — sticky top header for the public homepage (PROMPT_1A).
 *
 * Design:
 *   - Always dark bg #1C1A17 (no scroll transition)
 *   - Left: "Soleil" wordmark in brand amber + "HOSTEL" label
 *   - Right: 3 compact ghost links — no hamburger, no VI|EN badge
 *   - Unauthenticated: Phòng | Đăng nhập | Đăng ký
 *   - Authenticated:   Phòng | Bảng điều khiển | Đăng xuất
 */
const HeaderMobile: React.FC = () => {
  const { isAuthenticated, logoutHttpOnly } = useAuth()
  const navigate = useNavigate()

  const handleLogout = async () => {
    try {
      await logoutHttpOnly()
      navigate('/')
    } catch {
      // Logout API failure is non-critical
    }
  }

  return (
    <header className="fixed top-0 left-0 right-0 z-50 bg-[#1C1A17] h-14">
      <div className="flex items-center justify-between px-4 h-full">
        {/* Wordmark */}
        <Link to="/" className="flex items-center gap-1.5">
          <span className="font-serif font-bold text-xl text-[#C9973A] leading-none">Soleil</span>
          <span className="text-[10px] font-sans tracking-widest uppercase text-white/50 mt-0.5">
            HOSTEL
          </span>
        </Link>

        {/* Right nav — 3 compact ghost links, no hamburger */}
        <nav className="flex items-center gap-4" aria-label="Điều hướng chính">
          <Link
            to="/rooms"
            className="text-[13px] font-sans text-white/80 hover:text-white transition-colors"
          >
            Phòng
          </Link>
          {isAuthenticated ? (
            <>
              <Link
                to="/dashboard"
                className="text-[13px] font-sans text-white/80 hover:text-white transition-colors"
              >
                Bảng điều khiển
              </Link>
              <button
                onClick={handleLogout}
                className="text-[13px] font-sans text-white/80 hover:text-white transition-colors"
              >
                Đăng xuất
              </button>
            </>
          ) : (
            <>
              <Link
                to="/login"
                className="text-[13px] font-sans text-white/80 hover:text-white transition-colors"
              >
                Đăng nhập
              </Link>
              <Link
                to="/register"
                className="text-[13px] font-sans text-[#C9973A] border border-[#C9973A] rounded-lg px-3 py-1 hover:bg-[#C9973A] hover:text-white transition-colors"
              >
                Đăng ký
              </Link>
            </>
          )}
        </nav>
      </div>
    </header>
  )
}

export default HeaderMobile
