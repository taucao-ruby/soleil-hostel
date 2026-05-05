# 🎨 Soleil Hostel Frontend

> **Last Updated:** May 5, 2026 | **React:** 19.0.0 | **TypeScript:** ~5.7 | **Vite:** 6.4.2 | **Vitest:** ^4.1 | **Package Manager:** pnpm 9.x

## 🎯 Overview

The Soleil Hostel frontend is a React 19 + TypeScript SPA on a feature-sliced architecture. Auth is dual-mode (Bearer + HttpOnly cookie); state uses `useState`/`useEffect` with `AbortController` (no React Query). Validation is plain TypeScript (no Zod). User-facing strings are Vietnamese.

### Key Features

- ✅ **Modern Stack**: React 19 + TypeScript (strict) + Vite 6
- ✅ **Feature-Sliced Design**: `features/` modules for `auth`, `bookings`, `booking`, `rooms`, `locations`, `home`, `admin`, `assistant`
- ✅ **Authentication**: Dual-mode (Bearer Token + HttpOnly Cookie) with CSRF (`X-XSRF-TOKEN` from `sessionStorage`)
- ✅ **Routing**: React Router v7 with lazy loading; `ProtectedRoute` + `AdminRoute` (`minRole` prop) for RBAC
- ✅ **Mobile-First**: TailwindCSS 3, Core Web Vitals tracked via `web-vitals`
- ✅ **Type Safety**: TypeScript strict — `npx tsc --noEmit` is a pre-push gate
- ✅ **Code-Splitting**: `React.lazy` + `Suspense` for routes
- ✅ **Testing**: Vitest unit/integration (39 test files); Playwright E2E scaffolded under `tests/e2e/`
- ✅ **Accessibility**: WCAG 2.1 AA targets

---

## 🚀 Quick Start

### Prerequisites

- Node.js 20+
- pnpm 9.x (frontend uses `packageManager: pnpm@9.15.9` in `package.json`)

### Installation

```bash
# 1. Install dependencies
pnpm install

# 2. Configure environment (optional — Vite proxies /api → http://127.0.0.1:8000 in dev)
cp .env.example .env

# 3. Start development server
pnpm dev
# Vite auto-binds to port 5173 (configured in package.json scripts)
```

Frontend will be available at: http://localhost:5173

### Build for Production

```bash
pnpm build      # tsc -b && vite build
pnpm preview    # serve the production bundle locally
```

---

## 📂 Project Structure

```
frontend/
├── public/                    # Static assets
├── src/
│   ├── app/                   # Application shell
│   │   ├── App.tsx            # Root component
│   │   ├── providers.tsx      # AuthProvider + global providers
│   │   ├── router.tsx         # React Router v7 with lazy loading
│   │   └── Layout.tsx         # Shared layout (header / footer)
│   │
│   ├── features/              # Feature-sliced business logic
│   │   ├── auth/              # AuthContext, login, register, ProtectedRoute, AdminRoute
│   │   ├── booking/           # Booking creation form (BookingForm, validation, view-model)
│   │   ├── bookings/          # Booking lifecycle UI (Guest/Admin dashboards, detail, ReviewForm)
│   │   ├── rooms/             # Room list + detail
│   │   ├── locations/         # Location list + detail (boutique hero, reviews)
│   │   ├── home/              # Homepage (SearchCard, FilterChips, HeaderMobile, TrustBar)
│   │   ├── admin/             # Admin shell (AdminLayout, AdminSidebar, room/booking dashboards)
│   │   └── assistant/         # AI room-discovery widget + proposal-confirmation flow
│   │
│   ├── shared/                # Cross-cutting code
│   │   ├── components/        # ui/, layout/, feedback/
│   │   ├── lib/               # API client (api.ts), navigation, booking helpers
│   │   └── utils/             # CSRF, security utilities
│   │
│   ├── pages/                 # Route-level pages (HomePage, DashboardPage, NotFoundPage)
│   ├── types/                 # TypeScript interfaces and DTOs
│   ├── utils/                 # Toast, web-vitals
│   └── test/                  # Vitest setup
│
├── tests/                     # Playwright E2E (scaffolded — see tests/e2e/README.md)
├── package.json               # pnpm + scripts
├── vite.config.ts             # Vite config (proxy /api → backend)
└── tsconfig.json              # TypeScript strict
```

---

## 📚 Documentation

Full documentation available in the `docs/` folder:

- [Frontend Architecture](../docs/frontend/ARCHITECTURE.md)
- [App Layer](../docs/frontend/APP_LAYER.md)
- [Features Layer](../docs/frontend/FEATURES_LAYER.md)
- [Testing Guide](../docs/frontend/TESTING.md)
- [Performance & Security](../docs/frontend/PERFORMANCE_SECURITY.md)
- [Deployment Guide](../docs/frontend/DEPLOYMENT.md)

---

## 🧪 Testing

```bash
pnpm test               # vitest run (unit + integration)
pnpm exec vitest        # watch mode
pnpm exec playwright test   # E2E (requires running app — see tests/e2e/README.md)
npx tsc --noEmit        # type-check (pre-push gate)
```

Test counts: see [PROJECT_STATUS.md](../PROJECT_STATUS.md). The pre-push hook runs `npx tsc --noEmit` + `npx vitest run` for any frontend change.

---

**Frontend URL**: http://localhost:5173
**Backend API**: http://127.0.0.1:8000
