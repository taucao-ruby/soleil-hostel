# Performance & Security

> Implemented performance optimizations and security measures

## 1. Performance

### Code Splitting

Routes are lazy-loaded with `React.lazy` and wrapped in `Suspense`:

```typescript
// src/app/router.tsx
const LoginPage = lazy(() => import('@/features/auth/LoginPage'))
const RoomList = lazy(() => import('@/features/rooms/RoomList'))
const BookingForm = lazy(() => import('@/features/booking/BookingForm'))
const LocationList = lazy(() => import('@/features/locations/LocationList'))
const LocationDetail = lazy(() => import('@/features/locations/LocationDetail'))
```

Only `HomePage` and `NotFoundPage` are eagerly loaded. All other pages are separate chunks.

### Vendor Chunk Separation

```typescript
// vite.config.ts
rollupOptions: {
  output: {
    manualChunks: {
      'react-vendor': ['react', 'react-dom'],
    },
  },
},
```

Only one manual chunk is configured. React core is separated for better caching.

### Build Optimization

- **Target**: `esnext` (modern browsers only)
- **Minification**: Terser with CSS minification enabled
- **Manifest**: Generated for server-side integration

### Loading States

- `LoadingSpinner` component with full-screen overlay mode
- `Skeleton` and `SkeletonCard` components for content placeholders
- Suspense boundaries prevent layout shift during chunk loading

### Web Vitals Monitoring

Tracked via `web-vitals` v5+ in `src/utils/webVitals.ts`:

| Metric | Good     | Poor     |
| ------ | -------- | -------- |
| CLS    | <= 0.1   | >= 0.25  |
| INP    | <= 200ms | >= 500ms |
| FCP    | <= 1.8s  | >= 3.0s  |
| LCP    | <= 2.5s  | >= 4.0s  |
| TTFB   | <= 800ms | >= 1.8s  |

- Development: logs to console
- Production: analytics integration (TODO)

---

## 2. Security

### HttpOnly Cookie Authentication

The primary security feature. Token is stored in an httpOnly cookie that JavaScript cannot access:

```text
Login → Backend sets: Set-Cookie: token=...; HttpOnly; Secure; SameSite=Strict
Requests → Browser sends cookie automatically (withCredentials: true)
Logout → Backend clears cookie (Set-Cookie with Max-Age=0)
```

**Benefits:**

- XSS attacks cannot steal the authentication token
- No token in localStorage/sessionStorage
- Browser handles cookie lifecycle

### CSRF Protection (Double-Submit Pattern)

```text
Login response body → { csrf_token: "..." }
    → Stored in sessionStorage
    → Added to X-XSRF-TOKEN header on POST/PUT/PATCH/DELETE
    → Backend validates header matches session
```

Implementation in `src/shared/utils/csrf.ts` and the request interceptor in `src/shared/lib/api.ts`.

### Automatic Token Refresh

```text
Protected request → 401 Unauthorized
    → Mutex prevents concurrent refreshes
    → POST /auth/refresh-httponly
    → New cookie + csrf_token
    → Retry original request + all queued requests
    → If refresh fails → clear storage, redirect to /login
```

Uses `appNavigate()` for SPA-friendly redirects (no full page reload).

### CSP Nonce Injection

Custom Vite plugin (`vite-plugin-csp-nonce.js`) injects nonces into inline `<script>` and `<style>` tags. Uses `{{ csp_nonce() }}` placeholder for Laravel server-side nonce generation.

### XSS Prevention

`src/shared/utils/security.ts` provides sanitization utilities:

- Input sanitization for user-provided content
- URL validation for safe redirects
- Covered by 22 unit tests

**Note:** The project does NOT use DOMPurify. Sanitization is implemented with custom functions.

### Error Boundary

`src/shared/components/ErrorBoundary.tsx` catches React rendering errors:

- Displays fallback UI with reload button
- Logs errors to console
- Prevents full app crash from component errors

---

## 3. What Is NOT Implemented

The following features from earlier documentation do NOT exist in the codebase:

- **Service Worker / Offline support** - No `sw.js` or PWA configuration
- **CDN integration** - No `cdn.ts` or CDN URL configuration
- **Performance budget checker** - No `performanceBudget.ts`
- **DOMPurify** - Not installed; custom sanitization in `security.ts`
- **Image optimization component** - No `OptimizedImage` component
- **Cache service** - No client-side cache utility (no `src/services/cache.ts`)
- **Zod runtime validation** - Not installed; types are compile-time TypeScript only
- **Google Analytics integration** - gtag calls are commented out in webVitals.ts
