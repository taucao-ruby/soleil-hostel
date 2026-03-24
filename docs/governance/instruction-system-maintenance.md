---
schema_version: 1.0
produced_by_batch: B10E
phase: Phase D
date: 2026-03-22
input_artifacts:
  - docs/cleanup/00-inventory.md
  - docs/cleanup/01-classification-matrix.md
  - docs/validation/10a-structural-results.md
  - docs/validation/drift-matrix.md
  - docs/validation/boundary-checklist.md
  - docs/cleanup/unresolved-registry.md
authority_order_applied: true
unresolved_count: 0
---

# Instruction System Maintenance Guide

> Governance loop for keeping the Soleil Hostel instruction system healthy.
> Generated: 2026-03-22 | Based on refactor pipeline Phases A-D

## System Overview

The instruction system has **150 governance files** across **14 layers**, totaling ~16,000 lines. It guides Claude Code sessions, subagents, skills, and CI/CD workflows.

### Layer Architecture

```
┌─────────────────────────────────────────────┐
│  CLAUDE.md (root contract, auto-loaded)     │ ← Session constitution
│  ├── @ARCHITECTURE_FACTS.md (invariants)    │
│  └── @CONTRACT.md (Definition of Done)      │
├─────────────────────────────────────────────┤
│  Rules & Policy                             │ ← Domain truth
│  ├── PERMISSION_MATRIX.md (RBAC baseline)   │
│  ├── DB_FACTS.md (operational DB reference) │
│  ├── DOMAIN_LAYERS.md (four-layer model)    │
│  └── .agent/rules/*.md (subagent fast-load) │
├─────────────────────────────────────────────┤
│  Skills                                     │ ← Task execution
│  ├── .claude/skills/gitnexus/ (6 skills)    │
│  ├── .claude/skills/generated/ (20, auto)   │
│  ├── skills/ (17 reference skills)          │
│  └── skill-os/ (4 verification skills)      │
├─────────────────────────────────────────────┤
│  Commands (6) │ Agents (4) │ Hooks (3)      │ ← Execution infrastructure
├─────────────────────────────────────────────┤
│  Volatile State                             │ ← Session memory
│  ├── COMPACT.md (current snapshot)          │
│  └── WORKLOG.md (append-only history)       │
├─────────────────────────────────────────────┤
│  MCP │ CI/CD │ Settings │ Output Styles     │ ← Boundaries & config
└─────────────────────────────────────────────┘
```

## Maintenance Cadence

### After Every Code Session

| Task | Owner | Automation |
|------|-------|-----------|
| Update COMPACT.md §1 if code changed | Agent | Manual (per lifecycle policy) |
| Run `gitnexus_detect_changes()` before commit | Agent | CLAUDE.md mandate |
| Verify gates pass | Agent | Pre-push hook |

### Weekly

| Task | How | Threshold |
|------|-----|-----------|
| Check test counts in COMPACT.md vs actual | Run `php artisan test` + `npx vitest run` | If counts diverge by >5, update |
| Review COMPACT.md line count | `wc -l docs/COMPACT.md` | Archive to WORKLOG if >80 lines |
| Spot-check 3 cross-references | Pick 3 paths from CLAUDE.md on-demand list | All must resolve |

### Monthly

| Task | How | Output |
|------|-----|--------|
| Semantic drift check | Run drift fixtures (RC-001, SE-001, CD-001) | Log drift in FINDINGS_BACKLOG.md |
| Boundary validation | Run boundary-checklist.md checks | Update checklist |
| Skill freshness | Check if generated skills need re-analysis | Run `npx gitnexus analyze` if stale |
| WORKLOG size check | `wc -l docs/WORKLOG.md` | Archive if >300 lines |

### Quarterly

| Task | How | Output |
|------|-----|--------|
| Full inventory refresh | Re-run Batch 1 (compare against docs/cleanup/00-inventory.md) | Update inventory |
| Classification review | Re-run Batch 2 (check for new conflicts/redundancies) | Update matrix |
| Rule decay audit | Read each .agent/rules/*.md; compare against ARCHITECTURE_FACTS.md | Fix drift |
| Docs-sync full pass | Run `/sync-docs` command | Fix stale facts |

## Decay Risk Register

Files most likely to become stale, ordered by risk:

| File | Risk | Decay Vector | Mitigation |
|------|------|-------------|------------|
| `docs/COMPACT.md` | HIGH | Every session changes state | Lifecycle policy enforced |
| `PROJECT_STATUS.md` | HIGH | Test counts change with every test addition | Single source of truth for counts |
| `docs/DB_FACTS.md` | MEDIUM | Schema changes via migrations | DB_FACTS delegates to ARCHITECTURE_FACTS |
| `docs/DATABASE.md` | MEDIUM | 767 lines; ER diagrams hard to maintain | Periodic manual review |
| `.agent/rules/*.md` | MEDIUM | Derived from ARCHITECTURE_FACTS.md | Compare quarterly |
| `skill-os/ROLLOUT-14DAY.md` | HIGH | Time-bound schedule | Archive when expired |
| `docs/API_DEPRECATION.md` | MEDIUM | Sunset date July 2026 approaching | Review before July 2026 |
| `BACKLOG.md` | MEDIUM | Items completed without updating | Regular grooming |

## Authority Chain

When files disagree, this is the resolution order:

1. **CLAUDE.md** (root contract — overrides everything)
2. **docs/agents/ARCHITECTURE_FACTS.md** (domain invariants)
3. **docs/agents/CONTRACT.md** (Definition of Done)
4. **docs/PERMISSION_MATRIX.md** (RBAC baseline)
5. **docs/DB_FACTS.md** (operational DB reference — defers to #2 for invariants)
6. **skills/, skill-os/, .agent/rules/** (task-specific — defer to #1-#4)
7. **docs/COMPACT.md** (volatile — never authoritative for invariants)

## Refactor History

| Date | Phase | Changes | Lines Changed |
|------|-------|---------|---------------|
| 2026-03-22 | A | Inventory (147→150 files) + classification (8 conflicts, 6 redundancies) | 0 (audit-only) |
| 2026-03-22 | B | CLAUDE.md +2 refs, AGENTS.md -101 (GitNexus dedup), DB_FACTS +3 (header), COMMANDS +2 (xref), deleted 2 skill wrappers | -156 lines |
| 2026-03-22 | C | Hooks/compact/MCP/agents audited; .agent/rules/ gap found | 0 (audit-only) |
| 2026-03-22 | D | Structural + semantic + boundary validation; 1 drift found (frontend test count) | 0 (validation-only) |

## Validation Artifacts

| Artifact | Location | Purpose |
|----------|----------|---------|
| Inventory | `docs/cleanup/00-inventory.md` | Complete file catalog |
| Classification | `docs/cleanup/01-classification-matrix.md` | Layer assignments + conflicts |
| Invariant baseline | `foundation/03-invariant-baseline.md` | 78 instructions preserved |
| Invariant delta | `docs/cleanup/02-invariant-delta.md` | Change tracking |
| Rules consolidation | `docs/cleanup/03-rules-consolidation-report.md` | C-04, C-05 resolution |
| Skills consolidation | `docs/cleanup/04-skills-refactor-report.md` | C-02, C-03 resolution |
| Command-skill map | `docs/cleanup/05-command-skill-map.md` | Dependency graph |
| Hooks report | `docs/cleanup/06a-hooks-report.md` | Settings alignment |
| Compact/worklog report | `docs/cleanup/06b-compact-worklog-report.md` | Lifecycle compliance |
| MCP boundary report | `docs/cleanup/07-boundary-contract-report.md` | Policy alignment |
| Agent matrix | `docs/cleanup/08-agent-responsibility-matrix.md` | Responsibility boundaries |
| Structural validation | `docs/validation/10a-structural-results.md` | 25/25 paths valid |
| Drift matrix | `docs/validation/drift-matrix.md` | 9/10 consistent |
| Boundary checklist | `docs/validation/boundary-checklist.md` | All boundaries valid |
| Conformance fixtures | `docs/validation/fixtures/RC-001.md` | Booking overlap rule |
| Execution fixtures | `docs/validation/fixtures/SE-001.md` | /fix-backend gates |
| Command fixtures | `docs/validation/fixtures/CD-001.md` | Slash command availability |
| Gate A | `docs/gates/gate-a-result.md` | PASS_WITH_CONDITIONS |
| Gate B | `docs/gates/gate-b-result.md` | PASS_WITH_CONDITIONS |
| Gate C | `docs/gates/gate-c-result.md` | PASS_WITH_CONDITIONS |

## Open Items (carried from all phases)

| ID | Item | Priority | Phase |
|----|------|----------|-------|
| U-01 | DB_FACTS.md Section 2 invariant overlap | LOW | B |
| U-02 | DEVELOPMENT_HOOKS.md redirect deletion | LOW | B |
| U-03 | COMPACT.md §1 one line over limit | LOW | C |
| U-04 | COMPACT.md branch state stale | LOW | C |
| U-05 | policy.json schema_version field | LOW | C |
| U-06 | frontend_lint npm vs pnpm | LOW | C |
| U-07 | .agent/rules/ missing from inventory | MEDIUM | C |
| U-08 | No API contract handoff between agents | LOW | C |
| U-09 | docs-sync verified-against frontmatter gap | LOW | C |
| U-10 | Frontend test count drift (226→236) | LOW | D |
