# Frontend Architecture

> React 19 + TypeScript + Vite with Feature-Sliced Design

## Overview

The frontend uses **Feature-Sliced Design (FSD)** architecture:

```text
src/
├── app/             # App shell - routing, layout, error boundary
├── features/        # Business features - auth, booking, rooms, locations
├── pages/           # Page components - HomePage, NotFoundPage
├── shared/          # Reusable code - UI components, API client, utilities
├── types/           # Shared TypeScript interfaces
├── utils/           # App utilities - toast, web vitals
├── test/            # Test setup (setup.ts)
└── main.tsx         # Entry point
```

### Principles

- **Feature isolation**: Each feature is self-contained (components, API, types, validation)
- **Unidirectional imports**: Features import from `shared/` and `types/`, never from other features
- **No shared mutable state**: Only `AuthContext` provides global state
- **Plain TypeScript**: No runtime validation libraries (no Zod, no io-ts)

---

## Tech Stack

### Production Dependencies

| Package            | Version | Purpose                |
| ------------------ | ------- | ---------------------- |
| `react`            | ^19.0.0 | UI framework           |
| `react-dom`        | ^19.0.0 | React DOM renderer     |
| `react-router-dom` | ^7.9.6  | Client-side routing    |
| `axios`            | ^1.11.0 | HTTP client            |
| `react-toastify`   | ^11.0.5 | Toast notifications    |
| `web-vitals`       | ^5.1.0  | Performance monitoring |

### Dev Dependencies

| Package                       | Purpose                  |
| ----------------------------- | ------------------------ |
| `vite` ^6.3.1                 | Build tool + dev server  |
| `vitest` ^2.1.2               | Unit test runner         |
| `@testing-library/react` ^16  | Component testing        |
| `@playwright/test` ^1.45      | E2E testing (scaffolded) |
| `tailwindcss` ^3.4.1          | Utility-first CSS        |
| `typescript` ~5.7.2           | Type checking            |
| `eslint` ^9.26 + prettier     | Linting + formatting     |

### Not Installed (Previously Documented)

Zod, React Hook Form, Framer Motion, React DatePicker, date-fns, DOMPurify, react-i18next, @tanstack/react-query, class-variance-authority, clsx, tailwind-merge, lucide-react.

---

## Architecture Layers

### App Layer (`src/app/`)

Entry point and routing. See [APP_LAYER.md](APP_LAYER.md).

- `App.tsx` - ErrorBoundary + Router + ToastContainer
- `router.tsx` - AuthLayout pattern (AuthProvider inside router tree)
- `Layout.tsx` - Header + Outlet + Footer

### Features Layer (`src/features/`)

Business logic organized by domain. See [FEATURES_LAYER.md](FEATURES_LAYER.md).

| Feature      | Components                                             |
| ------------ | ------------------------------------------------------ |
| `auth/`      | AuthContext, LoginPage, RegisterPage, ProtectedRoute   |
| `booking/`   | BookingForm, booking.api, booking.validation, types    |
| `rooms/`     | RoomList, room.api, room.types                         |
| `locations/` | LocationList, LocationDetail, LocationCard, types, api |

### Shared Layer (`src/shared/`)

Reusable components and libraries. See [SHARED_LAYER.md](SHARED_LAYER.md).

- **UI**: Button, Card, Input, Label, Skeleton, SkeletonCard
- **Layout**: Header, Footer
- **Feedback**: LoadingSpinner, ErrorBoundary
- **Lib**: api.ts (Axios + interceptors), navigation.ts
- **Utils**: csrf.ts, security.ts

### Types Layer (`src/types/`)

Plain TypeScript interfaces. See [TYPES_LAYER.md](TYPES_LAYER.md).

- `api.ts` - User, Room, Booking, Review, ApiResponse, ApiError

### Utils Layer (`src/utils/`)

App-level utilities. See [UTILS_LAYER.md](UTILS_LAYER.md).

- `toast.ts` - react-toastify wrapper with showToast helpers
- `webVitals.ts` - Core Web Vitals monitoring (CLS, INP, FCP, LCP, TTFB)

---

## Key Patterns

### Authentication

HttpOnly cookie with CSRF double-submit. See [SERVICES_LAYER.md](SERVICES_LAYER.md).

```text
Login → httpOnly cookie (XSS-safe) + csrf_token (sessionStorage)
Requests → cookie auto-sent + X-XSRF-TOKEN header
401 → auto refresh with mutex + retry queue
```

### Routing

React Router v7 with `createBrowserRouter`. AuthProvider lives inside the router tree via AuthLayout wrapper (allows auth code to use `useNavigate`).

### Navigation Service

`shared/lib/navigation.ts` provides `appNavigate()` for programmatic navigation outside React tree (used by API interceptor for login redirect).

### Toast Notifications

`react-toastify` wrapped in `utils/toast.ts`. Provides `showToast.success/error/warning/info/promise`.

---

## Configuration

See [CONFIGURATION.md](CONFIGURATION.md).

- Single path alias: `@` maps to `./src`
- Vite + Vitest config in one file
- Tailwind CSS with minimal customization
- ESLint flat config + Prettier

---

## Testing

See [TESTING.md](TESTING.md).

- **Unit**: Vitest + @testing-library/react (11 files, 142 tests)
- **E2E**: Playwright (scaffolded, 1 spec)

---

## Performance & Security

See [PERFORMANCE_SECURITY.md](PERFORMANCE_SECURITY.md).

- Code splitting via React.lazy
- HttpOnly cookie auth (XSS protection)
- CSRF double-submit pattern
- CSP nonce injection plugin
- XSS sanitization utilities
- Web Vitals monitoring
