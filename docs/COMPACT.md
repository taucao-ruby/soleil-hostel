# COMPACT — Soleil Hostel (AI Session Memory)

## 1) Current Snapshot (keep under 12 lines)
- Date updated: 2026-02-12
- Current branch: `dev` (local `main` currently resolves to commit `e05ae68`)
- Branch baseline (2026-02-11): `dev` at `096adfa`, 8 commits ahead of `main` at `712478e` (from `../PROJECT_STATUS.md`)
- CI status: Last recorded green in `../PROJECT_STATUS.md` on 2026-02-11
- Latest verified commands: `cd backend && php artisan test` (722 tests, 2012 assertions), `cd frontend && npx tsc --noEmit`, `cd frontend && npx vitest run` (142 tests), `docker compose config` (PASS)
- Progress summary: Audit v1 `61/61` and audit v2 `98/98` are historically complete; repo health marked green on 2026-02-11
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

### Next
- Re-run verification command set after backend/frontend behavior changes (why: fast regression detection).
- Re-check booking overlap and soft-delete semantics when booking migrations change (why: prevent double-booking regressions).
- Re-check auth revocation/expiry/rotation paths when auth middleware/controllers change (why: token/session safety).
- Refresh branch and CI snapshot lines after each PR merge or commit batch (why: avoid stale memory).
- Remove warning entries only after confirmed clean runs (why: preserve signal and avoid false confidence).

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
cd frontend && npm run lint
cd frontend && npm run format
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
- [Agent Instructions](../AGENTS.md)
- [Skills Folder](../skills/)

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
