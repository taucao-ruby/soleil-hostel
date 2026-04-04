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

## Canonical rules

- `.agent/rules/booking-integrity.md`
- `.agent/rules/auth-token-safety.md`
- `.agent/rules/migration-safety.md`
- `.agent/rules/frontend-preserve-boundaries-and-ui-standards.md`
- `.agent/rules/instruction-surface-and-task-boundaries.md`
- `.agent/rules/soleil-ai-review-engine-impact-and-change-scope.md`

## Setup

Apply bundle: `full-release-gate` (see [TASK_BUNDLES.md](../../docs/agents/TASK_BUNDLES.md))

## Gate Sequence

Run in order, stop on first failure:

```bash
cd backend && php artisan test --bail
cd frontend && npx tsc --noEmit
cd frontend && npx vitest run
docker compose config
bash scripts/verify-control-plane.sh
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

## Escalation

If the agent cannot resolve after completing all steps:
1. Stop and preserve all work in progress.
2. Output a structured summary: what was completed, what remains unresolved, and the specific blocker.
3. Surface to the human operator for decision.

## Summary
## Gates Run + Results
## Ship Verdict
## Residual Risk
