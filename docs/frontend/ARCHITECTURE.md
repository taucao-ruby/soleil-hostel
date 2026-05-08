# Frontend Architecture

> React 19 + TypeScript + Vite with Feature-Sliced Design
>
> **UI DESIGN CONTEXT (Google Stitch):**
> Use this document to understand the component hierarchy and layout system for producing component-tree sketches.
> Three top-level layout zones drive all screens — see the Layout System section below.
> Import direction is strictly one-way: `pages/` and `features/` import from `shared/`; features do not import from each other.
> Component names referenced in FEATURES_LAYER.md map directly to `src/features/` subdirectories listed here.

---

## Overview

The frontend uses **Feature-Sliced Design (FSD)** architecture:

```text
src/
├── app/             # App shell - router, providers, Layout
├── assets/          # Bundled static assets imported from source
├── features/        # Feature-sliced modules (auth, booking, bookings, rooms, locations, home, admin, assistant)
├── pages/           # Route-level pages (HomePage, DashboardPage, NotFoundPage)
├── shared/          # Cross-cutting code (components, lib, types, utils)
├── test/            # Vitest test setup
├── main.tsx         # Entry point — mounts App into #root
└── vite-env.d.ts    # Vite ambient types
```

> **There is no top-level `src/types/`, `src/services/`, or `src/utils/` directory.** Shared types live in `src/shared/types/`; shared utilities live in `src/shared/utils/` and `src/shared/lib/`. Feature-specific types stay co-located with their feature module (e.g. `features/booking/booking.types.ts`).

### Principles

- **Feature isolation**: Each feature is self-contained (components, API, types, validation)
- **Unidirectional imports**: Features import from `shared/` and `types/`, never from other features
- **No shared mutable state**: Only `AuthContext` provides global state
- **Plain TypeScript**: No runtime validation libraries (no Zod, no io-ts)

---

## Tech Stack

### Production Dependencies (verified against `package.json` at HEAD `6372d7f`, 2026-05-08)

| Package            | Version | Purpose                |
| ------------------ | ------- | ---------------------- |
| `react`            | ^19.0.0 | UI framework           |
| `react-dom`        | ^19.0.0 | React DOM renderer     |
| `react-router-dom` | ^7.x    | Client-side routing    |
| `axios`            | ^1.16.0 | HTTP client (bumped from 1.15.0 on 2026-05-05 via `97c684c`)            |
| `react-toastify`   | ^11.x   | Toast notifications    |
| `web-vitals`       | ^5.x    | Performance monitoring |

### Dev Dependencies

| Package                       | Purpose                  |
| ----------------------------- | ------------------------ |
| `vite` 6.4.2                  | Build tool + dev server (pinned for CVE GHSA-p9ff-h696-f583) |
| `vitest` ^4.1.2               | Unit test runner         |
| `@testing-library/react` ^16  | Component testing        |
| `@playwright/test`            | E2E testing (scaffolded — see [`tests/e2e/README.md`](../../frontend/tests/e2e/README.md)) |
| `tailwindcss` ^3              | Utility-first CSS        |
| `typescript` ~5.7.2           | Type checking            |
| `eslint` ^9.x + prettier      | Linting + formatting     |
| `pnpm`                        | Package manager (`packageManager: pnpm@9.15.9`) |

### Not Installed (Previously Documented)

Zod, React Hook Form, Framer Motion, React DatePicker, date-fns, DOMPurify, react-i18next, @tanstack/react-query, class-variance-authority, clsx, tailwind-merge, lucide-react.

---

## Layout System (Component Hierarchy for Stitch)

```
App
├── ErrorBoundary
├── RouterProvider
│   └── AuthLayout (AuthProvider + NavigationSetter)
│       ├── PublicLayout  — route: /
│       │   ├── HeaderMobile (sticky top, mobile)
│       │   ├── HomePage (Hero, SearchCard, FilterChips, RoomCard grid, PromoBanner, ReviewsCarousel)
│       │   └── BottomNav (sticky bottom, mobile)
│       │
│       ├── Layout  — all routes except / and /admin/*
│       │   ├── Header (dark, sticky top — shows auth-conditional nav links)
│       │   ├── <Outlet> (page content)
│       │   └── Footer
│       │
│       └── AdminLayout  — routes: /admin/*
│           ├── AdminSidebar (left rail; desktop visible, mobile overlay)
│           └── <Outlet> (admin page content)
│               ├── AdminBookingDashboard / BookingCalendar / TodayOperations
│               ├── CustomerList / CustomerProfile + StayJournal
│               └── AdminRoomDashboard / RoomForm
└── ToastContainer (portal, z-index top)
```

### Shared UI Components (`src/shared/components/`)

| Component | Usage |
|---|---|
| `Button` | All CTA buttons (variant: primary / outline / danger) |
| `Card` | Content container cards |
| `Input`, `Label` | Shared form fields (NOT used in auth forms — auth uses native inputs) |
| `Skeleton`, `SkeletonCard` | Loading placeholder states |
| `LoadingSpinner` | Full-page loading (ProtectedRoute, Suspense) |
| `ErrorBoundary` | Runtime error fallback |
| `Header` | Top navigation bar (uses `useAuth()` for conditional links) |
| `Footer` | Bottom footer |
| `ConfirmDialog` | Reusable cancel/restore confirm dialog (full a11y: `role=dialog`, Escape key) |

---

## Architecture Layers

### App Layer (`src/app/`)

Entry point and routing. See [APP_LAYER.md](APP_LAYER.md).

- `App.tsx` - ErrorBoundary + Router + ToastContainer
- `router.tsx` - AuthLayout pattern (AuthProvider inside router tree)
- `Layout.tsx` - Header + Outlet + Footer

### Features Layer (`src/features/`)

Business logic organized by domain. See [FEATURES_LAYER.md](FEATURES_LAYER.md).

| Feature | Components |
|---|---|
| `auth/` | AuthContext, LoginPage, RegisterPage, EmailVerifyPage (OTP, since Apr 3), ProtectedRoute, AdminRoute |
| `booking/` | BookingForm, BookingList, booking.api, booking.validation, booking.types |
| `bookings/` | GuestDashboard, BookingDetailPanel, BookingDetailPage, ReviewForm, useMyBookings, bookingViewModel |
| `admin/` | AdminDashboard, AdminLayout, AdminSidebar, admin.api, admin.types |
| `admin/bookings/` | AdminBookingDashboard (7 filters), AdminBookingTable, BookingCalendar, TodayOperations |
| `admin/customers/` | CustomerList, CustomerProfile, StayJournal |
| `admin/rooms/` | AdminRoomDashboard, RoomForm, RoomStatusBadge, RoomStatusBoard, RoomTable |
| `rooms/` | RoomList, room.api, room.types |
| `locations/` | LocationList, LocationDetail, LocationCard, location.api, location.types |
| `home/` | SearchCard, Hero, FilterChips, RoomCard, BottomNav, HeaderMobile, PromoBanner, ReviewsCarousel |
| `assistant/` | RoomDiscoveryWidget — AI proposal-confirmation flow widget; calls `/v1/ai/room_discovery` and posts decision via `/v1/ai/proposals/{hash}/decide`. Backed by the durable `ai_proposals` table (server-side drift detection, proposer-binding, F-67) |

### Shared Layer (`src/shared/`)

Reusable components and libraries. See [SHARED_LAYER.md](SHARED_LAYER.md).

- **components/ui**: Button, Card, Input, Label, Skeleton, SkeletonCard
- **components/layout**: Header, Footer
- **components/feedback**: LoadingSpinner, ErrorBoundary
- **lib**: `api.ts` (Axios + CSRF + 401-refresh interceptors), `room.api.ts`, `location.api.ts`, `navigation.ts`, `booking.utils.ts`, `formatCurrency.ts`
- **types**: `api.ts`, `booking.types.ts`, `location.types.ts` — shared DTOs
- **utils**: `csrf.ts`, `security.ts`, `toast.ts` (react-toastify wrapper), `webVitals.ts` (Core Web Vitals: CLS / INP / FCP / LCP / TTFB)

> See [TYPES_LAYER.md](TYPES_LAYER.md) and [UTILS_LAYER.md](UTILS_LAYER.md) for the canonical layout. There are no top-level `src/types/` or `src/utils/` folders — everything shared lives under `src/shared/`.

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

- **Unit**: Vitest + @testing-library/react — file count 39 as of HEAD `6372d7f` (2026-05-08); test count deferred to [PROJECT_STATUS.md](../../PROJECT_STATUS.md) (Mar 31 baseline 261; May 3 intermediate 418)
- **E2E**: Playwright — 4 scaffolded flows under [`frontend/tests/e2e/`](../../frontend/tests/e2e/README.md) (`guest-booking`, `payment-webhook`, `ai-proposal`, `admin-restore`); workflow_dispatch-gated until stable

---

## Performance & Security

See [PERFORMANCE_SECURITY.md](PERFORMANCE_SECURITY.md).

- Code splitting via React.lazy
- HttpOnly cookie auth (XSS protection)
- CSRF double-submit pattern
- CSP nonce injection plugin
- XSS sanitization utilities
- Web Vitals monitoring
