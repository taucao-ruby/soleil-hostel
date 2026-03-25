---
verified-against: CLAUDE.md
secondary-source: docs/agents/CONTRACT.md
section: "Mission, non-negotiable constraints, escalation rules"
last-verified: 2026-03-25
maintained-by: docs-sync
---

# Instruction Surface And Task Boundaries

## Purpose
Keep the instruction system thin at the top level, temporary where required, and strict about when work must stop or escalate.

## Rule
- `CLAUDE.md` stays constitutional only; detailed procedures, patterns, and tool workflows belong in mapped lower layers.
- `docs/COMPACT.md` stays a temporary snapshot, not a policy or architecture store; it keeps required metadata, edits the current snapshot in place, and keeps section 1 under 12 lines.
- Code tasks run the required quality gates before completion; docs-only tasks follow the documentation DoD instead of code gates.
- New behavior requires corresponding new or updated tests.
- Stop and confirm before docs-only tasks touch app/infra surfaces, before changing booking overlap/auth token/migration constraint behavior, before bypassing hooks or using `--no-verify`, before proceeding past new gate failures, when required files are missing, and before changing more than 25 files in one pass.
- Out-of-scope defects go to `docs/FINDINGS_BACKLOG.md`; do not fix them inline.
- Destructive shell operations and interactive shortcuts blocked by repo policy (`rm -rf`, force-reset/force-push patterns, `artisan tinker`) remain disallowed unless a higher-authority instruction explicitly overrides them.

## Why it exists
These boundaries stop instruction-layer sprawl, stale session truth, oversized diffs, hidden gate bypasses, and opportunistic side-fixes that escape review.

## Applies to
Agents, humans, commands, skills, reviews, hooks, docs-only tasks, code tasks, and release checks.

## Violations
- Expanding `CLAUDE.md` into a procedures document.
- Storing canonical invariants in `docs/COMPACT.md`.
- Closing a code task without gates or new-behavior tests.
- Continuing after a new gate failure, bypassing hooks without justification, or pushing a >25-file batch without explicit confirmation.
- Fixing unrelated findings inline instead of logging them to the backlog.
- Running blocked destructive shell commands or `artisan tinker`.

## Enforcement
- Canonical sources: `CLAUDE.md`, `docs/agents/CONTRACT.md`, `docs/HOOKS.md`, `docs/COMPACT.md`.
- Runtime enforcement: `.claude/hooks/block-dangerous-bash.sh`.
- Review and dispatch: `.claude/commands/fix-backend.md`, `.claude/commands/fix-frontend.md`, `.claude/commands/review-pr.md`, `.claude/commands/ship.md`, `.claude/commands/sync-docs.md`.

## Linked skills / hooks
- `skills/laravel/testing-skill.md`
- `.claude/commands/fix-backend.md`
- `.claude/commands/fix-frontend.md`
- `.claude/commands/review-pr.md`
- `.claude/commands/ship.md`
- `.claude/hooks/block-dangerous-bash.sh`
