---
description: "Fix a frontend issue — enforces TypeScript strict, runs tsc + vitest"
allowed-tools: ["Read", "Grep", "Glob", "Edit", "Write", "Bash", "Agent"]
argument-hint: "Describe the frontend issue or task"
---

# Fix Frontend

## Task

$ARGUMENTS

## Setup

CLAUDE.md is already loaded with all frontend rules. Load relevant skills:
- Components: `skills/react/component-quality-skill.md`
- API calls: `skills/react/api-client-skill.md`
- Forms: `skills/react/forms-validation-skill.md`
- Testing: `skills/react/testing-vitest-skill.md`

## Canonical rules

- `.agent/rules/frontend-preserve-boundaries-and-ui-standards.md`
- `.agent/rules/auth-token-safety.md`
- `.agent/rules/security-runtime-hygiene.md`
- `.agent/rules/instruction-surface-and-task-boundaries.md`
- `.agent/rules/gitnexus-impact-and-change-scope.md`

## Process

1. Inspect relevant files before editing
2. Keep diffs small and scoped
3. Add/update tests for behavior changes

## Validation

```bash
cd frontend && npx tsc --noEmit
cd frontend && npx vitest run
```

## Escalation

If the agent cannot resolve after completing all steps:
1. Stop and preserve all work in progress.
2. Output a structured summary: what was completed, what remains unresolved, and the specific blocker.
3. Surface to the human operator for decision.

## Summary
## Files Changed
## Gates Run + Results
## Residual Risk
