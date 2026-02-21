import React, { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'

/**
 * HeaderMobile — sticky top header for the public homepage.
 * Transitions from transparent to warmWhite/blur as user scrolls.
 */
const HeaderMobile: React.FC = () => {
  const [scrolled, setScrolled] = useState(false)
  const [menuOpen, setMenuOpen] = useState(false)

  useEffect(() => {
    const handleScroll = () => setScrolled(window.scrollY > 40)
    window.addEventListener('scroll', handleScroll, { passive: true })
    return () => window.removeEventListener('scroll', handleScroll)
  }, [])

  return (
    <header
      className={[
        'fixed top-0 left-0 right-0 z-50 transition-all duration-300',
        scrolled ? 'bg-warmWhite/90 backdrop-blur-md shadow-sm' : 'bg-transparent',
      ].join(' ')}
    >
      <div className="flex items-center justify-between px-4 h-14">
        {/* Logo */}
        <Link to="/" className="flex items-center gap-2">
          <span
            className={[
              'font-serif font-bold text-xl leading-none transition-colors duration-300',
              scrolled ? 'text-woodDark' : 'text-warmWhite',
            ].join(' ')}
          >
            Soleil
          </span>
          <span
            className={[
              'text-xs font-sans tracking-wider uppercase transition-colors duration-300',
              scrolled ? 'text-orangeCTA' : 'text-brandGold',
            ].join(' ')}
          >
            Hostel
          </span>
        </Link>

        {/* Right controls */}
        <div className="flex items-center gap-3">
          {/* VI | EN language badge */}
          <button
            aria-label="Chuyển ngôn ngữ"
            className={[
              'text-xs font-sans font-medium px-2 py-1 rounded border transition-colors duration-300',
              scrolled
                ? 'text-woodDark border-soleilBorder hover:border-orangeCTA'
                : 'text-warmWhite border-warmWhite/40 hover:border-warmWhite',
            ].join(' ')}
          >
            VI&nbsp;|&nbsp;EN
          </button>

          {/* Hamburger */}
          <button
            aria-label="Mở menu"
            aria-expanded={menuOpen}
            onClick={() => setMenuOpen(o => !o)}
            className={[
              'flex flex-col justify-center items-center w-8 h-8 gap-1.5 transition-colors duration-300',
              scrolled ? 'text-woodDark' : 'text-warmWhite',
            ].join(' ')}
          >
            <span
              className={[
                'block h-0.5 w-5 bg-current transition-transform duration-200',
                menuOpen ? 'translate-y-2 rotate-45' : '',
              ].join(' ')}
            />
            <span
              className={[
                'block h-0.5 w-5 bg-current transition-opacity duration-200',
                menuOpen ? 'opacity-0' : '',
              ].join(' ')}
            />
            <span
              className={[
                'block h-0.5 w-5 bg-current transition-transform duration-200',
                menuOpen ? '-translate-y-2 -rotate-45' : '',
              ].join(' ')}
            />
          </button>
        </div>
      </div>

      {/* Slide-down nav menu */}
      {menuOpen && (
        <nav className="bg-warmWhite border-t border-soleilBorder px-4 py-3 flex flex-col gap-1">
          <Link
            to="/rooms"
            className="py-2 text-sm font-sans text-woodDark hover:text-orangeCTA transition-colors"
            onClick={() => setMenuOpen(false)}
          >
            Xem phòng
          </Link>
          <Link
            to="/login"
            className="py-2 text-sm font-sans text-woodDark hover:text-orangeCTA transition-colors"
            onClick={() => setMenuOpen(false)}
          >
            Đăng nhập
          </Link>
          <Link
            to="/register"
            className="py-2 text-sm font-sans text-woodDark hover:text-orangeCTA transition-colors"
            onClick={() => setMenuOpen(false)}
          >
            Đăng ký
          </Link>
        </nav>
      )}
    </header>
  )
}

export default HeaderMobile
