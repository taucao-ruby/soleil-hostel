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
- `docs/PERMISSION_MATRIX.md` — canonical RBAC permission baseline (single source of truth)
- `docs/agents/COMMANDS.md` — command catalog, setup, dev servers
- `docs/COMPACT.md` — volatile session handoff log (see lifecycle policy inside)
- `skills/README.md` — skill index for task-specific guardrails
- `docs/DOMAIN_LAYERS.md` — four-layer operational model (bookings → stays → room_assignments → service_recovery)
- `docs/FINDINGS_BACKLOG.md` — out-of-scope issues log
- `.claude/output-styles/` — Execution and Audit response formats (use via `/output-style`)

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

<!-- gitnexus:start -->
# GitNexus — Code Intelligence

This project is indexed by GitNexus as **soleil-hostel** (5329 symbols, 13306 relationships, 199 execution flows). Use the GitNexus MCP tools to understand code, assess impact, and navigate safely.

> If any GitNexus tool warns the index is stale, run `npx gitnexus analyze` in terminal first.

## Always Do

- **MUST run impact analysis before editing any symbol.** Before modifying a function, class, or method, run `gitnexus_impact({target: "symbolName", direction: "upstream"})` and report the blast radius (direct callers, affected processes, risk level) to the user.
- **MUST run `gitnexus_detect_changes()` before committing** to verify your changes only affect expected symbols and execution flows.
- **MUST warn the user** if impact analysis returns HIGH or CRITICAL risk before proceeding with edits.
- When exploring unfamiliar code, use `gitnexus_query({query: "concept"})` to find execution flows instead of grepping. It returns process-grouped results ranked by relevance.
- When you need full context on a specific symbol — callers, callees, which execution flows it participates in — use `gitnexus_context({name: "symbolName"})`.

## When Debugging

1. `gitnexus_query({query: "<error or symptom>"})` — find execution flows related to the issue
2. `gitnexus_context({name: "<suspect function>"})` — see all callers, callees, and process participation
3. `READ gitnexus://repo/soleil-hostel/process/{processName}` — trace the full execution flow step by step
4. For regressions: `gitnexus_detect_changes({scope: "compare", base_ref: "main"})` — see what your branch changed

## When Refactoring

- **Renaming**: MUST use `gitnexus_rename({symbol_name: "old", new_name: "new", dry_run: true})` first. Review the preview — graph edits are safe, text_search edits need manual review. Then run with `dry_run: false`.
- **Extracting/Splitting**: MUST run `gitnexus_context({name: "target"})` to see all incoming/outgoing refs, then `gitnexus_impact({target: "target", direction: "upstream"})` to find all external callers before moving code.
- After any refactor: run `gitnexus_detect_changes({scope: "all"})` to verify only expected files changed.

## Never Do

- NEVER edit a function, class, or method without first running `gitnexus_impact` on it.
- NEVER ignore HIGH or CRITICAL risk warnings from impact analysis.
- NEVER rename symbols with find-and-replace — use `gitnexus_rename` which understands the call graph.
- NEVER commit changes without running `gitnexus_detect_changes()` to check affected scope.

## Tools Quick Reference

| Tool | When to use | Command |
|------|-------------|---------|
| `query` | Find code by concept | `gitnexus_query({query: "auth validation"})` |
| `context` | 360-degree view of one symbol | `gitnexus_context({name: "validateUser"})` |
| `impact` | Blast radius before editing | `gitnexus_impact({target: "X", direction: "upstream"})` |
| `detect_changes` | Pre-commit scope check | `gitnexus_detect_changes({scope: "staged"})` |
| `rename` | Safe multi-file rename | `gitnexus_rename({symbol_name: "old", new_name: "new", dry_run: true})` |
| `cypher` | Custom graph queries | `gitnexus_cypher({query: "MATCH ..."})` |

## Impact Risk Levels

| Depth | Meaning | Action |
|-------|---------|--------|
| d=1 | WILL BREAK — direct callers/importers | MUST update these |
| d=2 | LIKELY AFFECTED — indirect deps | Should test |
| d=3 | MAY NEED TESTING — transitive | Test if critical path |

## Resources

| Resource | Use for |
|----------|---------|
| `gitnexus://repo/soleil-hostel/context` | Codebase overview, check index freshness |
| `gitnexus://repo/soleil-hostel/clusters` | All functional areas |
| `gitnexus://repo/soleil-hostel/processes` | All execution flows |
| `gitnexus://repo/soleil-hostel/process/{name}` | Step-by-step execution trace |

## Self-Check Before Finishing

Before completing any code modification task, verify:
1. `gitnexus_impact` was run for all modified symbols
2. No HIGH/CRITICAL risk warnings were ignored
3. `gitnexus_detect_changes()` confirms changes match expected scope
4. All d=1 (WILL BREAK) dependents were updated

## Keeping the Index Fresh

After committing code changes, the GitNexus index becomes stale. Re-run analyze to update it:

```bash
npx gitnexus analyze
```

If the index previously included embeddings, preserve them by adding `--embeddings`:

```bash
npx gitnexus analyze --embeddings
```

To check whether embeddings exist, inspect `.gitnexus/meta.json` — the `stats.embeddings` field shows the count (0 means no embeddings). **Running analyze without `--embeddings` will delete any previously generated embeddings.**

> Claude Code users: A PostToolUse hook handles this automatically after `git commit` and `git merge`.

## CLI

| Task | Read this skill file |
|------|---------------------|
| Understand architecture / "How does X work?" | `.claude/skills/gitnexus/gitnexus-exploring/SKILL.md` |
| Blast radius / "What breaks if I change X?" | `.claude/skills/gitnexus/gitnexus-impact-analysis/SKILL.md` |
| Trace bugs / "Why is X failing?" | `.claude/skills/gitnexus/gitnexus-debugging/SKILL.md` |
| Rename / extract / split / refactor | `.claude/skills/gitnexus/gitnexus-refactoring/SKILL.md` |
| Tools, resources, schema reference | `.claude/skills/gitnexus/gitnexus-guide/SKILL.md` |
| Index, status, clean, wiki CLI commands | `.claude/skills/gitnexus/gitnexus-cli/SKILL.md` |

<!-- gitnexus:end -->
