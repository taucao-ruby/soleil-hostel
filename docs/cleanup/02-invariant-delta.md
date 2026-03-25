---
schema_version: 1.0
produced_by_batch: B3
phase: Phase B
date: 2026-03-25
input_artifacts:
  - foundation/00-master-contract.md
  - foundation/00-output-schemas.md
  - foundation/00-authority-order.md
  - docs/cleanup/01-classification-matrix.md
  - CLAUDE.md
  - AGENTS.md
  - README.md
  - docs/agents/ARCHITECTURE_FACTS.md
  - docs/agents/CONTRACT.md
  - docs/agents/COMMANDS.md
  - docs/PERMISSION_MATRIX.md
  - docs/DB_FACTS.md
  - docs/DOMAIN_LAYERS.md
  - docs/COMMANDS_AND_GATES.md
  - docs/HOOKS.md
  - docs/MCP.md
  - docs/AI_GOVERNANCE.md
  - docs/COMPACT.md
  - docs/FINDINGS_BACKLOG.md
  - docs/frontend/SERVICES_LAYER.md
  - docs/frontend/RBAC.md
  - docs/frontend/APP_LAYER.md
  - skills/README.md
  - skills/react/typescript-patterns-skill.md
  - skills/react/api-client-skill.md
  - skills/laravel/api-endpoints-skill.md
  - .agent/rules/booking-integrity.md
  - .agent/rules/auth-token-safety.md
  - .agent/rules/migration-safety.md
  - .claude/skills/gitnexus/gitnexus-guide/SKILL.md
  - .claude/skills/gitnexus/gitnexus-impact-analysis/SKILL.md
  - .claude/skills/gitnexus/gitnexus-refactoring/SKILL.md
authority_order_applied: true
unresolved_count: 0
---

# Invariant Delta — Batch 3

## Invariant baseline (before refactor)

24 grouped invariants were extracted from the pre-refactor `CLAUDE.md`.
Grouping was used only where the file expressed one compound constraint across several bullets
or repeated the same rule in more than one place. Distinct-invariant count after de-duplication:
24, which is below the batch stop threshold.

1. `I-01` `CLAUDE.md:3-4` — `CLAUDE.md` is constitutional only; detailed facts belong in referenced files, not in the root contract body.
2. `I-02` `CLAUDE.md:19` — `docs/PERMISSION_MATRIX.md` is the canonical RBAC permission baseline / single source of truth.
3. `I-03` `CLAUDE.md:32-34` — Booking overlap uses half-open intervals, only `pending` and `confirmed` block availability, and the PostgreSQL exclusion constraint keeps `deleted_at IS NULL`.
4. `I-04` `CLAUDE.md:35` — `bookings.location_id` is intentional denormalization and must not be removed.
5. `I-05` `CLAUDE.md:36` — One review belongs to one booking and must carry `booking_id`.
6. `I-06` `CLAUDE.md:39-40` — Booking-critical writes require `lockForUpdate()`, and optimistic locking via `lock_version` must not be silently skipped.
7. `I-07` `CLAUDE.md:43-45,54` — Dual auth, CSRF flow, token lookup chain, token validity checks, and `withCredentials: true` remain intact.
8. `I-08` `CLAUDE.md:46-48,105` — Secrets must never be committed, user input must go through `HtmlPurifierService`, and runtime code must use `config()` instead of `env()`.
9. `I-09` `CLAUDE.md:51,105` — Backend architecture remains Controller -> Service -> Repository, with request validation in `*Request.php`, not controllers.
10. `I-10` `CLAUDE.md:52-54` — Frontend architecture remains feature-sliced and uses `@/shared/lib/api` as the single API client.
11. `I-11` `CLAUDE.md:58-62` — Frontend work remains TypeScript-strict, free of production `console.log`, and Vietnamese for user-facing copy.
12. `I-12` `CLAUDE.md:60-63` — Frontend library/pattern constraints remain: no React Query, no Zod, no `react-hot-toast`, use `useState` + `useEffect` + `AbortController`, and use `vi.hoisted()` for shared mutable mock state.
13. `I-13` `CLAUDE.md:64-65,103` — Frontend boundary rules remain: cross-feature import restriction, `/v1/` API versioning, and no legacy unversioned endpoint use in booking APIs.
14. `I-14` `CLAUDE.md:69-75` — Code-task quality gates must pass before commit.
15. `I-15` `CLAUDE.md:76` — Docs-only tasks use documentation checks only, and new behavior requires new tests.
16. `I-16` `CLAUDE.md:89-90` — Docs-only tasks must stop and confirm before changing application or infrastructure surfaces.
17. `I-17` `CLAUDE.md:91` — Changes to booking overlap logic, auth token flow, or migration constraints require escalation before proceeding.
18. `I-18` `CLAUDE.md:92-95` — More-than-25-file diffs, `--no-verify`, new gate failures, or missing required files require escalation before proceeding.
19. `I-19` `CLAUDE.md:97` — Out-of-scope bugs belong in `docs/FINDINGS_BACKLOG.md` and must not be fixed inline.
20. `I-20` `CLAUDE.md:101` — The shared API client's CSRF interceptor must not be changed without first reading `docs/frontend/SERVICES_LAYER.md`.
21. `I-21` `CLAUDE.md:102` — Frontend routing keeps `PublicLayout` on `/`, `ProtectedRoute` + `Suspense` on protected routes, and `DashboardPage` lazy loaded with internal role routing.
22. `I-22` `CLAUDE.md:103` — `frontend/src/features/booking/booking.api.ts` stays on `/v1/` and must not call legacy unversioned endpoints.
23. `I-23` `CLAUDE.md:104` — `docs/COMPACT.md` remains a volatile handoff log; section 1 is edited in place, history appends, and section 1 stays under 12 lines.
24. `I-24` `CLAUDE.md:114-140,170-176` — GitNexus safety workflow governs impact analysis, refactor workflow, and pre-commit scope checks.

## Invariant tracking table

| invariant | original_location | disposition | new_location | justification |
|-----------|-------------------|-------------|--------------|---------------|
| I-01 — `CLAUDE.md` stays constitutional and delegates detail to referenced files | `CLAUDE.md:3-4` | PRESERVED_IN_PLACE | `CLAUDE.md — ## Mission` | Restated as the root contract scope statement. |
| I-02 — `docs/PERMISSION_MATRIX.md` is the RBAC permission source of truth | `CLAUDE.md:19` | PRESERVED_IN_PLACE | `CLAUDE.md — ## Non-negotiable constraints` | The permission truth remains explicit in the constitution. |
| I-03 — Booking overlap uses half-open intervals, active statuses only, and `deleted_at IS NULL` | `CLAUDE.md:32-34` | PRESERVED_IN_PLACE | `CLAUDE.md — ## Domain truths` | Preserved as a single booking-integrity truth. |
| I-04 — `bookings.location_id` remains intentional denormalization | `CLAUDE.md:35` | PRESERVED_IN_PLACE | `CLAUDE.md — ## Domain truths` | The denormalization is still called out explicitly. |
| I-05 — One review belongs to one booking and carries `booking_id` | `CLAUDE.md:36` | PRESERVED_IN_PLACE | `CLAUDE.md — ## Domain truths` | Review integrity remains in the root domain summary. |
| I-06 — Booking-critical writes require `lockForUpdate()` and effective `lock_version` handling | `CLAUDE.md:39-40` | PRESERVED_IN_PLACE | `CLAUDE.md — ## Domain truths` | Concurrency truth remains explicit. |
| I-07 — Dual auth, CSRF flow, token lookup chain, validity checks, and `withCredentials: true` stay intact | `CLAUDE.md:43-45,54` | PRESERVED_IN_PLACE | `CLAUDE.md — ## Domain truths` | Consolidated into one auth/session truth without loss. |
| I-08 — Never commit secrets; use `HtmlPurifierService`; avoid runtime `env()` | `CLAUDE.md:46-48,105` | PRESERVED_IN_PLACE | `CLAUDE.md — ## Non-negotiable constraints` | Security and runtime hygiene remain explicit. |
| I-09 — Backend stays Controller -> Service -> Repository with `*Request.php` validation | `CLAUDE.md:51,105` | PRESERVED_IN_PLACE | `CLAUDE.md — ## Domain truths`; `CLAUDE.md — ## Non-negotiable constraints` | Architecture and validation ownership remain in the constitution. |
| I-10 — Frontend stays feature-sliced and uses the shared API client only | `CLAUDE.md:52-54` | PRESERVED_IN_PLACE | `CLAUDE.md — ## Domain truths` | The frontend structural boundary remains explicit. |
| I-11 — Frontend remains TypeScript-strict, free of production `console.log`, and Vietnamese for user-facing copy | `CLAUDE.md:58-62` | PRESERVED_IN_PLACE | `CLAUDE.md — ## Non-negotiable constraints` | Preserved as constitutional frontend constraints. |
| I-12 — Frontend library/pattern constraints stay in force | `CLAUDE.md:60-63` | RELOCATED_WITH_REFERENCE | `skills/react/typescript-patterns-skill.md` | Detailed React implementation patterns are skill-level guidance, not constitutional text. |
| I-13 — Frontend boundary rules for imports and API versioning stay in force | `CLAUDE.md:64-65,103` | RELOCATED_WITH_REFERENCE | `skills/react/typescript-patterns-skill.md`; `skills/react/api-client-skill.md`; `docs/frontend/SERVICES_LAYER.md` | Detailed file-level frontend boundaries were reduced to references from the constitution. |
| I-14 — Code-task quality gates must pass before commit | `CLAUDE.md:69-75` | PRESERVED_IN_PLACE | `CLAUDE.md — ## Non-negotiable constraints` | The requirement remains in place; detailed command syntax moved by reference. |
| I-15 — Docs-only tasks use documentation checks only, and new behavior requires new tests | `CLAUDE.md:76` | PRESERVED_IN_PLACE | `CLAUDE.md — ## Non-negotiable constraints` | Preserved as a root constraint with the detailed DoD delegated to `docs/agents/CONTRACT.md`. |
| I-16 — Docs-only tasks must escalate before changing application or infrastructure surfaces | `CLAUDE.md:89-90` | PRESERVED_IN_PLACE | `CLAUDE.md — ## Escalation rules` | The same escalation trigger remains explicit. |
| I-17 — Changes to booking overlap logic, auth token flow, or migration constraints require escalation | `CLAUDE.md:91` | PRESERVED_IN_PLACE | `CLAUDE.md — ## Escalation rules` | Preserved as a high-risk escalation trigger. |
| I-18 — More-than-25-file diffs, `--no-verify`, new gate failures, or missing required files require escalation | `CLAUDE.md:92-95` | PRESERVED_IN_PLACE | `CLAUDE.md — ## Escalation rules` | Consolidated into one escalation rule without dropping any trigger. |
| I-19 — Out-of-scope bugs go to `docs/FINDINGS_BACKLOG.md` and must not be fixed inline | `CLAUDE.md:97` | PRESERVED_IN_PLACE | `CLAUDE.md — ## Non-negotiable constraints` | Preserved exactly as a root boundary rule. |
| I-20 — Do not change the shared API client's CSRF interceptor before reading `docs/frontend/SERVICES_LAYER.md` | `CLAUDE.md:101` | RELOCATED_WITH_REFERENCE | `docs/frontend/SERVICES_LAYER.md` | File-specific frontend guidance does not belong in the constitution. |
| I-21 — Frontend routing keeps `PublicLayout`, `ProtectedRoute`, `Suspense`, and lazy `DashboardPage` role routing | `CLAUDE.md:102` | RELOCATED_WITH_REFERENCE | `docs/frontend/RBAC.md`; `docs/frontend/APP_LAYER.md` | Router-layout specifics are frontend reference material, not root-contract text. |
| I-22 — `frontend/src/features/booking/booking.api.ts` stays `/v1/`-only and avoids legacy endpoints | `CLAUDE.md:103` | RELOCATED_WITH_REFERENCE | `skills/react/api-client-skill.md`; `docs/frontend/SERVICES_LAYER.md` | This is a file-level API client rule and was reduced to references. |
| I-23 — `docs/COMPACT.md` stays a volatile handoff log with section-1 editing limits | `CLAUDE.md:104` | RELOCATED_WITH_REFERENCE | `docs/COMPACT.md` | Compact lifecycle rules belong to the compact surface itself, not the constitution. |
| I-24 — GitNexus safety workflow governs impact analysis, refactor workflow, and pre-commit checks | `CLAUDE.md:114-140,170-176` | RELOCATED_WITH_REFERENCE | `docs/MCP.md`; `.claude/skills/gitnexus/gitnexus-guide/SKILL.md`; `.claude/skills/gitnexus/gitnexus-impact-analysis/SKILL.md`; `.claude/skills/gitnexus/gitnexus-refactoring/SKILL.md` | Boundary/tooling workflow was removed from the constitution and replaced with explicit references. |

## Content relocation map (source → destination for all moved content)

- `CLAUDE.md:6-11` Project Identity -> kept only repo purpose and branch flow in `CLAUDE.md — ## Mission`; removed exact stack/version/infrastructure inventory as non-constitutional descriptive context.
- `CLAUDE.md:13-25` Canonical References -> redistributed between `CLAUDE.md — ## Decision order` and `CLAUDE.md — ## Document map`.
- `CLAUDE.md:27-54` Non-Negotiable Invariants summary -> split between `CLAUDE.md — ## Domain truths` and `CLAUDE.md — ## Non-negotiable constraints`.
- `CLAUDE.md:56-65` Frontend Rules -> kept only constitution-level boundaries in `CLAUDE.md — ## Non-negotiable constraints`; detailed implementation rules moved by reference to `skills/react/typescript-patterns-skill.md`, `skills/react/api-client-skill.md`, `docs/frontend/SERVICES_LAYER.md`, `docs/frontend/RBAC.md`, and `docs/frontend/APP_LAYER.md`.
- `CLAUDE.md:67-76` Validation Gates -> kept only the gate requirement in `CLAUDE.md — ## Non-negotiable constraints`; detailed command syntax moved by reference to `docs/agents/CONTRACT.md`, `docs/agents/COMMANDS.md`, and `docs/COMMANDS_AND_GATES.md`.
- `CLAUDE.md:78-85` Commit Format -> moved by reference to `docs/HOOKS.md`.
- `CLAUDE.md:87-97` Editing Boundaries -> moved into `CLAUDE.md — ## Escalation rules`.
- `CLAUDE.md:99-105` File-Specific Rules -> removed from the constitution and replaced with references to `docs/frontend/SERVICES_LAYER.md`, `docs/frontend/RBAC.md`, `docs/frontend/APP_LAYER.md`, `docs/COMPACT.md`, `skills/laravel/api-endpoints-skill.md`, and `skills/react/api-client-skill.md`.
- `CLAUDE.md:107-225` GitNexus section -> moved by reference to `docs/MCP.md` and `.claude/skills/gitnexus/`; generated skill index content moved by reference to `skills/README.md` and `.claude/skills/generated/`.

## CLAUDE.md structure after refactor (section headers only)

```md
## Mission
## Domain truths
## Non-negotiable constraints
## Decision order
## Document map
## Escalation rules
```

## Unresolved items

None.
