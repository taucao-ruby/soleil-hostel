# Agent Self-Learning Memory — Schema Definition

This file defines the canonical schema for all learning entries in `AGENT_LEARNINGS.md`.
It contains NO entries. For illustrative entries showing correct usage, see `AGENT_LEARNINGS_EXAMPLES.md`.

---

## Required Fields

### `id`
- **Type**: string
- **Required**: yes
- **Format**: `SL-NNN` — zero-padded to 3 digits, sequential, never reused. Example: `SL-001`, `SL-042`.
- **Validation**: must be unique across all entries in AGENT_LEARNINGS.md (active + archived + proposed).
- **Justification**: provides a stable cross-reference identifier for `related_entries` links and `promotion_rule` tracking across tasks and conversations.

---

### `date`
- **Type**: string
- **Required**: yes
- **Format**: ISO 8601 (`YYYY-MM-DD`). The date the entry was recorded by the agent.
- **Validation**: must be a real calendar date; must not be a future date.
- **Justification**: establishes when the learning was recorded, enabling staleness calculations and audit history. Not the date the failure occurred — use `trigger` for that context.

---

### `status`
- **Type**: string (enum)
- **Required**: yes
- **Allowed values**:
  - `ACTIVE` — entry is in use for agent reads
  - `ARCHIVED` — superseded, resolved, or stale; skip during all normal reads (see R-06)
  - `PROMOTED` — graduated to a canonical doc; retain for audit, skip during reads (see S-03)
  - `UNDER_REVIEW` — written but not yet reviewed; treat as `INFERENCE` confidence (see S-01)
- **Validation**: must be one of the four values above. No other values are valid.
- **Justification**: controls which entries agents read during task execution. Prevents stale or unreviewed entries from influencing agent behavior.

---

### `confidence`
- **Type**: string (enum)
- **Required**: yes
- **Allowed values**:
  - `CONFIRMED` — requires `review_status: PEER_REVIEWED`; grounded in schema file, passing test, or runtime output
  - `INFERENCE` — agent-observed pattern, not yet peer-validated
  - `PROPOSED_PRACTICE` — no failure occurred; a better approach is noted for future guidance
- **Forbidden value**: `HYPOTHESIS` — entries at this level are rejected (see REJ-06 and REJ-FIELD-02).
- **Validation**: `CONFIRMED` is invalid unless `review_status` is also `PEER_REVIEWED`.
- **Justification**: prevents speculative content from being treated as verified operational truth.

---

### `area`
- **Type**: string (enum)
- **Required**: yes
- **Allowed values** (single value only):
  `booking` | `rbac` | `locking` | `cache` | `frontend` | `api_contract` |
  `migrations` | `testing` | `docs` | `audit` | `review_system`
- **Validation**: must be exactly one value. If a failure spans areas, pick the primary area and link to the secondary area's entry via `related_entries`.
- **Justification**: enables tag-scoped reads (R-01 through R-04) and prevents full-file reads (R-05). The area value must appear as a tag.

---

### `task_type`
- **Type**: string (free-form, specific)
- **Required**: yes
- **Format**: a precise task description. Not "coding" or "backend work".
- **Example values**: `"booking-write mutation"`, `"migration authoring"`, `"RBAC middleware verification"`, `"frontend API contract sync"`
- **Justification**: narrows applicability so agents can quickly judge whether the entry is relevant to their current task without reading the full entry.

---

### `trigger`
- **Type**: string
- **Required**: yes
- **Format**: one sentence. Describes what event or signal exposed the failure.
- **Example**: `"Test suite failure on BookingOverlapTest after date boundary refactor."`
- **Justification**: gives future agents context on what task class surfaces this failure, improving recall precision.

---

### `mistake`
- **Type**: string
- **Required**: yes
- **Format**: one sentence. Active voice. Past tense. Must be falsifiable.
- **Bad example**: `"Agent did something wrong with overlap."`
- **Good example**: `"Agent used closed interval [a,b] instead of [a,b) when constructing the overlap WHERE clause."`
- **Validation**: rejected if not a falsifiable statement (REJ-FIELD-03).
- **Justification**: falsifiable mistakes can be checked against code; vague mistakes provide no actionable signal.

---

### `impact`
- **Type**: string
- **Required**: yes
- **Format**: observable or measurable consequence. No speculation.
- **Bad example**: `"Could have caused problems."`
- **Good example**: `"Booking accepted for a room already confirmed for same period. PostgreSQL exclusion constraint caught it at DB level and raised exception."`
- **Justification**: quantifies the severity of the failure pattern, helping agents prioritize which learnings to act on first.

---

### `evidence`
- **Type**: string
- **Required**: yes — no entry is valid without this field populated.
- **Accepted evidence types**:
  - file path + line number: `"app/Services/BookingService.php:147"`
  - migration name: `"2024_03_01_create_booking_exclusion_constraint"`
  - test name + result: `"BookingOverlapTest::test_same_day_checkout FAILED"`
  - exact command + output excerpt (≤5 lines)
  - runtime log excerpt with timestamp
- **Not accepted**: `"assumed"`, `"inferred"`, `"based on prior context"`, `"seemed likely"`, empty string.
- **Validation**: rejected if absent, empty, or vague (REJ-FIELD-01).
- **Justification**: without verifiable evidence, the entry is speculation. Evidence anchors the learning to the current state of the repository.

---

### `incorrect_pattern`
- **Type**: string (code block or precise prose)
- **Required**: yes
- **Format**: fenced code block where applicable; precise prose where code is not the medium. Must show exactly what was done wrong.
- **Validation**: rejected if absent (REJ-FIELD-04). Must be concrete, not a vague description.
- **Justification**: agents need to recognize the wrong pattern when they encounter it. A vague description cannot be matched against code.

---

### `corrected_pattern`
- **Type**: string (code block or precise prose)
- **Required**: yes
- **Format**: fenced code block where applicable; precise prose where code is not the medium. Must show exactly what should be done instead.
- **Validation**: rejected if absent (REJ-FIELD-05). Must not remove transaction discipline, locking, RBAC enforcement, or constraint logic.
- **Justification**: agents need the correct pattern to implement without guessing. The corrected pattern must preserve all safety-critical constructs.

---

### `applicability`
- **Type**: string
- **Required**: yes
- **Format**: narrow, specific task class description.
- **Bad example**: `"All backend work."`
- **Good example**: `"Any task that writes to the bookings table."`
- **Justification**: scopes the entry to situations where it should be read. Overly broad applicability causes agents to read entries irrelevant to their current task.

---

### `related_invariants`
- **Type**: list of strings
- **Required**: yes (write `none` if genuinely not applicable)
- **Format**: list of `INV-XX` codes from the invariant registry in CLAUDE.md and ARCHITECTURE_FACTS.md.
- **Minimum**: one code for `booking`, `locking`, and `rbac` area entries.
- **Validation**: if an entry conflicts with an invariant without citing overriding repo evidence, it must be flagged for human review (REJ-FIELD-06).
- **Justification**: links learning entries to the invariant layer, enabling cross-reference and preventing entries from silently contradicting protected truths.

---

### `related_commands`
- **Type**: list of strings
- **Required**: yes
- **Format**: exact CLI commands an agent can run to verify the corrected pattern.
- **Examples**:
  - `"php artisan test --filter=BookingOverlapTest"`
  - `"psql -c '\\d+ bookings'"`
- **Justification**: agents should verify their implementation of the corrected pattern. Runnable commands make verification concrete and executable.

---

### `stale_after`
- **Type**: string
- **Required**: yes
- **Format**: ISO 8601 date (`YYYY-MM-DD`) or a named condition (e.g., `"After next booking schema migration"`).
- **Default**: 90 days from the `date` field.
- **Validation**: entries without `stale_after` default to +90 days (REJ-FIELD-07).
- **Justification**: entries become incorrect as the codebase evolves. The staleness date triggers an UNDER_REVIEW flag before the entry is used to guide behavior in a new task.

---

### `promotion_rule`
- **Type**: string
- **Required**: yes
- **Format**: condition under which the entry should graduate to a canonical doc. Must name the target file.
- **Example**: `"If this pattern is confirmed in 3+ separate tasks, migrate to ARCHITECTURE_FACTS.md section: Booking Invariants."`
- **Justification**: prevents indefinite accumulation of entries. Patterns confirmed across multiple tasks should be promoted to the canonical layer where they have more authority.

---

### `review_status`
- **Type**: string (enum)
- **Required**: yes
- **Allowed values**:
  - `PEER_REVIEWED` — reviewed by a human engineer; confidence may be `CONFIRMED`
  - `SELF_RECORDED` — written by agent; always treated as `INFERENCE` regardless of recorded confidence (see R-07)
  - `NEEDS_REVIEW` — flagged for review; treated as `INFERENCE` (see R-07)
- **Validation**: new entries from agents must always be `SELF_RECORDED`. `CONFIRMED` confidence requires `PEER_REVIEWED`.
- **Justification**: prevents agents from promoting their own entries to confirmed status. Human review is the gate between `INFERENCE` and `CONFIRMED`.

---

### `tags`
- **Type**: list of strings
- **Required**: yes
- **Format**: flat list, maximum 5 tags, lowercase kebab-case only.
- **Constraint**: must include the `area` value as a tag.
- **Examples**: `[booking, overlap, interval, half-open, availability]`
- **Justification**: enables tag-scoped reads (R-01 through R-04) without full-file reads. Tags are the primary mechanism for efficient entry retrieval.

---

## Optional Fields

### `owner_doc`
- **Type**: string
- **Required**: no — include only when the truth more properly belongs in another file.
- **Example**: `"ARCHITECTURE_FACTS.md — Booking Invariants section"`
- **Justification**: when a learning entry is a candidate for promotion, recording the target doc guides the promotion workflow.

---

### `related_entries`
- **Type**: list of `SL-NNN` references
- **Required**: no — include when entries share a failure class or are causally linked.
- **Justification**: connects entries that belong to the same failure cluster, enabling agents to read the full set when one entry matches.

---

### `notes`
- **Type**: string
- **Required**: no
- **Format**: free-form. Hard limit: 3 sentences. Not a narrative field. Not a workaround doc. Not a place to explain platform history.
- **Justification**: allows context that doesn't fit other structured fields, with a 3-sentence cap to prevent scope creep.

---

## Field Rejection Rules

These rules are enforced at write time. An entry violating any of these must be rejected or flagged before activation.

| Rule | Condition | Action |
|------|-----------|--------|
| `REJ-FIELD-01` | `evidence` is absent, empty, or vague | REJECT ENTRY |
| `REJ-FIELD-02` | `confidence` is `HYPOTHESIS` | REJECT ENTRY |
| `REJ-FIELD-03` | `mistake` is not a falsifiable statement | REJECT ENTRY |
| `REJ-FIELD-04` | `incorrect_pattern` is absent | REJECT ENTRY |
| `REJ-FIELD-05` | `corrected_pattern` is absent | REJECT ENTRY |
| `REJ-FIELD-06` | `related_invariants` conflicts with `INV-XX` without citing overriding repo evidence (schema file path + line, migration name, passing test output, or runtime log) | FLAG FOR HUMAN REVIEW |
| `REJ-FIELD-07` | `stale_after` is not set | Default to `date` + 90 days |

---

## Entry Format

All entries must use YAML front-matter followed by a markdown body. The exact structure:

```yaml
---
id: SL-NNN
date: YYYY-MM-DD
status: ACTIVE
confidence: INFERENCE
area: booking
task_type: "booking-write mutation"
trigger: "One sentence describing the event that exposed this failure."
mistake: "One sentence, active voice, past tense, falsifiable."
impact: "Observable consequence — no speculation."
evidence: "file path:line OR migration name OR test name + result OR command + output"
applicability: "Narrow task class where this learning applies."
related_invariants: [INV-01, INV-02]
related_commands:
  - "php artisan test --filter=BookingOverlapTest"
stale_after: YYYY-MM-DD
promotion_rule: "Condition and target file for graduation to canonical doc."
review_status: SELF_RECORDED
tags: [booking, overlap, interval, half-open]
---

### Incorrect Pattern
```php
// code here
```

### Corrected Pattern
```php
// code here
```

### Notes
(optional — max 3 sentences)
```

---

## Allowed `area` Values Reference

| Value | Domain |
|-------|--------|
| `booking` | Booking creation, mutation, cancellation, overlap |
| `rbac` | Role-based access control, authorization, middleware |
| `locking` | Pessimistic/optimistic locking, concurrency |
| `cache` | Redis cache reads/writes, invalidation |
| `frontend` | React, TypeScript, component behavior |
| `api_contract` | API request/response shape, Laravel Resources, TypeScript types |
| `migrations` | Schema migrations, rollback, PostgreSQL vs SQLite |
| `testing` | Test coverage, test driver selection, assertions |
| `docs` | Documentation, learning entry quality, file ownership |
| `audit` | Audit log writes, AdminAuditService, forensic recovery |
| `review_system` | Review creation, booking_id FK, approval workflow |
