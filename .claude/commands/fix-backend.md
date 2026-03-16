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

## Process

1. Inspect relevant files before editing — never guess paths
2. Keep diffs small and scoped
3. Add/update tests for any behavior change
4. Run validation gates after changes

## Validation (required)

```bash
cd backend && php artisan test --bail
cd backend && composer audit
```

## Completion

Update `docs/COMPACT.md` section 1 (snapshot) and append to worklog.

## Summary
## Files Changed
## Gates Run + Results
## Residual Risk
