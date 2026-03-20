---
description: "Release-safety gate — run all CI checks and verify ship readiness"
user-intent-required: true
---

# Ship Gate

Run all validation gates and assess ship readiness for the current branch.

## Block Conditions (any = no-ship)

- Any CI gate fails
- Migration added without safety review note
- Booking or auth changes without corresponding tests
- Architecture docs stale relative to changes

## Workflow

1. Run gates in order, stop on first failure:
   - `cd backend && php artisan test --bail`
   - `cd frontend && npx tsc --noEmit`
   - `cd frontend && npx vitest run`
   - `docker compose config`
2. Check for uncommitted changes
3. Cross-reference booking/auth file changes with test changes
4. Verify `docs/COMPACT.md` is current

## Output — use Execution style

```
SHIP VERDICT: GO | NO-GO
Blocker: <if NO-GO, exact failure>
```
