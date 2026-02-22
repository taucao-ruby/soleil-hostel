# Utils Layer (`src/utils/`)

> Application utilities - toast notifications and performance monitoring

## Overview

```text
src/utils/
├── toast.ts          # Toast notification wrapper (react-toastify)
└── webVitals.ts      # Core Web Vitals monitoring
```

**Note:** The utils layer is minimal. There are no `constants.ts`, `storage.ts`, or `formatters.ts` files. Shared utilities like CSRF and security live in `src/shared/utils/`.

---

## 1. Toast Notifications (`utils/toast.ts`)

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

## 2. Web Vitals (`utils/webVitals.ts`)

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

## 3. What Does NOT Exist

The following files are **not present** despite being in earlier documentation:

- `constants.ts` - No app-wide constants file
- `storage.ts` - No storage utility wrapper
- `formatters.ts` - No date/currency/text formatters
- `cdn.ts` - No CDN utility
- `performanceBudget.ts` - No performance budget checker
