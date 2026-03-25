---
description: "Fix a backend issue — enforces architecture invariants, runs tests"
allowed-tools: ["Read", "Grep", "Glob", "Edit", "Write", "Bash", "Agent"]
argument-hint: "Describe the backend issue or task"
---

# Fix Backend

## Task

$ARGUMENTS

## Setup

CLAUDE.md and ARCHITECTURE_FACTS.md are already loaded. Load relevant skills:
- Booking: `skills/laravel/booking-overlap-skill.md`
- Auth: `skills/laravel/auth-tokens-skill.md`
- API: `skills/laravel/api-endpoints-skill.md`
- Migrations: `skills/laravel/migrations-postgres-skill.md`
- Locking: `skills/laravel/transactions-locking-skill.md`
- Testing: `skills/laravel/testing-skill.md`

## Canonical rules

- `.agent/rules/booking-integrity.md`
- `.agent/rules/migration-safety.md`
- `.agent/rules/backend-preserve-rbac-source-and-request-validation.md`
- `.agent/rules/security-runtime-hygiene.md`
- `.agent/rules/instruction-surface-and-task-boundaries.md`
- `.agent/rules/gitnexus-impact-and-change-scope.md`

## Process

1. Inspect relevant files before editing — never guess paths
2. Keep diffs small and scoped
3. Add/update tests for any behavior change
4. Run the applicable rule-mandated validation gates after changes

## Validation

```bash
cd backend && php artisan test --bail
cd backend && composer audit
```

## Completion

Update `docs/COMPACT.md` section 1 (snapshot) and append to worklog.

## Escalation

If the agent cannot resolve after completing all steps:
1. Stop and preserve all work in progress.
2. Output a structured summary: what was completed, what remains unresolved, and the specific blocker.
3. Surface to the human operator for decision.

## Summary
## Files Changed
## Gates Run + Results
## Residual Risk
