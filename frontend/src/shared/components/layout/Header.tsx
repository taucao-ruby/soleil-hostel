import React, { useState } from 'react'
import { Link, useNavigate, useLocation } from 'react-router-dom'
import { useAuth } from '@/features/auth/AuthContext'
import Button from '../ui/Button'

/**
 * Header Component
 *
 * Sticky navigation header with:
 * - Logo
 * - Navigation links (Home, Rooms, Booking, Dashboard)
 * - Auth buttons (Login/Logout)
 * - Mobile hamburger menu
 */

const Header: React.FC = () => {
  const navigate = useNavigate()
  const location = useLocation()
  const { isAuthenticated, user, logoutHttpOnly } = useAuth()
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false)

  const handleLogout = async () => {
    try {
      await logoutHttpOnly()
      navigate('/')
    } catch (err) {
      console.error('Logout failed:', err)
    }
  }

  const isActive = (path: string) => location.pathname === path

  const navLinks = [
    { path: '/', label: 'Home' },
    { path: '/rooms', label: 'Rooms' },
    ...(isAuthenticated ? [{ path: '/booking', label: 'Book Now' }] : []),
    ...(isAuthenticated ? [{ path: '/dashboard', label: 'Dashboard' }] : []),
  ]

  return (
    <header className="sticky top-0 z-50 bg-white shadow-md">
      <nav className="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div className="flex items-center justify-between h-16">
          {/* Logo */}
          <Link
            to="/"
            className="flex items-center space-x-2 text-2xl font-bold text-blue-600 transition-colors hover:text-blue-700"
          >
            <svg className="w-8 h-8 text-yellow-400" fill="currentColor" viewBox="0 0 24 24">
              <path d="M12 2L2 7v10c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-10-5zm0 2.18l8 4v8.82c0 4.52-3.13 8.79-8 10-4.87-1.21-8-5.48-8-10V8.18l8-4z" />
              <circle cx="12" cy="12" r="3" />
            </svg>
            <span className="hidden sm:block">Soleil Hostel</span>
          </Link>

          {/* Desktop Navigation */}
          <div className="items-center hidden space-x-6 md:flex">
            {navLinks.map(link => (
              <Link
                key={link.path}
                to={link.path}
                className={`text-sm font-medium transition-colors ${
                  isActive(link.path)
                    ? 'text-blue-600 border-b-2 border-blue-600'
                    : 'text-gray-700 hover:text-blue-600'
                }`}
              >
                {link.label}
              </Link>
            ))}
          </div>

          {/* Auth Buttons (Desktop) */}
          <div className="items-center hidden space-x-4 md:flex">
            {isAuthenticated ? (
              <>
                <span className="text-sm text-gray-600">Hi, {user?.name}</span>
                <Button variant="outline" size="sm" onClick={handleLogout}>
                  Logout
                </Button>
              </>
            ) : (
              <>
                <Button variant="ghost" size="sm" onClick={() => navigate('/login')}>
                  Login
                </Button>
                <Button variant="primary" size="sm" onClick={() => navigate('/register')}>
                  Register
                </Button>
              </>
            )}
          </div>

          {/* Mobile Menu Button */}
          <button
            onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
            className="p-2 text-gray-700 rounded-lg md:hidden hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
            aria-label="Toggle menu"
          >
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              {mobileMenuOpen ? (
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

        {/* Mobile Menu */}
        {mobileMenuOpen && (
          <div className="py-4 border-t border-gray-200 md:hidden">
            <div className="flex flex-col space-y-3">
              {navLinks.map(link => (
                <Link
                  key={link.path}
                  to={link.path}
                  onClick={() => setMobileMenuOpen(false)}
                  className={`px-4 py-2 text-base font-medium rounded-lg transition-colors ${
                    isActive(link.path)
                      ? 'bg-blue-50 text-blue-600'
                      : 'text-gray-700 hover:bg-gray-100'
                  }`}
                >
                  {link.label}
                </Link>
              ))}
              <div className="pt-3 mt-3 border-t border-gray-200">
                {isAuthenticated ? (
                  <>
                    <div className="px-4 py-2 text-sm text-gray-600">Hi, {user?.name}</div>
                    <button
                      onClick={() => {
                        handleLogout()
                        setMobileMenuOpen(false)
                      }}
                      className="w-full px-4 py-2 text-base font-medium text-left text-gray-700 transition-colors rounded-lg hover:bg-gray-100"
                    >
                      Logout
                    </button>
                  </>
                ) : (
                  <>
                    <button
                      onClick={() => {
                        navigate('/login')
                        setMobileMenuOpen(false)
                      }}
                      className="w-full px-4 py-2 text-base font-medium text-left text-gray-700 transition-colors rounded-lg hover:bg-gray-100"
                    >
                      Login
                    </button>
                    <button
                      onClick={() => {
                        navigate('/register')
                        setMobileMenuOpen(false)
                      }}
                      className="w-full px-4 py-2 mt-2 text-base font-medium text-left text-white transition-colors bg-blue-600 rounded-lg hover:bg-blue-700"
                    >
                      Register
                    </button>
                  </>
                )}
              </div>
            </div>
          </div>
        )}
      </nav>
    </header>
  )
}

export default Header
