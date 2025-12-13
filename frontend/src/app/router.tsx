import React from 'react'
import { createBrowserRouter, RouterProvider } from 'react-router-dom'
import ProtectedRoute from '@/features/auth/ProtectedRoute'

// Pages
import HomePage from '@/pages/HomePage'
import LoginPage from '@/features/auth/LoginPage'
import RegisterPage from '@/features/auth/RegisterPage'
import RoomList from '@/features/rooms/RoomList'
import BookingForm from '@/features/booking/BookingForm'

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
    path: '/rooms',
    element: <RoomList />,
  },
  {
    path: '/booking',
    element: (
      <ProtectedRoute>
        <BookingForm />
      </ProtectedRoute>
    ),
  },
  {
    path: '/dashboard',
    element: (
      <ProtectedRoute>
        <DashboardPage />
      </ProtectedRoute>
    ),
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
