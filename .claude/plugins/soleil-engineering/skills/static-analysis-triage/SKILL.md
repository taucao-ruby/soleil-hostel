---
description: Triage Soleil Hostel static-analysis and test failures with minimal safe patches.
disable-model-invocation: true
allowed-tools: Bash Read Grep Glob Edit MultiEdit
---

# Static-analysis triage

Manually invoked. Goal: smallest correct patch that resolves the actual root cause, then verify with the narrowest gate first.

## Classify

Group every failure by tool. Read the raw output before editing anything.

| Tool | Repo command | Config to read |
|------|-------------|----------------|
| Pint (PHP CS) | `cd backend && vendor/bin/pint --test` | `backend/pint.json` if present |
| PHPStan | inspect `backend/composer.json` for `phpstan` script; do not assume baseline | `backend/phpstan.neon*` |
| Psalm | inspect `backend/composer.json` for `psalm` script | `backend/psalm.xml*` |
| PHPUnit | `cd backend && php artisan test` | `backend/phpunit.xml` |
| TypeScript | `cd frontend && npx tsc --noEmit` | `frontend/tsconfig*.json` |
| Vitest | `cd frontend && npx vitest run` | `frontend/vitest.config.*` |

If a tool is referenced but its config does not exist, stop and report — do not invent the tool.

## Fix order

For each failure:

1. Re-read the failing file plus its closest type/contract source (interface, FormRequest, repository signature, model, migration). Most "type errors" are a real contract drift.
2. Pick the smallest safe change:
   - Fix the cause, not the symptom.
   - Keep PHPDoc generics aligned with `docs/agents/ARCHITECTURE_FACTS.md`.
   - Preserve Service → Repository boundaries (Controller MUST NOT call Eloquent directly).
   - Frontend: keep TypeScript strict; do not widen with `any`, `as unknown as`, or `// @ts-ignore`.
3. NEVER suppress without justification. No `// @phpstan-ignore`, `@psalm-suppress`, `// @ts-expect-error`, `// eslint-disable-*`, or Pint exclusion unless the comment includes a link to the contract that forces the suppression.
4. NEVER mutate snapshot files, `phpunit.xml`, `vitest.config.*`, or CI workflow YAML to make a failure go away.

## Verify

Run from narrowest to widest:

1. The single failing test or file: e.g. `cd frontend && npx vitest run path/to/file.test.tsx` or `cd backend && php artisan test --filter=TestName`.
2. The full tool gate: `npx tsc --noEmit`, `npx vitest run`, `php artisan test`.
3. The release gate when you are done: `bash scripts/ship.sh`.

Report each command and its exit status. If a fix uncovers a deeper failure, surface it — do not auto-expand scope past 25 files without confirming.

## Hard rules

- Out-of-scope bugs go to `docs/FINDINGS_BACKLOG.md`. Do not fix them in this pass.
- Do not commit. The user owns commits.
- Do not run `composer install` / `pnpm install` / `npm install` to "fix" a missing dep — report it instead.
