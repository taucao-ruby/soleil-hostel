# Soleil Hostel AI Skills

This folder is the operational skill set for AI coding agents (Codex, Claude, etc.) working in the Soleil Hostel monorepo.

Use these documents as task-specific guardrails, not as generic docs.
Pick only the skills needed for the task.

## Prompt Template (Copy/Paste)

```text
You are working in the Soleil Hostel monorepo.

Task:
<describe the task and expected output>

Use these skills:
- /skills/<area>/<skill-file>.md
- /skills/<area>/<skill-file>.md

Constraints:
- Keep architecture boundaries intact.
- Do not break booking overlap/auth token/locking invariants.
- Keep CI and local quality gates green.

Deliverables:
- Summary of changes
- Verification commands run
- Residual risk (if any)
```

## Skill Selection Guide

Use 1 to 3 skills per task. More usually adds noise.

1. Start with the layer skill.
   - Backend: `skills/laravel/*`
   - Frontend: `skills/react/*`
   - Ops/CI: `skills/ops/*`
2. Add one risk skill for sensitive areas.
   - Booking conflicts: `booking-overlap-skill.md`
   - Auth/token changes: `auth-tokens-skill.md`
   - Concurrency changes: `transactions-locking-skill.md`
3. Add one quality gate skill.
   - Backend: `testing-skill.md`
   - Frontend: `testing-vitest-skill.md`
   - CI/Docker: `ci-quality-gates-skill.md` or `docker-compose-skill.md`

## Recommended Default Skill Sets

### Backend Task Default

1. `skills/laravel/api-endpoints-skill.md`
2. `skills/laravel/testing-skill.md`
3. One of:
   - `skills/laravel/booking-overlap-skill.md`
   - `skills/laravel/auth-tokens-skill.md`
   - `skills/laravel/transactions-locking-skill.md`

### Frontend Task Default

1. `skills/react/component-quality-skill.md`
2. `skills/react/api-client-skill.md`
3. `skills/react/testing-vitest-skill.md`

Add `skills/react/forms-validation-skill.md` for form or booking UI changes.
Add `skills/react/security-frontend-skill.md` for auth, CSRF, or sanitization changes.

### Ops or Pipeline Task Default

1. `skills/ops/ci-quality-gates-skill.md`
2. `skills/ops/docker-compose-skill.md`
3. `skills/ops/logging-observability-skill.md` when tracing/logging is impacted

## Baseline Definition of Done

Use these commands unless task scope is docs-only:

```bash
cd backend && php artisan test
cd frontend && npx tsc --noEmit
cd frontend && npx vitest run
docker compose config
```

Useful extra checks:

```bash
cd frontend && npm run lint
cd frontend && npm run format
cd backend && vendor/bin/pint --test
cd backend && vendor/bin/phpstan analyse
cd backend && vendor/bin/psalm
```

## Status File Reminder

After non-trivial implementation work, update project status docs if present:

- `PROJECT_STATUS.md`
- `STATUS.md` (if present)
- `STATUS.compact.md` or similar compact status files (if present)

## Skill Index

### Laravel

- `skills/laravel/api-endpoints-skill.md`
- `skills/laravel/booking-overlap-skill.md`
- `skills/laravel/auth-tokens-skill.md`
- `skills/laravel/transactions-locking-skill.md`
- `skills/laravel/migrations-postgres-skill.md`
- `skills/laravel/testing-skill.md`
- `skills/laravel/security-secrets-skill.md`

### React

- `skills/react/component-quality-skill.md`
- `skills/react/forms-validation-skill.md`
- `skills/react/api-client-skill.md`
- `skills/react/testing-vitest-skill.md`
- `skills/react/performance-core-web-vitals-skill.md`
- `skills/react/security-frontend-skill.md`

### Ops

- `skills/ops/ci-quality-gates-skill.md`
- `skills/ops/docker-compose-skill.md`
- `skills/ops/logging-observability-skill.md`
