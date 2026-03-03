import React from 'react'
import ErrorBoundary from '@/shared/components/ErrorBoundary'
import { ToastContainer } from '@/shared/utils/toast'
import Router from './router'

/**
 * App Component - Root Application Component
 *
 * Architecture:
 * 1. ErrorBoundary: Catches runtime errors, shows fallback UI
 * 2. Router: React Router v7 with nested layout routes
 *    - AuthProvider is inside the Router tree (via AuthLayout route)
 *    - This allows AuthProvider to use useNavigate() and other router hooks
 *    - Layout component (Header + Outlet + Footer) is inside AuthProvider
 *
 * This is the entry point for the feature-sliced architecture.
 * All global providers and error handling start here.
 */

const App: React.FC = () => {
  return (
    <ErrorBoundary>
      <Router />
      <ToastContainer />
    </ErrorBoundary>
  )
}

export default App
