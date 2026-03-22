# 14-Day Rollout Plan — Booking Skill OS

## Day 1: Foundation

**What gets done:** Create the Skill OS directory structure, TAXONOMY, and invariant context document.
**Deliverable:** `skill-os/TAXONOMY.md`, `skill-os/STRUCTURE.md`, `skill-os/context/INVARIANTS.md`
**Verification gate:** TAXONOMY has 4 categories, each tied to a concrete failure mode. STRUCTURE matches the canonical layout. INVARIANTS references real migration/model paths.
**Dependency:** None. Starting point.

## Day 2: Verification Framework

**What gets done:** Write the VERIFICATION-FRAMEWORK.md defining layers, hierarchy, tiers, and false confidence patterns.
**Deliverable:** `skill-os/VERIFICATION-FRAMEWORK.md`
**Verification gate:** Framework defines 5 layers, the source-of-truth hierarchy, 3 false confidence patterns specific to booking systems, and 3 verification tiers with crisp criteria.
**Dependency:** Day 1 (TAXONOMY and INVARIANTS exist).

## Day 3: verify-no-double-booking (Draft)

**What gets done:** Write the most critical skill. Cover all 4 booking invariants (INV-1 through INV-4). Include execution steps, invariant check, checklist, anti-patterns, and edge cases.
**Deliverable:** `skill-os/skills/verification/verify-no-double-booking/SKILL.md`, `checklist.md`
**Verification gate:** Skill has all required sections. Checklist has 15+ binary items. Edge cases include same-day turnover, cancelled-rebooked, and soft-delete gaps.
**Dependency:** Day 2 (framework defines what "verification" means).

## Day 4: verify-no-double-booking (Battle Test)

**What gets done:** Execute the skill against the actual codebase. Run every execution step. Fill in the checklist. Document findings.
**Deliverable:** `skill-os/outputs/verify-no-double-booking-2026-03-25.md` (first real execution output)
**Verification gate:** Every checklist item answered YES or NO with evidence. Any NO items have documented remediation. Skill text updated if execution revealed gaps in the instructions.
**Dependency:** Day 3 (skill exists to execute).

## Day 5: review-schema-change-risk

**What gets done:** Write the migration risk review skill. Cover FK changes, nullability, constraint modifications, rollback safety. Write the migration risk review template.
**Deliverable:** `skill-os/skills/review/review-schema-change-risk/SKILL.md`, `skill-os/templates/migration-risk-review.md`
**Verification gate:** Skill covers all escalation columns. Risk tier criteria are explicit (BLOCK/HIGH/MEDIUM/LOW). Template is fill-in-the-blank with no ambiguous fields.
**Dependency:** Day 2 (framework established).

## Day 6: review-schema-change-risk (Battle Test)

**What gets done:** Execute the skill against a real migration (pick the most recent one in the repo). Fill in the template. Document the process.
**Deliverable:** `skill-os/outputs/migration-risk-review-2026-03-27.md` (first template usage)
**Verification gate:** Template is fully filled in. Risk tier is justified. Execution revealed any gaps in skill instructions (and skill is updated).
**Dependency:** Day 5 (skill and template exist).

## Day 7: verify-docs-vs-code

**What gets done:** Write the docs drift verification skill. Define severity scale. Include "docs win" exceptions. Write the docs drift example.
**Deliverable:** `skill-os/skills/verification/verify-docs-vs-code/SKILL.md`, `skill-os/examples/docs-drift-review-example.md`
**Verification gate:** Skill defines STALE/MISLEADING/DANGEROUS with examples. Verification pass order is schema → model → controller → docs. Example includes a DANGEROUS drift finding involving INV-1.
**Dependency:** Day 2 (framework established). Battle-tested skills exist from Days 4 and 6.

## Day 8: Lessons Seed

**What gets done:** Write the booking invariant gotchas file. Seed with 8 entries covering the most common failure patterns. Incorporate findings from Day 4 and Day 6 battle tests.
**Deliverable:** `skill-os/lessons/booking-invariant-gotchas.md`
**Verification gate:** 8 gotchas, each with invariant reference, scenario, symptom, root cause, correct pattern, and detection method. At least 2 entries informed by battle test findings.
**Dependency:** Days 4, 6 (battle test findings feed lessons).

## Day 9: pre-release-verification

**What gets done:** Write the release gate skill. Define BLOCKED and CONDITIONAL criteria. Reference the three sub-skills. Write the release readiness report template.
**Deliverable:** `skill-os/skills/release/pre-release-verification/SKILL.md`, `skill-os/templates/release-readiness-report.md`
**Verification gate:** Skill references all 3 sub-skills. BLOCKED criteria are binary (9 items). CONDITIONAL criteria have resolution deadlines. Template covers all sections from the skill.
**Dependency:** Days 3, 5, 7 (sub-skills must exist before the aggregator references them).

## Day 10: pre-release-verification (Battle Test)

**What gets done:** Execute the full release verification against the current `dev` → `main` state. Run all sub-skills. Fill in the release readiness report.
**Deliverable:** `skill-os/outputs/release-readiness-2026-03-31.md`
**Verification gate:** Report is fully filled in. All 4 quality gates run. Sub-skill results referenced. Verdict is RELEASE / CONDITIONAL / BLOCKED with justification.
**Dependency:** Day 9 (release skill exists).

## Day 11: Operating Guide

**What gets done:** Write the operating guide — how to use the Skill OS in daily AI-assisted engineering.
**Deliverable:** `skill-os/OPERATING-GUIDE.md`
**Verification gate:** Includes decision tree for skill invocation, context loading instructions, invocation template, and anti-patterns. A new user could follow the guide without asking questions.
**Dependency:** Days 4, 6, 10 (battle test experience informs operational guidance).

## Day 12: Backlog and Risk Register

**What gets done:** Write the prioritized skill backlog (all skills worth building) and the risk register (open assumptions, deferred items).
**Deliverable:** `skill-os/BACKLOG.md`, `skill-os/RISK-REGISTER.md`
**Verification gate:** Backlog has P0/P1/P2 tiers. Each skill maps to a real risk. Risk register documents deferred decisions with justification.
**Dependency:** Days 1–11 (full system context needed to prioritize).

## Day 13: README and Integration

**What gets done:** Write the root README.md. Integrate Skill OS references into the project's CLAUDE.md (or equivalent). Ensure all cross-references between files are valid.
**Deliverable:** `skill-os/README.md`, updated project integration references
**Verification gate:** README has quick-start, philosophy, and file index. All internal links resolve. No orphaned files.
**Dependency:** Days 1–12 (all content exists).

## Day 14: Retrospective and Refinement

**What gets done:** Review all battle test outputs. Update skills based on lessons learned. Verify all files are consistent. Close out Day 14 with a state summary.
**Deliverable:** Updated skills (if needed), `skill-os/logs/rollout-retrospective-2026-04-04.md`
**Verification gate:** All 4 skills executed at least once. All templates used at least once. Lessons file has entries from real execution. Risk register updated with post-rollout findings.
**Dependency:** Days 1–13 (everything exists and has been used).
