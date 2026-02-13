# React Component Quality Skill

Use this skill when creating or updating React components in Soleil Hostel.

## When to Use This Skill

- You add or change components in `frontend/src/features/`, `frontend/src/pages/`, or `frontend/src/shared/components/`.
- You modify UI behavior that impacts accessibility, test stability, or render performance.
- You refactor form UIs, loading states, lists, or route-level screens.

## Non-negotiables

- Keep folder boundaries aligned with existing architecture.
  - Route shell in `src/app/`, domain logic in `src/features/`, shared primitives in `src/shared/`.
- Preserve accessibility baseline.
  - Inputs need labels.
  - Error states should use `aria-invalid` and `aria-describedby`.
  - Loading indicators should expose meaningful `role`/labels.
- Keep test selectors intentional.
  - Prefer semantic queries in tests (`getByRole`, `getByLabelText`).
  - Use `data-testid` only where semantic selection is unstable or ambiguous.
- Use stable component APIs.
  - Keep prop names explicit and typed.
  - Avoid unnecessary breaking prop changes across feature boundaries.
- Reuse shared UI primitives when practical (`Input`, `Button`, feedback components).

Component quality patterns to keep:

- Prefer explicit prop interfaces over broad `any` passthroughs.
- Keep component side effects local and predictable.
- Favor controlled inputs for forms that require validation/error UI.
- Keep loading/empty/error states explicit in JSX, not hidden in implicit conditions.

## Implementation Checklist

1. Place component in the correct layer (`features`, `pages`, `shared`).
2. Define strict TypeScript props and default behavior.
3. Add accessibility attributes where needed.
   - Label association (`htmlFor` and `id`).
   - Error association (`aria-invalid`, `aria-describedby`).
4. Ensure loading and error states are explicit.
5. Prefer shared components for consistency.
6. Keep props and markup stable for tests and downstream usage.
7. Add or update tests close to the component.
   - Cover rendering, interactions, and failure states.

## Verification / DoD

```bash
# Frontend quality checks
cd frontend && npx tsc --noEmit
cd frontend && npx vitest run
cd frontend && npm run lint

# Baseline repo gates
cd backend && php artisan test
docker compose config
```

## Common Failure Modes

- Missing labels and aria attributes on interactive fields.
- Overusing `data-testid` and skipping user-facing accessibility queries.
- Re-implementing styles/behavior instead of using existing shared components.
- Silent prop contract changes that break existing feature tests.
- Components rendering but not handling loading/error states predictably.
- Inconsistent class/variant naming that drifts from existing design tokens.
- Missing empty-state UI for list and card components.

## References

- `../../AGENTS.md`
- `../../frontend/src/app/router.tsx`
- `../../frontend/src/features/booking/BookingForm.tsx`
- `../../frontend/src/features/auth/LoginPage.tsx`
- `../../frontend/src/shared/components/ui/Input.tsx`
- `../../frontend/src/shared/components/ui/Button.tsx`
- `../../frontend/src/shared/components/feedback/LoadingSpinner.tsx`
- `../../frontend/src/features/booking/BookingForm.test.tsx`
- `../../frontend/src/features/auth/LoginPage.test.tsx`
