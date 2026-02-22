# Testing

> Unit testing with Vitest + Testing Library, E2E scaffolded with Playwright

## Overview

| Layer       | Framework                       | Status     | Files | Tests |
| ----------- | ------------------------------- | ---------- | ----- | ----- |
| Unit/Integ. | Vitest + @testing-library/react | Active     | 11    | 142   |
| E2E         | Playwright                      | Scaffolded | 1     | -     |

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

## 2. Test Files

### Authentication Tests

| File                         | Tests | Coverage                                    |
| ---------------------------- | ----- | ------------------------------------------- |
| `AuthContext.test.tsx`        | 12    | Login, logout, register, error states       |
| `LoginPage.test.tsx`         | 9     | Form rendering, validation, submission      |
| `RegisterPage.test.tsx`      | 16    | Form validation, password rules, submission |

### Booking Tests

| File                            | Tests | Coverage                                 |
| ------------------------------- | ----- | ---------------------------------------- |
| `BookingForm.test.tsx`          | 20    | Form rendering, date logic, submission   |
| `booking.validation.test.ts`   | 21    | Date validation, night calculation       |

### Shared Component Tests

| File                | Tests | Coverage                            |
| ------------------- | ----- | ----------------------------------- |
| `Button.test.tsx`   | 12    | Variants, loading, disabled states  |
| `Input.test.tsx`    | 10    | Types, error display, accessibility |

### Utility Tests

| File                | Tests | Coverage                              |
| ------------------- | ----- | ------------------------------------- |
| `csrf.test.ts`      | 6     | Token get/set/clear, header injection |
| `security.test.ts`  | 22    | XSS sanitization, URL validation      |
| `api.test.ts`       | 6     | Interceptors, error handling, refresh |

### Page Tests

| File                | Tests | Coverage                          |
| ------------------- | ----- | --------------------------------- |
| `HomePage.test.tsx` | 18    | Rendering, navigation, CTA links |

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

### API Mocking with vi.mock

```typescript
vi.mock('@/shared/lib/api', () => ({
  default: {
    post: vi.fn(),
    get: vi.fn(),
  },
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

## 4. E2E Testing (Playwright - Scaffolded)

A basic booking E2E spec exists at `tests/e2e/booking.spec.ts`. Playwright requires a running application instance for execution.

```bash
# Requires backend + frontend running
npx playwright test
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

### Latest Results (February 12, 2026)

```text
Test Files  11 passed (11)
     Tests  142 passed (142)
  Duration  ~13s
```

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
