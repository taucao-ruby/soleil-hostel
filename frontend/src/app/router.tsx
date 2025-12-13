import React, { Suspense, lazy } from 'react'
import { createBrowserRouter, RouterProvider } from 'react-router-dom'
import ProtectedRoute from '@/features/auth/ProtectedRoute'
import LoadingSpinner from '@/shared/components/feedback/LoadingSpinner'
import Layout from './Layout'

// Eager-loaded pages (critical for initial render)
import HomePage from '@/pages/HomePage'

// Lazy-loaded pages (code splitting for better performance)
const LoginPage = lazy(() => import('@/features/auth/LoginPage'))
const RegisterPage = lazy(() => import('@/features/auth/RegisterPage'))
const RoomList = lazy(() => import('@/features/rooms/RoomList'))
const BookingForm = lazy(() => import('@/features/booking/BookingForm'))

// Placeholder Dashboard
const DashboardPage = () => (
  <div className="min-h-screen p-8 bg-gray-50">
    <div className="max-w-4xl mx-auto">
      <h1 className="mb-4 text-3xl font-bold text-gray-900">Dashboard</h1>
      <p className="mb-6 text-gray-600">Welcome to your dashboard! Manage your bookings here.</p>
      <div className="p-6 bg-white shadow-md rounded-xl">
        <p className="text-gray-500">Dashboard features coming soon...</p>
      </div>
    </div>
  </div>
)

/**
 * Suspense Wrapper Component
 * Wraps lazy-loaded components with Suspense boundary
 */
const withSuspense = (Component: React.LazyExoticComponent<React.ComponentType>) => {
  return (
    <Suspense fallback={<LoadingSpinner size="xl" fullScreen message="Loading..." />}>
      <Component />
    </Suspense>
  )
}

/**
 * Router Configuration
 *
 * Uses React Router v7 with createBrowserRouter and nested routes.
 *
 * Layout Route (parent):
 * - Provides Header + Outlet + Footer structure
 * - Allows Header to use useNavigate() inside Router context
 *
 * Child Routes:
 * - / : Public home page
 * - /login : Login page
 * - /register : Register page
 * - /rooms : Room list
 * - /booking : Protected booking form
 * - /dashboard : Protected dashboard
 */
export const router = createBrowserRouter([
  {
    path: '/',
    element: <Layout />,
    children: [
      {
        index: true,
        element: <HomePage />,
      },
      {
        path: 'login',
        element: withSuspense(LoginPage),
      },
      {
        path: 'register',
        element: withSuspense(RegisterPage),
      },
      {
        path: 'rooms',
        element: withSuspense(RoomList),
      },
      {
        path: 'booking',
        element: (
          <ProtectedRoute>
            <Suspense fallback={<LoadingSpinner size="xl" fullScreen message="Loading..." />}>
              <BookingForm />
            </Suspense>
          </ProtectedRoute>
        ),
      },
      {
        path: 'dashboard',
        element: (
          <ProtectedRoute>
            <DashboardPage />
          </ProtectedRoute>
        ),
      },
    ],
  },
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
