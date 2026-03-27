# Invariant Baseline — Pre-Refactor

> Captured: 2026-03-22 | Source: CLAUDE.md (206 lines) + AGENTS.md (136 lines)
> This is the preservation test: every instruction below MUST survive the refactor.

## CLAUDE.md — Core Constitution (lines 1-105)

### Section: Project Identity (lines 6-11)
- I-01: Monorepo: Laravel 12 REST API + React 19 TypeScript SPA
- I-02: Branch model: feature/<name> → dev → main (--no-ff). PRs target dev
- I-03: Stack: PHP 8.2+, PostgreSQL 16, Redis 7, Vite 6, Vitest 2, TailwindCSS 3
- I-04: Infrastructure: Docker Compose. CI: .github/workflows/

### Section: Canonical References (lines 13-24)
- I-05: @docs/agents/ARCHITECTURE_FACTS.md (auto-expanded)
- I-06: @docs/agents/CONTRACT.md (auto-expanded)
- I-07: docs/PERMISSION_MATRIX.md — on-demand, RBAC single source of truth
- I-08: docs/agents/COMMANDS.md — on-demand, command catalog
- I-09: docs/COMPACT.md — on-demand, volatile session handoff
- I-10: skills/README.md — on-demand, skill index
- I-11: docs/FINDINGS_BACKLOG.md — on-demand, issues log
- I-12: .claude/output-styles/ — on-demand, response formats

### Section: Non-Negotiable Invariants (lines 26-53)
- I-13: Half-open intervals [check_in, check_out) everywhere
- I-14: Active overlap statuses: pending, confirmed only
- I-15: PostgreSQL EXCLUDE USING gist must filter deleted_at IS NULL
- I-16: bookings.location_id is intentionally denormalized
- I-17: One review per booking; review must carry booking_id
- I-18: lockForUpdate() required for booking-critical writes
- I-19: lock_version optimistic locking must not be silently skipped
- I-20: Sanctum dual auth (Bearer + HttpOnly cookie) must remain intact
- I-21: CSRF: sessionStorage csrf_token → X-XSRF-TOKEN header — do not bypass
- I-22: Token lookup: token_identifier → token_hash; enforce revoked_at + expires_at
- I-23: Never commit: APP_KEY, passwords, tokens, API keys, private keys
- I-24: XSS: all user input through HtmlPurifierService
- I-25: No env() in runtime code — use config()
- I-26: Backend: Controller → Service → Repository. No shortcuts.
- I-27: Frontend: feature-sliced src/features/<name>/ with co-located api/types/components/tests
- I-28: API calls via @/shared/lib/api only — no second Axios instance
- I-29: withCredentials: true must remain on Axios

### Section: Frontend Rules (lines 55-64)
- I-30: TypeScript strict — zero any, zero type errors
- I-31: No console.log in production code
- I-32: No React Query, no Zod, no react-hot-toast
- I-33: State: useState + useEffect + AbortController pattern
- I-34: UI text: Vietnamese strings for all user-facing copy
- I-35: Vitest: vi.hoisted() for mutable mock state shared with vi.mock() factories
- I-36: Cross-feature imports forbidden except bookings/ ↔ booking/ (same domain)
- I-37: /v1/ prefix for all API calls. Legacy unversioned endpoints sunset July 2026

### Section: Validation Gates (lines 66-75)
- I-38: cd backend && php artisan test — 0 failures
- I-39: cd frontend && npx tsc --noEmit — 0 errors
- I-40: cd frontend && npx vitest run — 0 failures
- I-41: docker compose config — valid
- I-42: Docs-only tasks: manual link check only
- I-43: New behavior → new tests. No exceptions.

### Section: Commit Format (lines 77-84)
- I-44: <type>(<scope>): <subject> format, hook-enforced
- I-45: Types: feat | fix | chore | docs | refactor | test | build | ci | perf | revert
- I-46: Scopes: backend | frontend | infra | docs (optional)
- I-47: Breaking: append ! before colon

### Section: Editing Boundaries (lines 86-96)
- I-48: Confirm before modifying backend/frontend/.github/docker-compose on docs-only tasks
- I-49: Confirm before changing booking overlap logic, auth token flow, migration constraints
- I-50: Confirm before changing more than 25 files in one pass
- I-51: Confirm before using --no-verify bypass
- I-52: Confirm when gates produce new failures not in baseline
- I-53: Confirm when a required file not existing at expected path
- I-54: Out-of-scope bugs: log in docs/FINDINGS_BACKLOG.md, do not fix inline

### Section: File-Specific Rules (lines 98-104)
- I-55: api.ts — do not modify CSRF interceptor without reading SERVICES_LAYER.md
- I-56: router.tsx — / uses PublicLayout; /booking and /dashboard in ProtectedRoute + Suspense; DashboardPage lazy-loaded
- I-57: booking.api.ts — /v1/ prefix only; never add legacy unversioned endpoints
- I-58: COMPACT.md — edit §1 in-place, append history; keep §1 under 12 lines
- I-59: backend/ — request validation in *Request.php, not controllers; no env() in controllers/services

### Section: soleil-ai-review-engine (lines 106-206) — auto-managed by <!-- soleil-ai-review-engine:start/end -->
- I-60: MUST run impact analysis before editing any symbol
- I-61: MUST run soleil-ai-review-engine_detect_changes() before committing
- I-62: MUST warn user if impact analysis returns HIGH or CRITICAL risk
- I-63: Use soleil-ai-review-engine_query for exploration instead of grepping
- I-64: Use soleil-ai-review-engine_context for full symbol context
- I-65: NEVER edit without soleil-ai-review-engine_impact first
- I-66: NEVER ignore HIGH/CRITICAL warnings
- I-67: NEVER rename with find-and-replace — use soleil-ai-review-engine_rename
- I-68: NEVER commit without soleil-ai-review-engine_detect_changes
- I-69: Tools reference table (query, context, impact, detect_changes, rename, cypher)
- I-70: Impact risk levels (d=1, d=2, d=3)
- I-71: Resources table (context, clusters, processes, process/{name})
- I-72: Self-check before finishing (4-point checklist)
- I-73: Index freshness — run npx soleil-ai-review-engine analyze after commits
- I-74: CLI skill file reference table (6 skills)

## AGENTS.md — Agent Onboarding (lines 1-35, unique content)

- A-01: Layer table (Commands, Session state, Slash commands, Subagents, Skills, Governance)
- A-02: Core domains: Locations, Rooms, Bookings, Reviews, Contact Messages, Authentication
- A-03: Primary business risks (double-booking, token security, cancellation state machine)
- A-04: Repo layout (backend/, frontend/, docs/, skills/, mcp/, .claude/)

## AGENTS.md — soleil-ai-review-engine (lines 36-136, DUPLICATE of CLAUDE.md lines 106-206)

- DUPLICATE of I-60 through I-74 — identical content

---

## Totals

- **74 unique instructions** from CLAUDE.md (I-01 through I-74)
- **4 unique instructions** from AGENTS.md (A-01 through A-04)
- **15 duplicated instructions** (soleil-ai-review-engine section in AGENTS.md)
- **Total unique: 78 instructions**
