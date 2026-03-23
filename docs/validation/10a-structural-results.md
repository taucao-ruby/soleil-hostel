# Structural Validation Results — Batch 10A

> Generated: 2026-03-22 | Branch: dev

## Path Reference Validation

25 file paths referenced across governance files were checked for existence.

| # | Source File | Referenced Path | Status |
|---|-----------|-----------------|--------|
| 1 | CLAUDE.md | docs/agents/ARCHITECTURE_FACTS.md | PASS |
| 2 | CLAUDE.md | docs/agents/CONTRACT.md | PASS |
| 3 | CLAUDE.md | docs/PERMISSION_MATRIX.md | PASS |
| 4 | CLAUDE.md | docs/agents/COMMANDS.md | PASS |
| 5 | CLAUDE.md | docs/COMPACT.md | PASS |
| 6 | CLAUDE.md | skills/README.md | PASS |
| 7 | CLAUDE.md | skill-os/README.md | PASS |
| 8 | CLAUDE.md | docs/DOMAIN_LAYERS.md | PASS |
| 9 | CLAUDE.md | docs/FINDINGS_BACKLOG.md | PASS |
| 10 | CLAUDE.md | .claude/output-styles/ | PASS (2 files: audit.md, execution.md) |
| 11 | AGENTS.md | docs/AI_GOVERNANCE.md | PASS |
| 12 | security-reviewer | .agent/rules/auth-token-safety.md | PASS |
| 13 | security-reviewer | .agent/rules/booking-integrity.md | PASS |
| 14 | db-investigator | .agent/rules/migration-safety.md | PASS |
| 15 | frontend-reviewer | skills/react/typescript-patterns-skill.md | PASS |
| 16 | frontend-reviewer | skills/react/testing-vitest-skill.md | PASS |
| 17 | CLAUDE.md | docs/frontend/SERVICES_LAYER.md | PASS |
| 18 | COMPACT.md | docs/WORKLOG.md | PASS |
| 19 | COMPACT.md | PROJECT_STATUS.md | PASS |
| 20 | COMPACT.md | docs/AUDIT_2026_02_21.md | PASS |
| 21 | COMPACT.md | docs/README.md | PASS |
| 22 | COMPACT.md | docs/OPERATIONAL_PLAYBOOK.md | PASS |
| 23 | COMPACT.md | docs/DB_FACTS.md | PASS |
| 24 | COMPACT.md | docs/agents/README.md | PASS |
| 25 | COMPACT.md | docs/COMMANDS_AND_GATES.md | PASS |

## File Structure Integrity

| Check | Status |
|-------|--------|
| All @import files exist | PASS (2/2: ARCHITECTURE_FACTS.md, CONTRACT.md) |
| All on-demand references exist | PASS (8/8) |
| All subagent rule files exist | PASS (3/3) |
| All skill file references exist | PASS (2/2) |
| All COMPACT.md links valid | PASS (8/8) |
| Output styles directory populated | PASS (2 files) |
| .claude/commands/ all present | PASS (6 files) |
| .claude/agents/ all present | PASS (4 files) |
| .claude/hooks/ all present | PASS (3 files) |

## Directory Structure Validation

| Directory | Expected Contents | Actual | Status |
|-----------|------------------|--------|--------|
| `.claude/commands/` | 6 command files | 6 files | PASS |
| `.claude/agents/` | 4 subagent files | 4 files | PASS |
| `.claude/hooks/` | 3 hook scripts | 3 files | PASS |
| `.claude/output-styles/` | 2 style files | 2 files | PASS |
| `.claude/skills/gitnexus/` | 6 skill dirs | 6 dirs | PASS |
| `.claude/skills/generated/` | 20 skill dirs | 20 dirs | PASS |
| `.agent/rules/` | 3 rule files | 3 files | PASS |
| `skills/laravel/` | 7 skill files | 7 files | PASS |
| `skills/react/` | 7 skill files | 7 files | PASS |
| `skills/ops/` | 3 skill files | 3 files | PASS |
| `skill-os/skills/` | 3 skill subdirs | 3 dirs | PASS |
| `mcp/soleil-mcp/` | Server + policy | Present | PASS |

## Summary: 25/25 path references valid. All directories intact.

## Remediation Required

None.
