---
description: "Release-safety gate — run all CI checks and verify ship readiness"
allowed-tools: ["Read", "Grep", "Glob", "Bash", "Agent"]
disable-model-invocation: true
---

# Ship Gate

**Scope confirmation required.** State the branch/state being evaluated
and wait for user confirmation before running gates.

## Block Conditions (any one = no-ship)

- Any CI gate fails
- Migration added without safety review note
- Code touches booking or auth paths without corresponding test
- Architecture docs appear stale relative to changes

## Gate Sequence

Run in order, stop on first failure:

```bash
cd backend && php artisan test --bail
cd frontend && npx tsc --noEmit
cd frontend && npx vitest run
docker compose config
```

## Post-Gate Checks

If all gates pass:
1. `git status` — check for uncommitted changes
2. Search `backend/database/migrations/` for recent files without review notes
3. Cross-reference booking/auth file changes with test changes
4. Verify `docs/COMPACT.md` is up to date

## On Success

Update `docs/COMPACT.md` section 1 with gate results and date.

```
SHIP VERDICT: GO
All 4 gates passed. No blocking conditions found.
```

## On Failure

```
SHIP VERDICT: NO-GO
Blocker: <exact failure description>
```

## Summary
## Gates Run + Results
## Ship Verdict
## Residual Risk
