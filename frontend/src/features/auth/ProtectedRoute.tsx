import React from 'react'
import { Navigate, useLocation } from 'react-router-dom'
import { useAuth } from './AuthContext'

/**
 * ProtectedRoute Component
 *
 * Guards routes that require authentication.
 *
 * Behavior:
 * - If loading: Show loading spinner
 * - If not authenticated: Redirect to /login
 * - If authenticated: Render children
 *
 * Usage:
 * <Route path="/dashboard" element={<ProtectedRoute><Dashboard /></ProtectedRoute>} />
 */

interface ProtectedRouteProps {
  children: React.ReactNode
}

const ProtectedRoute: React.FC<ProtectedRouteProps> = ({ children }) => {
  const { isAuthenticated, loading } = useAuth()
  const location = useLocation()

  // Show loading spinner while checking authentication
  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-screen bg-gradient-to-br from-cream via-cream-warm to-cream">
        <div className="text-center">
          {/* Spinner */}
          <div className="inline-block w-16 h-16 mb-4 border-4 border-gold/25 rounded-full border-t-gold animate-spin"></div>
          <p className="font-medium text-ink-soft">Đang kiểm tra phiên đăng nhập...</p>
        </div>
      </div>
    )
  }

  // Not authenticated - redirect to login, preserving intended URL
  if (!isAuthenticated) {
    return <Navigate to="/login" state={{ from: location.pathname }} replace />
  }

  // Authenticated - render protected content
  return <>{children}</>
}

export default ProtectedRoute
