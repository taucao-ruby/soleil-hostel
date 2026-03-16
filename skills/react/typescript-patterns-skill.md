# React TypeScript Patterns Skill

Use this skill when writing or reviewing React + TypeScript code in the frontend — components, hooks, API calls, state management, or Vitest tests.

## When to Use This Skill

- You add or modify components, hooks, or utilities.
- You write Vitest tests that require shared mock state.
- You wire up API calls or AbortController cancellation.
- You encounter TypeScript strict errors or `any` usage.

## Non-negotiables

- **Zero `any`** — TypeScript strict is enforced; use `unknown` + narrowing or explicit types.
- **No `console.log`** in production code — use test-only logging or remove before commit.
- **No React Query, no Zod, no `react-hot-toast`** — project prohibits these libraries.
- **State pattern**: `useState` + `useEffect` + `AbortController` — do not introduce a different async model.
- **API calls**: `@/shared/lib/api` only — never create a second Axios instance.
- **`withCredentials: true`** must remain on the shared Axios instance.
- **Cross-feature imports**: forbidden except `bookings/` ↔ `booking/` (same domain).
- **UI text**: Vietnamese strings for all user-facing copy.

## State + Effect Pattern

Standard data-fetch hook shape:

```ts
const [data, setData] = useState<MyType | null>(null)
const [loading, setLoading] = useState(false)
const [error, setError] = useState<string | null>(null)

useEffect(() => {
  const controller = new AbortController()
  setLoading(true)
  api.get<MyType>('/v1/resource', { signal: controller.signal })
    .then(res => setData(res.data))
    .catch(err => { if (err.name !== 'CanceledError') setError(err.message) })
    .finally(() => setLoading(false))
  return () => controller.abort()
}, [dependency])
```

## Vitest Mock Pattern (`vi.hoisted`)

Use `vi.hoisted()` for mutable mock state shared with `vi.mock()` factories.
Module-level `let` variables captured by `vi.mock` factories fail in Vitest 2.x jsdom env.

```ts
const { mockNavigate, mockAuthRef } = vi.hoisted(() => ({
  mockNavigate: vi.fn(),
  mockAuthRef: { current: { user: null, logout: vi.fn() } },
}))

vi.mock('react-router-dom', () => ({ useNavigate: () => mockNavigate }))
vi.mock('@/features/auth/AuthContext', () => ({
  useAuth: () => mockAuthRef.current,
}))
```

## Feature Structure

```
src/features/<name>/
  api/          ← API calls using @/shared/lib/api
  types/        ← TypeScript types and interfaces
  components/   ← React components
  tests/        ← co-located test files (*.test.ts / *.test.tsx)
```

## Verification / DoD

```bash
cd frontend && npx tsc --noEmit     # 0 errors
cd frontend && npx vitest run        # 0 failures
```

## Common Failure Modes

- Using `any` to silence TypeScript instead of narrowing the type.
- Capturing a `let` variable in a `vi.mock()` factory (use `vi.hoisted()` instead).
- Forgetting to abort the fetch on `useEffect` cleanup — causes state-update-on-unmounted-component warnings.
- Importing from a sibling feature outside the `bookings/` ↔ `booking/` exception.
- Creating a second Axios instance instead of using `@/shared/lib/api`.

## References

- `frontend/src/shared/lib/api.ts` — shared Axios instance (do not add a second one)
- `frontend/src/features/` — feature-sliced structure examples
- `frontend/src/test/setup.ts` — Vitest global setup
- `skills/react/testing-vitest-skill.md` — async testing patterns
