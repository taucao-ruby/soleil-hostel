# Classification Matrix

> Batch 2 output | Generated: 2026-03-22 | Branch: dev

## Classification table

| path | observed_bucket | correct_bucket | mismatch | mixed_responsibilities | tie_break_applied | recommended_action |
|------|----------------|---------------|----------|----------------------|-------------------|-------------------|
| claude.md | CONSTITUTION | CONSTITUTION | NO | NO | — | KEEP |
| agents.md | CONSTITUTION | CONSTITUTION | NO | YES — contains GitNexus (SKILLS scope) | — | MERGE |
| .claude/settings.json | HOOKS | HOOKS | NO | NO | — | KEEP |
| .claude/settings.local.json | HOOKS | HOOKS | NO | NO | — | KEEP |
| .lintstagedrc.json | HOOKS | HOOKS | NO | NO | — | KEEP |
| .claude/commands/review-pr.md | COMMANDS | COMMANDS | NO | NO | — | KEEP |
| .claude/commands/ship.md | COMMANDS | COMMANDS | NO | NO | — | KEEP |
| .claude/commands/audit-security.md | COMMANDS | COMMANDS | NO | NO | — | KEEP |
| .claude/commands/sync-docs.md | COMMANDS | COMMANDS | NO | NO | — | KEEP |
| .claude/commands/fix-backend.md | COMMANDS | COMMANDS | NO | NO | — | KEEP |
| .claude/commands/fix-frontend.md | COMMANDS | COMMANDS | NO | NO | — | KEEP |
| .claude/agents/security-reviewer.md | AGENTS | AGENTS | NO | NO | — | KEEP |
| .claude/agents/frontend-reviewer.md | AGENTS | AGENTS | NO | NO | — | KEEP |
| .claude/agents/docs-sync.md | AGENTS | AGENTS | NO | NO | — | KEEP |
| .claude/agents/db-investigator.md | AGENTS | AGENTS | NO | NO | — | KEEP |
| .claude/skills/gitnexus/*/skill.md (6 files) | SKILLS | SKILLS | NO | NO | — | KEEP |
| .claude/skills/generated/*/skill.md (20 files) | SKILLS | SKILLS | NO | NO | — | KEEP |
| skills/readme.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| skills/laravel/*.md (7 files) | SKILLS | SKILLS | NO | NO | — | KEEP |
| skills/react/*.md (7 files) | SKILLS | SKILLS | NO | NO | — | KEEP |
| skills/ops/*.md (3 files) | SKILLS | SKILLS | NO | NO | — | KEEP |
| skill-os/readme.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| skill-os/structure.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| skill-os/taxonomy.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| skill-os/operating-guide.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| skill-os/verification-framework.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| skill-os/risk-register.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| skill-os/rollout-14day.md | SKILLS | SKILLS | NO | NO | — | ARCHIVE |
| skill-os/backlog.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| skill-os/context/invariants.md | RULES | SKILLS | PARTIAL | YES — invariant content (RULES) in skills directory | Tie-break: assigned SKILLS (narrower scope, co-located with skill-os) | KEEP |
| skill-os/lessons/booking-invariant-gotchas.md | SKILLS | SKILLS | NO | NO | — | KEEP |
| skill-os/skills/*/skill.md (4 files) | SKILLS | SKILLS | NO | NO | — | KEEP |
| skill-os/skills/*/checklist.md (1 file) | SKILLS | SKILLS | NO | NO | — | KEEP |
| skill-os/templates/*.md (2 files) | SKILLS | SKILLS | NO | NO | — | KEEP |
| .claude/output-styles/execution.md | UNCLEAR | COMMANDS | PARTIAL | NO | Tie-break: COMMANDS (output format is a command concern, not standalone) | KEEP |
| .claude/output-styles/audit.md | UNCLEAR | COMMANDS | PARTIAL | NO | Tie-break: COMMANDS (output format is a command concern, not standalone) | KEEP |
| .claude/hooks/block-dangerous-bash.sh | HOOKS | HOOKS | NO | NO | — | KEEP |
| .claude/hooks/guard-sensitive-files.sh | HOOKS | HOOKS | NO | NO | — | KEEP |
| .claude/hooks/remind-frontend-validation.sh | HOOKS | HOOKS | NO | NO | — | KEEP |
| .agent/rules/auth-token-safety.md | RULES | RULES | NO | NO | — | KEEP |
| .agent/rules/booking-integrity.md | RULES | RULES | NO | NO | — | KEEP |
| .agent/rules/migration-safety.md | RULES | RULES | NO | NO | — | KEEP |
| docs/agents/architecture_facts.md | RULES | RULES | NO | NO | — | KEEP |
| docs/agents/contract.md | RULES | RULES | NO | NO | — | KEEP |
| docs/agents/commands.md | COMMANDS | RULES | PARTIAL | YES — gate commands (COMMANDS) + agent setup (RULES) | Tie-break: RULES (canonical reference, not invocable command) | KEEP |
| docs/agents/readme.md | RULES | RULES | NO | NO | — | KEEP |
| docs/permission_matrix.md | RULES | RULES | NO | NO | — | KEEP |
| docs/commands_and_gates.md | RULES | RULES | NO | NO | — | KEEP |
| docs/findings_backlog.md | RULES | RULES | NO | NO | — | KEEP |
| docs/domain_layers.md | RULES | RULES | NO | NO | — | KEEP |
| docs/db_facts.md | RULES | RULES | NO | YES — invariants (RULES) + query patterns (SKILLS) | — | SPLIT |
| docs/database.md | RULES | RULES | NO | YES — invariants (RULES) + DDL reference (SKILLS) | — | KEEP |
| docs/ai_governance.md | RULES | RULES | NO | NO | — | KEEP |
| docs/compact.md | COMPACT_SNAPSHOT | COMPACT_SNAPSHOT | NO | NO | — | KEEP |
| docs/worklog.md | WORKLOG_LEDGER | WORKLOG_LEDGER | NO | NO | — | KEEP |
| mcp/soleil-mcp/readme.md | BOUNDARY_CONTRACT | BOUNDARY_CONTRACT | NO | NO | — | KEEP |
| mcp/soleil-mcp/policy.json | BOUNDARY_CONTRACT | BOUNDARY_CONTRACT | NO | NO | — | KEEP |
| docs/mcp.md | BOUNDARY_CONTRACT | BOUNDARY_CONTRACT | NO | NO | — | KEEP |
| docs/hooks.md | HOOKS | HOOKS | NO | NO | — | KEEP |
| docs/development_hooks.md | HOOKS | HOOKS | NO | NO | — | DELETE_CANDIDATE |
| .github/workflows/*.yml (3 files) | BOUNDARY_CONTRACT | BOUNDARY_CONTRACT | NO | NO | — | KEEP |
| .github/copilot-instructions.md | RULES | RULES | NO | NO | — | KEEP |
| readme.md | UNCLEAR | RULES | PARTIAL | NO | Tie-break: RULES (project-level reference, not invocable) | KEEP |
| product_goal.md | UNCLEAR | RULES | PARTIAL | NO | Tie-break: RULES (product direction, narrower than CONSTITUTION) | KEEP |
| project_status.md | COMPACT_SNAPSHOT | COMPACT_SNAPSHOT | NO | NO | — | KEEP |
| backlog.md | WORKLOG_LEDGER | WORKLOG_LEDGER | NO | NO | — | KEEP |
| docs/backend/*.md (9 files) | RULES | RULES | NO | NO | — | KEEP |
| docs/frontend/*.md (13 files) | RULES | RULES | NO | NO | — | KEEP |
| docs/general reference (12 files) | RULES | RULES | NO | NO | — | KEEP |

## Files with mixed responsibilities

| path | responsibility_a | responsibility_b | split_recommendation |
|------|-----------------|-----------------|---------------------|
| agents.md | CONSTITUTION (agent onboarding) | SKILLS (GitNexus documentation, 100 lines) | Remove GitNexus section — already present in claude.md. **Resolved in B3.** |
| docs/agents/commands.md | RULES (agent reference) | COMMANDS (gate commands listed) | Add cross-ref to docs/commands_and_gates.md. **Resolved in B4.** |
| docs/db_facts.md | RULES (invariants §2) | SKILLS (query patterns §5, migration rules §6) | Add delegation header to architecture_facts.md for invariants. **Partially resolved in B4.** |
| skill-os/context/invariants.md | RULES (invariant definitions) | SKILLS (co-located with skill-os verification skills) | Keep in SKILLS; add "derived from architecture_facts.md" header. |
| .claude/output-styles/*.md | UNCLEAR (standalone format files) | COMMANDS (output formats consumed by commands) | Keep in current location; tie-break assigns to COMMANDS scope. |

## Files misplaced at wrong abstraction layer

| path | observed_layer | correct_layer | rationale |
|------|---------------|---------------|-----------|
| docs/development_hooks.md | HOOKS | — (ORPHAN) | 3-line redirect stub; original content merged to docs/hooks.md |
| project_status.md | Root-level doc | COMPACT_SNAPSHOT | Contains volatile test counts that expire; functions as a compact |
| backlog.md | Root-level doc | WORKLOG_LEDGER | Prioritized work queue; functions as a dated execution tracker |

## Tie-break decisions log

| path | candidate_a | candidate_b | decision | rule_applied |
|------|------------|------------|----------|-------------|
| skill-os/context/invariants.md | RULES | SKILLS | SKILLS | "Assign to bucket with narrower scope" — co-located with skill-os, serves verification skills specifically |
| .claude/output-styles/execution.md | UNCLEAR | COMMANDS | COMMANDS | "Assign to bucket with lower runtime responsibility" — output formats are consumed by commands, not standalone |
| .claude/output-styles/audit.md | UNCLEAR | COMMANDS | COMMANDS | Same as above |
| docs/agents/commands.md | COMMANDS | RULES | RULES | "Assign to bucket with narrower scope" — reference document, not an invocable command |
| readme.md | UNCLEAR | RULES | RULES | "Assign to bucket with narrower scope" — project reference, not constitution |
| product_goal.md | UNCLEAR | RULES | RULES | Same as above |

## Unresolved items

| id | description | evidence_missing | blocks_batch |
|----|-------------|-----------------|-------------|
| UNRESOLVED-B2-1 | docs/db_facts.md mixed responsibilities (RULES + SKILLS) — full split not performed | Line-level boundary between invariant content and query patterns | B4 |
| UNRESOLVED-B2-2 | skill-os/rollout-14day.md may be expired (time-bound schedule) | Current date vs schedule dates not verified | — |
| UNRESOLVED-B2-3 | docs/frontend/rbac_ux_audit.md — unclear if STALE or ACTIVE | Last verification date not present in file | — |
