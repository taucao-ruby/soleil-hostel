# COMPACT — Soleil Hostel (AI Session Memory)

> **Lifecycle Policy**
> - **Append** §1 snapshot after code tasks, gate runs, or milestone changes
> - **Do not append** for docs-only tasks, read-only exploration, or questions
> - **Archive**: when history exceeds ~80 lines, move resolved items to `docs/WORKLOG.md` and keep only the latest 5 entries here
> - **Stable facts** (invariants, architecture, auth) belong in `docs/agents/ARCHITECTURE_FACTS.md` — never here
> - **Owner**: this file is volatile session state; `ARCHITECTURE_FACTS.md` and `CLAUDE.md` own canonical truth
>
> **Lifetime metadata** (per master contract)
> - generated_from: ARCHITECTURE_FACTS.md, CONTRACT.md, COMMANDS_AND_GATES.md, FINDINGS_BACKLOG.md
> - last_verified_at: 2026-04-18
> - scope: AI session handoff state (current snapshot, active work, known warnings, pointers)
> - expiry_trigger: any code task, gate run, or milestone change

## 1) Current Snapshot (keep under 12 lines)

- Date updated: 2026-04-18
- Current branch: `dev` (HEAD=`aef28a1`)
- Latest commit: `aef28a1` — Merge branch 'main' into dev (post-F-06 remediation)
- Backend test baseline: **re-verification required** — F-06 proposer-binding + F-01/F-02/F-03 remediations added test surface since Mar 31 baseline (1047/2875)
- Frontend: LoginPage/RegisterPage/RoomList redesign, LocationDetail boutique redesign (`84b25e3`, `e6673dd`), AI assistant widgets; axios ^1.15.0, vite 6.4.2 (CVE fixes)
- AI Harness: Phases 0–4 ✅ Done. F-06 proposer-binding landed (`17a4880`, `39cba7a`): cache envelope carries `proposer_user_id`; `decide()` 404s on mismatch; service-layer cancellation ownership gate at `CancellationService::validateCancellation`
- Deploy hardening: F-04 pre-flight `DEPLOY_HOST` gate + migration-before-health reordering (`ec025ca`, `75bb790`). OpenAPI Spectral contract-lint CI gate added (`4a33755`)
- Open findings: F-23, F-25, F-26–F-62, F-63–F-66. See FINDINGS_BACKLOG.md. Note: 2026-02-21 F-06 (CHECK check_out>check_in) and 2026-04-18 F-06 (proposer-binding) share an ID — always cite with audit date.
- **H-06**: `phpunit.xml` defaults to PostgreSQL; run `docker compose up -d db` before `php artisan test`.
- **T-13 MITIGATED (2026-04-18)**: proposer-binding enforced; supersedes prior "Accepted" posture.

## 2) Invariants

Canonical detail: `docs/agents/ARCHITECTURE_FACTS.md` (auto-loaded via CLAUDE.md).
This section intentionally left as a pointer — do not duplicate invariants here.

## 3) Active work (Now / Next)

### Now

- **AI Harness Phases 0–4**: ✅ COMPLETE — all 7 endpoints, eval framework, kill switch, canary routing
- **F-06 proposer-binding**: ✅ COMPLETE (2026-04-18) — cache envelope carries `proposer_user_id`; `decide()` 404s on mismatch; service-layer cancellation ownership gate; T-13 reclassified Accepted→Mitigated
- **Documentation governance remediation (2026-04-18)**: ✅ COMPLETE — 11 docs aligned with post-F-06 code truth (ARCHITECTURE_FACTS, PERMISSION_MATRIX, THREAT_MODEL_AI, CONTRACT, COMMANDS_AND_GATES, OPERATIONAL_PLAYBOOK, ROLLOUT_AND_KILL_SWITCH, backend/.env.example, backend/.env.production.example, PROJECT_STATUS, COMPACT)
- **Deploy hardening**: ✅ COMPLETE — F-04 DEPLOY_HOST pre-flight gate + migration-before-health ordering + Spectral OpenAPI contract-lint CI gate
- PAY-001 Phase 2: Stripe checkout session + frontend payment UI
- TD-005 RBAC Follow-ups (FU-1..FU-5) — legacy test migration, coverage gaps, config verification (see `docs/PERMISSION_MATRIX.md`)
- OPS-001: SSH deploy step ✅ (real SSH deploy landed `40bcf6c`); automated health check after migration reorder; automatic rollback on health failure still pending

### Next

- M-11: Migration squash — BLOCKED, needs human-approved `php artisan schema:dump --prune` process
- I18N-002: Frontend i18n
- FE-004: Booking modification history (guest)
- TD-004: Audit log retention policy (`bookings:archive --older-than=2y`, log rotation)

## 4) Verification commands

See `docs/agents/COMMANDS.md` for full command catalog.

## 5) Known warnings / noise (non-blocking)

- PHPUnit doc-comment metadata deprecation warnings can appear; treat as non-blocking when `php artisan test` is PASS.
- Vitest can emit `act(...)` and non-boolean DOM attribute warnings; treat as non-blocking when `npx vitest run` is PASS.
- Any new warning pattern or warning volume increase should be treated as a change signal and reviewed.
- Test accounts (soleil_test DB): user@soleil.test / admin@soleil.test / moderator@soleil.test — `P@ssworD123`
- Pint 8 residual violations (email-verification cluster) are non-blocking for dev but will fail CI gate. Fix before next merge to main.

## 6) Key pointers (docs / important files)

- [Project Status](../PROJECT_STATUS.md)
- [Audit Report (2026-02-21)](./AUDIT_2026_02_21.md)
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
