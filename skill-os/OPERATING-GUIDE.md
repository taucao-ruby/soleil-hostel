# Operating Guide — Booking Skill OS

## When to Invoke a Skill

```
Is the change touching bookings, rooms, or availability logic?
├── YES → Run verify-no-double-booking
│         Is it a schema change? → Also run review-schema-change-risk
│         Is it a release? → Also run pre-release-verification
└── NO
    ├── Is it a migration on any table?
    │   └── YES → Run review-schema-change-risk
    ├── Is it a documentation change or review?
    │   └── YES → Run verify-docs-vs-code
    ├── Is it a release (any scope)?
    │   └── YES → Run pre-release-verification
    └── Is it a routine code change (no booking/migration/docs)?
        └── Standard code review. No skill needed.
```

**Rules:**
- When in doubt, run the skill. False positives are cheap; missed invariant violations are expensive.
- Skills can be combined. A migration to the bookings table triggers both `review-schema-change-risk` AND `verify-no-double-booking`.
- `pre-release-verification` is always the final skill before a release, regardless of what other skills were run during development.

## How to Load Context

Every session that will use skills should load these files:

**Always load (core context):**
```
skill-os/VERIFICATION-FRAMEWORK.md
docs/agents/ARCHITECTURE_FACTS.md
```

**Load per skill:**
```
verify-no-double-booking:  skill-os/skills/verification/verify-no-double-booking/SKILL.md
                           skill-os/skills/verification/verify-no-double-booking/checklist.md
review-schema-change-risk: skill-os/skills/review/review-schema-change-risk/SKILL.md
                           skill-os/templates/migration-risk-review.md
verify-docs-vs-code:       skill-os/skills/verification/verify-docs-vs-code/SKILL.md
pre-release-verification:  skill-os/skills/release/pre-release-verification/SKILL.md
                           skill-os/templates/release-readiness-report.md
```

**Load on demand:**
```
skill-os/lessons/booking-invariant-gotchas.md  — when debugging or reviewing booking changes
skill-os/examples/docs-drift-review-example.md — when running docs verification for the first time
```

## Skill Invocation Pattern

Use this template when asking Claude to execute a skill:

```
Execute the [skill-name] skill from skill-os/skills/[category]/[skill-name]/SKILL.md.

Context:
- Change description: [what was changed and why]
- Files affected: [list of changed files]
- Trigger condition matched: [which trigger condition from the skill applies]

Instructions:
- Follow every execution step in order
- Fill in the checklist completely (every item YES or NO)
- Produce the expected output format
- Flag any invariant check failures immediately
- If using a template, fill in every field (use N/A for non-applicable fields)

Output the results in skill-os/outputs/[skill-name]-[date].md
```

**Example:**
```
Execute the verify-no-double-booking skill from
skill-os/skills/verification/verify-no-double-booking/SKILL.md.

Context:
- Change description: Added 'waitlisted' status to BookingStatus enum
- Files affected: app/Enums/BookingStatus.php, migration 2026_03_25_000001
- Trigger condition matched: #6 — a new booking status is introduced

Follow every execution step. Fill in the checklist. Flag invariant failures.
Output to skill-os/outputs/verify-no-double-booking-2026-03-25.md
```

## Output Handling

### Where results go
- Skill execution outputs → `skill-os/outputs/[skill-name]-[date].md`
- Filled templates → `skill-os/outputs/[template-name]-[date].md`
- Outputs are gitignored by default (local audit trail, not repo bloat)
- Critical findings that need team visibility → PR description or PR comment

### How results feed lessons
When a skill execution reveals a new failure pattern:
1. Determine if it's a novel gotcha (not already in `lessons/booking-invariant-gotchas.md`)
2. If novel: add an entry using the GOTCHA-N format
3. If it reveals a gap in the skill: update the skill's checklist or anti-patterns
4. If it reveals a gap in the framework: update VERIFICATION-FRAMEWORK.md

### Result retention
- Keep outputs for 30 days minimum (local)
- Archive release readiness reports permanently (they document release decisions)
- Delete battle-test outputs after incorporating lessons

## Escalation Protocol

When skill output is insufficient to make a decision:

```
Skill says PASS but you're not confident?
├── Check: did you run every execution step? (partial runs are not valid)
├── Check: are there checklist items marked N/A that should be YES/NO?
├── Check: is the skill's coverage sufficient for this specific change?
│   └── NO → The skill has a coverage gap. Document it in RISK-REGISTER.md.
└── Still not confident → Escalate to human senior engineer review.
    Include: skill output, your specific concern, what additional verification you think is needed.

Skill says FAIL?
├── Is it a BLOCKED item? → Do not proceed. Fix the issue first.
├── Is it a CONDITIONAL item? → Proceed with documented resolution timeline.
└── Is the failure in the skill itself (wrong reference, outdated path)?
    → Fix the skill. Re-run. Do not ignore the failure because the skill is wrong.
```

## Skill Evolution

### When to update a skill
- After a battle test reveals execution steps that are unclear or incomplete
- After a new failure pattern is discovered (add to anti-patterns and checklist)
- After a codebase change makes references stale (file paths, column names)
- After a new invariant is added to the system

### How to update a skill
1. Read the current SKILL.md
2. Identify the specific section that needs updating
3. Make the change
4. Update the Changelog section with the date and what changed
5. If the change affects the checklist, update checklist.md
6. If the change affects the verification framework, update VERIFICATION-FRAMEWORK.md

### Version discipline
- Skills do not have version numbers. They have changelogs.
- The current file IS the current version. There is no "v1" vs "v2."
- Breaking changes (removed checklist items, changed pass criteria) should be called out in the changelog.

## Anti-Patterns

### AP-1: Running skills without reading them
**What:** Asking Claude to "run the double-booking check" without loading the SKILL.md.
**Why it fails:** Without the skill definition, Claude falls back on general knowledge. The execution will miss codebase-specific checks, real file paths, and the exact invariant alignment this skill is designed to verify.

### AP-2: Treating skill output as optional
**What:** Running a skill, getting a FAIL result, and proceeding anyway because "it's probably fine."
**Why it fails:** Skills are designed to catch issues that are NOT obvious. A FAIL that seems wrong is more likely a real problem that you don't yet understand than a false positive.

### AP-3: Running only the checklist without the execution steps
**What:** Jumping to the checklist and checking items YES/NO based on memory or assumptions.
**Why it fails:** The execution steps are ordered for a reason. Each step builds evidence for checklist items. Skipping steps means checklist answers are guesses, not verified facts.

### AP-4: Forking skills instead of updating them
**What:** Creating `verify-no-double-booking-v2.md` instead of updating the original.
**Why it fails:** Two versions of the same skill will drift apart. Contributors won't know which is current. Update the original and use the changelog.

### AP-5: Using skills for non-domain concerns
**What:** Running `verify-no-double-booking` to check CSS formatting or frontend component structure.
**Why it fails:** Skills are scoped to domain-level verification. Using them outside their scope wastes time and dilutes their authority. If it's not a booking, schema, docs, or release concern, it's not a Skill OS task.
