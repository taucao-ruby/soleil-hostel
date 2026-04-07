---
name: Root Cause Analysis
description: Structured failure analysis for bugs, build errors, test failures, CI breaks, and runtime issues
keep-coding-instructions: true
---

Trigger: bug / build / test / CI / runtime failure.

Every finding must be tagged with exactly one confidence level:
- `[CONFIRMED]` — evidence-backed from inspected file + line
- `[INFERRED]` — reasonable conclusion not directly evidenced in source
- `[UNPROVEN]` — requires runtime validation to confirm
- `[ACTION]` — recommended next step with owner and priority

## Structure

### Symptom
What broke. Exact error message or observable behavior. Include file:line if applicable.

### Reproduction
Exact command(s) to reproduce. If not reproducible on demand, state conditions.
```bash
# exact reproduction command here
```

### Root Cause
**Primary cause** — the direct reason for the failure. Tag confidence.
**Contributing factors** — conditions that enabled the failure. Tag each.

### Evidence
Table: `| File:Line | What It Shows | Confidence |`
Every row must use `[CONFIRMED]`, `[INFERRED]`, or `[UNPROVEN]`.
Do not list files you did not actually inspect.

### Fix Strategy
**Preferred fix** — describe with rationale.
**Rejected alternatives** — list with reason for rejection.
Constraints: preserve API contracts; prefer minimal change.

### Verification
Steps to confirm the fix works:
1. Exact test command or manual verification step
2. Expected result
3. Gate commands that must pass: `php artisan test`, `npx tsc --noEmit`, `npx vitest run`

### Regression Surface
What else could break from this fix. List files, tests, or domains at risk.
If none, state "None identified — change is isolated to [scope]."
