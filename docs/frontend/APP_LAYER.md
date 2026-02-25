# App Layer (`src/app/`)

> Core application layer - routing, layout, and provider wiring

## Overview

App Layer contains the core application structure:

- `App.tsx` - Root component with ErrorBoundary + ToastContainer
- `router.tsx` - React Router v7 configuration with AuthLayout
- `Layout.tsx` - Main layout with Header/Footer

**Note:** There is no separate `providers.tsx` file. The `AuthProvider` is wired inside the router tree via the `AuthLayout` component pattern.

---

## 1. App.tsx - Root Component

```typescript
// src/app/App.tsx
import React from 'react'
import ErrorBoundary from '@/shared/components/ErrorBoundary'
import { ToastContainer } from '@/utils/toast'
import Router from './router'

const App: React.FC = () => {
  return (
    <ErrorBoundary>
      <Router />
      <ToastContainer />
    </ErrorBoundary>
  )
}

export default App
```

**Architecture:**

1. `ErrorBoundary` - Catches runtime errors, shows fallback UI
2. `Router` - React Router v7 with nested layout routes
3. `ToastContainer` - react-toastify notifications (rendered at root level)

**Key decision:** `AuthProvider` is NOT at the root level. It lives inside the Router tree (via `AuthLayout`) so it can use `useNavigate()` and other router hooks.

---

## 2. Router (`router.tsx`)

```typescript
// src/app/router.tsx
import { createBrowserRouter, RouterProvider, Outlet } from 'react-router-dom'
import { AuthProvider } from '@/features/auth/AuthContext'
import { setNavigate } from '@/shared/lib/navigation'
import ProtectedRoute from '@/features/auth/ProtectedRoute'
import LoadingSpinner from '@/shared/components/feedback/LoadingSpinner'
import Layout from './Layout'

// Eager-loaded (critical for initial render)
import HomePage from '@/pages/HomePage'

// Lazy-loaded (code splitting)
const LoginPage = lazy(() => import('@/features/auth/LoginPage'))
const RegisterPage = lazy(() => import('@/features/auth/RegisterPage'))
const RoomList = lazy(() => import('@/features/rooms/RoomList'))
const BookingForm = lazy(() => import('@/features/booking/BookingForm'))
const LocationList = lazy(() => import('@/features/locations/LocationList'))
const LocationDetail = lazy(() => import('@/features/locations/LocationDetail'))
const DashboardPage = lazy(() => import('@/pages/DashboardPage'))
```

### AuthLayout Pattern

```typescript
// Wraps all routes with AuthProvider inside the Router tree
const AuthLayout: React.FC = () => {
  return (
    <AuthProvider>
      <NavigationSetter />
      <Outlet />
    </AuthProvider>
  )
}
```

### PublicLayout Pattern

```typescript
// Wraps the homepage with mobile-first chrome
// HeaderMobile (sticky top) + page content + BottomNav (sticky bottom)
// Does NOT use the dark Header.tsx used by other routes
const PublicLayout: React.FC = () => {
  return (
    <>
      <HeaderMobile />
      <Outlet />
      <BottomNav />
    </>
  )
}
```

This pattern ensures `AuthProvider` has access to React Router hooks (`useNavigate`, `useLocation`), which is required for auth-driven redirects.

### NavigationSetter

```typescript
// Registers React Router's navigate function with the navigation service
// so it can be used outside the component tree (e.g., API interceptors)
const NavigationSetter: React.FC = () => {
  const navigate = useNavigate()
  useEffect(() => {
    setNavigate(navigate)
  }, [navigate])
  return null
}
```

### Route Configuration

```typescript
export const router = createBrowserRouter([
  {
    element: <AuthLayout />,
    children: [
      // Homepage — mobile-first chrome (HeaderMobile + BottomNav)
      {
        element: <PublicLayout />,
        children: [{ path: '/', element: <HomePage /> }],
      },
      // All other routes — dark Header + Footer via Layout
      {
        element: <Layout />,
        children: [
          { path: 'login', element: withSuspense(LoginPage) },
          { path: 'register', element: withSuspense(RegisterPage) },
          { path: 'rooms', element: withSuspense(RoomList) },
          { path: 'locations', element: withSuspense(LocationList) },
          { path: 'locations/:slug', element: withSuspense(LocationDetail) },
          {
            path: 'booking',
            element: <ProtectedRoute>{withSuspense(BookingForm)}</ProtectedRoute>,
          },
          {
            path: 'dashboard',
            element: <ProtectedRoute>{withSuspense(DashboardPage)}</ProtectedRoute>,
          },
          { path: '*', element: <NotFoundPage /> },
        ],
      },
    ],
  },
])
```

### Routes Summary

| Path               | Component      | Layout      | Auth Required | Loading |
| ------------------ | -------------- | ----------- | ------------- | ------- |
| `/`                | HomePage       | PublicLayout| No            | Eager   |
| `/login`           | LoginPage      | Layout      | No            | Lazy    |
| `/register`        | RegisterPage   | Layout      | No            | Lazy    |
| `/rooms`           | RoomList       | Layout      | No            | Lazy    |
| `/locations`       | LocationList   | Layout      | No            | Lazy    |
| `/locations/:slug` | LocationDetail | Layout      | No            | Lazy    |
| `/booking`         | BookingForm    | Layout      | Yes           | Lazy    |
| `/dashboard`       | DashboardPage  | Layout      | Yes           | Lazy    |
| `*`                | NotFoundPage   | Layout      | No            | Eager   |

---

## 3. Layout (`Layout.tsx`)

```typescript
// src/app/Layout.tsx
import { Outlet } from 'react-router-dom'
import Header from '@/shared/components/layout/Header'
import Footer from '@/shared/components/layout/Footer'

const Layout: React.FC = () => {
  return (
    <div className="min-h-screen flex flex-col bg-gray-50">
      <Header />
      <main className="flex-1">
        <Outlet />
      </main>
      <Footer />
    </div>
  )
}
```

**Structure:**

- Sticky header with navigation
- Main content area with `<Outlet />`
- Footer with links
- Responsive design with TailwindCSS

---

## 4. Provider Architecture

```text
ErrorBoundary
  └── RouterProvider
        └── AuthLayout
              ├── NavigationSetter (registers navigate fn)
              └── AuthProvider
                    └── Layout
                          ├── Header (uses useAuth)
                          ├── Outlet (page content)
                          └── Footer
ToastContainer (sibling of RouterProvider)
```

**Current Providers:**

- `AuthProvider` - Authentication state management (httpOnly cookies)

**Toast Notifications:**

- `react-toastify` (NOT react-hot-toast)
- `ToastContainer` from `@/utils/toast`
- `showToast.success/error/warning/info` helpers

**Not Used:**

- No `QueryClientProvider` (no @tanstack/react-query)
- No `ThemeProvider`
- No `I18nProvider`
