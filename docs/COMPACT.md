# COMPACT — Soleil Hostel (AI Session Memory)

## 1) Current Snapshot (keep under 12 lines)
- Date updated: 2026-02-22
- Current branch: `main`
- Latest verified commands: `cd frontend && npx tsc --noEmit` (0 errors), `cd frontend && npx vitest run` (145 tests, 13 suites, 0 failures)
- Backend test baseline: `cd backend && php artisan test` (737 tests, 2071 assertions) — verified 2026-02-22
- Pint baseline: `cd backend && vendor/bin/pint --test` (250 files, 0 violations) — verified 2026-02-22
- Progress summary: Homepage Phase 1 complete; auth HttpOnly fix complete; audit v3 remediation complete (12/14 findings fixed)
- Deployment status: Not asserted here; validate pipeline/runbook status before release

## 2) What matters (invariants / guardrails)
- Booking overlap must remain half-open: `[check_in, check_out)`.
- Active overlap statuses are only `pending` and `confirmed`.
- Production invariant: PostgreSQL exclusion constraint uses `daterange(check_in, check_out, '[)')` with `deleted_at IS NULL`.
- Keep application overlap logic aligned with production constraint behavior.
- Test baseline runs on SQLite in-memory (`backend/phpunit.xml`); verify Postgres-only behavior against PostgreSQL before merge.
- Booking soft delete audit fields must be preserved: `deleted_at`, `deleted_by`.
- Cancellation audit fields must be preserved: `cancelled_at`, `cancelled_by`, `cancellation_reason`.
- Auth uses Sanctum plus custom token columns: `token_identifier`, `token_hash`, `device_id`, `device_fingerprint`, `expires_at`, `revoked_at`, `refresh_count`, `last_rotated_at`.
- HttpOnly cookie path must resolve `token_identifier` to DB `token_hash`, and must enforce expiry/revocation/refresh-abuse checks.
- Concurrency guardrails: optimistic locking via `lock_version` for rooms; pessimistic locking via `SELECT ... FOR UPDATE` for booking conflict/cancel flows.
- Never commit or paste secrets (`APP_KEY`, tokens, passwords, sensitive stack values).
- Keep runtime security/config reads through `config()`, not ad hoc `env()` usage in app logic.

## 3) Active work (Now / Next)
### Now
- Keep this COMPACT snapshot current after each meaningful change batch.
- Keep branch/test snapshot synced with `../PROJECT_STATUS.md` and latest local verification runs.
- Keep warning/noise notes current so failures are not masked by known benign output.

### Completed this session (2026-02-21)

- Homepage Phase 1: implemented 8-section mobile-first public homepage per spec
  - New files: `home.tokens.ts`, `home.types.ts`, `home.mock.ts`, `RoomsSection.tsx`, `SiteFooter.tsx`
  - Rewritten: `Hero.tsx`, `SearchCard.tsx`, `FilterChips.tsx`, `RoomCard.tsx`, `BottomNav.tsx`, `PromoBanner.tsx`, `ReviewsCarousel.tsx`, `HomePage.tsx`
  - Fixed 7 defects: C-01 (watermark), C-02 (flat colour), C-03 (no role=search), H-01 (Cuộn xuống), H-02 (duplicate CTA), H-03 (no pill), M-01/M-03 (layout)
  - Regression tests added in `HomePage.test.tsx` + `FilterChips.test.tsx` (14 + 4 tests)
  - Router: `PublicLayout` (HeaderMobile + BottomNav) added around `/`

### Next

- Dashboard Phase 2: `DashboardPage`, `Sidebar`, `KPICards`, `OccupancyStrip`, `RecentBookingsTable`, `BookingCalendar`, `CreateBookingDrawer`, `BookingDetailPanel`, `CancelBookingModal`
- Wire SearchCard to real availability API when ready
- Re-run verification command set after backend/frontend behavior changes (why: fast regression detection).
- Re-check booking overlap and soft-delete semantics when booking migrations change.
- Refresh branch and CI snapshot lines after each PR merge or commit batch.

## 4) Verification checklist (copy/paste)
Required baseline:
```bash
cd backend && php artisan test
cd frontend && npx tsc --noEmit
cd frontend && npx vitest run
docker compose config
```

Useful lint/format/static checks:
```bash
cd frontend && pnpm lint
cd frontend && pnpm format
cd backend && vendor/bin/pint --test
cd backend && vendor/bin/phpstan analyse
cd backend && vendor/bin/psalm
```

## 5) Known warnings / noise (non-blocking)
- PHPUnit doc-comment metadata deprecation warnings can appear; treat as non-blocking when `php artisan test` is PASS.
- Vitest can emit `act(...)` and non-boolean DOM attribute warnings; treat as non-blocking when `npx vitest run` is PASS.
- Any new warning pattern or warning volume increase should be treated as a change signal and reviewed.

## 6) Key pointers (docs / important files)
- [Project Status](../PROJECT_STATUS.md)
- [Audit Report](../AUDIT_REPORT.md)
- [Audit Fix Promts v1](../AUDIT_FIX_PROMTS_V1.md)
- [Audit Fix Promts v2](../AUDIT_FIX_PROMTS_V2.md)
- [Remaining Audit Promts](../AUDIT_FIX_PROMTS.md)
- [Docs Index](./README.md)
- [Operational Playbook](./OPERATIONAL_PLAYBOOK.md)
- [Database Notes](./DATABASE.md)
- [DB Facts (Invariants)](./DB_FACTS.md)
- [Agent Instructions](../AGENTS.md)
- [Agent Framework](./agents/README.md)
- [AI Governance](./AI_GOVERNANCE.md)
- [Commands & Gates](./COMMANDS_AND_GATES.md)
- [MCP Server](./MCP.md)
- [Hooks](./HOOKS.md)
- [Skills Folder](../skills/)
- [Audit 2026-02-21](./AUDIT_2026_02_21.md)
- [Findings Backlog](./FINDINGS_BACKLOG.md)

## 7) Update protocol (how to maintain COMPACT)
- When to update:
  - after each PR/merge
  - after each batch of agent changes
  - when invariants change
- How to update:
  - edit sections 1, 3, and 5
  - append an entry to WORKLOG (if enabled)
- Format rules:
  - short lines, no essays, no secrets

## 2026-02-21 — Repo-wide Docs Audit

### What changed

- Created `docs/agents/` directory: `README.md`, `CONTRACT.md`, `ARCHITECTURE_FACTS.md`, `COMMANDS.md`
- Created `docs/AUDIT_2026_02_21.md` — full audit findings
- Created `docs/FINDINGS_BACKLOG.md` — 14 code issues logged (not fixed)
- Created `docs/COMMANDS_AND_GATES.md` — verified commands + CI gate mapping
- Created `docs/AI_GOVERNANCE.md` — operational checklists for AI agents
- Created `docs/MCP.md` — MCP server documentation
- Created `docs/HOOKS.md` — hook enforcement docs
- Updated `docs/README.md` — added AI agents section, high-risk callouts, all-docs index, fixed Laravel version
- Updated `skills/README.md` — added "Adding a New Skill" section + governance links
- Updated `docs/COMPACT.md` — added key pointers + this audit entry

### Confirmed Architecture Truths

- Booking exclusion constraint with `daterange(check_in, check_out, '[)')` + `deleted_at IS NULL` — verified
- Dual auth mode (Bearer + HttpOnly cookie) — verified
- Custom token columns across 2 migrations — verified (8 columns total)
- UserRole enum: `user | moderator | admin` — verified (PG ENUM + PHP backed enum)
- Optimistic locking: `lock_version` on rooms + locations — verified
- Pessimistic locking: `lockForUpdate()` in CancellationService + Booking model — verified
- Brand tokens match: 6 colors confirmed in `tailwind.config.js`
- BottomNav: 4 tabs confirmed
- "Cuộn xuống" absent from rendered UI — confirmed (regression test guards it)

### Docs Drift Fixed

- `docs/README.md`: "Laravel 11 + PHP 8.3" corrected to "Laravel 12 + PHP 8.2+"
- `docs/README.md`: test count updated from 142 to 145 frontend tests
- `docs/README.md`: date updated from Feb 12 to Feb 21

### Code Issues Found (backlog)

See `docs/FINDINGS_BACKLOG.md` (14 items):

- F-04 (High): CI triggers `develop` branch but repo uses `dev`
- F-01 (Medium): DATABASE.md claims room_status PG ENUM but it's VARCHAR
- F-05 (Medium): CI uses pnpm, local docs reference npm
- F-06/F-07/F-08 (Medium): Missing CHECK constraints
- Others: Low severity docs drift

### Gates

- MCP run_verify not available in this environment
- Commands for human to verify locally:
  - `cd backend && php artisan test`
  - `cd frontend && npx tsc --noEmit`
  - `cd frontend && npx vitest run`
  - `docker compose config`

### Warnings

- Audit complete — all 15 tier-2 searches executed, all tier-3 reads done
- No code was modified — docs-only changes

### Completed (2026-02-22) — Audit v3 Remediation

- PR-1: F-04 (CI `develop`→`dev`) + F-14 (Redis conditional requirepass)
- PR-2: F-06/F-07/F-08 (CHECK constraints migration)
- PR-3: F-09 (FK `reviews.booking_id → bookings.id`)
- PR-4: F-01/F-05/F-10–F-13 (docs sync)
- Post-fix: Pint style violations + minimatch pnpm override

### Next steps (prioritized)

1. Dashboard Phase 2 implementation
2. Wire SearchCard to real availability API
3. Fix F-02/F-03 in docs/README.md (low — stale version/count references)
