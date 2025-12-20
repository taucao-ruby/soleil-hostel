# App Layer (`src/app/`)

> Lớp ứng dụng cốt lõi - cấu hình global, providers, routing và layout

## Tổng quan

App Layer chứa các thành phần cốt lõi của ứng dụng:

- `App.tsx` - Component gốc với Error Boundary
- `providers.tsx` - Context providers
- `router.tsx` - React Router configuration
- `Layout.tsx` - Main layout với header/footer

## 1. App.tsx - Component Gốc

```typescript
// src/app/App.tsx
import React from "react";
import ErrorBoundary from "@/shared/components/ErrorBoundary";
import Providers from "./providers";
import Router from "./router";

const App: React.FC = () => {
  return (
    <ErrorBoundary>
      <Providers>
        <Router />
      </Providers>
    </ErrorBoundary>
  );
};

export default App;
```

**Trách nhiệm:**

- Cung cấp Error Boundary cho toàn bộ ứng dụng
- Wrap các Context Providers
- Khởi tạo React Router

**Error Boundary Pattern:**

```typescript
// src/shared/components/ErrorBoundary.tsx
import React from "react";

interface ErrorBoundaryState {
  hasError: boolean;
  error?: Error;
}

class ErrorBoundary extends React.Component<
  React.PropsWithChildren<{}>,
  ErrorBoundaryState
> {
  constructor(props: React.PropsWithChildren<{}>) {
    super(props);
    this.state = { hasError: false };
  }

  static getDerivedStateFromError(error: Error): ErrorBoundaryState {
    return { hasError: true, error };
  }

  componentDidCatch(error: Error, errorInfo: React.ErrorInfo) {
    // Log error to monitoring service
    console.error("Application Error:", error, errorInfo);

    // Send to error reporting service (e.g., Sentry)
    // reportError(error, errorInfo)
  }

  render() {
    if (this.state.hasError) {
      return (
        <div className="min-h-screen flex items-center justify-center bg-gray-50">
          <div className="text-center">
            <h1 className="text-2xl font-bold text-gray-900 mb-4">
              Something went wrong
            </h1>
            <p className="text-gray-600 mb-8">
              We're sorry, but something unexpected happened.
            </p>
            <button
              onClick={() => window.location.reload()}
              className="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"
            >
              Reload Page
            </button>
          </div>
        </div>
      );
    }

    return this.props.children;
  }
}

export default ErrorBoundary;
```

## 2. Providers (`providers.tsx`)

```typescript
// src/app/providers.tsx
import React from "react";
import { AuthProvider } from "@/features/auth/AuthContext";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { Toaster } from "react-hot-toast";

// Create a client
const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 1000 * 60 * 5, // 5 minutes
      retry: 1,
    },
  },
});

const Providers: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  return (
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        {children}
        <Toaster
          position="top-right"
          toastOptions={{
            duration: 4000,
            style: {
              background: "#363636",
              color: "#fff",
            },
          }}
        />
      </AuthProvider>
    </QueryClientProvider>
  );
};

export default Providers;
```

**Current Providers:**

- `AuthProvider`: Quản lý authentication state
- `QueryClientProvider`: React Query cho data fetching
- `Toaster`: Toast notifications

**Future Providers:**

- `ThemeProvider`: Dark/light theme
- `I18nProvider`: Internationalization
- `ErrorProvider`: Global error handling

## 3. Router (`router.tsx`)

```typescript
// src/app/router.tsx
import { createBrowserRouter, RouterProvider } from "react-router-dom";
import { lazy, Suspense } from "react";
import Layout from "./Layout";
import LoadingSpinner from "@/shared/components/feedback/LoadingSpinner";

// Lazy-loaded pages
const HomePage = lazy(() => import("@/pages/HomePage"));
const LoginPage = lazy(() => import("@/features/auth/LoginPage"));
const RegisterPage = lazy(() => import("@/features/auth/RegisterPage"));
const RoomList = lazy(() => import("@/features/rooms/RoomList"));
const BookingForm = lazy(() => import("@/features/booking/BookingForm"));
const Dashboard = lazy(() => import("@/features/dashboard/Dashboard"));
const NotFoundPage = lazy(() => import("@/pages/NotFoundPage"));

// Suspense wrapper for lazy components
const withSuspense = (Component: React.ComponentType) => (
  <Suspense fallback={<LoadingSpinner fullScreen />}>
    <Component />
  </Suspense>
);

export const router = createBrowserRouter([
  {
    path: "/",
    element: <Layout />,
    errorElement: <NotFoundPage />,
    children: [
      {
        index: true,
        element: withSuspense(HomePage),
      },
      {
        path: "login",
        element: withSuspense(LoginPage),
      },
      {
        path: "register",
        element: withSuspense(RegisterPage),
      },
      {
        path: "rooms",
        element: withSuspense(RoomList),
      },
      {
        path: "booking",
        element: withSuspense(BookingForm),
      },
      {
        path: "dashboard",
        element: withSuspense(Dashboard),
      },
    ],
  },
]);

const Router: React.FC = () => {
  return <RouterProvider router={router} />;
};

export default Router;
```

**Tính năng Router:**

- React Router v7 với nested routes
- Code splitting với React.lazy
- Suspense boundaries cho loading states
- Protected routes với authentication guards
- Error boundaries cho route errors

## 4. Layout (`Layout.tsx`)

```typescript
// src/app/Layout.tsx
import React from "react";
import { Outlet } from "react-router-dom";
import Header from "@/shared/components/layout/Header";
import Footer from "@/shared/components/layout/Footer";

const Layout: React.FC = () => {
  return (
    <div className="min-h-screen flex flex-col bg-gray-50">
      <Header />
      <main className="flex-1">
        <Outlet />
      </main>
      <Footer />
    </div>
  );
};

export default Layout;
```

**Cấu trúc Layout:**

- Sticky header với navigation
- Main content area với `<Outlet />`
- Footer với thông tin liên hệ
- Responsive design với TailwindCSS
- Background và spacing nhất quán

## Best Practices

### Error Handling

- Global Error Boundary ở root level
- Route-level error boundaries
- Graceful fallbacks cho failed components

### Performance

- Lazy loading cho tất cả route components
- Suspense boundaries prevent layout shift
- Minimal re-renders với proper memoization

### Accessibility

- Semantic HTML structure
- Proper heading hierarchy
- Focus management cho navigation

### Testing

- Test error boundaries
- Test lazy loading behavior
- Test responsive layout
- Test keyboard navigation
