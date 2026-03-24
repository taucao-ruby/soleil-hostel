---
schema_version: 1.0
produced_by_batch: B4
phase: Phase B
date: 2026-03-22
input_artifacts:
  - docs/cleanup/01-classification-matrix.md
  - foundation/00-master-contract.md
  - foundation/00-output-schemas.md
authority_order_applied: true
unresolved_count: 3
---

# Rules Consolidation Report — Batch 4

> Generated: 2026-03-22 | Branch: dev

## Observed reality

- docs/db_facts.md §2 (invariants) overlaps docs/agents/architecture_facts.md §Booking Domain
- docs/agents/commands.md §Quality Gates overlaps docs/commands_and_gates.md §Gates
- docs/development_hooks.md is a 3-line redirect stub (original merged to docs/hooks.md)

## Conflicts detected

Two content-level conflicts identified (C-04, C-05).

## Refactor plan proposed

1. C-04: Add delegation header to docs/db_facts.md — establish architecture_facts.md as canonical for invariants
2. C-05: Add cross-reference in docs/agents/commands.md pointing to docs/commands_and_gates.md as full gate reference
3. C-07: No action on development_hooks.md (safe redirect, pending link audit)

## Rules consolidation table

| rule_id | canonical_file | source_files | conflicts_detected | resolution | downstream_references_updated |
|---------|---------------|-------------|-------------------|------------|------------------------------|
| R-01 | docs/agents/architecture_facts.md | docs/db_facts.md §2, skill-os/context/invariants.md | YES — invariant text duplicated | Delegation header added to db_facts.md | NO — db_facts.md consumers not enumerated |
| R-02 | docs/commands_and_gates.md | docs/agents/commands.md §Quality Gates | YES — gate listing duplicated | Cross-reference added to commands.md | NO — commands.md consumers not enumerated |
| R-03 | docs/hooks.md | docs/development_hooks.md | NO — already consolidated (redirect) | Deferred deletion | NO — pending link audit |

## Conflict resolution table

| conflict_id | source_a | source_b | nature | authority_applied | resolution |
|-------------|----------|----------|--------|-------------------|------------|
| C-04 | docs/agents/architecture_facts.md | docs/db_facts.md §2 | Invariant text duplicated across both files | Level 2 (RULES) — architecture_facts.md is canonical | Delegation header added to db_facts.md: "When invariants overlap, ARCHITECTURE_FACTS.md is authoritative" |
| C-05 | docs/commands_and_gates.md | docs/agents/commands.md | Quality gate listing appears in both | Level 2 (RULES) — commands_and_gates.md is the comprehensive reference | Cross-reference note added above gates section in commands.md |

## Downstream replacement map

| file | reference_updated | type |
|------|------------------|------|
| docs/db_facts.md | Added delegation header pointing to architecture_facts.md | Header addition (+3 lines) |
| docs/agents/commands.md | Added cross-reference to commands_and_gates.md | Note addition (+2 lines) |

## Changes applied

| file | change | lines |
|------|--------|-------|
| docs/db_facts.md | Delegation header to architecture_facts.md | +3 |
| docs/agents/commands.md | Cross-reference to commands_and_gates.md | +2 |

## Unresolved items

| id | description | evidence_missing | blocks_batch |
|----|-------------|-----------------|-------------|
| UNRESOLVED-B4-1 | docs/db_facts.md §2 invariant text not deduplicated — full text still present | Risk assessment of removing invariant text from a file agents read in isolation | — |
| UNRESOLVED-B4-2 | docs/development_hooks.md not deleted — pending link audit | List of files/scripts that may reference this path | — |
| UNRESOLVED-B4-3 | Downstream consumers of db_facts.md and commands.md not enumerated | Full grep for references not performed | — |

## Deliverables produced

- docs/cleanup/03-rules-consolidation-report.md (this file)

## Risks and follow-up for next batch

- B5 (skills) should verify that skill files referencing architecture_facts.md are not affected by db_facts.md header change
