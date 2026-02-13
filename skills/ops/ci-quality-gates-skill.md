# CI Quality Gates Skill

Use this skill when making changes that must remain compatible with Soleil Hostel CI/CD workflows.

## When to Use This Skill

- You change backend/frontend code and need merge-safe validation.
- You update tests, linting/static-analysis setup, or workflow files.
- You touch high-risk domains (booking overlap, auth tokens, locking, migrations).

## Non-negotiables

- Keep baseline quality gates green:
  - `cd backend && php artisan test`
  - `cd frontend && npx tsc --noEmit`
  - `cd frontend && npx vitest run`
  - `docker compose config`
- Keep CI parity in mind.
  - CI uses PostgreSQL service for backend test jobs.
  - Frontend CI path uses pnpm scripts; local equivalent commands are acceptable.
- Do not weaken security and analysis jobs without explicit reason.
  - Gitleaks, dependency audits, static analysis, and lint tasks should remain valid.
- Verify branch-target assumptions when editing workflow triggers.
  - Current workflows target `main` and `develop`; repo working branch is `dev`.

CI-to-local mapping:

- Backend tests (CI pgsql) -> local `php artisan test` plus targeted pgsql verification when needed.
- Frontend unit tests -> local `npx vitest run`.
- Frontend build/lint -> local `npm run build` and `npm run lint`.

## Implementation Checklist

1. Identify which jobs your change can impact.
   - Backend tests, frontend tests/build/lint, security scans, deployment gates.
2. Run relevant local equivalents before finishing.
3. Add targeted tests for domain-specific risk changes.
4. Confirm scripts/commands used in docs and CI still match repo reality.
5. If editing workflows, validate trigger branch names and env assumptions.
6. Keep changes minimal and avoid broad CI refactors for narrow feature work.
7. Document any intentional workflow behavior changes in the PR summary.
8. For high-risk changes, run targeted suites before full-suite execution.
   - Booking overlap/concurrency
   - Token expiry/revocation
   - Room optimistic lock conflict flows

## Verification / DoD

```bash
# Local gate baseline
cd backend && php artisan test
cd frontend && npx tsc --noEmit
cd frontend && npx vitest run
docker compose config

# Useful optional checks
cd frontend && npm run lint
cd backend && vendor/bin/pint --test
cd backend && vendor/bin/phpstan analyse
cd backend && vendor/bin/psalm
```

## Common Failure Modes

- Passing local SQLite tests while breaking PostgreSQL CI behavior.
- Updating endpoint payloads without updating frontend type/test expectations.
- Workflow trigger edits that miss actual branch strategy.
- Skipping targeted concurrency/auth tests on high-risk changes.
- Accidentally reducing CI coverage by removing jobs or checks.
- Build passes locally but fails CI due pnpm lockfile or Node version drift.
- Static analysis tools configured in CI but never exercised locally before merge.

## References

- `../../AGENTS.md`
- `../../PROJECT_STATUS.md`
- `../../.github/workflows/tests.yml`
- `../../.github/workflows/deploy.yml`
- `../../backend/phpunit.xml`
- `../../docker-compose.yml`
