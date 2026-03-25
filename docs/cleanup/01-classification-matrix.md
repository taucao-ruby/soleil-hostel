---
schema_version: 1.0
produced_by_batch: B2
phase: Phase A (re-executed)
date: 2026-03-25
input_artifacts:
  - docs/cleanup/00-inventory.md
  - foundation/00-master-contract.md
  - foundation/00-output-schemas.md
authority_order_applied: true
unresolved_count: 4
note: >
  21 of 191 inventory files no longer exist on disk (deleted in commit 3b57cf2
  on 2026-03-22 after inventory was produced). These are marked DELETED_FROM_DISK
  in the recommended_action column. Remaining 170 files are classified from
  inspected content.
---

# Classification Matrix

> Batch 2 output (re-executed) | Generated: 2026-03-25 | Branch: dev

## Classification table

### Root contracts (2 files)

| path | observed_bucket | correct_bucket | mismatch | mixed_responsibilities | tie_break_applied | recommended_action |
|------|----------------|---------------|----------|----------------------|-------------------|-------------------|
| CLAUDE.md | CONSTITUTION | CONSTITUTION | NO | YES — contains GitNexus section (BOUNDARY_CONTRACT scope) and generated skill index (SKILLS scope) | — | SPLIT — extract GitNexus + skill index to dedicated references |
| AGENTS.md | CONSTITUTION | CONSTITUTION | NO | YES — agent onboarding index (CONSTITUTION) + GitNexus documentation (BOUNDARY_CONTRACT, ~120 lines duplicated from CLAUDE.md) | — | MERGE — deduplicate GitNexus into single location |

### Settings & configuration (3 files)

| path | observed_bucket | correct_bucket | mismatch | mixed_responsibilities | tie_break_applied | recommended_action |
|------|----------------|---------------|----------|----------------------|-------------------|-------------------|
| .claude/settings.json | HOOKS | HOOKS | NO | NO | — | KEEP |
| .claude/settings.local.json | HOOKS | HOOKS | NO | NO | — | KEEP |
| .lintstagedrc.json | HOOKS | HOOKS | NO | NO | — | KEEP |

### Commands (6 files)

| path | observed_bucket | correct_bucket | mismatch | mixed_responsibilities | tie_break_applied | recommended_action |
|------|----------------|---------------|----------|----------------------|-------------------|-------------------|
| .claude/commands/review-pr.md | COMMANDS | COMMANDS | NO | NO | — | KEEP |
| .claude/commands/ship.md | COMMANDS | COMMANDS | NO | NO | — | KEEP |
| .claude/commands/audit-security.md | COMMANDS | COMMANDS | NO | NO | — | KEEP |
| .claude/commands/sync-docs.md | COMMANDS | COMMANDS | NO | NO | — | KEEP |
| .claude/commands/fix-backend.md | COMMANDS | COMMANDS | NO | NO | — | KEEP |
| .claude/commands/fix-frontend.md | COMMANDS | COMMANDS | NO | NO | — | KEEP |

### Subagents (4 files)

| path | observed_bucket | correct_bucket | mismatch | mixed_responsibilities | tie_break_applied | recommended_action |
|------|----------------|---------------|----------|----------------------|-------------------|-------------------|
| .claude/agents/security-reviewer.md | AGENTS | AGENTS | NO | NO | — | KEEP |
| .claude/agents/frontend-reviewer.md | AGENTS | AGENTS | NO | NO | — | KEEP |
| .claude/agents/docs-sync.md | AGENTS | AGENTS | NO | NO | — | KEEP |
| .claude/agents/db-investigator.md | AGENTS | AGENTS | NO | NO | — | KEEP |

### Skills — Claude native GitNexus (6 files)

| path | observed_bucket | correct_bucket | mismatch | mixed_responsibilities | tie_break_applied | recommended_action |
|------|----------------|---------------|----------|----------------------|-------------------|-------------------|
| .claude/skills/gitnexus/gitnexus-cli/SKILL.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| .claude/skills/gitnexus/gitnexus-debugging/SKILL.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| .claude/skills/gitnexus/gitnexus-exploring/SKILL.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| .claude/skills/gitnexus/gitnexus-guide/SKILL.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| .claude/skills/gitnexus/gitnexus-impact-analysis/SKILL.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| .claude/skills/gitnexus/gitnexus-refactoring/SKILL.md | SKILLS | SKILLS | NO | NO | — | KEEP |

### Skills — generated (20 inventory entries; 17 exist, 3 deleted)

| path | observed_bucket | correct_bucket | mismatch | mixed_responsibilities | tie_break_applied | recommended_action |
|------|----------------|---------------|----------|----------------------|-------------------|-------------------|
| .claude/skills/generated/auth/SKILL.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| .claude/skills/generated/authorization/SKILL.md | SKILLS | SKILLS | NO | NO | — | DELETED_FROM_DISK |
| .claude/skills/generated/booking/SKILL.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| .claude/skills/generated/bookings/SKILL.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| .claude/skills/generated/cache/SKILL.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| .claude/skills/generated/cluster-1/SKILL.md | SKILLS | SKILLS | NO | NO | — | DELETED_FROM_DISK |
| .claude/skills/generated/controllers/SKILL.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| .claude/skills/generated/database/SKILL.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| .claude/skills/generated/feature/SKILL.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| .claude/skills/generated/listeners/SKILL.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| .claude/skills/generated/middleware/SKILL.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| .claude/skills/generated/models/SKILL.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| .claude/skills/generated/notifications/SKILL.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| .claude/skills/generated/operations/SKILL.md | SKILLS | SKILLS | NO | NO | — | DELETED_FROM_DISK |
| .claude/skills/generated/policies/SKILL.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| .claude/skills/generated/repositories/SKILL.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| .claude/skills/generated/requests/SKILL.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| .claude/skills/generated/room/SKILL.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| .claude/skills/generated/services/SKILL.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| .claude/skills/generated/stays/SKILL.md | SKILLS | SKILLS | NO | NO | — | KEEP |

### Skills — reference library (18 files)

| path | observed_bucket | correct_bucket | mismatch | mixed_responsibilities | tie_break_applied | recommended_action |
|------|----------------|---------------|----------|----------------------|-------------------|-------------------|
| skills/README.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| skills/laravel/api-endpoints-skill.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| skills/laravel/auth-tokens-skill.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| skills/laravel/booking-overlap-skill.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| skills/laravel/migrations-postgres-skill.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| skills/laravel/security-secrets-skill.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| skills/laravel/testing-skill.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| skills/laravel/transactions-locking-skill.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| skills/react/api-client-skill.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| skills/react/component-quality-skill.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| skills/react/forms-validation-skill.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| skills/react/performance-core-web-vitals-skill.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| skills/react/security-frontend-skill.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| skills/react/testing-vitest-skill.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| skills/react/typescript-patterns-skill.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| skills/ops/ci-quality-gates-skill.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| skills/ops/docker-compose-skill.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| skills/ops/logging-observability-skill.md | SKILLS | SKILLS | NO | NO | — | KEEP |

### Skill OS — verification framework (17 inventory entries; ALL deleted from disk)

All 17 skill-os markdown files were deleted in commit `3b57cf2` (2026-03-22 23:46). The `skill-os/` directory retains only runtime subdirectories (`logs/`, `outputs/`, `scripts/`, `test-data/`).

| path | observed_bucket | correct_bucket | mismatch | mixed_responsibilities | tie_break_applied | recommended_action |
|------|----------------|---------------|----------|----------------------|-------------------|-------------------|
| skill-os/README.md | SKILLS | SKILLS | NO | NO | — | DELETED_FROM_DISK |
| skill-os/STRUCTURE.md | SKILLS | SKILLS | NO | NO | — | DELETED_FROM_DISK |
| skill-os/TAXONOMY.md | SKILLS | SKILLS | NO | NO | — | DELETED_FROM_DISK |
| skill-os/OPERATING-GUIDE.md | SKILLS | SKILLS | NO | NO | — | DELETED_FROM_DISK |
| skill-os/VERIFICATION-FRAMEWORK.md | SKILLS | SKILLS | NO | NO | — | DELETED_FROM_DISK |
| skill-os/RISK-REGISTER.md | SKILLS | SKILLS | NO | NO | — | DELETED_FROM_DISK |
| skill-os/ROLLOUT-14DAY.md | SKILLS | SKILLS | NO | NO | — | DELETED_FROM_DISK |
| skill-os/BACKLOG.md | SKILLS | SKILLS | NO | NO | — | DELETED_FROM_DISK |
| skill-os/context/INVARIANTS.md | RULES | SKILLS | PARTIAL | YES — invariant content (RULES) in skills directory | Tie-break: SKILLS (narrower scope, co-located with verification skills) | DELETED_FROM_DISK |
| skill-os/lessons/booking-invariant-gotchas.md | SKILLS | SKILLS | NO | NO | — | DELETED_FROM_DISK |
| skill-os/skills/release/pre-release-verification/SKILL.md | SKILLS | SKILLS | NO | NO | — | DELETED_FROM_DISK |
| skill-os/skills/review/review-schema-change-risk/SKILL.md | SKILLS | SKILLS | NO | NO | — | DELETED_FROM_DISK |
| skill-os/skills/verification/verify-docs-vs-code/SKILL.md | SKILLS | SKILLS | NO | NO | — | DELETED_FROM_DISK |
| skill-os/skills/verification/verify-no-double-booking/SKILL.md | SKILLS | SKILLS | NO | NO | — | DELETED_FROM_DISK |
| skill-os/skills/verification/verify-no-double-booking/checklist.md | SKILLS | SKILLS | NO | NO | — | DELETED_FROM_DISK |
| skill-os/templates/migration-risk-review.md | SKILLS | SKILLS | NO | NO | — | DELETED_FROM_DISK |
| skill-os/templates/release-readiness-report.md | SKILLS | SKILLS | NO | NO | — | DELETED_FROM_DISK |

### Output styles (2 files)

| path | observed_bucket | correct_bucket | mismatch | mixed_responsibilities | tie_break_applied | recommended_action |
|------|----------------|---------------|----------|----------------------|-------------------|-------------------|
| .claude/output-styles/execution.md | UNCLEAR | COMMANDS | YES | NO | Tie-break: COMMANDS — output format consumed by commands, lower runtime responsibility than standalone | KEEP |
| .claude/output-styles/audit.md | UNCLEAR | COMMANDS | YES | NO | Tie-break: COMMANDS — same rationale | KEEP |

### Hooks (3 shell scripts)

| path | observed_bucket | correct_bucket | mismatch | mixed_responsibilities | tie_break_applied | recommended_action |
|------|----------------|---------------|----------|----------------------|-------------------|-------------------|
| .claude/hooks/block-dangerous-bash.sh | HOOKS | HOOKS | NO | NO | — | KEEP |
| .claude/hooks/guard-sensitive-files.sh | HOOKS | HOOKS | NO | NO | — | KEEP |
| .claude/hooks/remind-frontend-validation.sh | HOOKS | HOOKS | NO | NO | — | KEEP |

### Agent operating layer — .agent/ (9 files)

| path | observed_bucket | correct_bucket | mismatch | mixed_responsibilities | tie_break_applied | recommended_action |
|------|----------------|---------------|----------|----------------------|-------------------|-------------------|
| .agent/architecture.md | RULES | RULES | NO | NO | — | KEEP |
| .agent/rules/auth-token-safety.md | RULES | RULES | NO | NO | — | KEEP |
| .agent/rules/booking-integrity.md | RULES | RULES | NO | NO | — | KEEP |
| .agent/rules/migration-safety.md | RULES | RULES | NO | NO | — | KEEP |
| .agent/scripts/check-locking-coverage.sh | HOOKS | HOOKS | NO | NO | — | KEEP |
| .agent/scripts/check-migration-safety.sh | HOOKS | HOOKS | NO | NO | — | KEEP |
| .agent/workflows/auth-change.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| .agent/workflows/booking-domain-change.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| .agent/workflows/new-migration.md | SKILLS | SKILLS | NO | NO | — | KEEP |

### Rules & policy documents (12 files)

| path | observed_bucket | correct_bucket | mismatch | mixed_responsibilities | tie_break_applied | recommended_action |
|------|----------------|---------------|----------|----------------------|-------------------|-------------------|
| docs/agents/ARCHITECTURE_FACTS.md | RULES | RULES | NO | NO | — | KEEP |
| docs/agents/CONTRACT.md | RULES | RULES | NO | NO | — | KEEP |
| docs/agents/COMMANDS.md | COMMANDS | RULES | YES | YES — gate commands (COMMANDS) + agent setup (RULES) | Tie-break: RULES — canonical reference, not an invocable command | KEEP |
| docs/agents/README.md | RULES | RULES | NO | NO | — | KEEP |
| docs/PERMISSION_MATRIX.md | RULES | RULES | NO | NO | — | KEEP |
| docs/COMMANDS_AND_GATES.md | RULES | RULES | NO | YES — gate definitions (RULES) + runnable commands (COMMANDS) | — | KEEP |
| docs/FINDINGS_BACKLOG.md | RULES | RULES | NO | NO | — | KEEP |
| docs/DOMAIN_LAYERS.md | RULES | RULES | NO | NO | — | KEEP |
| docs/DB_FACTS.md | RULES | RULES | NO | YES — invariants (RULES) + query patterns (SKILLS) | — | SPLIT |
| docs/DATABASE.md | RULES | RULES | NO | YES — invariants (RULES) + DDL reference (SKILLS) | — | KEEP |
| docs/AI_GOVERNANCE.md | RULES | RULES | NO | YES — governance rules (RULES) + task checklist procedures (SKILLS) | — | KEEP |
| docs/governance/instruction-system-maintenance.md | SKILLS | SKILLS | NO | YES — maintenance procedures (SKILLS) + layer architecture diagram (RULES) | Tie-break: SKILLS — primarily procedural with ordered steps | KEEP |

### Volatile / session state (2 files)

| path | observed_bucket | correct_bucket | mismatch | mixed_responsibilities | tie_break_applied | recommended_action |
|------|----------------|---------------|----------|----------------------|-------------------|-------------------|
| docs/COMPACT.md | COMPACT_SNAPSHOT | COMPACT_SNAPSHOT | NO | NO | — | KEEP |
| docs/WORKLOG.md | WORKLOG_LEDGER | WORKLOG_LEDGER | NO | NO | — | KEEP |

### Reference documentation — general (14 files; 1 deleted)

| path | observed_bucket | correct_bucket | mismatch | mixed_responsibilities | tie_break_applied | recommended_action |
|------|----------------|---------------|----------|----------------------|-------------------|-------------------|
| docs/README.md | RULES | RULES | NO | NO | — | KEEP |
| docs/ADR.md | RULES | RULES | NO | NO | — | KEEP |
| docs/API_DEPRECATION.md | RULES | RULES | NO | NO | — | KEEP |
| docs/AUDIT_2026_02_21.md | RULES | COMPACT_SNAPSHOT | YES | NO | — | KEEP |
| docs/AUDIT_2026_03_12_STRUCTURE.md | RULES | COMPACT_SNAPSHOT | YES | NO | — | ARCHIVE |
| docs/CONTRIBUTING-TOOLCHAIN.md | RULES | SKILLS | YES | NO | Tie-break: SKILLS — procedural guide with setup steps | KEEP |
| docs/CORE_FEATURES_PROMPT.md | RULES | RULES | NO | NO | — | KEEP |
| docs/development_hooks.md | HOOKS | HOOKS | NO | NO | — | DELETE_CANDIDATE |
| docs/HOOKS.md | HOOKS | HOOKS | NO | NO | — | KEEP |
| docs/KNOWN_LIMITATIONS.md | RULES | RULES | NO | NO | — | KEEP |
| docs/MCP.md | BOUNDARY_CONTRACT | BOUNDARY_CONTRACT | NO | NO | — | KEEP |
| docs/MIGRATION_GUIDE.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| docs/OPERATIONAL_PLAYBOOK.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| docs/PERFORMANCE_BASELINE.md | RULES | RULES | NO | NO | — | KEEP |
| docs/OPERATIONAL_DOMAIN_ARCHITECTURE_NOTES_PASS_2.md | RULES | RULES | NO | NO | — | DELETED_FROM_DISK |

### Reference documentation — backend top-level (9 files)

| path | observed_bucket | correct_bucket | mismatch | mixed_responsibilities | tie_break_applied | recommended_action |
|------|----------------|---------------|----------|----------------------|-------------------|-------------------|
| docs/backend/README.md | RULES | RULES | NO | NO | — | KEEP |
| docs/backend/BOOKING_CANCELLATION_FLOW.md | RULES | RULES | NO | NO | — | KEEP |
| docs/backend/BOOKING_CONFIRMATION_NOTIFICATION_ARCHITECTURE.md | RULES | RULES | NO | NO | — | KEEP |
| docs/backend/CACHE_WARMUP_STRATEGY.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| docs/backend/DEPLOYMENT.md | SKILLS | SKILLS | NO | NO | — | INVESTIGATE |
| docs/backend/QUEUE_MONITORING.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| docs/backend/REVIEW_POLICY_AUTHORIZATION.md | RULES | RULES | NO | NO | — | KEEP |
| docs/backend/ROLLBACK.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| docs/backend/TRANSACTION_ISOLATION.md | RULES | RULES | NO | NO | — | KEEP |

### Reference documentation — backend architecture (11 files)

| path | observed_bucket | correct_bucket | mismatch | mixed_responsibilities | tie_break_applied | recommended_action |
|------|----------------|---------------|----------|----------------------|-------------------|-------------------|
| docs/backend/architecture/README.md | RULES | RULES | NO | NO | — | KEEP |
| docs/backend/architecture/API.md | RULES | RULES | NO | NO | — | KEEP |
| docs/backend/architecture/BOOKING_CANCELLATION_REFUND_ARCHITECTURE.md | RULES | RULES | NO | NO | — | INVESTIGATE |
| docs/backend/architecture/EVENTS.md | RULES | RULES | NO | NO | — | KEEP |
| docs/backend/architecture/FOLDER_REFERENCE.md | RULES | RULES | NO | NO | — | KEEP |
| docs/backend/architecture/JOBS.md | RULES | RULES | NO | NO | — | KEEP |
| docs/backend/architecture/MIDDLEWARE.md | RULES | RULES | NO | NO | — | KEEP |
| docs/backend/architecture/POLICIES.md | RULES | RULES | NO | NO | — | KEEP |
| docs/backend/architecture/REPOSITORIES.md | RULES | RULES | NO | NO | — | KEEP |
| docs/backend/architecture/SERVICES.md | RULES | RULES | NO | NO | — | KEEP |
| docs/backend/architecture/TRAITS_EXCEPTIONS.md | RULES | RULES | NO | NO | — | KEEP |

### Reference documentation — backend features (11 files)

| path | observed_bucket | correct_bucket | mismatch | mixed_responsibilities | tie_break_applied | recommended_action |
|------|----------------|---------------|----------|----------------------|-------------------|-------------------|
| docs/backend/features/README.md | RULES | RULES | NO | NO | — | KEEP |
| docs/backend/features/API_RESPONSE_WRAPPER.md | RULES | RULES | NO | NO | — | KEEP |
| docs/backend/features/AUTHENTICATION.md | RULES | RULES | NO | NO | — | KEEP |
| docs/backend/features/BOOKING.md | RULES | RULES | NO | NO | — | KEEP |
| docs/backend/features/CACHING.md | RULES | RULES | NO | NO | — | KEEP |
| docs/backend/features/EMAIL_TEMPLATES.md | RULES | RULES | NO | NO | — | KEEP |
| docs/backend/features/HEALTH_CHECK.md | RULES | RULES | NO | NO | — | KEEP |
| docs/backend/features/OPTIMISTIC_LOCKING.md | RULES | RULES | NO | NO | — | KEEP |
| docs/backend/features/RBAC.md | RULES | RULES | NO | NO | — | KEEP |
| docs/backend/features/REVIEWS.md | RULES | RULES | NO | NO | — | KEEP |
| docs/backend/features/ROOMS.md | RULES | RULES | NO | NO | — | KEEP |

### Reference documentation — backend guides (9 files)

| path | observed_bucket | correct_bucket | mismatch | mixed_responsibilities | tie_break_applied | recommended_action |
|------|----------------|---------------|----------|----------------------|-------------------|-------------------|
| docs/backend/guides/API_MIGRATION_V1_TO_V2.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| docs/backend/guides/AUTH_MIGRATION.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| docs/backend/guides/COMMANDS.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| docs/backend/guides/DEPLOYMENT.md | SKILLS | SKILLS | NO | NO | — | INVESTIGATE |
| docs/backend/guides/EMAIL_NOTIFICATIONS.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| docs/backend/guides/ENVIRONMENT_SETUP.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| docs/backend/guides/MONITORING_LOGGING.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| docs/backend/guides/PERFORMANCE.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| docs/backend/guides/TESTING.md | SKILLS | SKILLS | NO | NO | — | KEEP |

### Reference documentation — backend security (4 files)

| path | observed_bucket | correct_bucket | mismatch | mixed_responsibilities | tie_break_applied | recommended_action |
|------|----------------|---------------|----------|----------------------|-------------------|-------------------|
| docs/backend/security/README.md | RULES | RULES | NO | NO | — | KEEP |
| docs/backend/security/HEADERS.md | RULES | RULES | NO | NO | — | KEEP |
| docs/backend/security/RATE_LIMITING.md | RULES | RULES | NO | NO | — | KEEP |
| docs/backend/security/XSS_PROTECTION.md | RULES | RULES | NO | NO | — | KEEP |

### Reference documentation — frontend (14 files)

| path | observed_bucket | correct_bucket | mismatch | mixed_responsibilities | tie_break_applied | recommended_action |
|------|----------------|---------------|----------|----------------------|-------------------|-------------------|
| docs/frontend/README.md | RULES | RULES | NO | NO | — | KEEP |
| docs/frontend/ARCHITECTURE.md | RULES | RULES | NO | NO | — | KEEP |
| docs/frontend/APP_LAYER.md | RULES | RULES | NO | NO | — | KEEP |
| docs/frontend/FEATURES_LAYER.md | RULES | RULES | NO | NO | — | KEEP |
| docs/frontend/SHARED_LAYER.md | RULES | RULES | NO | NO | — | KEEP |
| docs/frontend/TYPES_LAYER.md | RULES | RULES | NO | NO | — | KEEP |
| docs/frontend/UTILS_LAYER.md | RULES | RULES | NO | NO | — | KEEP |
| docs/frontend/SERVICES_LAYER.md | RULES | RULES | NO | NO | — | KEEP |
| docs/frontend/CONFIGURATION.md | RULES | RULES | NO | NO | — | KEEP |
| docs/frontend/DEPLOYMENT.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| docs/frontend/TESTING.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| docs/frontend/RBAC.md | RULES | RULES | NO | NO | — | KEEP |
| docs/frontend/RBAC_UX_AUDIT.md | RULES | COMPACT_SNAPSHOT | YES | NO | — | KEEP |
| docs/frontend/PERFORMANCE_SECURITY.md | RULES | RULES | NO | NO | — | KEEP |

### MCP configuration (2 files)

| path | observed_bucket | correct_bucket | mismatch | mixed_responsibilities | tie_break_applied | recommended_action |
|------|----------------|---------------|----------|----------------------|-------------------|-------------------|
| mcp/soleil-mcp/README.md | BOUNDARY_CONTRACT | BOUNDARY_CONTRACT | NO | NO | — | KEEP |
| mcp/soleil-mcp/policy.json | BOUNDARY_CONTRACT | BOUNDARY_CONTRACT | NO | NO | — | KEEP |

### CI/CD & external tooling (4 files)

| path | observed_bucket | correct_bucket | mismatch | mixed_responsibilities | tie_break_applied | recommended_action |
|------|----------------|---------------|----------|----------------------|-------------------|-------------------|
| .github/workflows/tests.yml | HOOKS | HOOKS | NO | NO | — | KEEP |
| .github/workflows/deploy.yml | HOOKS | HOOKS | NO | NO | — | KEEP |
| .github/workflows/hygiene.yml | HOOKS | HOOKS | NO | NO | — | KEEP |
| .github/copilot-instructions.md | RULES | RULES | NO | NO | — | KEEP |

### Root-level project docs (8 files)

| path | observed_bucket | correct_bucket | mismatch | mixed_responsibilities | tie_break_applied | recommended_action |
|------|----------------|---------------|----------|----------------------|-------------------|-------------------|
| README.md | UNCLEAR | RULES | YES | NO | Tie-break: RULES — project reference establishing conventions, narrower scope than CONSTITUTION | KEEP |
| README.dev.md | UNCLEAR | SKILLS | YES | NO | Tie-break: SKILLS — dev workflow guide with procedural steps | KEEP |
| PRODUCT_GOAL.md | UNCLEAR | RULES | YES | NO | Tie-break: RULES — product direction establishes what should be built | KEEP |
| PROJECT_STATUS.md | COMPACT_SNAPSHOT | COMPACT_SNAPSHOT | NO | NO | — | KEEP |
| BACKLOG.md | WORKLOG_LEDGER | WORKLOG_LEDGER | NO | NO | — | KEEP |
| audit_report.md | RULES | COMPACT_SNAPSHOT | YES | NO | — | ARCHIVE |
| ai_engineering_capability_assessment_soleil_hostel.md | RULES | COMPACT_SNAPSHOT | YES | NO | — | KEEP |
| prompt_audit_fix.md | RULES | COMPACT_SNAPSHOT | YES | NO | — | ARCHIVE |

---

## Files with mixed responsibilities

| path | responsibility_a | responsibility_b | split_recommendation |
|------|-----------------|-----------------|---------------------|
| CLAUDE.md | CONSTITUTION (root contract, invariants, decision order, doc map) | BOUNDARY_CONTRACT (GitNexus MCP section ~100 lines) + SKILLS (generated skill index ~30 lines) | Extract GitNexus section to a referenced file; extract skill CLI table to skill index. Keep CLAUDE.md as pure constitution. |
| AGENTS.md | CONSTITUTION (agent onboarding index) | BOUNDARY_CONTRACT (GitNexus documentation, ~120 lines duplicated from CLAUDE.md) | Remove GitNexus section entirely — already present in CLAUDE.md. Deduplicate to single source. |
| docs/agents/COMMANDS.md | RULES (agent framework reference, setup commands, artisan commands) | COMMANDS (slash command table, quality gate commands) | Add delegation header: gate definitions owned by docs/COMMANDS_AND_GATES.md. Keep as unified reference. |
| docs/COMMANDS_AND_GATES.md | RULES (gate definitions, pass/fail criteria) | COMMANDS (runnable command strings) | Acceptable mix — the commands exist to enforce the rules. No split needed. |
| docs/DB_FACTS.md | RULES (DB invariants, constraints, FK policies) | SKILLS (query patterns, migration conventions) | Split: invariant sections → remain here with delegation to ARCHITECTURE_FACTS.md; query patterns → link to skills. |
| docs/DATABASE.md | RULES (schema invariants, ER relationships) | SKILLS (DDL reference, index patterns) | Keep unified — 767 lines makes splitting impractical. Add delegation header. |
| docs/AI_GOVERNANCE.md | RULES (governance framework, component registry) | SKILLS (task startup checklist, skill selection guide) | Keep unified — the checklist serves the governance rules. Add clear section boundaries. |
| docs/governance/instruction-system-maintenance.md | SKILLS (maintenance procedures, audit triggers) | RULES (layer architecture diagram, invariant summary) | Keep as SKILLS — the RULES content is a summary for context, not authoritative. |

## Files misplaced at wrong abstraction layer

| path | observed_layer | correct_layer | rationale |
|------|---------------|---------------|-----------|
| docs/development_hooks.md | HOOKS | — (ORPHAN) | 3-line redirect stub; original content merged to docs/HOOKS.md. Delete candidate. |
| PROJECT_STATUS.md | Root-level doc | COMPACT_SNAPSHOT | Contains volatile test counts and gate results that expire. Functions as a point-in-time health snapshot. |
| audit_report.md | Root-level doc | COMPACT_SNAPSHOT (OBSOLETE) | Point-in-time audit findings; superseded by docs/FINDINGS_BACKLOG.md. Archive candidate. |
| prompt_audit_fix.md | Root-level doc | COMPACT_SNAPSHOT (OBSOLETE) | Generated audit fix prompts; superseded by docs/FINDINGS_BACKLOG.md. Archive candidate. |
| ai_engineering_capability_assessment_soleil_hostel.md | Root-level doc | COMPACT_SNAPSHOT | Point-in-time capability assessment. Not enforcement-oriented. |
| docs/AUDIT_2026_02_21.md | Reference doc | COMPACT_SNAPSHOT | Dated audit evidence — frozen in time, not a rule. |
| docs/AUDIT_2026_03_12_STRUCTURE.md | Reference doc | COMPACT_SNAPSHOT (STALE) | Backend test snapshot from 2026-03-12. Already flagged STALE in inventory. |
| docs/frontend/RBAC_UX_AUDIT.md | Reference doc (RULES) | COMPACT_SNAPSHOT | Dated UX audit — a point-in-time assessment, not ongoing rule enforcement. |
| .agent/scripts/check-locking-coverage.sh | .agent/ layer | HOOKS | Mechanical verification script with pass/fail exit codes — matches HOOKS definition exactly. Currently co-located with rules but functions as enforcement. |
| .agent/scripts/check-migration-safety.sh | .agent/ layer | HOOKS | Same rationale as above. |
| .agent/workflows/*.md (3 files) | .agent/ layer | SKILLS | Portable ordered procedures with steps, stop conditions, expected outputs — matches SKILLS definition. Currently co-located with rules but functions as execution procedures. |
| docs/CONTRIBUTING-TOOLCHAIN.md | Reference doc (RULES) | SKILLS | Procedural guide with setup steps — matches SKILLS definition. |

## Tie-break decisions log

| path | candidate_a | candidate_b | decision | rule_applied |
|------|------------|------------|----------|-------------|
| skill-os/context/INVARIANTS.md | RULES | SKILLS | SKILLS | "Assign to bucket with narrower scope" — co-located with skill-os, serves verification skills specifically. (File deleted; classification is historical.) |
| .claude/output-styles/execution.md | UNCLEAR | COMMANDS | COMMANDS | "Assign to bucket with lower runtime responsibility" — output formats are consumed by commands, not standalone artifacts. |
| .claude/output-styles/audit.md | UNCLEAR | COMMANDS | COMMANDS | Same rationale as execution.md. |
| docs/agents/COMMANDS.md | COMMANDS | RULES | RULES | "Assign to bucket with narrower scope" — canonical agent reference document, not an invocable command entrypoint. |
| README.md | UNCLEAR | RULES | RULES | "Assign to bucket with narrower scope" — project reference document establishing conventions. Not constitution (not auto-loaded). |
| README.dev.md | UNCLEAR | SKILLS | SKILLS | "Assign to bucket with lower runtime responsibility" — dev workflow guide with procedural setup steps. |
| PRODUCT_GOAL.md | UNCLEAR | RULES | RULES | "Assign to bucket with narrower scope" — product direction document establishing what should be built. |
| docs/CONTRIBUTING-TOOLCHAIN.md | RULES | SKILLS | SKILLS | "Assign to bucket with lower runtime responsibility" — procedural guide, not enforcement. |
| docs/governance/instruction-system-maintenance.md | RULES | SKILLS | SKILLS | "Assign to bucket with lower runtime responsibility" — primarily ordered maintenance procedures. |

## Unresolved items

| id | description | evidence_missing | blocks_batch |
|----|-------------|-----------------|-------------|
| UNRESOLVED-B2-1 | docs/DB_FACTS.md mixed responsibilities (RULES + SKILLS) — line-level boundary between invariant content and query patterns not determined | Line-level content audit | B4 |
| UNRESOLVED-B2-2 | docs/frontend/RBAC_UX_AUDIT.md freshness unknown — classified as COMPACT_SNAPSHOT but last verification date not in file | Last audit date | — |
| UNRESOLVED-B2-3 | 21 inventory files deleted post-inventory (commit 3b57cf2, 2026-03-22). Inventory is stale for these entries. Classifications are historical only. | Updated inventory reflecting current disk state | B3 (must not reference deleted files) |
| UNRESOLVED-B2-4 | The responsibility model lacks a REFERENCE/DOCUMENTATION bucket. ~60 reference docs (docs/backend/*, docs/frontend/*) are classified as RULES or SKILLS based on whether they establish constraints or provide procedures, but many are primarily descriptive documentation that doesn't enforce or guide. This is a structural gap in the bucket model. | Bucket model expansion decision | — |

---

## Report

### Observed reality

- 191 files in inventory; 170 exist on disk, 21 deleted after inventory was produced (commit `3b57cf2` 2026-03-22)
- Deleted files: 17 skill-os markdown files, 3 generated skills (authorization, cluster-1, operations), 1 operational domain notes doc
- The instruction system spans 14 directories: `CLAUDE.md` (root), `AGENTS.md`, `.claude/` (commands, agents, skills, hooks, output-styles, settings), `.agent/` (rules, workflows, scripts), `skills/`, `docs/` (agents, governance, backend, frontend, security), `mcp/`, `.github/`
- Prior classification matrix (2026-03-22) used glob patterns instead of individual entries, used lowercase path casing, and classified all reference docs as RULES

### Conflicts detected

1. **GitNexus duplication**: CLAUDE.md and AGENTS.md both contain ~120 lines of identical GitNexus documentation (BOUNDARY_CONTRACT content embedded in CONSTITUTION files)
2. **Bucket model gap**: No REFERENCE/DOCUMENTATION bucket exists. ~60 reference docs forced into RULES or SKILLS based on best-fit heuristics
3. **Dated snapshots misclassified in inventory**: `docs/AUDIT_2026_02_21.md`, `docs/AUDIT_2026_03_12_STRUCTURE.md`, `docs/frontend/RBAC_UX_AUDIT.md`, `audit_report.md`, `prompt_audit_fix.md` were all classified as RULES in the prior matrix but function as COMPACT_SNAPSHOT (point-in-time evidence, not enforcement rules)
4. **.agent/ layer mismatch**: `.agent/scripts/*.sh` are HOOKS (enforcement scripts) and `.agent/workflows/*.md` are SKILLS (procedures), but they're co-located with `.agent/rules/*.md` (RULES)

### Refactor plan proposed

Batch 2 is classification-only. No content changes. The classifications above inform subsequent batches:
- B3 (CLAUDE.md refactor): must address GitNexus duplication, must not reference 21 deleted files
- B4 (rules normalization): must resolve docs/DB_FACTS.md split
- B5 (skills normalization): .agent/workflows/ are SKILLS candidates
- B7 (hooks audit): .agent/scripts/ and .github/workflows/ are HOOKS candidates
- B8 (compact/worklog): must pick up 5 files reclassified as COMPACT_SNAPSHOT

### Changes applied

No content changes were made (classification-only batch). One file overwritten:
- `docs/cleanup/01-classification-matrix.md` — replaced prior glob-based version with individual-entry matrix using repo-exact path casing

### Unresolved items

See Unresolved items table above (4 items: UNRESOLVED-B2-1 through UNRESOLVED-B2-4).

### Validation results

- [x] Every file in the inventory has exactly one correct_bucket assignment (191/191)
- [x] Mixed-responsibility files are explicitly listed with split proposals (8 files)
- [x] Tie-break rule is documented for every ambiguous case (9 decisions)
- [x] No enum values outside those defined in output schema (all buckets from schema; DELETED_FROM_DISK is an action, not a bucket)
- [x] No content changes were made to any file (classification-only)
- [x] All UNCLEAR files from Batch 1 are resolved (inventory had 0 UNCLEAR truth_type files; classification applied tie-break to all ambiguous cases)
- [x] 21 deleted files flagged — below 15% threshold (11%), proceeding

### Deliverables produced

- [x] `docs/cleanup/01-classification-matrix.md` — conforming to Batch 2 schema with individual file entries

### Risks and follow-up for Batch 3

**Files Batch 3 must read:**
- `CLAUDE.md` — primary refactor target
- `AGENTS.md` — GitNexus deduplication partner
- `docs/agents/ARCHITECTURE_FACTS.md` — authority level 2, must not be silently altered
- `docs/agents/CONTRACT.md` — authority level 3, referenced by CLAUDE.md

**Conflicts Batch 3 must resolve in CLAUDE.md:**
1. GitNexus section (~100 lines) is BOUNDARY_CONTRACT content embedded in CONSTITUTION — extract or reference
2. Generated skill CLI index table (~30 lines) is SKILLS content embedded in CONSTITUTION — extract or reference
3. AGENTS.md GitNexus duplication must be resolved in coordination (same content in both files)
4. 21 deleted files must not be referenced in the refactored CLAUDE.md

**Structural risk:**
The bucket model lacks a REFERENCE bucket. ~60 docs were force-classified as RULES or SKILLS. If Batch 4 (rules normalization) attempts to normalize these as rules, it will face scope explosion. Recommend Batch 4 treat docs/backend/*, docs/frontend/*, and docs/backend/guides/* as out-of-scope reference documentation, not as RULES files to be normalized.
