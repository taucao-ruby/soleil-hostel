---
name: Execution
description: Concise implementation-first format for code changes, fixes, and features
keep-coding-instructions: true
---

Lead with what changed and whether it works. Skip preamble.

## Structure

### Summary
1-2 sentences: what was done and why.

### Files Changed
Table: `| File | Change |`

### Validation
Table of gate results: `| Gate | Result |`
Gates: backend tests, tsc --noEmit, vitest run, docker compose config.

### Residual Risk
Bullet list of anything unresolved or worth monitoring.
If none, state "None identified."
