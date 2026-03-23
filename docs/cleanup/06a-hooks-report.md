# Hooks Audit Report — Batch 7

> Generated: 2026-03-22 | Branch: dev

## Observed reality

3 hook scripts in .claude/hooks/, configured in .claude/settings.json. All hooks implement jq fail-open policy. Settings.json deny list contains 10 patterns that mirror hook blocking rules.

## Conflicts detected

None. Hooks and settings.json deny list are fully aligned.

## Hooks audit table

| hook_file | trigger | enforcement_action | linked_rule | policy_duplication_found | deterministic |
|-----------|---------|-------------------|-------------|------------------------|---------------|
| .claude/hooks/block-dangerous-bash.sh | PreToolUse:Bash | BLOCK (exit 2) — rm -rf, git push --force, git reset --hard, git checkout -- ., artisan tinker, APP_KEY, DB_PASSWORD, REDIS_PASSWORD, SECRET, private_key | claude.md §Editing Boundaries, settings.json deny list | YES — same patterns in settings.json deny list (defense in depth, intentional) | YES |
| .claude/hooks/guard-sensitive-files.sh | PreToolUse:Edit\|Write | BLOCK (exit 2) — .env*, .key, .pem, .secret, id_rsa | claude.md §Auth/security ("Never commit: APP_KEY, passwords, tokens, API keys, private keys") | NO — file-level patterns not in settings.json deny list (different tool matcher) | YES |
| .claude/hooks/remind-frontend-validation.sh | PostToolUse:Edit\|Write | ALLOW (exit 0) + inject additionalContext reminder for frontend/src/* edits | claude.md §Validation Gates | NO | YES |

## Changes applied

No changes made in this batch (audit-only).

## Unresolved items

| id | description | evidence_missing | blocks_batch |
|----|-------------|-----------------|-------------|
| — | — | — | — |

No unresolved items.

## Deliverables produced

- docs/cleanup/06a-hooks-report.md (this file)

## Risks and follow-up for next batch

- Hook scripts are stable and narrowly scoped — low maintenance burden
- If new destructive commands are added to settings.json deny list, corresponding hook patterns should be added for defense in depth
