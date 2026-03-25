# CLAUDE.md — Soleil Hostel

Root contract for the Soleil Hostel instruction system. This file defines constitution-level mission, domain truths, non-negotiable constraints, decision order, document map, and escalation rules only.

## Mission

- Maintain the Soleil Hostel monorepo as a Laravel API + React SPA for Locations, Rooms, Bookings, Reviews, Contact Messages, and Authentication.
- Work within the repository flow `feature/*` -> `dev` -> `main`; merges to `main` remain human-reviewed.
- Preserve the business-critical areas first: double-booking prevention, token/session security, and cancellation/refund integrity.
- Keep this file constitutional. Detailed procedures, commands, skills, hooks, session state, and tooling workflows live in the mapped documents below, not here.

## Domain truths

- Booking availability uses half-open intervals `[check_in, check_out)`; only `pending` and `confirmed` block overlap; the PostgreSQL exclusion constraint keeps `deleted_at IS NULL`.
- `bookings.location_id` is intentional denormalization; one review belongs to one booking and carries `booking_id`.
- Booking-critical writes keep pessimistic locking, and optimistic locking through `lock_version` remains part of the write contract.
- Auth remains dual-mode Sanctum: Bearer plus HttpOnly cookie. Cookie auth uses `token_identifier` -> `token_hash`, token validity keeps `revoked_at` and `expires_at`, and frontend CSRF stays `sessionStorage` `csrf_token` -> `X-XSRF-TOKEN` with `withCredentials: true`.
- Backend architecture remains Controller -> Service -> Repository. Frontend remains feature-sliced and uses the shared API client only.

## Non-negotiable constraints

- Inspect before changing. Do not guess missing contracts. Use `docs/PERMISSION_MATRIX.md` as the RBAC permission source of truth.
- Never commit secrets. Do not use `env()` in runtime code; use `config()`.
- Backend request validation lives in `*Request.php`, not controllers. Frontend work remains TypeScript-strict, user-facing copy remains Vietnamese, and versioned/shared-client API boundaries remain in force.
- Code-task completion requires the repository quality gates defined in `docs/agents/CONTRACT.md`, `docs/agents/COMMANDS.md`, and `docs/COMMANDS_AND_GATES.md`. Docs-only tasks follow the documentation DoD in `docs/agents/CONTRACT.md`. Commit-message and hook/bypass rules live in `docs/HOOKS.md`.
- Out-of-scope bugs go to `docs/FINDINGS_BACKLOG.md`; do not fix them inline.
- Detailed frontend patterns and file-level rules have been relocated by reference: see `skills/react/typescript-patterns-skill.md`, `skills/react/api-client-skill.md`, `docs/frontend/SERVICES_LAYER.md`, `docs/frontend/RBAC.md`, and `docs/frontend/APP_LAYER.md`.
- GitNexus and MCP execution workflows are boundary/tooling guidance, not constitutional text: see `docs/MCP.md` and `.claude/skills/gitnexus/`.

## Decision order

1. `CLAUDE.md`
2. `docs/agents/ARCHITECTURE_FACTS.md`
3. `docs/agents/CONTRACT.md`
4. `docs/PERMISSION_MATRIX.md`, `docs/DB_FACTS.md`
5. `.agent/rules/*.md`, `skills/**/*.md`, `.claude/skills/**/SKILL.md`
6. `docs/agents/COMMANDS.md`, `.claude/commands/*.md`, `.claude/output-styles/*`
7. `.claude/hooks/*.sh`, `.claude/settings*.json`
8. `.claude/agents/*.md`
9. `docs/COMPACT.md`, `PROJECT_STATUS.md`
10. `docs/WORKLOG.md`, `BACKLOG.md`
- Resolve conceptual buckets to repo paths before judging conflicts.
- Do not negotiate wording across layers; the higher layer wins.

## Document map

- Auto-expanded canon: `docs/agents/ARCHITECTURE_FACTS.md`, `docs/agents/CONTRACT.md`
- Canonical policy references: `docs/PERMISSION_MATRIX.md`, `docs/DB_FACTS.md`, `docs/DOMAIN_LAYERS.md`
- Derived rules: `.agent/rules/booking-integrity.md`, `.agent/rules/auth-token-safety.md`, `.agent/rules/migration-safety.md`
- Commands and gates: `docs/agents/COMMANDS.md`, `docs/COMMANDS_AND_GATES.md`, `.claude/commands/`, `.claude/output-styles/`
- Skills and workflows: `skills/README.md`, `skills/laravel/*.md`, `skills/react/*.md`, `.claude/skills/gitnexus/`, `.claude/skills/generated/`
- Runtime enforcement: `docs/HOOKS.md`, `.claude/hooks/*.sh`, `.claude/settings*.json`
- Boundary/tooling docs: `docs/MCP.md`
- Session and ledger surfaces: `docs/COMPACT.md`, `docs/WORKLOG.md`, `PROJECT_STATUS.md`, `BACKLOG.md`
- Governance and onboarding: `docs/AI_GOVERNANCE.md`, `AGENTS.md`
- Findings: `docs/FINDINGS_BACKLOG.md`

## Escalation rules

- Stop and confirm before docs-only tasks change `backend/`, `frontend/`, `.github/`, or `docker-compose*`.
- Stop and confirm before changing booking overlap logic, auth token flow, migration constraints, or other high-risk invariant sources.
- Stop and confirm before changing more than 25 files in one pass, using `--no-verify`, proceeding past new gate failures, or continuing when a required file is missing.
- Mark unresolved conflicts as `UNRESOLVED` instead of inventing a rule.
- If needed guidance lives in a lower layer, follow the document map rather than expanding this file.
