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
- soleil-ai-review-engine and MCP execution workflows are boundary/tooling guidance, not constitutional text: see `docs/MCP.md` and `.claude/skills/soleil-ai-review-engine/`.

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
- Skills and workflows: `skills/README.md`, `skills/laravel/*.md`, `skills/react/*.md`, `.claude/skills/soleil-ai-review-engine/`, `.claude/skills/generated/`
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

<!-- soleil-ai-review-engine:start -->
# soleil-ai-review-engine — Code Intelligence

This project is indexed by soleil-ai-review-engine as **soleil-hostel** (4391 symbols, 11393 relationships, 182 execution flows). Use the soleil-ai-review-engine MCP tools to understand code, assess impact, and navigate safely.

> If any soleil-ai-review-engine tool warns the index is stale, run `npx soleil-engine-cli analyze` in terminal first.

## Always Do

- **MUST run impact analysis before editing any symbol.** Before modifying a function, class, or method, run `soleil-ai-review-engine_impact({target: "symbolName", direction: "upstream"})` and report the blast radius (direct callers, affected processes, risk level) to the user.
- **MUST run `soleil-ai-review-engine_detect_changes()` before committing** to verify your changes only affect expected symbols and execution flows.
- **MUST warn the user** if impact analysis returns HIGH or CRITICAL risk before proceeding with edits.
- When exploring unfamiliar code, use `soleil-ai-review-engine_query({query: "concept"})` to find execution flows instead of grepping. It returns process-grouped results ranked by relevance.
- When you need full context on a specific symbol — callers, callees, which execution flows it participates in — use `soleil-ai-review-engine_context({name: "symbolName"})`.

## When Debugging

1. `soleil-ai-review-engine_query({query: "<error or symptom>"})` — find execution flows related to the issue
2. `soleil-ai-review-engine_context({name: "<suspect function>"})` — see all callers, callees, and process participation
3. `READ soleil-ai-review-engine://repo/soleil-hostel/process/{processName}` — trace the full execution flow step by step
4. For regressions: `soleil-ai-review-engine_detect_changes({scope: "compare", base_ref: "main"})` — see what your branch changed

## When Refactoring

- **Renaming**: MUST use `soleil-ai-review-engine_rename({symbol_name: "old", new_name: "new", dry_run: true})` first. Review the preview — graph edits are safe, text_search edits need manual review. Then run with `dry_run: false`.
- **Extracting/Splitting**: MUST run `soleil-ai-review-engine_context({name: "target"})` to see all incoming/outgoing refs, then `soleil-ai-review-engine_impact({target: "target", direction: "upstream"})` to find all external callers before moving code.
- After any refactor: run `soleil-ai-review-engine_detect_changes({scope: "all"})` to verify only expected files changed.

## Never Do

- NEVER edit a function, class, or method without first running `soleil-ai-review-engine_impact` on it.
- NEVER ignore HIGH or CRITICAL risk warnings from impact analysis.
- NEVER rename symbols with find-and-replace — use `soleil-ai-review-engine_rename` which understands the call graph.
- NEVER commit changes without running `soleil-ai-review-engine_detect_changes()` to check affected scope.

## Tools Quick Reference

| Tool | When to use | Command |
|------|-------------|---------|
| `query` | Find code by concept | `soleil-ai-review-engine_query({query: "auth validation"})` |
| `context` | 360-degree view of one symbol | `soleil-ai-review-engine_context({name: "validateUser"})` |
| `impact` | Blast radius before editing | `soleil-ai-review-engine_impact({target: "X", direction: "upstream"})` |
| `detect_changes` | Pre-commit scope check | `soleil-ai-review-engine_detect_changes({scope: "staged"})` |
| `rename` | Safe multi-file rename | `soleil-ai-review-engine_rename({symbol_name: "old", new_name: "new", dry_run: true})` |
| `cypher` | Custom graph queries | `soleil-ai-review-engine_cypher({query: "MATCH ..."})` |

## Impact Risk Levels

| Depth | Meaning | Action |
|-------|---------|--------|
| d=1 | WILL BREAK — direct callers/importers | MUST update these |
| d=2 | LIKELY AFFECTED — indirect deps | Should test |
| d=3 | MAY NEED TESTING — transitive | Test if critical path |

## Resources

| Resource | Use for |
|----------|---------|
| `soleil-ai-review-engine://repo/soleil-hostel/context` | Codebase overview, check index freshness |
| `soleil-ai-review-engine://repo/soleil-hostel/clusters` | All functional areas |
| `soleil-ai-review-engine://repo/soleil-hostel/processes` | All execution flows |
| `soleil-ai-review-engine://repo/soleil-hostel/process/{name}` | Step-by-step execution trace |

## Self-Check Before Finishing

Before completing any code modification task, verify:
1. `soleil-ai-review-engine_impact` was run for all modified symbols
2. No HIGH/CRITICAL risk warnings were ignored
3. `soleil-ai-review-engine_detect_changes()` confirms changes match expected scope
4. All d=1 (WILL BREAK) dependents were updated

## Keeping the Index Fresh

After committing code changes, the soleil-ai-review-engine index becomes stale. Re-run analyze to update it:

```bash
npx soleil-engine-cli analyze
```

If the index previously included embeddings, preserve them by adding `--embeddings`:

```bash
npx soleil-engine-cli analyze --embeddings
```

To check whether embeddings exist, inspect `.soleil-ai-review-engine/meta.json` — the `stats.embeddings` field shows the count (0 means no embeddings). **Running analyze without `--embeddings` will delete any previously generated embeddings.**

> Claude Code users: A PostToolUse hook handles this automatically after `git commit` and `git merge`.

## CLI

| Task | Read this skill file |
|------|---------------------|
| Understand architecture / "How does X work?" | `.claude/skills/soleil-ai-review-engine/soleil-ai-review-engine-exploring/SKILL.md` |
| Blast radius / "What breaks if I change X?" | `.claude/skills/soleil-ai-review-engine/soleil-ai-review-engine-impact-analysis/SKILL.md` |
| Trace bugs / "Why is X failing?" | `.claude/skills/soleil-ai-review-engine/soleil-ai-review-engine-debugging/SKILL.md` |
| Rename / extract / split / refactor | `.claude/skills/soleil-ai-review-engine/soleil-ai-review-engine-refactoring/SKILL.md` |
| Tools, resources, schema reference | `.claude/skills/soleil-ai-review-engine/soleil-ai-review-engine-guide/SKILL.md` |
| Index, status, clean, wiki CLI commands | `.claude/skills/soleil-ai-review-engine/soleil-ai-review-engine-cli/SKILL.md` |
| Work in the Feature area (228 symbols) | `.claude/skills/generated/feature/SKILL.md` |
| Work in the Booking area (164 symbols) | `.claude/skills/generated/booking/SKILL.md` |
| Work in the Services area (163 symbols) | `.claude/skills/generated/services/SKILL.md` |
| Work in the Room area (100 symbols) | `.claude/skills/generated/room/SKILL.md` |
| Work in the Repositories area (90 symbols) | `.claude/skills/generated/repositories/SKILL.md` |
| Work in the Cache area (67 symbols) | `.claude/skills/generated/cache/SKILL.md` |
| Work in the Models area (67 symbols) | `.claude/skills/generated/models/SKILL.md` |
| Work in the Policies area (65 symbols) | `.claude/skills/generated/policies/SKILL.md` |
| Work in the Auth area (56 symbols) | `.claude/skills/generated/auth/SKILL.md` |
| Work in the Database area (43 symbols) | `.claude/skills/generated/database/SKILL.md` |
| Work in the Stays area (37 symbols) | `.claude/skills/generated/stays/SKILL.md` |
| Work in the Controllers area (36 symbols) | `.claude/skills/generated/controllers/SKILL.md` |
| Work in the Notifications area (30 symbols) | `.claude/skills/generated/notifications/SKILL.md` |
| Work in the Enums area (26 symbols) | `.claude/skills/generated/enums/SKILL.md` |
| Work in the Listeners area (26 symbols) | `.claude/skills/generated/listeners/SKILL.md` |
| Work in the Bookings area (26 symbols) | `.claude/skills/generated/bookings/SKILL.md` |
| Work in the Authorization area (24 symbols) | `.claude/skills/generated/authorization/SKILL.md` |
| Work in the Requests area (22 symbols) | `.claude/skills/generated/requests/SKILL.md` |
| Work in the Middleware area (22 symbols) | `.claude/skills/generated/middleware/SKILL.md` |
| Work in the Cluster_1 area (19 symbols) | `.claude/skills/generated/cluster-1/SKILL.md` |

<!-- soleil-ai-review-engine:end -->
