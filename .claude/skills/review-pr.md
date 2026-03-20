---
description: "Review a PR or branch for architecture compliance, invariant safety, and test coverage"
user-intent-required: true
---

# Review PR

Review the specified PR/branch against project invariants (CLAUDE.md + ARCHITECTURE_FACTS.md).

## Input

PR number or branch name provided by user.

## Workflow

1. Get full diff: `git diff main...<branch>` or `gh pr diff <number>`
2. Categorize changes by area (backend/frontend/docs/infra)
3. Check architecture compliance (Controller→Service→Repository, feature-sliced, no `env()`, no `any`)
4. Check invariant safety (booking overlap, locking, auth tokens, no secrets)
5. Flag new behavior without tests
6. Verify `docs/COMPACT.md` updated if code changed

## Output — use Audit style

Verdict: **approve** | **request-changes** | **block**

Per finding: `| File:Line | Severity | Issue | Action Required |`
