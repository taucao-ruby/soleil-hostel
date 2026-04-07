# Frontend Reviewer — Subagent Memory

Role-scoped memory for API contract usage, RBAC UX, TypeScript strictness, test reliability.

## Stable Memory

### Frontend Role Checks Are UX Only
- Frontend route guards and role-based rendering are convenience — not security boundaries
- Backend middleware + policies are the RBAC authority
- Admin/moderator UI surfaces can drift from backend permissions after endpoint changes
  - Source: `.agent/rules/frontend-preserve-boundaries-and-ui-standards.md`, `docs/PERMISSION_MATRIX.md`

### API Client Contract
- Single shared client: `frontend/src/shared/lib/api.ts`
- `withCredentials: true` must remain — removing it breaks cookie auth
- All endpoints use `/v1/` prefix — no legacy unversioned paths
- CSRF: `sessionStorage` → `X-XSRF-TOKEN` header
- No second Axios instance allowed
  - Source: `.agent/rules/frontend-preserve-boundaries-and-ui-standards.md`, `skills/react/api-client-skill.md`

### TypeScript Strictness
- Zero `any` in production code — use `unknown` + narrowing or explicit types
- No `console.log` in production code
- No React Query, Zod, or `react-hot-toast`
- Data-fetch pattern: `useState + useEffect + AbortController`
  - Source: `.claude/agents/frontend-reviewer.md`, `skills/react/typescript-patterns-skill.md`

### Test Patterns
- `vi.hoisted()` required for shared mutable mock state in `vi.mock()` factories
- Module-level `let` is NOT captured by `vi.mock()` factories
- Async interactions: `userEvent.setup()` + `waitFor`
  - Source: `skills/react/testing-vitest-skill.md`

### UI Language
- All user-facing copy must be Vietnamese
  - Source: `.agent/rules/frontend-preserve-boundaries-and-ui-standards.md`

### Known Drift (from PERMISSION_MATRIX.md)
- FU-5: Room CUD auth tests still target legacy `/api/rooms/*` paths
- Admin/moderator UI conditional rendering must match Tier 5 (Table F) in PERMISSION_MATRIX.md

## Learned Patterns

- Build/test fixes must validate: option rendering, loading state transitions, API error states — not just "it compiles"
- Booking availability UI must align with backend half-open interval semantics `[check_in, check_out)`
- Cross-feature import boundaries: only `bookings/` ↔ `booking/` exception is allowed
- Frontend gating is never a security boundary — escalate to security-reviewer if auth surface is modified

## Revalidation Notes

- After backend endpoint changes: verify frontend API calls match new paths and contracts
- After PERMISSION_MATRIX.md updates: verify frontend role-based rendering matches new permissions
- After `api.ts` changes: trigger security-reviewer handoff per `docs/agents/api-handoff-protocol.md`
- After availability UI changes: verify alignment with backend overlap semantics
