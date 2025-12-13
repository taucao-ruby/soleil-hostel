import React from 'react'
import { createBrowserRouter, RouterProvider } from 'react-router-dom'
import ProtectedRoute from '@/features/auth/ProtectedRoute'

// Placeholder components - will be implemented in Phase 2
const HomePage = () => <div>Home Page - Coming Soon</div>
const LoginPage = () => <div>Login Page - Coming Soon</div>
const RegisterPage = () => <div>Register Page - Coming Soon</div>
const DashboardPage = () => <div>Dashboard Page - Coming Soon</div>

/**
 * Router Configuration
 *
 * Uses React Router v7 with createBrowserRouter.
 *
 * Routes:
 * - / : Public home page
 * - /login : Login page
 * - /register : Register page
 * - /dashboard : Protected dashboard (requires auth)
 *
 * Protected Routes:
 * Wrapped with <ProtectedRoute> component to require authentication.
 */
export const router = createBrowserRouter([
  {
    path: '/',
    element: <HomePage />,
  },
  {
    path: '/login',
    element: <LoginPage />,
  },
  {
    path: '/register',
    element: <RegisterPage />,
  },
  {
    path: '/dashboard',
    element: (
      <ProtectedRoute>
        <DashboardPage />
      </ProtectedRoute>
    ),
  },
  // Add more routes here in Phase 2
])

/**
 * Router Component
 *
 * Provides routing functionality to the app.
 */
const Router: React.FC = () => {
  return <RouterProvider router={router} />
}

export default Router
