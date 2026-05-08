# Testing

> Unit testing with Vitest + Testing Library, E2E scaffolded with Playwright (4 flows, workflow_dispatch-gated)
>
> **Last Updated:** May 8, 2026

## Overview

| Layer       | Framework                       | Status     | Files | Tests |
| ----------- | ------------------------------- | ---------- | ----- | ----- |
| Unit/Integ. | Vitest ^4.1 + @testing-library/react | Active     | 39    | see [PROJECT_STATUS.md](../../PROJECT_STATUS.md) |
| E2E         | Playwright                      | Scaffolded | 4 flows | gated |

> Per-suite test counts moved to [PROJECT_STATUS.md](../../PROJECT_STATUS.md) as the single source of truth. Mar 31 baseline 261 / 25 files; May 3 intermediate 418; current file count 39 — re-verification required.

## 1. Vitest Configuration

Test configuration is embedded in `vite.config.ts`:

```typescript
// vite.config.ts
test: {
  globals: true,
  environment: 'jsdom',
  setupFiles: './src/test/setup.ts',
  exclude: ['**/tests/e2e/**', '**/node_modules/**'],
}
```

### Test Setup (`src/test/setup.ts`)

```typescript
import '@testing-library/jest-dom'
```

Imports Testing Library matchers (toBeInTheDocument, toHaveTextContent, etc.) globally.

### Scripts

```bash
pnpm test             # Run all unit tests once
pnpm test:unit        # Alias for test
pnpm test:watch       # Watch mode for development
```

---

## 2. Test Areas (39 files as of HEAD `6372d7f`)

> Per-file test counts moved to [PROJECT_STATUS.md](../../PROJECT_STATUS.md). The areas below describe **what** is covered, not how many tests each file holds.

### Authentication
- `AdminRoute.test.tsx` — admin-only guard, `minRole` prop, redirect targets
- `AuthContext.test.tsx` — login/logout/register, CSRF flow, error states
- `LoginPage.test.tsx`, `RegisterPage.test.tsx` — form validation, password complexity, submission

### Booking
- `BookingDetailPanel.test.tsx` — detail panel states, retry, keyboard/backdrop close, ReviewForm integration for confirmed past bookings
- `BookingDetailPage.test.tsx` — page-level guest detail
- `BookingForm.test.tsx` — form rendering, room dropdown, URL params pre-fill
- `booking.validation.test.ts` — date validation, night calculation
- `bookingViewModel.test.ts` — `toBookingViewModel`, `isUpcoming`, `isPast`, `canCancel`
- `BookingList.test.tsx` — `/my-bookings` paginated list

### Bookings (guest dashboard)
- `GuestDashboard.test.tsx` — loading skeleton, empty state, filter tabs (Tất cả / Sắp tới / Đã qua)
- `ReviewForm.test.tsx` — star-rating submission, 403 (already-reviewed) and 422 (validation) handling

### Admin
- `AdminLayout.test.tsx` — shell + sidebar + outlet
- `AdminSidebar.test.tsx` — desktop sidebar, hamburger trigger, slide-over drawer
- `AdminDashboard.test.tsx` — legacy 3-tab view (Bookings / Trashed / Contacts)
- `DashboardPage.test.tsx` — role-based routing (admin → AdminDashboard, else GuestDashboard)
- `bookings/AdminBookingDashboard.test.tsx` — 7 server-side filters
- `bookings/TodayOperations.test.tsx` — today's arrivals/departures view

### Home
- `HomePage.test.tsx` — hero + search + tabs + room cards
- `HeaderMobile.test.tsx` — auth states, hamburger drawer
- `SearchCard.test.tsx` — location dropdown, navigation on submit
- `FilterChips.test.tsx` — chip rendering, active state

### Locations
- `LocationList.test.tsx` — grid + city filter
- `LocationDetail.test.tsx` — boutique hero + reviews + availability search
- `LocationsNav.test.tsx` — nav link rendering

### Rooms
- `RoomList.test.tsx` — loading skeleton, status badges, empty state
- `admin/AdminRoomDashboard.test.tsx` — location filter, table interactions
- `admin/RoomForm.test.tsx` — create/edit form

### Assistant (AI proposal-confirmation)
- `RoomDiscoveryWidget.test.tsx` — natural-language query, proposal-shown POST, decide POST, error mapping for `ProposalNotShownException` / `ProposalExpiredException` / `ProposalPriceChangedException` / `ProposedRoomNoLongerAvailableException`

### Shared / UI primitives
- `Button.test.tsx`, `Input.test.tsx`, `StatusBadge.test.tsx`

### Shared lib + utils
- `lib/api.test.ts` — interceptors, 401 refresh + retry queue, CSRF header injection
- `lib/booking.utils.test.ts` — `getStatusConfig`, `formatDateVN`, `formatDateRangeVN`
- `utils/csrf.test.ts` — token get/set/clear, null safety
- `utils/security.test.ts` — XSS sanitisation, URL validation

---

## 3. Testing Patterns

### Component Testing with Testing Library

```typescript
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { describe, it, expect, vi } from 'vitest'

describe('LoginPage', () => {
  it('renders the login form', () => {
    render(<LoginPage />, { wrapper: TestProviders })
    expect(screen.getByLabelText(/email/i)).toBeInTheDocument()
    expect(screen.getByLabelText(/password/i)).toBeInTheDocument()
  })

  it('shows validation error for invalid email', async () => {
    render(<LoginPage />, { wrapper: TestProviders })
    fireEvent.change(screen.getByLabelText(/email/i), { target: { value: 'invalid' } })
    fireEvent.click(screen.getByRole('button', { name: /sign in/i }))
    await waitFor(() => {
      expect(screen.getByText(/valid email/i)).toBeInTheDocument()
    })
  })
})
```

### API Mocking with `vi.mock` + `vi.hoisted` (Vitest 2.x+ pattern, mandatory in 4.x)

In Vitest 2.x and later, module-level `let` variables captured by `vi.mock` factories cause a jsdom env failure (the factory is hoisted above your `let`, so it sees `undefined`). Use `vi.hoisted` for any **mutable mock state** that needs to be shared between the factory and the test body.

```typescript
// ✅ Correct — vi.hoisted gives the factory and the test body the same reference
const { mockGet, mockPost, csrfRef } = vi.hoisted(() => ({
  mockGet: vi.fn(),
  mockPost: vi.fn(),
  csrfRef: { current: 'initial-csrf' as string | null },
}))

vi.mock('@/shared/lib/api', () => ({
  default: { get: mockGet, post: mockPost },
}))

vi.mock('@/shared/utils/csrf', () => ({
  getCsrfToken: () => csrfRef.current,
  setCsrfToken: (t: string) => { csrfRef.current = t },
  clearCsrfToken: () => { csrfRef.current = null },
}))

beforeEach(() => {
  mockGet.mockReset()
  mockPost.mockReset()
  csrfRef.current = 'initial-csrf'
})
```

```typescript
// ❌ Anti-pattern — module-level `let` is NOT hoisted; the factory captures undefined
let csrf: string | null = 'initial'
vi.mock('@/shared/utils/csrf', () => ({
  getCsrfToken: () => csrf,   // jsdom env failure under Vitest 2.x+
}))
```

For pure mocks that don't need to mutate, the simple form is still fine:

```typescript
vi.mock('@/shared/lib/api', () => ({
  default: { post: vi.fn(), get: vi.fn() },
}))
```

### Validation Function Testing

```typescript
import { getMinCheckInDate, calculateNights } from './booking.validation'

describe('booking validation', () => {
  it('calculates nights correctly', () => {
    expect(calculateNights('2026-01-01', '2026-01-05')).toBe(4)
  })
})
```

---

## 4. E2E Testing (Playwright — 4 scaffolded flows)

Four user-visible flows live under [`frontend/tests/e2e/`](../../frontend/tests/e2e/README.md), each using the page object model so locators and step semantics are reused across tests:

| Spec | Flow |
|---|---|
| `flows/guest-booking.spec.ts` | Land on availability → select room → complete booking form → confirmation |
| `flows/payment-webhook.spec.ts` | Trigger Stripe `payment_intent.succeeded` → admin dashboard shows `confirmed` |
| `flows/ai-proposal.spec.ts` | Open RoomDiscoveryWidget → submit NL query → confirm proposal → booking created |
| `flows/admin-restore.spec.ts` | Admin soft-deletes booking → trashed list → restore → reappears in active list |

Stability discipline:
- Each `*.spec.ts` is self-contained — no shared state. Playwright runs files in parallel by default.
- No external network: Stripe webhooks are simulated by hitting our own `/api/webhooks/stripe` endpoint with a signed test payload.
- Selectors prefer `getByRole` / `getByTestId` over CSS.

CI: flows are gated behind `workflow_dispatch` until the suite stabilises (per [`tests/e2e/README.md`](../../frontend/tests/e2e/README.md)). Promote to `pull_request` trigger after two consecutive green merges.

```bash
# Local run
cd frontend
pnpm exec playwright install --with-deps  # first time only
pnpm exec playwright test                  # all flows
pnpm exec playwright test flows/guest-booking
pnpm exec playwright test --ui             # interactive
```

---

## 5. Running Tests

```bash
# Run all unit/integration tests
cd frontend && npx vitest run

# Watch mode (re-runs on file changes)
cd frontend && npx vitest

# Run specific test file
cd frontend && npx vitest run src/features/auth/LoginPage.test.tsx

# With coverage
cd frontend && npx vitest run --coverage
```

### Latest Results

See [PROJECT_STATUS.md](../../PROJECT_STATUS.md) for the canonical baseline. May 3 intermediate run reported 418 tests PASS at HEAD `b69a7a0`. Re-verification required after the May 5–8 maintenance batch (no test count regression expected — `axios` bump and Redis-free `AiHarnessDisabledTest` are backend-side).

---

## 6. Test Dependencies

| Package                        | Purpose                     |
| ------------------------------ | --------------------------- |
| `vitest`                       | Test runner                 |
| `@vitest/ui`                   | Browser-based test UI       |
| `@vitest/coverage-v8`          | Code coverage               |
| `@testing-library/react`       | Component testing utilities |
| `@testing-library/jest-dom`    | Custom DOM matchers         |
| `@testing-library/user-event`  | User interaction simulation |
| `jsdom`                        | DOM environment for tests   |
