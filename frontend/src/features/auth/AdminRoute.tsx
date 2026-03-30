import React from 'react'
import { Navigate } from 'react-router-dom'
import { useAuth } from './AuthContext'

/**
 * AdminRoute — UX-only role guard for admin routes.
 *
 * By default allows both 'admin' and 'moderator' roles.
 * Pass minRole="admin" on routes that require admin-only access (room CUD, etc.).
 *
 * Redirects ineligible authenticated users to /dashboard.
 * This is a UX convenience layer — NOT a security boundary.
 * Backend middleware enforces actual authorization.
 *
 * Must be nested inside ProtectedRoute (which handles authentication).
 */
interface AdminRouteProps {
  children: React.ReactNode
  /** Minimum role required. 'moderator' allows both admin+moderator; 'admin' is admin-only. */
  minRole?: 'moderator' | 'admin'
}

const AdminRoute: React.FC<AdminRouteProps> = ({ children, minRole = 'moderator' }) => {
  const { user, loading } = useAuth()

  // Still loading auth state — show nothing (ProtectedRoute handles spinner)
  if (loading) return null

  const hasAccess =
    user?.role === 'admin' || (minRole === 'moderator' && user?.role === 'moderator')

  if (!hasAccess) {
    return <Navigate to="/dashboard" replace />
  }

  return <>{children}</>
}

export default AdminRoute
