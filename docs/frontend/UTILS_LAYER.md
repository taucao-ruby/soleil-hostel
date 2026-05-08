# Utils Layer (`src/shared/utils/`)

> Application utilities — toast notifications, performance monitoring, CSRF, security
>
> **Last Updated:** May 8, 2026

## Overview

All utilities live under `src/shared/utils/`. **There is no top-level `src/utils/` directory** — earlier docs that placed `toast.ts` and `webVitals.ts` at `src/utils/` are stale.

```text
src/shared/utils/
├── csrf.ts           # CSRF token get/set/clear (sessionStorage-backed)
├── security.ts       # XSS sanitization, URL validation, isValidEmail
├── toast.ts          # Toast notification wrapper (react-toastify)
└── webVitals.ts      # Core Web Vitals monitoring
```

> Adjacent — utility-flavoured helpers that ship via `src/shared/lib/` (so consumers can import from a single `lib/` path alongside the API client): `booking.utils.ts` (status config + Vietnamese date formatters), `formatCurrency.ts`, `navigation.ts`. Test files (`csrf.test.ts`, `security.test.ts`, `booking.utils.test.ts`) live next to their source.

**Not present** (despite earlier docs that placed them under `src/utils/`): no top-level `src/utils/` folder, no `constants.ts`, no `storage.ts`, no `formatters.ts`, no `cdn.ts`, no `performanceBudget.ts`.

---

## 1. Toast Notifications (`src/shared/utils/toast.ts`)

Wrapper around `react-toastify` for consistent notification styling.

### Exports

```typescript
// Show notifications
export const showToast = {
  success: (message: string, options?: ToastOptions) => void
  error: (message: string, options?: ToastOptions) => void
  warning: (message: string, options?: ToastOptions) => void
  info: (message: string, options?: ToastOptions) => void
  promise: <T>(promise: Promise<T>, messages: { pending, success, error }) => void
}

// Root-level container component
export function ToastContainer(): React.ReactElement

// Error message extraction helper
export function getErrorMessage(error: unknown): string
```

### Configuration

- Position: top-right
- Auto-close: 5000ms (errors: 7000ms)
- Draggable, pauseOnHover, closeOnClick
- Custom class names for color-coded variants

### Error Message Helper

`getErrorMessage()` handles multiple error shapes:

- Plain strings
- Axios errors with `response.data.message`
- Laravel validation errors (`response.data.errors`)
- Standard Error objects
- Fallback: "An unexpected error occurred"

---

## 2. Web Vitals (`src/shared/utils/webVitals.ts`)

Core Web Vitals monitoring using the `web-vitals` library (v5+).

### Initialization

```typescript
export function initWebVitals(): void {
  onCLS(sendToAnalytics)
  onINP(sendToAnalytics)  // Replaces FID in web-vitals v3+
  onFCP(sendToAnalytics)
  onLCP(sendToAnalytics)
  onTTFB(sendToAnalytics)
}
```

Called once from `main.tsx` at application startup.

### Metrics Tracked

| Metric | Full Name                    | Good Threshold |
| ------ | ---------------------------- | -------------- |
| CLS    | Cumulative Layout Shift      | <= 0.1         |
| INP    | Interaction to Next Paint    | <= 200ms       |
| FCP    | First Contentful Paint       | <= 1800ms      |
| LCP    | Largest Contentful Paint     | <= 2500ms      |
| TTFB   | Time to First Byte           | <= 800ms       |

### Rating Helper

```typescript
export function getPerformanceRating(
  metricName: string,
  value: number
): 'good' | 'needs-improvement' | 'poor'
```

### Current Behavior

- **Development**: Logs metrics to console
- **Production**: Analytics service integration (Planned, Issue: TBD-ANALYTICS-01 - currently commented out)

### Thresholds

```typescript
export const WEB_VITALS_THRESHOLDS = {
  CLS: { good: 0.1, poor: 0.25 },
  INP: { good: 200, poor: 500 },
  FCP: { good: 1800, poor: 3000 },
  LCP: { good: 2500, poor: 4000 },
  TTFB: { good: 800, poor: 1800 },
}
```

---

## 3. CSRF Token Helpers (`src/shared/utils/csrf.ts`)

```typescript
export function getCsrfToken(): string | null    // sessionStorage.getItem('csrf_token')
export function setCsrfToken(token: string): void
export function clearCsrfToken(): void
```

- Stored in `sessionStorage` (cleared on browser close, isolated per tab where applicable)
- Set after successful login/refresh, cleared on logout
- Added to `X-XSRF-TOKEN` header by the request interceptor in `shared/lib/api.ts`
- Tested in `csrf.test.ts` (6 tests covering get/set/clear/header injection/null safety)

---

## 4. Security Helpers (`src/shared/utils/security.ts`)

```typescript
export function isValidEmail(email: string): boolean        // RFC-5322-flavoured regex
export function sanitizeHtml(input: string): string          // strips tags/scripts; not a full XSS shield
export function isValidUrl(url: string): boolean             // protocol allowlist
export function escapeHtml(input: string): string
```

> `sanitizeHtml` is **not** a substitute for backend HTML Purifier sanitisation. The frontend treats all user-generated content from the API as already-sanitised; these helpers are for forms and URL/redirect validation only.

Tested in `security.test.ts` (22 tests covering XSS payload classes, protocol smuggling, and unicode edge cases).

---

## 5. What Does NOT Exist

The following files are **not present** despite being in earlier documentation:

- `src/utils/` — there is no top-level `utils` directory
- `constants.ts` — no app-wide constants file
- `storage.ts` — no storage utility wrapper
- `formatters.ts` — no date/currency/text formatters at this level (date formatters live in `src/shared/lib/booking.utils.ts`; currency in `formatCurrency.ts`)
- `cdn.ts` — no CDN utility
- `performanceBudget.ts` — no performance budget checker (Web Vitals only)
- `react-hot-toast` — not installed; only `react-toastify`
- DOMPurify / xss libraries — `security.ts` is the only frontend sanitisation surface and it is intentionally narrow
