import React, { Suspense, lazy, useEffect } from 'react'
import { createBrowserRouter, RouterProvider, Outlet, useNavigate } from 'react-router-dom'
import { AuthProvider } from '@/features/auth/AuthContext'
import { setNavigate } from '@/shared/lib/navigation'
import ProtectedRoute from '@/features/auth/ProtectedRoute'
import LoadingSpinner from '@/shared/components/feedback/LoadingSpinner'
import Layout from './Layout'
import NotFoundPage from '@/pages/NotFoundPage'
import HeaderMobile from '@/features/home/components/HeaderMobile'
import BottomNav from '@/features/home/components/BottomNav'

// Eager-loaded pages (critical for initial render)
import HomePage from '@/pages/HomePage'

// Lazy-loaded pages (code splitting for better performance)
const LoginPage = lazy(() => import('@/features/auth/LoginPage'))
const RegisterPage = lazy(() => import('@/features/auth/RegisterPage'))
const RoomList = lazy(() => import('@/features/rooms/RoomList'))
const BookingForm = lazy(() => import('@/features/booking/BookingForm'))
const BookingList = lazy(() => import('@/features/booking/BookingList'))
const BookingDetailPage = lazy(() => import('@/features/bookings/BookingDetailPage'))
const LocationList = lazy(() => import('@/features/locations/LocationList'))
const LocationDetail = lazy(() => import('@/features/locations/LocationDetail'))
const DashboardPage = lazy(() => import('@/pages/DashboardPage'))

// Admin lazy-loaded components
const AdminLayout = lazy(() => import('@/features/admin/AdminLayout'))
const AdminDashboard = lazy(() => import('@/features/admin/AdminDashboard'))
const AdminRoomDashboard = lazy(() => import('@/features/admin/rooms/AdminRoomDashboard'))
const RoomForm = lazy(() => import('@/features/admin/rooms/RoomForm'))
const AdminBookingDashboard = lazy(() => import('@/features/admin/bookings/AdminBookingDashboard'))
const BookingCalendar = lazy(() => import('@/features/admin/bookings/BookingCalendar'))
const TodayOperations = lazy(() => import('@/features/admin/bookings/TodayOperations'))
const CustomerList = lazy(() => import('@/features/admin/customers/CustomerList'))
const CustomerProfile = lazy(() => import('@/features/admin/customers/CustomerProfile'))

/**
 * NavigationSetter - Registers React Router's navigate function
 * with the navigation service so it can be used outside the component tree.
 *
 * Must be rendered inside RouterProvider to access useNavigate().
 */
const NavigationSetter: React.FC = () => {
  const navigate = useNavigate()
  useEffect(() => {
    setNavigate(navigate)
  }, [navigate])
  return null
}

/**
 * AuthLayout - Wraps all routes with AuthProvider inside the Router tree.
 *
 * This ensures AuthProvider has access to React Router hooks (useNavigate,
 * useLocation, etc.) which is required for auth-driven redirects.
 */
const AuthLayout: React.FC = () => {
  return (
    <AuthProvider>
      <NavigationSetter />
      <Outlet />
    </AuthProvider>
  )
}

/**
 * PublicLayout - Wraps the homepage with mobile-first chrome.
 * HeaderMobile (sticky top) + page content + BottomNav (sticky bottom).
 * Does NOT include the existing dark Header.tsx used by other pages.
 */
const PublicLayout: React.FC = () => {
  return (
    <>
      <HeaderMobile />
      <Outlet />
      <BottomNav />
    </>
  )
}

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
 * Uses React Router v7 with createBrowserRouter and nested pathless layout routes.
 *
 * PublicLayout (pathless):
 * - / : Mobile-first homepage (HeaderMobile + BottomNav)
 *
 * Layout (pathless):
 * - /login, /register, /rooms, /locations, /booking, /dashboard, *
 */
// eslint-disable-next-line react-refresh/only-export-components
export const router = createBrowserRouter([
  {
    element: <AuthLayout />,
    children: [
      // Public homepage — mobile-first chrome (no existing dark Header)
      {
        element: <PublicLayout />,
        children: [
          {
            path: '/',
            element: <HomePage />,
          },
        ],
      },
      // All other routes — existing Layout (dark Header + Footer)
      {
        element: <Layout />,
        children: [
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
            path: 'locations',
            element: withSuspense(LocationList),
          },
          {
            path: 'locations/:slug',
            element: withSuspense(LocationDetail),
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
            path: 'my-bookings',
            element: (
              <ProtectedRoute>
                <Suspense fallback={<LoadingSpinner size="xl" fullScreen message="Loading..." />}>
                  <BookingList />
                </Suspense>
              </ProtectedRoute>
            ),
          },
          {
            path: 'my-bookings/:id',
            element: (
              <ProtectedRoute>
                <Suspense fallback={<LoadingSpinner size="xl" fullScreen message="Loading..." />}>
                  <BookingDetailPage />
                </Suspense>
              </ProtectedRoute>
            ),
          },
          {
            path: 'dashboard',
            element: (
              <ProtectedRoute>
                <Suspense fallback={<LoadingSpinner size="xl" fullScreen message="Loading..." />}>
                  <DashboardPage />
                </Suspense>
              </ProtectedRoute>
            ),
          },
          {
            path: '*',
            element: <NotFoundPage />,
          },
        ],
      },
      // Admin routes — Uses AdminLayout (Sidebar + content area)
      {
        path: '/admin',
        element: (
          <ProtectedRoute>
            <Suspense fallback={<LoadingSpinner size="xl" fullScreen message="Loading Admin..." />}>
              <AdminLayout />
            </Suspense>
          </ProtectedRoute>
        ),
        children: [
          {
            index: true,
            element: withSuspense(AdminDashboard),
          },
          {
            path: 'rooms',
            element: withSuspense(AdminRoomDashboard),
          },
          {
            path: 'rooms/new',
            element: withSuspense(RoomForm),
          },
          {
            path: 'rooms/:id/edit',
            element: withSuspense(RoomForm),
          },
          {
            path: 'bookings',
            element: withSuspense(AdminBookingDashboard),
          },
          {
            path: 'bookings/calendar',
            element: withSuspense(BookingCalendar),
          },
          {
            path: 'bookings/today',
            element: withSuspense(TodayOperations),
          },
          {
            path: 'bookings/:id',
            element: withSuspense(BookingDetailPage),
          },
          {
            path: 'customers',
            element: withSuspense(CustomerList),
          },
          {
            path: 'customers/:email',
            element: withSuspense(CustomerProfile),
          },
          // More admin routes will be added here
        ],
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
