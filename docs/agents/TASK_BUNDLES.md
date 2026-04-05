# Task Bundles — Soleil Hostel

> **Default skill/rule composition for common agent task types.**
> Commands and agents should reference bundles by name instead of listing individual skills.
> When a task matches multiple bundles, use the most specific one.
>
> Created: 2026-04-04 (Harness Hardening Wave 2)
> Owner: Tech Lead (see [CONTROL_PLANE_OWNERSHIP.md](./CONTROL_PLANE_OWNERSHIP.md))

## Bundle Definitions

### `backend-safe-fix`

**Triggers:** Backend bug fix, API endpoint change, service layer edit, repository layer edit.

| Category | Included |
|----------|----------|
| Skills | `api-endpoints-skill`, `transactions-locking-skill`, `security-secrets-skill`, `booking-overlap-skill` |
| Rules | `booking-integrity`, `migration-safety`, `backend-preserve-rbac-source-and-request-validation`, `security-runtime-hygiene` |
| Gates | `php artisan test --bail`, `composer audit` |

### `frontend-contract-fix`

**Triggers:** Frontend bug, component change, form change, API wiring change.

| Category | Included |
|----------|----------|
| Skills | `api-client-skill`, `typescript-patterns-skill`, `forms-validation-skill`, `security-frontend-skill` |
| Rules | `frontend-preserve-boundaries-and-ui-standards`, `auth-token-safety`, `security-runtime-hygiene` |
| Gates | `npx tsc --noEmit`, `npx vitest run` |

### `migration-audit`

**Triggers:** Schema change, new migration, constraint modification.

| Category | Included |
|----------|----------|
| Skills | `migrations-postgres-skill`, `transactions-locking-skill`, `testing-skill` |
| Rules | `migration-safety`, `booking-integrity` |
| Gates | `php artisan test --bail`, `php artisan migrate:rollback --step=1` |

### `auth-review`

**Triggers:** Auth flow change, token handling, cookie auth, CSRF modification.

| Category | Included |
|----------|----------|
| Skills | `auth-tokens-skill`, `security-secrets-skill`, `security-frontend-skill` |
| Rules | `auth-token-safety`, `security-runtime-hygiene` |
| Gates | `php artisan test --bail`, `npx tsc --noEmit`, `npx vitest run` |

### `docs-sync-only`

**Triggers:** Documentation comparison, stale fact audit, docs-only changes.

| Category | Included |
|----------|----------|
| Skills | None (no code skills) |
| Rules | `instruction-surface-and-task-boundaries` |
| Gates | Markdown lint, link validation (spot-check minimum 5 links) |
| Excluded tools | Edit/Write on code files (backend/, frontend/ source) |

### `full-release-gate`

**Triggers:** `/ship` command, pre-merge check, release candidate validation.

| Category | Included |
|----------|----------|
| Skills | `ci-quality-gates-skill`, `testing-skill`, `testing-vitest-skill` |
| Rules | All 8 rules |
| Gates | All 4 quality gates + `scripts/verify-control-plane.sh` |

## How to Reference

In `.claude/commands/*.md`, reference bundles by name:

```
## Setup
Apply bundle: `backend-safe-fix`
```

Agents resolve the bundle by reading this file and loading the listed skills and rules.

## Maintenance

When a skill is added or removed:
1. Update the relevant bundle(s) in this file
2. Update `skills/README.md` skill index
3. Run `scripts/verify-control-plane.sh` to confirm governance files exist
