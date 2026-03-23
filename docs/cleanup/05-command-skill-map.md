# Command-Skill Map — Batch 6

> Generated: 2026-03-22 | Branch: dev

## Observed reality

6 commands in .claude/commands/. Each inspected for skill references, upstream dependencies, output artifacts, failure behavior, and escalation paths.

## Conflicts detected

None. No commands reference the deleted skill files (.claude/skills/review-pr.md, .claude/skills/ship.md).

## Command-skill map

| command | invoked_skills | upstream_dependencies | output_artifact | failure_behavior_defined | escalation_defined |
|---------|---------------|----------------------|-----------------|------------------------|-------------------|
| /review-pr | — (checklist-driven) | architecture_facts.md, contract.md | Verdict: approve / request-changes / block + findings table | YES (scope confirmation required before proceeding) | NO |
| /ship | — (gate-driven) | commands_and_gates.md, compact.md | SHIP VERDICT: GO / NO-GO + blocker details | YES (stop on first gate failure) | NO |
| /audit-security | skills/laravel/security-secrets-skill.md, skills/react/security-frontend-skill.md | architecture_facts.md, OWASP Top 10 | Findings table by severity | YES (scope confirmation before proceeding) | NO |
| /sync-docs | — | claude.md, agents.md, architecture_facts.md, contract.md, commands.md, compact.md | Findings table: stale / contradictions / missing | YES (propose line-level edits only) | NO |
| /fix-backend | skills/laravel/booking-overlap-skill.md, skills/laravel/auth-tokens-skill.md, skills/laravel/api-endpoints-skill.md, skills/laravel/migrations-postgres-skill.md, skills/laravel/transactions-locking-skill.md, skills/laravel/testing-skill.md | architecture_facts.md, compact.md | Fixed code + test results | YES (run gates after fix) | NO |
| /fix-frontend | skills/react/component-quality-skill.md, skills/react/api-client-skill.md, skills/react/forms-validation-skill.md, skills/react/testing-vitest-skill.md | claude.md | Fixed code + test results | YES (run tsc + vitest after fix) | NO |

## Commands with embedded policy (must be extracted)

| command | embedded_policy | extraction_recommendation |
|---------|----------------|--------------------------|
| /review-pr | Architecture compliance checklist (Controller→Service→Repository, feature-sliced, no env(), no any) | Already derived from architecture_facts.md; no extraction needed |
| /ship | Block conditions list (gate fail, migration without review, booking/auth without test, stale docs) | Could reference a "ship-readiness-rules" file, but minimal benefit — 4 conditions, tightly scoped |

No commands contain policy that contradicts higher-level rules.

## Commands missing failure behavior

| command | gap |
|---------|-----|
| /review-pr | No escalation path defined (what to do when a BLOCK verdict is issued) |
| /ship | No escalation path defined (what to do when NO-GO is issued) |
| /audit-security | No escalation path defined |
| /sync-docs | No escalation path defined |
| /fix-backend | No escalation path defined |
| /fix-frontend | No escalation path defined |

All 6 commands lack explicit escalation paths. Failure behavior (what to do on failure) is defined in all 6. Escalation (who to notify, what to do if the agent cannot resolve) is not defined in any.

## Changes applied

No changes to command files in this batch (audit-only).

## Unresolved items

| id | description | evidence_missing | blocks_batch |
|----|-------------|-----------------|-------------|
| UNRESOLVED-B6-1 | All 6 commands lack escalation paths | Decision on whether escalation paths are required for this project | — |
| UNRESOLVED-B6-2 | 7 of 17 reference library skills not invoked by any command | Decision on whether unreferenced skills should be archived or retained as on-demand reference | — |

## Deliverables produced

- docs/cleanup/05-command-skill-map.md (this file)

## Risks and follow-up for next batch

- B7 (hooks) should verify hook trigger alignment with command execution patterns
- Escalation path gap may need project-level decision
