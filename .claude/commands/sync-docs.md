---
description: "Compare docs against codebase reality — find stale facts, contradictions, missing coverage"
allowed-tools: ["Read", "Grep", "Glob", "Edit", "Agent"]
disable-model-invocation: true
---

# Sync Docs

**Scope confirmation required.** State which docs will be checked and
wait for user confirmation before proceeding.

## Focus Area

$ARGUMENTS

If no focus area specified, run full sync check.

## Documents to Verify

- `CLAUDE.md`, `AGENTS.md`
- `docs/agents/ARCHITECTURE_FACTS.md`, `CONTRACT.md`, `COMMANDS.md`
- `docs/COMPACT.md`

Cross-reference against: migrations, services, repositories, auth controllers,
routes, frontend features, `docker-compose.yml`, `package.json`, `composer.json`.

## Canonical rules

- `.agent/rules/instruction-surface-and-task-boundaries.md`
- `.agent/rules/backend-preserve-rbac-source-and-request-validation.md`
- `.agent/rules/frontend-preserve-boundaries-and-ui-standards.md`
- `.agent/rules/security-runtime-hygiene.md`
- `.agent/rules/soleil-ai-review-engine-impact-and-change-scope.md`

## Review boundaries

- Propose specific line-level edits only — do not rewrite entire documents
- Do not invent facts; only surface real discrepancies found by reading code
- Keep `docs/COMPACT.md` section 1 under 12 lines

## Output

Per finding: `| Doc File | Section | Stale Content | Current Reality | Proposed Edit |`

## Escalation

If the agent cannot resolve after completing all steps:
1. Stop and preserve all work in progress.
2. Output a structured summary: what was completed, what remains unresolved, and the specific blocker.
3. Surface to the human operator for decision.

## Summary
## Findings
## Proposed Edits
## Residual Risk
