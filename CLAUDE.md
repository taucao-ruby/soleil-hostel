# CLAUDE.md — Soleil Hostel

> Project constitution for Claude Code. Auto-loaded every session.
> Detailed facts live in the files referenced below — not here.

## Project Identity

Monorepo: Laravel 12 REST API + React 19 TypeScript SPA for hostel booking.
Branch model: `feature/<name>` → `dev` → `main` (--no-ff). PRs target `dev`.
Stack: PHP 8.2+, PostgreSQL 16, Redis 7, Vite 6, Vitest 2, TailwindCSS 3.
Infrastructure: Docker Compose. CI: `.github/workflows/`.

## Canonical References (auto-expanded)

@docs/agents/ARCHITECTURE_FACTS.md
@docs/agents/CONTRACT.md

Read on demand (not auto-loaded):
- `docs/agents/COMMANDS.md` — command catalog, setup, dev servers
- `docs/COMPACT.md` — volatile session handoff log
- `skills/README.md` — skill index for task-specific guardrails
- `docs/FINDINGS_BACKLOG.md` — out-of-scope issues log

## Non-Negotiable Invariants (summary)

Full detail in ARCHITECTURE_FACTS.md. Hard constraints:

**Booking domain:**
- Half-open intervals `[check_in, check_out)` everywhere
- Active overlap statuses: `pending`, `confirmed` only
- PostgreSQL EXCLUDE USING gist must filter `deleted_at IS NULL`
- `bookings.location_id` is intentionally denormalized — do not remove
- One review per booking; review must carry `booking_id`

**Concurrency:**
- `lockForUpdate()` required for booking-critical writes
- `lock_version` optimistic locking must not be silently skipped

**Auth / security:**
- Sanctum dual auth (Bearer + HttpOnly cookie) must remain intact
- CSRF: `sessionStorage` csrf_token → `X-XSRF-TOKEN` header — do not bypass
- Token lookup: `token_identifier` → `token_hash`; enforce `revoked_at` + `expires_at`
- Never commit: `APP_KEY`, passwords, tokens, API keys, private keys
- XSS: all user input through `HtmlPurifierService`
- No `env()` in runtime code — use `config()`

**Architecture:**
- Backend: Controller → Service → Repository. No shortcuts.
- Frontend: feature-sliced `src/features/<name>/` with co-located api/types/components/tests
- API calls via `@/shared/lib/api` only — no second Axios instance
- `withCredentials: true` must remain on Axios

## Frontend Rules

- TypeScript strict — zero `any`, zero type errors
- No `console.log` in production code
- No React Query, no Zod, no `react-hot-toast`
- State: `useState` + `useEffect` + `AbortController` pattern
- UI text: Vietnamese strings for all user-facing copy
- Vitest: `vi.hoisted()` for mutable mock state shared with `vi.mock()` factories
- Cross-feature imports forbidden except `bookings/` ↔ `booking/` (same domain)
- `/v1/` prefix for all API calls. Legacy unversioned endpoints sunset July 2026

## Validation Gates

Code tasks — all must pass before commit:
```bash
cd backend && php artisan test          # 0 failures
cd frontend && npx tsc --noEmit         # 0 errors
cd frontend && npx vitest run           # 0 failures
docker compose config                   # valid
```
Docs-only tasks: manual link check only. New behavior → new tests. No exceptions.

## Commit Format (hook-enforced)

```
<type>(<scope>): <subject>
Types:  feat | fix | chore | docs | refactor | test | build | ci | perf | revert
Scopes: backend | frontend | infra | docs   (optional)
Breaking: append ! before colon
```

## Editing Boundaries

Stop and confirm with the user before:
- Modifying `backend/`, `frontend/`, `.github/`, `docker-compose*` when task is docs-only
- Changing booking overlap logic, auth token flow, or migration constraints
- Changing more than 25 files in one pass
- Using `--no-verify` bypass
- Gates producing new failures not in baseline
- A required file not existing at expected path

Out-of-scope bugs: log in `docs/FINDINGS_BACKLOG.md`, do not fix inline.

## File-Specific Rules

- `frontend/src/shared/lib/api.ts` — do not modify CSRF interceptor without reading `docs/frontend/SERVICES_LAYER.md`
- `frontend/src/app/router.tsx` — `/` uses `PublicLayout`; `/booking` and `/dashboard` wrapped in `ProtectedRoute` + `Suspense`; `DashboardPage` lazy-loaded with internal role routing
- `frontend/src/features/booking/booking.api.ts` — `/v1/` prefix only; never add calls to legacy unversioned endpoints
- `docs/COMPACT.md` — volatile handoff log; edit §1 in-place, append history; keep §1 under 12 lines
- `backend/` — request validation in `*Request.php`, not controllers; no `env()` in controllers/services
