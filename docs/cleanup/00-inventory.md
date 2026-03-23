# Instruction System Inventory

> Batch 1 output | Generated: 2026-03-22 | Branch: dev | Re-executed with full file inspection

## Summary counts

| Metric | Count |
|--------|-------|
| Total files | 191 |
| By status: ACTIVE | 185 |
| By status: STALE | 3 |
| By status: ORPHAN | 1 |
| By status: OBSOLETE | 2 |
| By truth_type: SOURCE_OF_TRUTH | 45 |
| By truth_type: DERIVED | 145 |
| By truth_type: DUPLICATE | 1 |
| By truth_type: UNCLEAR | 0 |

## Inventory table

### Root contracts

| path | current_role_inferred | truth_type | status | overlap_with | risk_level | recommended_action |
|------|-----------------------|------------|--------|-------------|------------|-------------------|
| claude.md | Root contract / constitution (208 lines) | SOURCE_OF_TRUTH | ACTIVE | agents.md (GitNexus section) | LOW | KEEP |
| agents.md | Agent onboarding index (35 lines) | DERIVED | ACTIVE | claude.md (GitNexus section duplicated) | MEDIUM | MERGE |

### Settings & configuration

| path | current_role_inferred | truth_type | status | overlap_with | risk_level | recommended_action |
|------|-----------------------|------------|--------|-------------|------------|-------------------|
| .claude/settings.json | Permissions + hook config (69 lines) | SOURCE_OF_TRUTH | ACTIVE | .claude/hooks/*.sh (deny patterns mirrored) | LOW | KEEP |
| .claude/settings.local.json | Local session overrides (21 lines) | SOURCE_OF_TRUTH | ACTIVE | — | LOW | KEEP |
| .lintstagedrc.json | Lint-staged config (8 lines) | SOURCE_OF_TRUTH | ACTIVE | — | LOW | KEEP |

### Commands

| path | current_role_inferred | truth_type | status | overlap_with | risk_level | recommended_action |
|------|-----------------------|------------|--------|-------------|------------|-------------------|
| .claude/commands/review-pr.md | PR review command (51 lines) | SOURCE_OF_TRUTH | ACTIVE | .claude/skills/review-pr.md (deleted B5) | LOW | KEEP |
| .claude/commands/ship.md | Release gate command (57 lines) | SOURCE_OF_TRUTH | ACTIVE | .claude/skills/ship.md (deleted B5) | LOW | KEEP |
| .claude/commands/audit-security.md | OWASP audit command (50 lines) | SOURCE_OF_TRUTH | ACTIVE | — | LOW | KEEP |
| .claude/commands/sync-docs.md | Doc-code sync command (40 lines) | SOURCE_OF_TRUTH | ACTIVE | — | LOW | KEEP |
| .claude/commands/fix-backend.md | Backend fix command (44 lines) | SOURCE_OF_TRUTH | ACTIVE | — | LOW | KEEP |
| .claude/commands/fix-frontend.md | Frontend fix command (37 lines) | SOURCE_OF_TRUTH | ACTIVE | — | LOW | KEEP |

### Subagents

| path | current_role_inferred | truth_type | status | overlap_with | risk_level | recommended_action |
|------|-----------------------|------------|--------|-------------|------------|-------------------|
| .claude/agents/security-reviewer.md | Security review agent (74 lines) | SOURCE_OF_TRUTH | ACTIVE | .claude/agents/db-investigator.md (locking scope split) | LOW | KEEP |
| .claude/agents/frontend-reviewer.md | Frontend review agent (70 lines) | SOURCE_OF_TRUTH | ACTIVE | — | LOW | KEEP |
| .claude/agents/docs-sync.md | Docs sync agent (56 lines) | SOURCE_OF_TRUTH | ACTIVE | — | LOW | KEEP |
| .claude/agents/db-investigator.md | DB investigation agent (48 lines) | SOURCE_OF_TRUTH | ACTIVE | .claude/agents/security-reviewer.md (locking scope split) | LOW | KEEP |

### Skills — Claude native (GitNexus)

| path | current_role_inferred | truth_type | status | overlap_with | risk_level | recommended_action |
|------|-----------------------|------------|--------|-------------|------------|-------------------|
| .claude/skills/gitnexus/gitnexus-cli/skill.md | GitNexus CLI skill (82 lines) | SOURCE_OF_TRUTH | ACTIVE | — | LOW | KEEP |
| .claude/skills/gitnexus/gitnexus-debugging/skill.md | GitNexus debug skill (89 lines) | SOURCE_OF_TRUTH | ACTIVE | — | LOW | KEEP |
| .claude/skills/gitnexus/gitnexus-exploring/skill.md | GitNexus explore skill (78 lines) | SOURCE_OF_TRUTH | ACTIVE | — | LOW | KEEP |
| .claude/skills/gitnexus/gitnexus-guide/skill.md | GitNexus guide skill (64 lines) | SOURCE_OF_TRUTH | ACTIVE | — | LOW | KEEP |
| .claude/skills/gitnexus/gitnexus-impact-analysis/skill.md | GitNexus impact skill (97 lines) | SOURCE_OF_TRUTH | ACTIVE | — | LOW | KEEP |
| .claude/skills/gitnexus/gitnexus-refactoring/skill.md | GitNexus refactor skill (121 lines) | SOURCE_OF_TRUTH | ACTIVE | — | LOW | KEEP |

### Skills — generated (auto-managed by GitNexus)

| path | current_role_inferred | truth_type | status | overlap_with | risk_level | recommended_action |
|------|-----------------------|------------|--------|-------------|------------|-------------------|
| .claude/skills/generated/auth/skill.md | Auth area skill (93 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| .claude/skills/generated/authorization/skill.md | Authorization area skill (72 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| .claude/skills/generated/booking/skill.md | Booking area skill (87 lines, 47 symbols) | DERIVED | ACTIVE | .claude/skills/generated/bookings/skill.md | LOW | INVESTIGATE |
| .claude/skills/generated/bookings/skill.md | Bookings area skill (85 lines, 26 symbols) | DERIVED | ACTIVE | .claude/skills/generated/booking/skill.md | LOW | INVESTIGATE |
| .claude/skills/generated/cache/skill.md | Cache area skill (95 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| .claude/skills/generated/cluster-1/skill.md | Cluster-1 area skill (81 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| .claude/skills/generated/controllers/skill.md | Controllers area skill (93 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| .claude/skills/generated/database/skill.md | Database area skill (94 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| .claude/skills/generated/feature/skill.md | Feature area skill (94 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| .claude/skills/generated/listeners/skill.md | Listeners area skill (96 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| .claude/skills/generated/middleware/skill.md | Middleware area skill (76 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| .claude/skills/generated/models/skill.md | Models area skill (95 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| .claude/skills/generated/notifications/skill.md | Notifications area skill (87 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| .claude/skills/generated/operations/skill.md | Operations area skill (88 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| .claude/skills/generated/policies/skill.md | Policies area skill (82 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| .claude/skills/generated/repositories/skill.md | Repositories area skill (76 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| .claude/skills/generated/requests/skill.md | Requests area skill (81 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| .claude/skills/generated/room/skill.md | Room area skill (76 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| .claude/skills/generated/services/skill.md | Services area skill (98 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| .claude/skills/generated/stays/skill.md | Stays area skill (74 lines) | DERIVED | ACTIVE | — | LOW | KEEP |

### Skills — reference library

| path | current_role_inferred | truth_type | status | overlap_with | risk_level | recommended_action |
|------|-----------------------|------------|--------|-------------|------------|-------------------|
| skills/readme.md | Skill index (157 lines) | SOURCE_OF_TRUTH | ACTIVE | skill-os/readme.md (scope overlap) | LOW | KEEP |
| skills/laravel/api-endpoints-skill.md | API endpoints guardrails (87 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| skills/laravel/auth-tokens-skill.md | Auth token guardrails (86 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| skills/laravel/booking-overlap-skill.md | Overlap guardrails (78 lines) | DERIVED | ACTIVE | skill-os/skills/verification/verify-no-double-booking/skill.md | LOW | KEEP |
| skills/laravel/migrations-postgres-skill.md | Migration guardrails (86 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| skills/laravel/security-secrets-skill.md | Security guardrails (83 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| skills/laravel/testing-skill.md | Testing guardrails (88 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| skills/laravel/transactions-locking-skill.md | Locking guardrails (76 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| skills/react/api-client-skill.md | API client guardrails (80 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| skills/react/component-quality-skill.md | Component quality guardrails (80 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| skills/react/forms-validation-skill.md | Forms guardrails (84 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| skills/react/performance-core-web-vitals-skill.md | Performance guardrails (80 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| skills/react/security-frontend-skill.md | Frontend security guardrails (80 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| skills/react/testing-vitest-skill.md | Vitest guardrails (80 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| skills/react/typescript-patterns-skill.md | TS patterns guardrails (90 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| skills/ops/ci-quality-gates-skill.md | CI gates guardrails (80 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| skills/ops/docker-compose-skill.md | Docker guardrails (80 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| skills/ops/logging-observability-skill.md | Logging guardrails (82 lines) | DERIVED | ACTIVE | — | LOW | KEEP |

### Skill OS (verification framework)

| path | current_role_inferred | truth_type | status | overlap_with | risk_level | recommended_action |
|------|-----------------------|------------|--------|-------------|------------|-------------------|
| skill-os/readme.md | Booking Skill OS overview (54 lines) | SOURCE_OF_TRUTH | ACTIVE | skills/readme.md (scope overlap) | LOW | KEEP |
| skill-os/structure.md | Organizational structure (65 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| skill-os/taxonomy.md | Skill taxonomy (73 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| skill-os/operating-guide.md | Execution guide (170 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| skill-os/verification-framework.md | Verification gates (153 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| skill-os/risk-register.md | Operational risks (83 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| skill-os/rollout-14day.md | Release schedule (99 lines) | DERIVED | STALE | — | HIGH | ARCHIVE |
| skill-os/backlog.md | Skill roadmap (103 lines) | DERIVED | ACTIVE | — | MEDIUM | KEEP |
| skill-os/context/invariants.md | Booking invariants checklist (69 lines) | DERIVED | ACTIVE | docs/agents/architecture_facts.md | MEDIUM | KEEP |
| skill-os/lessons/booking-invariant-gotchas.md | Failure patterns (131 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| skill-os/skills/release/pre-release-verification/skill.md | Release verification (238 lines) | SOURCE_OF_TRUTH | ACTIVE | — | LOW | KEEP |
| skill-os/skills/review/review-schema-change-risk/skill.md | Schema risk review (222 lines) | SOURCE_OF_TRUTH | ACTIVE | — | LOW | KEEP |
| skill-os/skills/verification/verify-docs-vs-code/skill.md | Doc-code alignment (211 lines) | SOURCE_OF_TRUTH | ACTIVE | — | LOW | KEEP |
| skill-os/skills/verification/verify-no-double-booking/skill.md | Overlap verification (181 lines) | SOURCE_OF_TRUTH | ACTIVE | skills/laravel/booking-overlap-skill.md | LOW | KEEP |
| skill-os/skills/verification/verify-no-double-booking/checklist.md | Overlap checklist (44 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| skill-os/templates/migration-risk-review.md | Template (89 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| skill-os/templates/release-readiness-report.md | Template (108 lines) | DERIVED | ACTIVE | — | LOW | KEEP |

### Output styles

| path | current_role_inferred | truth_type | status | overlap_with | risk_level | recommended_action |
|------|-----------------------|------------|--------|-------------|------------|-------------------|
| .claude/output-styles/execution.md | Execution report format (23 lines) | SOURCE_OF_TRUTH | ACTIVE | — | LOW | KEEP |
| .claude/output-styles/audit.md | Audit report format (24 lines) | SOURCE_OF_TRUTH | ACTIVE | — | LOW | KEEP |

### Hooks

| path | current_role_inferred | truth_type | status | overlap_with | risk_level | recommended_action |
|------|-----------------------|------------|--------|-------------|------------|-------------------|
| .claude/hooks/block-dangerous-bash.sh | Bash command blocker (30 lines) | SOURCE_OF_TRUTH | ACTIVE | .claude/settings.json (deny list mirrors) | LOW | KEEP |
| .claude/hooks/guard-sensitive-files.sh | Sensitive file guard (24 lines) | SOURCE_OF_TRUTH | ACTIVE | — | LOW | KEEP |
| .claude/hooks/remind-frontend-validation.sh | Frontend reminder (23 lines) | SOURCE_OF_TRUTH | ACTIVE | — | LOW | KEEP |

### Agent operating layer (.agent/)

| path | current_role_inferred | truth_type | status | overlap_with | risk_level | recommended_action |
|------|-----------------------|------------|--------|-------------|------------|-------------------|
| .agent/architecture.md | AI operating layer model (82 lines) | DERIVED | ACTIVE | claude.md, agents.md (layer model overlaps) | MEDIUM | KEEP |
| .agent/rules/auth-token-safety.md | Auth/token invariants (51 lines) | DERIVED | ACTIVE | docs/agents/architecture_facts.md | MEDIUM | KEEP |
| .agent/rules/booking-integrity.md | Booking STOP conditions (46 lines) | DERIVED | ACTIVE | docs/agents/architecture_facts.md | MEDIUM | KEEP |
| .agent/rules/migration-safety.md | Migration safety rules (41 lines) | DERIVED | ACTIVE | docs/agents/architecture_facts.md | MEDIUM | KEEP |
| .agent/scripts/check-locking-coverage.sh | Locking verification script (61 lines) | DERIVED | ACTIVE | .agent/rules/booking-integrity.md | LOW | KEEP |
| .agent/scripts/check-migration-safety.sh | Migration safety verification (126 lines) | DERIVED | ACTIVE | .agent/rules/migration-safety.md | LOW | KEEP |
| .agent/workflows/auth-change.md | Auth change workflow (84 lines) | DERIVED | ACTIVE | docs/agents/architecture_facts.md (auth section) | LOW | KEEP |
| .agent/workflows/booking-domain-change.md | Booking change workflow (84 lines) | DERIVED | ACTIVE | docs/agents/architecture_facts.md (booking section) | LOW | KEEP |
| .agent/workflows/new-migration.md | Migration workflow (81 lines) | DERIVED | ACTIVE | skills/laravel/migrations-postgres-skill.md | LOW | KEEP |

### Rules & policy documents

| path | current_role_inferred | truth_type | status | overlap_with | risk_level | recommended_action |
|------|-----------------------|------------|--------|-------------|------------|-------------------|
| docs/agents/architecture_facts.md | Domain invariants (221 lines) | SOURCE_OF_TRUTH | ACTIVE | docs/db_facts.md, skill-os/context/invariants.md | MEDIUM | KEEP |
| docs/agents/contract.md | Definition of Done (68 lines) | SOURCE_OF_TRUTH | ACTIVE | — | LOW | KEEP |
| docs/agents/commands.md | Agent command reference (79 lines) | DERIVED | ACTIVE | docs/commands_and_gates.md | MEDIUM | KEEP |
| docs/agents/readme.md | Agent docs index (31 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| docs/permission_matrix.md | RBAC baseline (206 lines) | SOURCE_OF_TRUTH | ACTIVE | docs/frontend/rbac.md | MEDIUM | KEEP |
| docs/commands_and_gates.md | CI gate reference (220 lines) | SOURCE_OF_TRUTH | ACTIVE | docs/agents/commands.md | LOW | KEEP |
| docs/findings_backlog.md | Issues log (90 lines) | SOURCE_OF_TRUTH | ACTIVE | — | LOW | KEEP |
| docs/domain_layers.md | Four-layer model (115 lines) | SOURCE_OF_TRUTH | ACTIVE | — | LOW | KEEP |
| docs/db_facts.md | DB operational reference (241 lines) | DERIVED | ACTIVE | docs/agents/architecture_facts.md | MEDIUM | KEEP |
| docs/database.md | Comprehensive DB reference (767 lines) | DERIVED | ACTIVE | docs/agents/architecture_facts.md, docs/db_facts.md | HIGH | KEEP |
| docs/ai_governance.md | AI governance model (75 lines) | SOURCE_OF_TRUTH | ACTIVE | — | LOW | KEEP |
| docs/governance/instruction-system-maintenance.md | Governance maintenance guide (150 lines) | DERIVED | ACTIVE | — | LOW | KEEP |

### Volatile / session state

| path | current_role_inferred | truth_type | status | overlap_with | risk_level | recommended_action |
|------|-----------------------|------------|--------|-------------|------------|-------------------|
| docs/compact.md | AI session memory (87 lines) | DERIVED | STALE | — | HIGH | KEEP |
| docs/worklog.md | Change log (203 lines) | DERIVED | ACTIVE | — | MEDIUM | KEEP |

### Reference documentation — general

| path | current_role_inferred | truth_type | status | overlap_with | risk_level | recommended_action |
|------|-----------------------|------------|--------|-------------|------------|-------------------|
| docs/readme.md | Documentation index (216 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| docs/adr.md | Architecture decisions (847 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| docs/api_deprecation.md | Deprecation schedule (491 lines) | DERIVED | ACTIVE | — | MEDIUM | KEEP |
| docs/audit_2026_02_21.md | Audit evidence (104 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| docs/audit_2026_03_12_structure.md | Backend test snapshot (373 lines) | DERIVED | STALE | — | LOW | ARCHIVE |
| docs/contributing-toolchain.md | Toolchain guide (84 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| docs/core_features_prompt.md | Feature descriptions (644 lines) | DERIVED | ACTIVE | — | MEDIUM | KEEP |
| docs/development_hooks.md | Stub redirect (3 lines) | DUPLICATE | ORPHAN | docs/hooks.md | LOW | DELETE_CANDIDATE |
| docs/hooks.md | Hook documentation (138 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| docs/known_limitations.md | Known limitations (432 lines) | DERIVED | ACTIVE | — | MEDIUM | KEEP |
| docs/mcp.md | MCP overview (126 lines) | DERIVED | ACTIVE | mcp/soleil-mcp/readme.md | LOW | KEEP |
| docs/migration_guide.md | Migration guide (164 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| docs/operational_playbook.md | Ops playbook (952 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| docs/performance_baseline.md | Performance baseline (277 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| docs/operational_domain_architecture_notes_pass_2.md | Operational domain notes (187 lines) | DERIVED | ACTIVE | docs/domain_layers.md | MEDIUM | KEEP |

### Reference documentation — backend top-level (9 files)

| path | current_role_inferred | truth_type | status | overlap_with | risk_level | recommended_action |
|------|-----------------------|------------|--------|-------------|------------|-------------------|
| docs/backend/readme.md | Backend docs index (69 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| docs/backend/booking_cancellation_flow.md | Cancellation spec (905 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| docs/backend/booking_confirmation_notification_architecture.md | Notification arch (709 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| docs/backend/cache_warmup_strategy.md | Cache strategy (353 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| docs/backend/deployment.md | Backend deployment (262 lines) | DERIVED | ACTIVE | docs/backend/guides/deployment.md | MEDIUM | INVESTIGATE |
| docs/backend/queue_monitoring.md | Queue monitoring (348 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| docs/backend/review_policy_authorization.md | Review auth policy (627 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| docs/backend/rollback.md | Rollback procedures (389 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| docs/backend/transaction_isolation.md | Transaction isolation (357 lines) | DERIVED | ACTIVE | — | LOW | KEEP |

### Reference documentation — backend architecture (11 files)

| path | current_role_inferred | truth_type | status | overlap_with | risk_level | recommended_action |
|------|-----------------------|------------|--------|-------------|------------|-------------------|
| docs/backend/architecture/readme.md | Architecture docs index (54 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| docs/backend/architecture/api.md | API architecture (400 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| docs/backend/architecture/booking_cancellation_refund_architecture.md | Cancellation refund arch (893 lines) | DERIVED | ACTIVE | docs/backend/booking_cancellation_flow.md | MEDIUM | INVESTIGATE |
| docs/backend/architecture/events.md | Event system (329 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| docs/backend/architecture/folder_reference.md | Folder structure reference (336 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| docs/backend/architecture/jobs.md | Queue jobs architecture (230 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| docs/backend/architecture/middleware.md | Middleware stack (302 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| docs/backend/architecture/policies.md | Authorization policies (334 lines) | DERIVED | ACTIVE | docs/backend/features/rbac.md | LOW | KEEP |
| docs/backend/architecture/repositories.md | Repository pattern (453 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| docs/backend/architecture/services.md | Service layer (428 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| docs/backend/architecture/traits_exceptions.md | Traits & exceptions (355 lines) | DERIVED | ACTIVE | — | LOW | KEEP |

### Reference documentation — backend features (11 files)

| path | current_role_inferred | truth_type | status | overlap_with | risk_level | recommended_action |
|------|-----------------------|------------|--------|-------------|------------|-------------------|
| docs/backend/features/readme.md | Features docs index (78 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| docs/backend/features/api_response_wrapper.md | API response wrapper (378 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| docs/backend/features/authentication.md | Auth feature spec (396 lines) | DERIVED | ACTIVE | docs/agents/architecture_facts.md (auth section) | LOW | KEEP |
| docs/backend/features/booking.md | Booking feature spec (363 lines) | DERIVED | ACTIVE | docs/agents/architecture_facts.md (booking section) | LOW | KEEP |
| docs/backend/features/caching.md | Caching feature (262 lines) | DERIVED | ACTIVE | docs/backend/cache_warmup_strategy.md | LOW | KEEP |
| docs/backend/features/email_templates.md | Email templates (406 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| docs/backend/features/health_check.md | Health check feature (437 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| docs/backend/features/optimistic_locking.md | Optimistic locking (974 lines) | DERIVED | ACTIVE | docs/agents/architecture_facts.md (concurrency section) | LOW | KEEP |
| docs/backend/features/rbac.md | RBAC feature (243 lines) | DERIVED | ACTIVE | docs/permission_matrix.md | LOW | KEEP |
| docs/backend/features/reviews.md | Reviews feature (305 lines) | DERIVED | ACTIVE | docs/agents/architecture_facts.md (reviews section) | LOW | KEEP |
| docs/backend/features/rooms.md | Rooms feature (297 lines) | DERIVED | ACTIVE | — | LOW | KEEP |

### Reference documentation — backend guides (9 files)

| path | current_role_inferred | truth_type | status | overlap_with | risk_level | recommended_action |
|------|-----------------------|------------|--------|-------------|------------|-------------------|
| docs/backend/guides/api_migration_v1_to_v2.md | API migration guide (470 lines) | DERIVED | ACTIVE | docs/api_deprecation.md | LOW | KEEP |
| docs/backend/guides/auth_migration.md | Auth migration guide (307 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| docs/backend/guides/commands.md | Artisan commands guide (226 lines) | DERIVED | ACTIVE | docs/agents/commands.md | LOW | KEEP |
| docs/backend/guides/deployment.md | Deployment guide (259 lines) | DERIVED | ACTIVE | docs/backend/deployment.md | MEDIUM | INVESTIGATE |
| docs/backend/guides/email_notifications.md | Email notifications (729 lines) | DERIVED | ACTIVE | docs/backend/features/email_templates.md | LOW | KEEP |
| docs/backend/guides/environment_setup.md | Environment setup (216 lines) | DERIVED | ACTIVE | readme.dev.md | LOW | KEEP |
| docs/backend/guides/monitoring_logging.md | Monitoring & logging (1872 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| docs/backend/guides/performance.md | Performance guide (384 lines) | DERIVED | ACTIVE | docs/performance_baseline.md | LOW | KEEP |
| docs/backend/guides/testing.md | Testing guide (276 lines) | DERIVED | ACTIVE | — | LOW | KEEP |

### Reference documentation — backend security (4 files)

| path | current_role_inferred | truth_type | status | overlap_with | risk_level | recommended_action |
|------|-----------------------|------------|--------|-------------|------------|-------------------|
| docs/backend/security/readme.md | Security docs index (106 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| docs/backend/security/headers.md | Security headers (188 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| docs/backend/security/rate_limiting.md | Rate limiting (211 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| docs/backend/security/xss_protection.md | XSS protection (207 lines) | DERIVED | ACTIVE | docs/agents/architecture_facts.md (XSS line) | LOW | KEEP |

### Reference documentation — frontend (14 files)

| path | current_role_inferred | truth_type | status | overlap_with | risk_level | recommended_action |
|------|-----------------------|------------|--------|-------------|------------|-------------------|
| docs/frontend/readme.md | Frontend docs index (352 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| docs/frontend/architecture.md | Frontend arch (165 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| docs/frontend/app_layer.md | App layer spec (231 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| docs/frontend/features_layer.md | Features spec (610 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| docs/frontend/shared_layer.md | Shared layer spec (157 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| docs/frontend/types_layer.md | Types spec (177 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| docs/frontend/utils_layer.md | Utils spec (123 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| docs/frontend/services_layer.md | Services spec (269 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| docs/frontend/configuration.md | Frontend config (220 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| docs/frontend/deployment.md | Frontend deployment (978 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| docs/frontend/testing.md | Frontend testing (210 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| docs/frontend/rbac.md | Frontend RBAC (461 lines) | DERIVED | ACTIVE | docs/permission_matrix.md | LOW | KEEP |
| docs/frontend/rbac_ux_audit.md | RBAC UX audit (542 lines) | DERIVED | ACTIVE | docs/frontend/rbac.md | MEDIUM | KEEP |
| docs/frontend/performance_security.md | Perf & security (143 lines) | DERIVED | ACTIVE | — | LOW | KEEP |

### MCP configuration

| path | current_role_inferred | truth_type | status | overlap_with | risk_level | recommended_action |
|------|-----------------------|------------|--------|-------------|------------|-------------------|
| mcp/soleil-mcp/readme.md | MCP server docs (194 lines) | SOURCE_OF_TRUTH | ACTIVE | docs/mcp.md | LOW | KEEP |
| mcp/soleil-mcp/policy.json | Allowed commands policy (72 lines) | SOURCE_OF_TRUTH | ACTIVE | — | LOW | KEEP |

### CI/CD & external tooling

| path | current_role_inferred | truth_type | status | overlap_with | risk_level | recommended_action |
|------|-----------------------|------------|--------|-------------|------------|-------------------|
| .github/workflows/tests.yml | Test suite CI (628 lines) | SOURCE_OF_TRUTH | ACTIVE | — | LOW | KEEP |
| .github/workflows/deploy.yml | Deployment CI (499 lines) | SOURCE_OF_TRUTH | ACTIVE | — | LOW | KEEP |
| .github/workflows/hygiene.yml | Hygiene checks (24 lines) | SOURCE_OF_TRUTH | ACTIVE | — | LOW | KEEP |
| .github/copilot-instructions.md | Copilot guidance (30 lines) | DERIVED | ACTIVE | — | LOW | KEEP |

### Root-level project docs

| path | current_role_inferred | truth_type | status | overlap_with | risk_level | recommended_action |
|------|-----------------------|------------|--------|-------------|------------|-------------------|
| readme.md | Project README (523 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| readme.dev.md | Dev workflow guide (161 lines) | DERIVED | ACTIVE | docs/contributing-toolchain.md | LOW | KEEP |
| product_goal.md | Product direction (170 lines) | SOURCE_OF_TRUTH | ACTIVE | — | LOW | KEEP |
| project_status.md | Health snapshot (165 lines) | DERIVED | ACTIVE | — | HIGH | KEEP |
| backlog.md | Work queue (468 lines) | SOURCE_OF_TRUTH | ACTIVE | — | MEDIUM | KEEP |
| audit_report.md | Audit findings snapshot (349 lines) | DERIVED | OBSOLETE | docs/findings_backlog.md (superseded) | LOW | ARCHIVE |
| ai_engineering_capability_assessment_soleil_hostel.md | AI capability report (404 lines) | DERIVED | ACTIVE | — | LOW | KEEP |
| prompt_audit_fix.md | Generated audit fix prompts (279 lines) | DERIVED | OBSOLETE | docs/findings_backlog.md (superseded) | LOW | ARCHIVE |

## Key overlaps identified

| Overlap | Files | Severity |
|---------|-------|----------|
| GitNexus documentation duplicated | claude.md ↔ agents.md (100 identical lines) | HIGH — resolved in B3 |
| Booking invariants repeated | architecture_facts.md ↔ db_facts.md ↔ skill-os/context/invariants.md | MEDIUM — delegation headers added in B4 |
| Quality gates duplicated | docs/agents/commands.md ↔ docs/commands_and_gates.md | MEDIUM — cross-ref added in B4 |
| review-pr/ship command-skill duplication | .claude/commands/*.md ↔ .claude/skills/*.md | HIGH — skills deleted in B5 |
| RBAC documented in 3 places | permission_matrix.md ↔ docs/frontend/rbac.md ↔ docs/frontend/rbac_ux_audit.md | LOW — different scopes (backend vs frontend vs audit) |
| MCP docs in 2 places | docs/mcp.md ↔ mcp/soleil-mcp/readme.md | LOW — different audiences |
| booking vs bookings generated skills | .claude/skills/generated/booking/ ↔ .claude/skills/generated/bookings/ | LOW — different clusters (47 vs 26 symbols) |
| Backend deployment documented twice | docs/backend/deployment.md ↔ docs/backend/guides/deployment.md | MEDIUM — needs dedup investigation |
| Cancellation arch documented twice | docs/backend/booking_cancellation_flow.md ↔ docs/backend/architecture/booking_cancellation_refund_architecture.md | MEDIUM — 905 + 893 lines, likely overlapping |
| Operational domain notes overlap | docs/operational_domain_architecture_notes_pass_2.md ↔ docs/domain_layers.md | MEDIUM — notes may be superseded by canonical model |
| Agent layer model overlap | .agent/architecture.md ↔ agents.md ↔ claude.md | LOW — .agent/ provides operating-layer detail |

## High-risk items flagged

| path | risk_level | reason |
|------|-----------|--------|
| docs/compact.md | HIGH | Volatile; stale branch state; test counts behind |
| project_status.md | HIGH | Test counts must stay current; single source for health |
| docs/database.md | HIGH | 767 lines; ER diagrams hard to maintain; overlaps architecture_facts.md |
| skill-os/rollout-14day.md | HIGH | Time-bound schedule; likely expired |

## Unresolved items

| id | description | evidence_missing | blocks_batch |
|----|-------------|-----------------|-------------|
| UNRESOLVED-B1-1 | Exact overlap between docs/database.md and docs/db_facts.md not line-diffed | Line-level diff not performed | B4 |
| UNRESOLVED-B1-2 | docs/frontend/rbac_ux_audit.md freshness unknown | Last verification date not in file | — |
| UNRESOLVED-B1-3 | docs/backend/deployment.md vs docs/backend/guides/deployment.md overlap not assessed | Line-level diff not performed | — |
| UNRESOLVED-B1-4 | docs/backend/booking_cancellation_flow.md vs docs/backend/architecture/booking_cancellation_refund_architecture.md overlap not assessed | Line-level diff not performed (905 + 893 lines) | — |
| UNRESOLVED-B1-5 | audit_report.md and prompt_audit_fix.md marked OBSOLETE — confirm superseded by findings_backlog.md | User confirmation needed | — |
