---
schema_version: 1.0
produced_by_batch: B9B
phase: Phase C
date: 2026-03-22
input_artifacts:
  - docs/cleanup/01-classification-matrix.md
  - foundation/00-master-contract.md
  - foundation/00-output-schemas.md
authority_order_applied: false
unresolved_count: 4
---

# Agent Responsibility Matrix — Batch 9B

> Generated: 2026-03-22 | Branch: dev

## Observed reality

4 subagents in .claude/agents/. 3 fast-load rule files in .agent/rules/ (NOT in .claude/). Agents have explicit scope boundaries with intentional locking handoff between security-reviewer and db-investigator.

### Inventory gap

.agent/rules/ directory (3 files, 138 lines) was NOT captured in original Batch 1 inventory. Added retroactively.

## Conflicts detected

No conflicts between agent scopes. Intentional overlap on booking locking is explicitly documented in both agent files (security-reviewer owns call-sites, db-investigator owns column existence).

## Agent overlap matrix

| agent_a | agent_b | overlap_area | severity | resolution |
|---------|---------|-------------|----------|------------|
| security-reviewer | db-investigator | Booking locking | MINOR | Explicit handoff documented: security-reviewer owns lockForUpdate() call-site correctness; db-investigator owns lock_version column existence. Both reference .agent/rules/booking-integrity.md. |
| security-reviewer | frontend-reviewer | API endpoint security | MINOR | security-reviewer checks backend enforcement; frontend-reviewer checks frontend API call hygiene. No formal handoff protocol. |
| docs-sync | security-reviewer | ARCHITECTURE_FACTS.md accuracy | NONE | docs-sync verifies doc accuracy; security-reviewer applies invariants during review. No overlap. |
| docs-sync | db-investigator | Schema documentation | NONE | docs-sync verifies docs; db-investigator verifies actual schema. No overlap. |
| frontend-reviewer | db-investigator | — | NONE | No shared scope. |
| docs-sync | frontend-reviewer | — | NONE | No shared scope. |

## Changes applied

No changes made in this batch (audit-only).

## Unresolved items

| id | description | evidence_missing | blocks_batch |
|----|-------------|-----------------|-------------|
| UNRESOLVED-B9B-1 | .agent/rules/ directory missing from Batch 1 inventory (3 files, 138 lines) | Retroactively documented but inventory file not re-issued at time of B9B | — |
| UNRESOLVED-B9B-2 | No formal API contract handoff between frontend-reviewer and security-reviewer | Decision on whether explicit handoff is needed or implicit separation is sufficient | — |
| UNRESOLVED-B9B-3 | docs-sync agent references .agent/rules/*.md with "verified-against" frontmatter — but actual rule files lack this field | Rule file frontmatter format not standardized | — |
| UNRESOLVED-B9B-4 | Agent contract template (Role/Scope/Out of scope/Inputs/Output contract/Escalation path/Forbidden actions/Negative examples/Linked rules) not applied to existing agent files | Template conformance audit not performed — would require modifying 4 agent files | — |

## Deliverables produced

- docs/cleanup/08-agent-responsibility-matrix.md (this file)

## Risks and follow-up for next batch

- If new agents are added, overlap matrix must be updated
- Agent contract template conformance (UNRESOLVED-B9B-4) is a future normalization task
- .agent/rules/ files should carry verified-against dates to prevent silent drift from architecture_facts.md
