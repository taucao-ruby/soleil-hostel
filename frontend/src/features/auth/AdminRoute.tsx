import React from 'react'
import { Navigate } from 'react-router-dom'
import { useAuth } from './AuthContext'

/**
 * AdminRoute — UX-only role guard for admin routes.
 *
 * Redirects non-admin authenticated users to /dashboard.
 * This is a UX convenience layer — NOT a security boundary.
 * Backend middleware `role:admin` enforces actual authorization.
 *
 * Must be nested inside ProtectedRoute (which handles authentication).
 */
interface AdminRouteProps {
  children: React.ReactNode
}

const AdminRoute: React.FC<AdminRouteProps> = ({ children }) => {
  const { user, loading } = useAuth()

  // Still loading auth state — show nothing (ProtectedRoute handles spinner)
  if (loading) return null

  // Non-admin → redirect to dashboard
  if (user?.role !== 'admin') {
    return <Navigate to="/dashboard" replace />
  }

  return <>{children}</>
}

export default AdminRoute
