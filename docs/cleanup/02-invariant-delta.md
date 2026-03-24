---
schema_version: 1.0
produced_by_batch: B3
phase: Phase B
date: 2026-03-22
input_artifacts:
  - foundation/03-invariant-baseline.md
  - foundation/00-master-contract.md
  - foundation/00-output-schemas.md
  - docs/cleanup/01-classification-matrix.md
authority_order_applied: false
unresolved_count: 1
---

# Invariant Delta — Batch 3

> Generated: 2026-03-22 | Branch: dev

## Invariant baseline (before refactor)

78 unique instructions extracted from claude.md (74) and agents.md (4).
Full baseline captured in `foundation/03-invariant-baseline.md` with IDs I-01 through I-74 and A-01 through A-04.

15 duplicate instructions identified in agents.md GitNexus section (identical to claude.md lines 106-206).

## Invariant tracking table

| invariant | original_location | disposition | new_location | justification |
|-----------|-------------------|------------|--------------|---------------|
| I-01: Monorepo identity | claude.md:8 | PRESERVED_IN_PLACE | claude.md:8 | — |
| I-02: Branch model | claude.md:9 | PRESERVED_IN_PLACE | claude.md:9 | — |
| I-03: Stack versions | claude.md:10 | PRESERVED_IN_PLACE | claude.md:10 | — |
| I-04: Infrastructure | claude.md:11 | PRESERVED_IN_PLACE | claude.md:11 | — |
| I-05: @ARCHITECTURE_FACTS import | claude.md:15 | PRESERVED_IN_PLACE | claude.md:15 | — |
| I-06: @CONTRACT import | claude.md:16 | PRESERVED_IN_PLACE | claude.md:16 | — |
| I-07: PERMISSION_MATRIX on-demand | claude.md:19 | PRESERVED_IN_PLACE | claude.md:19 | — |
| I-08: COMMANDS on-demand | claude.md:20 | PRESERVED_IN_PLACE | claude.md:20 | — |
| I-09: COMPACT on-demand | claude.md:21 | PRESERVED_IN_PLACE | claude.md:21 | — |
| I-10: skills/README on-demand | claude.md:22 | PRESERVED_IN_PLACE | claude.md:22 | "(generic)" qualifier added |
| I-11: FINDINGS_BACKLOG on-demand | claude.md:25 | PRESERVED_IN_PLACE | claude.md:25 | Shifted +2 lines from new refs |
| I-12: output-styles on-demand | claude.md:26 | PRESERVED_IN_PLACE | claude.md:26 | Shifted +2 lines from new refs |
| I-13 through I-29: Non-negotiable invariants (17) | claude.md:28-53 | PRESERVED_IN_PLACE | claude.md:30-55 | Line shift only (+2) |
| I-30 through I-37: Frontend rules (8) | claude.md:55-64 | PRESERVED_IN_PLACE | claude.md:57-66 | Line shift only (+2) |
| I-38 through I-43: Validation gates (6) | claude.md:66-75 | PRESERVED_IN_PLACE | claude.md:68-77 | Line shift only (+2) |
| I-44 through I-47: Commit format (4) | claude.md:77-84 | PRESERVED_IN_PLACE | claude.md:79-86 | Line shift only (+2) |
| I-48 through I-54: Editing boundaries (7) | claude.md:86-96 | PRESERVED_IN_PLACE | claude.md:88-98 | Line shift only (+2) |
| I-55 through I-59: File-specific rules (5) | claude.md:98-104 | PRESERVED_IN_PLACE | claude.md:100-106 | Line shift only (+2) |
| I-60 through I-74: GitNexus (15) | claude.md:106-206 | PRESERVED_IN_PLACE | claude.md:108-208 | Auto-managed section unchanged |
| A-01: Layer table | agents.md:6-14 | PRESERVED_IN_PLACE | agents.md:6-14 | skill-os row added |
| A-02: Core domains | agents.md:18 | PRESERVED_IN_PLACE | agents.md:18 | — |
| A-03: Business risks | agents.md:22-24 | PRESERVED_IN_PLACE | agents.md:22-24 | — |
| A-04: Repo layout | agents.md:28-34 | PRESERVED_IN_PLACE | agents.md:28-34 | — |
| GitNexus duplicate (15 instructions) | agents.md:36-136 | INTENTIONALLY_REMOVED | — | Identical content exists in claude.md:108-208; agents.md copy was pure duplication with no unique content; gitnexus:start/end markers removed to prevent re-injection |

## Content relocation map

| source | destination | type |
|--------|-------------|------|
| agents.md lines 36-136 (GitNexus) | REMOVED — canonical copy at claude.md:108-208 | Deduplication |
| (new) skill-os/readme.md reference | claude.md:23 | Addition |
| (new) docs/domain_layers.md reference | claude.md:24 | Addition |
| (new) skill-os row | agents.md:13 | Addition |

## claude.md structure after refactor (section headers only)

```
# CLAUDE.md — Soleil Hostel
## Project Identity
## Canonical References (auto-expanded)
## Non-Negotiable Invariants (summary)
## Frontend Rules
## Validation Gates
## Commit Format (hook-enforced)
## Editing Boundaries
## File-Specific Rules
# GitNexus — Code Intelligence (auto-managed)
## Always Do
## When Debugging
## When Refactoring
## Never Do
## Tools Quick Reference
## Impact Risk Levels
## Resources
## Self-Check Before Finishing
## Keeping the Index Fresh
## CLI
```

## Unresolved items

| id | description | evidence_missing | blocks_batch |
|----|-------------|-----------------|-------------|
| UNRESOLVED-B3-1 | GitNexus gitnexus:start/end markers removed from agents.md — if GitNexus CLI re-injects on analyze, duplication returns | Behavior of `npx gitnexus analyze` on files without markers | — |
