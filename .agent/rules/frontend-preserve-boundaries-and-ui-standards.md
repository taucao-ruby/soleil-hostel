---
verified-against: CLAUDE.md
secondary-source: docs/frontend/SERVICES_LAYER.md
section: "Non-negotiable constraints"
last-verified: 2026-03-25
maintained-by: docs-sync
---

# Frontend Preserve Boundaries And UI Standards

## Purpose
Keep the frontend inside the approved client, import, typing, copy, and validation boundaries that the repo depends on.

## Rule
- Frontend code remains feature-sliced and uses the shared API client in `frontend/src/shared/lib/api.ts`; do not create a second app API client or ad hoc wrapper.
- Shared-client auth transport keeps `withCredentials: true`, the established CSRF flow, and versioned endpoint boundaries; booking APIs stay on `/v1/` and do not fall back to legacy unversioned paths.
- TypeScript strict remains in force: no production `any`, no production `console.log`, and no unsupported library substitutions such as React Query, Zod, or `react-hot-toast`.
- User-facing copy remains Vietnamese.
- Cross-feature import restrictions stay in force, and frontend validation must remain aligned with backend request contracts.
- Repo-established async/testing patterns stay in force: `useState` + `useEffect` + `AbortController` for the standard async flow, and `vi.hoisted()` for shared mutable Vitest mock state.

## Why it exists
These limits prevent transport drift, inconsistent endpoint use, type-safety regressions, untranslated UI, and fragile test/runtime behavior.

## Applies to
Agents, humans, skills, commands, reviews, and tests touching frontend API calls, React components, hooks, validation, or Vitest mocks.

## Violations
- Adding another Axios instance or direct app-API fetch wrapper.
- Removing `withCredentials`, bypassing the shared CSRF flow, or mixing legacy and `/v1/` booking endpoints.
- Introducing `any`, unsupported frontend libraries, or English copy in user-facing UI.
- Importing across features outside the documented exception paths.
- Letting frontend validation drift from backend request rules.

## Enforcement
- Canonical sources: `CLAUDE.md`, `docs/frontend/SERVICES_LAYER.md`, `docs/frontend/APP_LAYER.md`, `docs/frontend/RBAC.md`.
- Validation: `cd frontend && npx tsc --noEmit`, `cd frontend && npx vitest run`.
- Review and runtime checks: `.claude/commands/fix-frontend.md`, `.claude/commands/review-pr.md`, `.claude/hooks/remind-frontend-validation.sh`.

## Linked skills / hooks
- `skills/react/api-client-skill.md`
- `skills/react/typescript-patterns-skill.md`
- `skills/react/forms-validation-skill.md`
- `skills/react/security-frontend-skill.md`
- `.claude/hooks/remind-frontend-validation.sh`
