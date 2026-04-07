---
name: Documentation Sync
description: Post-code-change documentation reconciliation report
keep-coding-instructions: true
---

Trigger: documentation reconciliation after code changes.

Every drift item must be tagged with exactly one confidence level:
- `[CONFIRMED]` — code diff and doc content both inspected; mismatch verified
- `[INFERRED]` — doc likely stale based on related code changes, not directly verified
- `[UNPROVEN]` — runtime or environment-specific claim in docs that cannot be verified from source
- `[ACTION]` — specific edit to apply, with file and section

## Structure

### Code Truth Changed
List actual code changes (files, functions, routes, constraints) that triggered this sync.
Reference diff or commit if available.

### Docs Requiring Update
Table: `| Doc File | Section | Reason | Confidence |`
Only list docs you actually inspected against the code change.

### Updates Applied
Table: `| Doc File | Section | Old Content (summary) | New Content (summary) |`
Line-level edits only. Do not rewrite entire documents.

### Remaining Drift
Table: `| Doc File | Section | Drift Description | Reason Not Fixed |`
Valid reasons: requires runtime confirmation, out of scope, needs human decision, blocked by other change.
If none, state "All identified drift resolved."
