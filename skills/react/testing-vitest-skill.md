# React Testing (Vitest) Skill

Use this skill for unit/integration tests in the frontend app.

## When to Use This Skill

- You add or modify React components, hooks, contexts, or utilities.
- You adjust API client/interceptor behavior.
- You need stable tests for auth flows, booking forms, or shared UI primitives.

## Non-negotiables

- Keep test stack aligned with project config:
  - Vitest + Testing Library + jsdom.
  - Setup file: `frontend/src/test/setup.ts`.
- Prefer user-centered assertions.
  - Query by role/label/text before falling back to `data-testid`.
- Keep async interactions properly awaited.
  - Use `userEvent.setup()` and `await` interactions.
  - Use `waitFor` for async UI state transitions.
- Keep mocks explicit and local to the test scope.
  - `vi.mock(...)` for API and utilities.
- Resolve act-related warnings by awaiting UI updates rather than suppressing errors.

Quick async testing pattern:

```ts
const user = userEvent.setup()
await user.click(screen.getByRole('button', { name: /sign in/i }))
await waitFor(() => expect(screen.getByText(/welcome/i)).toBeInTheDocument())
```

## Implementation Checklist

1. Identify behavior contract to test (rendering, interaction, error, success).
2. Write tests near feature/module (`*.test.tsx` or `*.test.ts`).
3. Mock external dependencies (API, routing, browser storage) intentionally.
4. Assert user-visible outcomes, not implementation details.
5. Cover negative paths and edge cases.
6. Run focused tests, then full Vitest run.
7. Re-run with `--runInBand` only when debugging timing issues, not as default.
8. Ensure tests stay deterministic under parallel execution.

## Verification / DoD

```bash
# Focused runs (choose impacted files)
cd frontend && npx vitest run src/features/booking/BookingForm.test.tsx
cd frontend && npx vitest run src/features/auth/AuthContext.test.tsx
cd frontend && npx vitest run src/shared/lib/api.test.ts

# Full frontend checks
cd frontend && npx vitest run
cd frontend && npx tsc --noEmit
cd frontend && npm run lint

# Baseline repo gates
cd backend && php artisan test
docker compose config
```

## Common Failure Modes

- Tests tied to implementation details instead of UI behavior.
- Missing `await` for user interactions causing flaky async assertions.
- Unstable global mocks leaking across test cases.
- Overusing `data-testid` when semantic queries are available.
- Ignoring act warnings until they become flaky failures.
- Tests that pass only in watch mode due implicit state leakage.
- Missing assertions for visible error states on failed async actions.

## References

- `../../AGENTS.md`
- `../../frontend/vite.config.ts`
- `../../frontend/src/test/setup.ts`
- `../../frontend/src/features/auth/AuthContext.test.tsx`
- `../../frontend/src/features/booking/BookingForm.test.tsx`
- `../../frontend/src/shared/lib/api.test.ts`
- `../../docs/frontend/TESTING.md`
