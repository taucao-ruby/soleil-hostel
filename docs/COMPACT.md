# COMPACT — Soleil Hostel (AI Session Memory)

> **Lifecycle Policy**
> - **Append** §1 snapshot after code tasks, gate runs, or milestone changes
> - **Do not append** for docs-only tasks, read-only exploration, or questions
> - **Archive**: when history exceeds ~80 lines, move resolved items to `docs/WORKLOG.md` and keep only the latest 5 entries here
> - **Stable facts** (invariants, architecture, auth) belong in `docs/agents/ARCHITECTURE_FACTS.md` — never here
> - **Owner**: this file is volatile session state; `ARCHITECTURE_FACTS.md` and `CLAUDE.md` own canonical truth

## 1) Current Snapshot (keep under 12 lines)

- Date updated: 2026-03-14
- Current branch: `claude/strange-raman` (worktree; targets `dev`)
- Latest commit: `29300ef` — test(backend): update EmailVerificationTest to use complex password
- Backend test baseline: 901 tests, 2510 assertions — verified 2026-03-11
- Frontend test baseline: 226 tests, 21 suites — verified 2026-03-11
- Pint: 283 files, 0 style issues. PHPStan: Level 5, 151 pre-existing. Psalm: Level 1, 0 blocking.
- Open findings: F-23 (MD lint — low). All others resolved (F-01–F-22, F-24).
- **Logout-401 (2026-03-14) RESOLVED**: No code bug — stale `soleil_token` cookie from old test users. Curl + browser confirm 200 on login→me→logout. Minor non-critical: `csrf_token()` null on API routes; `api.ts` refresh CSRF path wrong (`data.csrf_token` → should be `data.data.csrf_token`).
- Test accounts (soleil_test DB): user@soleil.test / admin@soleil.test / moderator@soleil.test — `P@ssworD123`
- **H-06**: `phpunit.xml` defaults to PostgreSQL; run `docker compose up -d db` before `php artisan test`.

## 2) Invariants

Canonical detail: `docs/agents/ARCHITECTURE_FACTS.md` (auto-loaded via CLAUDE.md).
This section intentionally left as a pointer — do not duplicate invariants here.

## 3) Active work (Now / Next)

### Now

- M-11 (migration squash) BLOCKED — no squash protocol in governance; requires human approval
- TD-005 RBAC Follow-ups (FU-1..FU-5) — legacy test migration, coverage gaps, config verification (see `docs/PERMISSION_MATRIX.md`)

### Next

- M-11: Migration squash — needs human-approved `php artisan schema:dump --prune` process
- H-06 CI alignment: update `.github/workflows/` to start PostgreSQL service before `php artisan test`
- PAY-001 Phase 2: Stripe checkout session + frontend payment UI
- TD-005: FU-1 (cancellation test migration), FU-2 (moderator-denial + v1 pin), FU-3 (config verify), FU-4 (RoomController re-verify), FU-5 (room test migration)

## 4) Verification commands

See `docs/agents/COMMANDS.md` for full command catalog.

## 5) Known warnings / noise (non-blocking)

- PHPUnit doc-comment metadata deprecation warnings can appear; treat as non-blocking when `php artisan test` is PASS.
- Vitest can emit `act(...)` and non-boolean DOM attribute warnings; treat as non-blocking when `npx vitest run` is PASS.
- Any new warning pattern or warning volume increase should be treated as a change signal and reviewed.

## 6) Key pointers (docs / important files)

- [Project Status](../PROJECT_STATUS.md)
- [Audit Report](../AUDIT_REPORT.md)
- [Docs Index](./README.md)
- [Operational Playbook](./OPERATIONAL_PLAYBOOK.md)
- [DB Facts (Invariants)](./DB_FACTS.md)
- [Agent Framework](./agents/README.md)
- [Commands & Gates](./COMMANDS_AND_GATES.md)
- [Findings Backlog](./FINDINGS_BACKLOG.md)
- [WORKLOG](./WORKLOG.md)

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

## History (archived 2026-03-09)

Full history for 2026-02-12 through 2026-03-06 archived to `docs/WORKLOG.md`.
