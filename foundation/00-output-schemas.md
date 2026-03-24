# OUTPUT SCHEMA CONTRACT
# Defines the mandatory output format for each batch.
# Batch prompts must reference this file and conform to it.

---

## GLOBAL RULES
- Every table must have exactly the columns defined here — no additions, no omissions
- Enum values must be used exactly as written — no synonyms
- Unresolved items must use the UNRESOLVED schema — no freeform text
- Path conventions: always relative from repo root and use repo-exact casing,
  dot-prefixes, and extensions; use lowercase-kebab-case only when that matches the
  actual repo path
- All deliverable files must be listed under "Deliverables produced"
- Historical artifacts produced before this harmonization may retain legacy aliases
  (`claude.md`, `rules/*.md`, `commands/*.md`, `hooks/*.md`, `compact/*.md`,
  `agents/*.md`, `subagents/*.md`); treat those as compatibility aliases when
  validating prior outputs, but use repo-exact paths for new outputs

## REPO SURFACE MAP (compatibility-safe)

Use these concrete repo surfaces when a batch prompt or older artifact uses a
generic bucket label:

| legacy schema label | repo surface for new outputs | compatibility note |
|---------------------|------------------------------|--------------------|
| `claude.md` | `CLAUDE.md` | Exact casing required in new outputs |
| `rules/*.md` | `.agent/rules/*.md` | RULES remains the bucket name; file paths must use `.agent/rules/` |
| `skills/*.md` | `skills/**/*.md` | When bundled or generated skills are in scope, also use `.claude/skills/**/SKILL.md` |
| `commands/*.md` | `.claude/commands/*.md` | Slash-command contracts live under `.claude/commands/` |
| `hooks/*.md` | `.claude/hooks/*.sh` | Runtime hooks are shell scripts, not markdown files |
| `compact/*.md` | `docs/COMPACT.md` | Apply the same metadata block to future compact snapshots if they are introduced |
| `worklog/*.md` | `docs/WORKLOG.md` | Append-only ledger surface in this repo |
| `mcp/*.md` / `integrations/*.md` | `docs/mcp/*.md` / `docs/integrations/*.md` | Supporting runtime assets may live under `mcp/*/` or `integrations/*/` |
| `agents/*.md` / `subagents/*.md` | `.claude/agents/*.md` | This repo stores agent/subagent role contracts in one directory |

---

## BATCH 1 — INVENTORY OUTPUT SCHEMA

### File: docs/cleanup/00-inventory.md

Required table: inventory_table
Columns (exact, in order):
| path | current_role_inferred | truth_type | status | overlap_with | risk_level | recommended_action |

Enum — truth_type:
- SOURCE_OF_TRUTH
- DERIVED
- DUPLICATE
- UNCLEAR

Enum — status:
- ACTIVE
- STALE
- ORPHAN
- OBSOLETE
- UNCLEAR

Enum — risk_level:
- HIGH
- MEDIUM
- LOW

Enum — recommended_action:
- KEEP
- MOVE
- SPLIT
- MERGE
- ARCHIVE
- DELETE_CANDIDATE
- INVESTIGATE

Required sections:
- ## Summary counts (total files, by status, by truth_type)
- ## Inventory table
- ## Key overlaps identified
- ## High-risk items flagged
- ## Unresolved items

---

## BATCH 2 — CLASSIFICATION OUTPUT SCHEMA

### File: docs/cleanup/01-classification-matrix.md

Required table: classification_table
Columns (exact, in order):
| path | observed_bucket | correct_bucket | mismatch | mixed_responsibilities | tie_break_applied | recommended_action |

Enum — observed_bucket / correct_bucket:
- CONSTITUTION
- RULES
- SKILLS
- COMMANDS
- HOOKS
- AGENTS
- BOUNDARY_CONTRACT
- COMPACT_SNAPSHOT
- WORKLOG_LEDGER
- UNCLEAR

Enum — mismatch:
- YES
- NO
- PARTIAL

Tie-break rule (must be stated explicitly when applied):
"When a file could belong to two buckets, assign to the bucket with
lower runtime responsibility and narrower scope. If still ambiguous: UNRESOLVED."

Required sections:
- ## Classification table
- ## Files with mixed responsibilities (list with split recommendation)
- ## Files misplaced at wrong abstraction layer
- ## Tie-break decisions log
- ## Unresolved items

---

## BATCH 3 — CLAUDE.MD REFACTOR OUTPUT SCHEMA

### Files:
- CLAUDE.md (updated)
- docs/cleanup/02-invariant-delta.md

Required in docs/cleanup/02-invariant-delta.md:

Table: invariant_tracking_table
Columns (exact, in order):
| invariant | original_location | disposition | new_location | justification |

Enum — disposition:
- PRESERVED_IN_PLACE
- RELOCATED_WITH_REFERENCE
- INTENTIONALLY_REMOVED

Rule: No invariant may have disposition = INTENTIONALLY_REMOVED without
an explicit justification AND confirmation that no active behavior depends on it.

Required sections in 02-invariant-delta.md:
- ## Invariant baseline (before refactor)
- ## Invariant tracking table
- ## Content relocation map (source → destination for all moved content)
- ## CLAUDE.md structure after refactor (section headers only)
  Legacy alias accepted for pre-harmonization artifacts:
  `## claude.md structure after refactor (section headers only)`
- ## Unresolved items

---

## BATCH 4 — RULES NORMALIZATION OUTPUT SCHEMA

### Files:
- .agent/rules/*.md (normalized)
- docs/cleanup/03-rules-consolidation-report.md

Rule template (every .agent/rules/*.md in scope must have):
```
## Purpose
## Rule
## Why it exists
## Applies to
## Violations
## Enforcement
## Linked skills / hooks
```

Required in 03-rules-consolidation-report.md:

Table: rules_consolidation_table
Columns (exact, in order):
| rule_id | canonical_file | source_files | conflicts_detected | resolution | downstream_references_updated |

Table: conflict_resolution_table
Columns (exact, in order):
| conflict_id | source_a | source_b | nature | authority_applied | resolution |

Required sections:
- ## Rules consolidation table
- ## Conflict resolution table
- ## Downstream replacement map (file → reference updated)
- ## Unresolved items

---

## BATCH 5 — SKILLS NORMALIZATION OUTPUT SCHEMA

### Files:
- skills/**/*.md and/or .claude/skills/**/SKILL.md (normalized, when in scope)
- docs/cleanup/04-skills-refactor-report.md

Skill template (every normalized skill file in scope must have):
```
## Outcome
## When to use
## Required inputs
## Steps
## Stop conditions
## Evidence required
## Output schema
## Linked rules
```

Compound skill rule:
"A skill may contain multiple sub-steps if those steps ALWAYS execute together
as a unit. In that case, sub-steps must be clearly labeled as sub-steps, not
as independent outcomes."

Table: skills_refactor_table
Columns (exact, in order):
| original_file | new_file(s) | outcome | split_applied | compound_justified | linked_rules | output_schema_defined |

Required sections:
- ## Skills refactor table
- ## Over-split risk items (skills that were split but may be too granular)
- ## Skills with undefined output schemas
- ## Unresolved items

---

## BATCH 6 — COMMANDS OUTPUT SCHEMA

### Files:
- .claude/commands/*.md (normalized)
- docs/cleanup/05-command-skill-map.md

Command template (every .claude/commands/*.md in scope must have):
```
## Intent
## Required context
## Preconditions
## Invoked skills
## Upstream dependencies
## Expected output artifact
## Failure behavior
## Escalation path
```

Table: command_skill_map
Columns (exact, in order):
| command | invoked_skills | upstream_dependencies | output_artifact | failure_behavior_defined | escalation_defined |

Required sections:
- ## Command-skill map
- ## Commands with embedded policy (must be extracted)
- ## Commands missing failure behavior
- ## Unresolved items

---

## BATCH 7 — HOOKS OUTPUT SCHEMA

### Files:
- .claude/hooks/*.sh (audited runtime hooks)
- docs/cleanup/06a-hooks-report.md

Per-hook audit fields (each .claude/hooks/*.sh entry must capture in the report):
```
## Trigger
## Check performed
## Enforcement action
## Evidence logged
## Linked rule
```

Table: hooks_audit_table
Columns (exact, in order):
| hook_file | trigger | enforcement_action | linked_rule | policy_duplication_found | deterministic |

---

## BATCH 8 — COMPACT + WORKLOG OUTPUT SCHEMA

### Files:
- docs/COMPACT.md (normalized with freshness metadata)
- docs/WORKLOG.md (audited append-only ledger)
- docs/cleanup/06b-compact-worklog-report.md

Compact required metadata block (top of docs/COMPACT.md and any future compact/*.md snapshots):
```
---
generated_from: [list of source files]
last_verified_at: [session ID or timestamp]
scope: [what this compact covers]
expiry_trigger: [what invalidates this compact]
status: ACTIVE | STALE | ARCHIVED
---
```

Table: compact_audit_table
Columns (exact, in order):
| compact_file | generated_from | last_verified_at | scope | status | action |

Table: worklog_audit_table
Columns (exact, in order):
| worklog_file | date_range | contains_policy | contains_architecture_claims | action |

---

## BATCH 9A — BOUNDARY CONTRACTS OUTPUT SCHEMA

### Files:
- docs/mcp/*.md or docs/integrations/*.md (normalized boundary contract docs)
- docs/cleanup/07-boundary-contract-report.md

Boundary contract template:
```
## Capability
## Request / response contract
## Schema version
## Last verified against
## Assumptions
## Auth / security model
## Idempotency / retry behavior
## Failure modes
## Observability
## Fallback / manual procedure
## Test / validation strategy
```

Table: boundary_coverage_table
Columns (exact, in order):
| boundary | capability_defined | schema_version | failure_modes_defined | auth_defined | fallback_defined | gaps |

---

## BATCH 9B — AGENTS/SUBAGENTS OUTPUT SCHEMA

### Files:
- .claude/agents/*.md (normalized agent/subagent contracts)
- docs/cleanup/08-agent-responsibility-matrix.md

Agent contract template:
```
## Role
## Scope
## Out of scope
## Inputs
## Output contract
## Escalation path
## Forbidden actions
## Negative examples (min 2 "should NOT do this" cases)
## Linked rules / skills
```

Table: agent_overlap_matrix
Columns (exact, in order):
| agent_a | agent_b | overlap_area | severity | resolution |

Enum — severity: CRITICAL | MAJOR | MINOR | NONE

---

## UNRESOLVED ITEM SCHEMA (use in all batches)

Every unresolved item must be logged as:
```
| UNRESOLVED-[batch]-[N] | [description] | [evidence_missing] | [blocks_batch] |
```

Columns: id | description | evidence_missing | blocks_batch

---

## GATE REVIEW OUTPUT SCHEMA

Required sections:
- ## Contracts intact? (YES / NO + details)
- ## Silent deletions detected? (YES / NO + items)
- ## Naming drift detected? (YES / NO + items)
- ## Broken references detected? (YES / NO + items)
- ## UNRESOLVED items carried forward
- ## Gate verdict: PASS | FAIL | BLOCKED
- ## Blockers (if FAIL or BLOCKED)

Gate verdict enum:
```
PASS    — all acceptance criteria met; next phase may begin
FAIL    — one or more criteria unmet; remediate before re-run
BLOCKED — gate cannot run or close due to missing prerequisite input or pending human action
```

> Legacy note: historical gate artifacts produced before 2026-03-24 may contain
> `PASS_WITH_CONDITIONS` as a verdict value. Treat these as historical evidence records.
> The active schema does not permit this value for new gate artifacts.
