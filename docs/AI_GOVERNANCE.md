# AI Governance — Soleil Hostel

Operational guide for AI coding agents working in this repository.

## Overview

Components of the AI governance framework:

| Component | Location | Purpose |
|-----------|----------|---------|
| AGENTS.md | `./AGENTS.md` | Agent onboarding + conventions |
| Skills | `./skills/` | Task-specific guardrails |
| COMPACT | `./docs/COMPACT.md` | Session memory / current state |
| Hooks | `./docs/HOOKS.md` | Local enforcement |
| MCP | `./docs/MCP.md` | Tool server + safety policy |
| CONTRACT | `./docs/agents/CONTRACT.md` | Definition of Done |
| ARCHITECTURE_FACTS | `./docs/agents/ARCHITECTURE_FACTS.md` | Domain invariants |
| COMMANDS | `./docs/agents/COMMANDS.md` | Verified command reference |

## Starting a Task (checklist)

Run in this order before writing any code or docs:

- [ ] Read `AGENTS.md` (rules + boundaries)
- [ ] Read `docs/agents/CONTRACT.md` (Definition of Done + gates)
- [ ] Read `docs/agents/ARCHITECTURE_FACTS.md` (invariants to preserve)
- [ ] Read `docs/COMPACT.md` (current codebase state + active work)
- [ ] Select skills from `skills/` relevant to this task type (see Skill Selection Guide below)
- [ ] Discover file paths via MCP or search (never assume paths exist)
- [ ] State implementation plan before writing any code or docs

## Skill Selection Guide

| Task Type | Skills to Apply |
|-----------|----------------|
| React component | `component-quality-skill` + `testing-vitest-skill` |
| React form / booking UI | + `forms-validation-skill` |
| API client wiring | `api-client-skill` |
| Backend API endpoint | `api-endpoints-skill` + `testing-skill` |
| Booking logic | + `booking-overlap-skill` |
| Auth / token changes | + `auth-tokens-skill` |
| Concurrency / locking | + `transactions-locking-skill` |
| Migration / schema | `migrations-postgres-skill` |
| Security | `security-secrets-skill` or `security-frontend-skill` |
| CI / Docker | `ci-quality-gates-skill` + `docker-compose-skill` |
| Performance | `performance-core-web-vitals-skill` |
| Logging / observability | `logging-observability-skill` |

Use 1–3 skills per task. More usually adds noise.

## Completing a Task (checklist)

- [ ] Run gates via MCP `run_verify` or locally (see [COMMANDS_AND_GATES.md](./COMMANDS_AND_GATES.md))
- [ ] Update `docs/COMPACT.md` with what changed, files touched, gate results
- [ ] No broken links in updated docs (spot-check minimum 5 links)
- [ ] PR description follows PR template (see `docs/agents/PR_TEMPLATE.md` if it exists)
- [ ] Changes reviewed by human before merge to main

## High-Risk Areas (read runbook before touching)

These domains have critical invariants. Read the relevant docs before making changes:

| Area | Key Docs | Invariant |
|------|----------|-----------|
| Booking overlap | `docs/DB_FACTS.md`, `skills/laravel/booking-overlap-skill.md` | Half-open `[check_in, check_out)`, exclusion constraint, `lockForUpdate()` |
| Auth / Sanctum tokens | `docs/backend/features/AUTHENTICATION.md`, `skills/laravel/auth-tokens-skill.md` | Dual-mode (Bearer + HttpOnly), token rotation, revocation checks |
| Migrations | `docs/DB_FACTS.md`, `skills/laravel/migrations-postgres-skill.md` | PG-only features, SQLite test fallback, idempotent patterns |
| Concurrency | `docs/backend/features/OPTIMISTIC_LOCKING.md`, `skills/laravel/transactions-locking-skill.md` | `lock_version` on rooms + locations, pessimistic locking in transactions |

## MCP Safety Rules

- Use `repo_overview` / `search` / `read_file` for discovery
- Use `run_verify` ONLY for allowlisted commands (see [MCP.md](./MCP.md))
- NEVER run arbitrary commands outside the allowlist
- NEVER guess file paths — always discover via MCP or search first

## Stale-Index Degradation (soleil-ai-review-engine)

When the `soleil-ai-review-engine` index is stale or unavailable (tool errors, missing `.soleil-ai-review-engine/` directory, or `meta.json` showing outdated `analyzed_at`):

1. **Flag the condition**: Before any multi-file change, emit: "Impact analysis unavailable — proceeding with conservative scope only."
2. **Conservative scope**: Touch only the file(s) explicitly named in the task. Do not infer related files from memory or prior sessions.
3. **Do not auto-reindex**: Surface the condition and wait for the operator to run `npx soleil-engine-cli analyze`.
4. **Skip rename workflow**: Do not use `soleil-ai-review-engine_rename` in degraded mode — fall back to manual grep-based rename with explicit human review of all call sites.
5. **Resume normal mode**: Once reindex completes and tools respond without error, full impact analysis is required again before edits.

This degradation protocol prevents false-negative blast radius assessments from outdated graph data.

## Control Plane Ownership

Component ownership is defined in [docs/agents/CONTROL_PLANE_OWNERSHIP.md](./agents/CONTROL_PLANE_OWNERSHIP.md). That file is the single canonical source — do not redefine ownership elsewhere.
