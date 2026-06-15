---
schema_version: 1.0
produced_by_batch: B4
phase: Phase B
date: 2026-03-25
input_artifacts:
  - foundation/00-master-contract.md
  - foundation/00-output-schemas.md
  - foundation/00-authority-order.md
  - CLAUDE.md
  - docs/cleanup/02-invariant-delta.md
  - docs/cleanup/01-classification-matrix.md
  - docs/agents/ARCHITECTURE_FACTS.md
  - docs/agents/CONTRACT.md
  - docs/PERMISSION_MATRIX.md
  - docs/DB_FACTS.md
  - docs/HOOKS.md
  - docs/COMPACT.md
  - AGENTS.md
  - .agent/rules/auth-token-safety.md
  - .agent/rules/booking-integrity.md
  - .agent/rules/migration-safety.md
authority_order_applied: true
unresolved_count: 2
---

# Rules Consolidation Report — Batch 4

## Rules consolidation table

| rule_id | canonical_file | source_files | conflicts_detected | resolution | downstream_references_updated |
|---------|----------------|--------------|--------------------|------------|-------------------------------|
| R-B4-01 | .agent/rules/instruction-surface-and-task-boundaries.md | CLAUDE.md, docs/agents/CONTRACT.md, docs/HOOKS.md, docs/COMPACT.md, .claude/commands/fix-backend.md, .claude/commands/fix-frontend.md, .claude/commands/review-pr.md, .claude/commands/ship.md, .claude/commands/sync-docs.md | YES — top-layer scope rules and lower-layer completion/bypass rules were duplicated across commands and docs | Preserved higher-authority wording in CLAUDE.md / CONTRACT.md, created one derived rule file for the lower rule layer, replaced command-level policy duplication with references | YES — .claude/commands/fix-backend.md, .claude/commands/fix-frontend.md, .claude/commands/review-pr.md, .claude/commands/ship.md, .claude/commands/sync-docs.md, skills/laravel/testing-skill.md |
| R-B4-02 | .agent/rules/backend-preserve-rbac-source-and-request-validation.md | CLAUDE.md, docs/PERMISSION_MATRIX.md, docs/agents/CONTRACT.md, skills/react/forms-validation-skill.md, .claude/commands/review-pr.md | YES — RBAC and validation ownership were restated in skills and review checklists | Used CLAUDE.md + docs/PERMISSION_MATRIX.md as authority, consolidated the lower-layer policy into one rule file, replaced downstream copies with references | YES — skills/react/forms-validation-skill.md, .claude/commands/review-pr.md, .claude/commands/fix-backend.md |
| R-B4-03 | .agent/rules/booking-integrity.md | CLAUDE.md, docs/agents/ARCHITECTURE_FACTS.md, docs/DB_FACTS.md, skills/laravel/booking-overlap-skill.md, skills/laravel/transactions-locking-skill.md, skills/react/forms-validation-skill.md, .claude/commands/audit-security.md, .claude/commands/review-pr.md, .claude/commands/ship.md | YES — booking overlap, soft-delete, and locking constraints were duplicated across skills, commands, and DB docs | Used higher-authority CLAUDE.md / ARCHITECTURE_FACTS.md wording as the source authority, normalized the fast-load rule, and replaced lower-layer copies with references | YES — skills/laravel/booking-overlap-skill.md, skills/laravel/transactions-locking-skill.md, skills/react/forms-validation-skill.md, .claude/commands/audit-security.md, .claude/commands/review-pr.md, .claude/commands/ship.md |
| R-B4-04 | .agent/rules/auth-token-safety.md | CLAUDE.md, docs/agents/ARCHITECTURE_FACTS.md, skills/laravel/auth-tokens-skill.md, skills/react/api-client-skill.md, skills/react/security-frontend-skill.md, .claude/commands/audit-security.md, .claude/commands/review-pr.md, .claude/commands/fix-frontend.md | YES — dual-mode auth, cookie chain, and CSRF constraints were duplicated across skills and commands | Preserved CLAUDE.md / ARCHITECTURE_FACTS.md authority, normalized the rule template, and replaced downstream duplicates with references | YES — skills/laravel/auth-tokens-skill.md, skills/react/api-client-skill.md, skills/react/security-frontend-skill.md, .claude/commands/audit-security.md, .claude/commands/review-pr.md, .claude/commands/fix-frontend.md |
| R-B4-05 | .agent/rules/security-runtime-hygiene.md | CLAUDE.md, docs/HOOKS.md, skills/laravel/security-secrets-skill.md, skills/react/security-frontend-skill.md, .claude/commands/audit-security.md | YES — secrets/config/sanitization rules were duplicated between skills, commands, and hook policy docs | Used CLAUDE.md as authority, consolidated the rule into one derived file, and replaced lower-layer non-negotiable blocks with references | YES — skills/laravel/security-secrets-skill.md, skills/react/security-frontend-skill.md, .claude/commands/audit-security.md, .claude/commands/fix-backend.md, .claude/commands/fix-frontend.md, .claude/commands/review-pr.md, .claude/commands/sync-docs.md |
| R-B4-06 | .agent/rules/frontend-preserve-boundaries-and-ui-standards.md | CLAUDE.md, docs/frontend/SERVICES_LAYER.md, docs/frontend/APP_LAYER.md, docs/frontend/RBAC.md, skills/react/api-client-skill.md, skills/react/typescript-patterns-skill.md, skills/react/forms-validation-skill.md, skills/react/security-frontend-skill.md, .claude/commands/fix-frontend.md, .claude/commands/review-pr.md | YES — frontend client, TS strict, import, copy, and validation rules were fragmented across multiple skills | Used CLAUDE.md as authority, consolidated the lower-layer frontend constraints into one rule file, and replaced skill/command copies with references | YES — skills/react/api-client-skill.md, skills/react/typescript-patterns-skill.md, skills/react/forms-validation-skill.md, skills/react/security-frontend-skill.md, .claude/commands/fix-frontend.md, .claude/commands/review-pr.md, .claude/commands/ship.md, .claude/commands/sync-docs.md |
| R-B4-07 | .agent/rules/migration-safety.md | CLAUDE.md, docs/agents/CONTRACT.md, docs/DB_FACTS.md, skills/laravel/migrations-postgres-skill.md, .claude/commands/fix-backend.md, .claude/commands/review-pr.md, .claude/commands/ship.md | YES — rollback, PG guard, and constraint-safety rules were duplicated across the skill and command layer | Kept higher-authority docs as source authority, normalized the rule template, and replaced downstream copies with references | YES — skills/laravel/migrations-postgres-skill.md, .claude/commands/fix-backend.md, .claude/commands/review-pr.md, .claude/commands/ship.md |
| R-B4-08 | .agent/rules/soleil-ai-review-engine-impact-and-change-scope.md | AGENTS.md, .claude/commands/fix-backend.md, .claude/commands/fix-frontend.md, .claude/commands/review-pr.md, .claude/commands/ship.md, .claude/commands/sync-docs.md | YES — soleil-ai-review-engine safety requirements were authoritative in AGENTS.md but absent from the derived rule layer | Bound the soleil-ai-review-engine policy to AGENTS.md authority, created a dedicated derived rule file, and pointed the active commands at that file | YES — .claude/commands/fix-backend.md, .claude/commands/fix-frontend.md, .claude/commands/review-pr.md, .claude/commands/ship.md, .claude/commands/sync-docs.md |

## Conflict resolution table

| conflict_id | source_a | source_b | nature | authority_applied | resolution |
|-------------|----------|----------|--------|-------------------|------------|
| C-B4-01 | CLAUDE.md | .agent/rules/instruction-surface-and-task-boundaries.md | Batch objective sought a canonical rules home, but CLAUDE.md remains higher-authority and out of scope | Level 1 — CLAUDE.md | Kept CLAUDE.md as constitutional authority and treated `.agent/rules/` as the canonical derived rule layer; downstream commands/skills now reference rules instead of restating policy |
| C-B4-02 | docs/agents/ARCHITECTURE_FACTS.md | docs/DB_FACTS.md | Booking overlap, soft-delete, and locking constraints were duplicated across two documentation layers | Level 2 — docs/agents/ARCHITECTURE_FACTS.md | Used ARCHITECTURE_FACTS.md as the authority source for `.agent/rules/booking-integrity.md`; did not strip the canonical/higher-layer docs in Batch 4 |
| C-B4-03 | docs/PERMISSION_MATRIX.md | skills/react/forms-validation-skill.md | Permission/validation ownership leaked into a skill file | Level 4 — docs/PERMISSION_MATRIX.md | Moved the lower-layer policy to `.agent/rules/backend-preserve-rbac-source-and-request-validation.md` and replaced the skill copy with a reference |
| C-B4-04 | CLAUDE.md | skills/react/typescript-patterns-skill.md | Frontend standards from the constitution were duplicated and expanded inside skill-level policy prose | Level 1 — CLAUDE.md | Consolidated lower-layer frontend policy into `.agent/rules/frontend-preserve-boundaries-and-ui-standards.md` and replaced skill copies with rule references |
| C-B4-05 | CLAUDE.md | skills/laravel/security-secrets-skill.md | Security/config/secrets rules were duplicated across lower-layer skills and commands | Level 1 — CLAUDE.md | Consolidated the lower-layer copy into `.agent/rules/security-runtime-hygiene.md` and replaced the skill/command duplication with references |
| C-B4-06 | AGENTS.md | .claude/commands/review-pr.md | soleil-ai-review-engine execution safety lived in AGENTS.md but not in the derived rule layer, so command files had no canonical rule target | Level 1 for AGENTS.md within the repo-local soleil-ai-review-engine contract | Created `.agent/rules/soleil-ai-review-engine-impact-and-change-scope.md` as the derived rule target and pointed active commands at it |

## Downstream replacement map (file → reference updated)

| file | reference_updated |
|------|-------------------|
| .claude/commands/audit-security.md | Added canonical rule references for auth, security hygiene, booking integrity, backend authority, and task boundaries; converted duplicated checklist policy into inspection phrasing |
| .claude/commands/fix-backend.md | Added canonical rule references for booking, migration, backend authority, security hygiene, task boundaries, and soleil-ai-review-engine |
| .claude/commands/fix-frontend.md | Added canonical rule references for frontend, auth, security hygiene, task boundaries, and soleil-ai-review-engine |
| .claude/commands/review-pr.md | Added canonical rule references and replaced duplicated architecture/security policy bullets with rule-based review checks |
| .claude/commands/ship.md | Added canonical rule references for release-blocking domains |
| .claude/commands/sync-docs.md | Added canonical rule references for instruction-surface, backend, frontend, security, and soleil-ai-review-engine checks |
| skills/laravel/auth-tokens-skill.md | Replaced `## Non-negotiables` with references to `.agent/rules/auth-token-safety.md` and `.agent/rules/security-runtime-hygiene.md` |
| skills/laravel/booking-overlap-skill.md | Replaced `## Non-negotiables` with references to `.agent/rules/booking-integrity.md` and `.agent/rules/migration-safety.md` |
| skills/laravel/migrations-postgres-skill.md | Replaced `## Non-negotiables` with references to `.agent/rules/migration-safety.md` and `.agent/rules/booking-integrity.md` |
| skills/laravel/transactions-locking-skill.md | Replaced `## Non-negotiables` with references to `.agent/rules/booking-integrity.md` and `.agent/rules/migration-safety.md` |
| skills/laravel/security-secrets-skill.md | Replaced `## Non-negotiables` with references to `.agent/rules/security-runtime-hygiene.md` and `.agent/rules/auth-token-safety.md` |
| skills/laravel/testing-skill.md | Replaced `## Non-negotiables` with references to `.agent/rules/instruction-surface-and-task-boundaries.md`, `.agent/rules/booking-integrity.md`, and `.agent/rules/auth-token-safety.md` |
| skills/react/api-client-skill.md | Replaced `## Non-negotiables` with references to `.agent/rules/frontend-preserve-boundaries-and-ui-standards.md` and `.agent/rules/auth-token-safety.md` |
| skills/react/typescript-patterns-skill.md | Replaced `## Non-negotiables` with a reference to `.agent/rules/frontend-preserve-boundaries-and-ui-standards.md` |
| skills/react/forms-validation-skill.md | Replaced `## Non-negotiables` with references to backend/frontend/booking rule files |
| skills/react/security-frontend-skill.md | Replaced `## Non-negotiables` with references to auth, security hygiene, and frontend rules |

## Unresolved items

| id | description | evidence_missing | blocks_batch |
|----|-------------|------------------|--------------|
| UNRESOLVED-B4-1 | Higher-authority files (`CLAUDE.md`, `docs/agents/ARCHITECTURE_FACTS.md`, `docs/agents/CONTRACT.md`, `docs/PERMISSION_MATRIX.md`, `docs/DB_FACTS.md`) still contain non-negotiable statements by design, so `.agent/rules/` is now the canonical derived rules layer rather than a replacement for higher-authority policy text | Formal decision to rewrite or demote higher-authority sources, which Batch 4 does not own | no |
| UNRESOLVED-B4-2 | Lower-surface docs not edited in this pass (for example `docs/agents/COMMANDS.md`, `docs/COMMANDS_AND_GATES.md`, `docs/HOOKS.md`) still contain rule-like prose; fully thinning those documents would cross later batch ownership and the repo's >25-file escalation boundary | Human approval to exceed the 25-file boundary or a later-batch rewrite of commands/hooks docs | no |
