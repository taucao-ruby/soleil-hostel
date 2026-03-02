# CLAUDE.md — Soleil Hostel

> **Claude Code CLI master context file.**
> Read this first. Then read the files linked in each section before writing any code or docs.

---

## 1. Project Overview

**What this is:** Monorepo for a hostel booking platform — Laravel REST API backend + React TypeScript SPA frontend.

**Verified stack:**

| Layer          | Technology                                                         |
| -------------- | ------------------------------------------------------------------ |
| Backend        | Laravel 12 (`^12.0`), PHP `^8.2`, Sanctum + custom token columns   |
| Frontend       | React 19, TypeScript `~5.7`, Vite 6, Vitest 2, TailwindCSS 3       |
| Database       | PostgreSQL 16 (production), SQLite in-memory (tests)               |
| Cache / Queue  | Redis 7                                                            |
| Infrastructure | Docker Compose (`docker-compose.yml`); CI via `.github/workflows/` |

**Branching convention:**

```
feature/<name>  →  dev  →  main (--no-ff merge)
```

- All PRs target `dev`. Human review required before merge to `main`.
- Commit format enforced by hook — see §3.

**Repo health baseline (verified 2026-03-02):**

| Gate                                   | Result                        |
| -------------------------------------- | ----------------------------- |
| `cd backend && php artisan test`       | ✅ 857 tests, 2430 assertions |
| `cd frontend && npx tsc --noEmit`      | ✅ 0 errors                   |
| `cd frontend && npx vitest run`        | ✅ 226 tests, 21 files        |
| `docker compose config`                | ✅ valid                      |
| `cd backend && vendor/bin/pint --test` | ✅ 275 files, 0 violations    |

**Key directories (verified):**

```
./backend/          Laravel API — controllers, services, repositories, migrations, tests
./frontend/         React SPA — src/app/, src/features/, src/pages/, src/shared/, src/utils/
./docs/             Architecture, guides, operations docs
./skills/           Task-specific guardrails for AI agents (13 skill files)
./mcp/soleil-mcp/   Local MCP server (read-only repo access + allowlisted verify commands)
./AGENTS.md         Root onboarding — read before anything else
```

---

## 2. Non-Negotiable Standards

### Frontend (verified against codebase)

- **TypeScript strict** — zero `any`, zero type errors (`tsc --noEmit` must pass)
- **No `console.log`** in production code — pre-commit hook catches this
- **No secrets or credentials** in any committed file
- **Feature-sliced architecture** — each feature in `src/features/<name>/` owns its `*.api.ts`, `*.types.ts`, components, and tests. Do not import across features except `bookings/` ↔ `booking/` (same domain)
- **API calls** — all features import from `@/shared/lib/api` (Axios instance with CSRF + refresh interceptors). Never create a second Axios instance
- **No React Query, no Zod, no `react-hot-toast`** — not installed; do not add without explicit approval
- **State management** — use `useState + useEffect + AbortController` pattern (see `useMyBookings.ts` for reference)
- **UI text** — Vietnamese strings for all user-facing copy
- **Vitest mocking** — use `vi.hoisted()` for any mutable variable captured by a `vi.mock()` factory. Never use module-level `let` before `vi.mock` (causes jsdom env failure in Vitest 2.x)

### Backend (do not reinvent — link to canonical docs)

See [`docs/agents/ARCHITECTURE_FACTS.md`](./docs/agents/ARCHITECTURE_FACTS.md) for verified invariants:

- Booking overlap: half-open `[check_in, check_out)` + PostgreSQL exclusion constraint
- Auth: dual-mode Bearer + HttpOnly cookie; 8 custom token columns; rotation/revocation enforced
- Concurrency: `lock_version` optimistic (rooms/locations) + `lockForUpdate()` pessimistic (booking flows)
- No `env()` in runtime logic — use `config()` and config files only

### Security (verified)

- **HttpOnly cookie auth**: `withCredentials: true` on Axios; cookie flags must stay intact
- **CSRF**: `csrf_token` stored in `sessionStorage`; sent as `X-XSRF-TOKEN` header on non-GET requests by the shared interceptor in `api.ts` — do not bypass
- **Token lookup**: `token_identifier` → `token_hash` DB lookup; must enforce `revoked_at` and `expires_at`
- **Never commit**: `APP_KEY`, Redis passwords, tokens, API keys, private keys

### Testing (required gates from CONTRACT + HOOKS)

Docs-only task — run manual link check only (no app code = no test gates required per CONTRACT §DoD:Documentation).

Code task — all four gates must pass before commit:

```bash
cd backend && php artisan test          # 0 failures
cd frontend && npx tsc --noEmit         # 0 errors
cd frontend && npx vitest run           # 0 failures
docker compose config                   # valid
```

New behavior → new tests. No exceptions.

---

## 3. Workflow Protocol

Run this checklist **before writing any code or docs:**

```
[ ] 1. Read AGENTS.md (rules + hard boundaries)
[ ] 2. Read docs/agents/CONTRACT.md (Definition of Done for this task type)
[ ] 3. Read docs/agents/ARCHITECTURE_FACTS.md (invariants to preserve)
[ ] 4. Read docs/COMPACT.md (current branch/test baseline + active work)
[ ] 5. Select 1–3 skills from skills/ (see skill selection table below)
[ ] 6. Discover all file paths via MCP read_file/search — never assume a path exists
[ ] 7. State implementation plan before writing anything
[ ] 8. Execute — keep diffs small and scoped (≤25 files per pass)
[ ] 9. Run required gates (see §2)
[ ] 10. Update docs/COMPACT.md (what changed, files touched, gate results)
[ ] 11. Commit with Conventional Commits format (enforced by hook)
[ ] 12. Human review before merge to main
```

**Commit format** (hook-enforced — violations block commit):

```
<type>(<scope>): <subject>

Types:  feat | fix | chore | docs | refactor | test | build | ci | perf | revert
Scopes: backend | frontend | infra | docs   (optional)
Breaking: append ! before colon

Examples:
  feat(frontend): add booking date guard
  fix(backend)!: tighten token revocation checks
  docs: sync frontend test counts
```

**Scope boundaries (from CONTRACT):**

- Found a bug outside task scope? Log it in [`docs/FINDINGS_BACKLOG.md`](./docs/FINDINGS_BACKLOG.md) — do not fix inline.
- Max 25 files per pass. If more needed, split into batches.

---

## 4. AI Governance

Full governance framework: [`docs/AI_GOVERNANCE.md`](./docs/AI_GOVERNANCE.md)

**Evidence-first rule:** Every claim about the repo must be verified via MCP `read_file` or `search`. Never state a file path, line number, or behavior without reading the source.

**No guessing paths:** Use MCP `repo_overview` → `search` → `read_file`. If a path is uncertain, search for it. Paths that "should exist" often don't.

**No arbitrary commands:** MCP `run_verify` is limited to the allowlist in [`docs/MCP.md`](./docs/MCP.md). Do not execute commands outside the allowlist.

**Out-of-scope findings:** Do not fix. Log to [`docs/FINDINGS_BACKLOG.md`](./docs/FINDINGS_BACKLOG.md) with: `| ID | File:Line | Issue | Severity | Suggested Fix | Status |`

**COMPACT discipline:** After every non-trivial task, update [`docs/COMPACT.md`](./docs/COMPACT.md) — section 1 (snapshot), section 3 (active work), append to WORKLOG.

**High-risk areas — read the runbook before touching:**

| Area                         | Must-read before changing                                                                      |
| ---------------------------- | ---------------------------------------------------------------------------------------------- |
| Booking overlap / date logic | `docs/DB_FACTS.md` + `skills/laravel/booking-overlap-skill.md`                                 |
| Auth / token / cookie flow   | `docs/backend/features/AUTHENTICATION.md` + `skills/laravel/auth-tokens-skill.md`              |
| Migrations / constraints     | `docs/DB_FACTS.md` + `skills/laravel/migrations-postgres-skill.md`                             |
| Concurrency / locking        | `docs/backend/features/OPTIMISTIC_LOCKING.md` + `skills/laravel/transactions-locking-skill.md` |

---

## 5. File-Specific Rules

### `src/features/booking/booking.api.ts`

- Endpoints use `/v1/` prefix: `POST /v1/bookings`, `GET /v1/bookings`, `POST /v1/bookings/:id/cancel`
- Legacy `/bookings` (no version) is deprecated — sunset July 2026. Never add new calls to it.

### `src/features/rooms/room.api.ts`

- Endpoint: `GET /v1/rooms`. Legacy `/rooms` is deprecated — same sunset.

### `src/shared/lib/api.ts`

- Do not modify the CSRF interceptor or refresh mutex without reading `docs/frontend/SERVICES_LAYER.md` first.
- `withCredentials: true` must remain set.

### `src/app/router.tsx`

- `/` route uses `PublicLayout` (HeaderMobile + BottomNav). All other routes use `Layout` (dark Header + Footer).
- `/booking` and `/dashboard` are wrapped in `ProtectedRoute` + `Suspense`.
- `DashboardPage` is lazy-loaded; role-based routing (`user.role === 'admin'`) happens inside it.

### `docs/COMPACT.md`

- Append-compatible. Edit sections 1 and 3 in-place; add new dated block at bottom for history.
- Keep section 1 under 12 lines. No secrets. Short lines only.

### `backend/` (general)

- Controllers → thin HTTP layer only. Logic in Services. Data in Repositories.
- Request validation in `*Request.php` classes, not controllers.
- No `env()` in controllers/services — use `config()`.

---

## 6. Quickstart for Claude Code CLI

### Skill Selection (pick 1–3 per task)

| Task type               | Skills to load                                                                          |
| ----------------------- | --------------------------------------------------------------------------------------- |
| React component         | `skills/react/component-quality-skill.md` + `skills/react/testing-vitest-skill.md`      |
| React form / booking UI | + `skills/react/forms-validation-skill.md`                                              |
| API client wiring       | `skills/react/api-client-skill.md`                                                      |
| Backend API endpoint    | `skills/laravel/api-endpoints-skill.md` + `skills/laravel/testing-skill.md`             |
| Booking domain logic    | + `skills/laravel/booking-overlap-skill.md`                                             |
| Auth / token changes    | + `skills/laravel/auth-tokens-skill.md`                                                 |
| Migration / schema      | `skills/laravel/migrations-postgres-skill.md`                                           |
| Security                | `skills/laravel/security-secrets-skill.md` or `skills/react/security-frontend-skill.md` |
| CI / Docker             | `skills/ops/ci-quality-gates-skill.md` + `skills/ops/docker-compose-skill.md`           |

Full skill index and canonical task prompt template: [`skills/README.md`](./skills/README.md)

### Task Prompt Template

```text
You are working in the Soleil Hostel monorepo.
Read CLAUDE.md first, then AGENTS.md, then the skills listed below.

Task:
<describe the task and expected output>

Skills:
- skills/<area>/<skill-file>.md
- skills/<area>/<skill-file>.md

Constraints:
- Keep architecture boundaries intact (feature-sliced frontend; Controller→Service→Repository backend).
- Do not break booking overlap / auth token / locking invariants.
- Keep all 4 CI gates green (artisan test, tsc, vitest run, docker compose config).
- Diffs ≤ 25 files. Log out-of-scope issues to docs/FINDINGS_BACKLOG.md.

Deliverables:
- Summary of changes and files touched
- Gate commands run and results
- COMPACT update applied
- Residual risk (if any)
```

### Example Task Prompts

**1. Add a new React feature component:**

```text
Task: Add a LocationReviews component to src/features/locations/ that fetches
and displays reviews for a location slug via GET /v1/locations/:slug/reviews.
Skills: skills/react/component-quality-skill.md, skills/react/api-client-skill.md,
        skills/react/testing-vitest-skill.md
```

**2. Fix a backend validation bug:**

```text
Task: The BookingRequest does not validate that check_out > check_in at the HTTP
layer. Add this validation to backend/app/Http/Requests/BookingRequest.php and
add a test in the existing BookingTest.
Skills: skills/laravel/api-endpoints-skill.md, skills/laravel/testing-skill.md,
        skills/laravel/booking-overlap-skill.md
```

**3. Docs-only update:**

```text
Task: Sync docs/frontend/FEATURES_LAYER.md to reflect the new admin/ feature.
No app code changes. Update docs/COMPACT.md when done.
Skills: (none — docs task)
Constraints: docs/agents/CONTRACT.md §DoD:Documentation applies.
```

### MCP Connection (Claude Desktop / Cursor)

MCP server lives at `mcp/soleil-mcp/`. See [`docs/MCP.md`](./docs/MCP.md) for full setup.

```bash
# Build once
cd mcp/soleil-mcp && npm install && npm run build

# Claude Desktop config snippet (Windows)
{
  "command": "node",
  "args": ["C:\\Users\\Admin\\myProject\\soleil-hostel\\mcp\\soleil-mcp\\dist\\index.js"]
}
```

MCP provides: `repo_overview`, `read_file`, `search`, `run_verify`, `project_invariants` — all read/verify-only.

### When to STOP and Ask

Stop and confirm with the user before proceeding if:

- The task would modify files in `backend/`, `frontend/`, `.github/`, `docker-compose*`, or `scripts/` when the task was stated as docs-only
- A required file does not exist at the expected path
- Running the DoD gates produces new failures not present in the baseline
- The change affects booking overlap logic, auth token flow, or migration constraints
- More than 25 files would need to change in one pass
- A bypass (`--no-verify`) seems necessary — document the reason and confirm first

---

## Related Governance Docs

| Doc                                                                        | Purpose                                      |
| -------------------------------------------------------------------------- | -------------------------------------------- |
| [`AGENTS.md`](./AGENTS.md)                                                 | Root onboarding — read first                 |
| [`docs/agents/CONTRACT.md`](./docs/agents/CONTRACT.md)                     | Definition of Done per task type             |
| [`docs/agents/ARCHITECTURE_FACTS.md`](./docs/agents/ARCHITECTURE_FACTS.md) | Verified domain invariants                   |
| [`docs/agents/COMMANDS.md`](./docs/agents/COMMANDS.md)                     | Verified command reference                   |
| [`docs/AI_GOVERNANCE.md`](./docs/AI_GOVERNANCE.md)                         | Full agent workflow + skill selection        |
| [`docs/COMPACT.md`](./docs/COMPACT.md)                                     | Current session state + health baseline      |
| [`docs/HOOKS.md`](./docs/HOOKS.md)                                         | Git hook behavior + bypass policy            |
| [`docs/MCP.md`](./docs/MCP.md)                                             | MCP server setup + safety policy             |
| [`skills/README.md`](./skills/README.md)                                   | Skill index + canonical task prompt template |
| [`docs/FINDINGS_BACKLOG.md`](./docs/FINDINGS_BACKLOG.md)                   | Out-of-scope issues log                      |
