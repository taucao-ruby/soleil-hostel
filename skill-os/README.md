# Booking Skill OS — Soleil Hostel

A structured system of executable skills for verifying booking-system invariants, reviewing schema changes, validating documentation currency, and gating releases.

## Why This Exists

Green CI does not mean the booking system is correct. Tests can pass while the PostgreSQL exclusion constraint disagrees with the PHP overlap scope. RBAC middleware can exist on routes while controllers lack authorization checks. Documentation can describe closed intervals while code uses half-open intervals.

This system catches what CI cannot: cross-layer alignment failures specific to hostel booking systems.

## Quick Start

1. **Read the invariants:** `context/INVARIANTS.md` — 10 rules that must never be violated
2. **Pick a skill:** See the decision tree in `OPERATING-GUIDE.md` §When to Invoke a Skill
3. **Load context:** Load the skill's SKILL.md + `VERIFICATION-FRAMEWORK.md` + `ARCHITECTURE_FACTS.md`
4. **Execute:** Follow every step in the skill. Fill in the checklist. Produce the expected output.
5. **Capture lessons:** If you find something new, add it to `lessons/booking-invariant-gotchas.md`

## File Index

| File | Purpose |
|---|---|
| `TAXONOMY.md` | Skill categories with failure-mode justifications |
| `BACKLOG.md` | Prioritized skill backlog (P0/P1/P2) |
| `STRUCTURE.md` | Canonical folder layout and rationale |
| `VERIFICATION-FRAMEWORK.md` | Verification philosophy, layers, hierarchy, tiers |
| `OPERATING-GUIDE.md` | Daily usage: decision tree, invocation pattern, anti-patterns |
| `RISK-REGISTER.md` | Open assumptions, deferred decisions, expansion plan |
| `ROLLOUT-14DAY.md` | 14-day adoption sequence with verification gates |
| `context/INVARIANTS.md` | Domain invariants INV-1 through INV-10 |
| `skills/verification/verify-no-double-booking/` | P0: Overlap prevention verification |
| `skills/verification/verify-docs-vs-code/` | P0: Documentation drift detection |
| `skills/review/review-schema-change-risk/` | P0: Migration risk assessment |
| `skills/release/pre-release-verification/` | P0: Release gate |
| `templates/migration-risk-review.md` | Fill-in template for migration review |
| `templates/release-readiness-report.md` | Fill-in template for release gate |
| `examples/docs-drift-review-example.md` | Worked example of docs-vs-code review |
| `lessons/booking-invariant-gotchas.md` | Institutional knowledge: 8 failure patterns |

## P0 Skills (Must exist before next release)

| Skill | Protects Against |
|---|---|
| `verify-no-double-booking` | Double-booking via overlap logic drift |
| `review-schema-change-risk` | Silent constraint/FK weakening via migrations |
| `verify-docs-vs-code` | Developers violating invariants by following wrong docs |
| `pre-release-verification` | Releasing with unverified domain-level regressions |

## Philosophy

- **Cross-layer alignment over per-layer correctness.** A correct PHP scope + a correct SQL constraint that disagree = a broken system.
- **Binary verdicts.** Every checklist item is YES or NO. Every release gate is PASS or FAIL. No "consider whether."
- **Booking-specific, not generic.** Every skill, checklist, and anti-pattern is grounded in this codebase's actual schema, migration history, and failure modes.
- **Prevention over detection.** Skills run before merge, not after incident.
