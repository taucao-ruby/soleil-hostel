# Commands and Quality Gates

Verified against code on 2026-04-18. Source: `composer.json`, `frontend/package.json`, root `package.json`, `.github/workflows/*.yml`, `tools/hooks/`, `mcp/soleil-mcp/policy.json`, `.spectral.yaml`.

## Backend Commands

### Setup

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
```

### Dev Server

```bash
cd backend && php artisan serve --host=127.0.0.1 --port=8000
```

Or via root monorepo:

```bash
npm run dev:backend
```

### Tests

```bash
cd backend && php artisan test
```

CI runs parallel with PostgreSQL:

```bash
cd backend && php artisan test --parallel --processes=4
```

Composer script (clears config first):

```bash
cd backend && composer test
```

### Static Analysis

```bash
cd backend && vendor/bin/phpstan analyse          # PHPStan Level 5
cd backend && vendor/bin/psalm                     # Psalm Level 1 (continue-on-error in CI)
```

### Code Style

```bash
cd backend && vendor/bin/pint --test               # Laravel Pint (dry-run check)
cd backend && vendor/bin/pint                      # Auto-fix
```

### Security Audit

```bash
cd backend && composer audit
```

### Custom Artisan Commands

```bash
# Operational stay backfill (run once after deploying the four-layer domain model)
cd backend && php artisan stays:backfill-operational            # persist rows
cd backend && php artisan stays:backfill-operational --dry-run  # count eligible, persist nothing

# Cache warmup
cd backend && php artisan cache:warmup

# Prune old soft-deleted bookings
cd backend && php artisan bookings:prune-soft-deleted
```

`stays:backfill-operational` selection criteria:
- `status = 'confirmed'` AND `check_out >= today` AND no existing stay row → create `expected`

Idempotent — safe to re-run. Source: `app/Console/Commands/BackfillOperationalStays.php`. Canonical operational note and source-of-truth boundaries: `docs/DOMAIN_LAYERS.md`.

## Frontend Commands

### Setup

```bash
cd frontend
pnpm install         # CI uses pnpm with --frozen-lockfile
```

### Dev Server

```bash
cd frontend && pnpm dev
# Starts Vite on http://localhost:5173
```

### Typecheck (GATE)

```bash
cd frontend && npx tsc --noEmit
```

Expected: `Found 0 errors.` (implicit — no output means pass)

### Unit Tests (GATE)

```bash
cd frontend && npx vitest run
```

<!-- SYNC-EDIT: DRIFT-06 F-05 -->
<!-- SOURCE: frontend/package.json, verified 2026-03-11 -->
Expected: 226 tests passed, 0 failed <!-- AS OF: 2026-03-11 -->.

CI variant:

```bash
cd frontend && pnpm test:unit --coverage
```

### Lint

```bash
cd frontend && pnpm lint                           # ESLint
cd frontend && pnpm format                         # Prettier (write mode)
```

### Build

```bash
cd frontend && pnpm build                          # tsc -b && vite build
```

## Docker

### Compose Up / Down

```bash
docker compose up --build                          # Full stack
docker compose up --build frontend                 # Frontend only
docker compose down
```

### Validate Config

```bash
docker compose config
```

## Monorepo Scripts (root package.json)

```bash
npm run dev              # Concurrent backend + frontend
npm run dev:backend      # Backend only
npm run dev:frontend     # Frontend only
npm run start:docker     # Docker compose up --build
npm run hooks:install    # Install Husky hooks
npm run hooks:run:prepush  # Dry-run pre-push
```

## CI/CD Jobs Map

### tests.yml (CI)

Triggers: PR to `main`/`dev`, push to `main`/`dev`.

<!-- SYNC-EDIT: DRIFT-01 F-03 — composer-audit is blocking (continue-on-error: false) in tests.yml -->
<!-- SYNC-EDIT: DRIFT-04 F-04 — added missing frontend-typecheck and docker-compose-validate jobs -->
<!-- SOURCE: .github/workflows/tests.yml -->
| Job                       | Commands                                                                 | Expected                     | Blocking               |
| ------------------------- | ------------------------------------------------------------------------ | ---------------------------- | ---------------------- |
| backend-tests             | `php artisan test --parallel --processes=4 --min-coverage-percentage=95` | Pass + 95% coverage          | Yes                    |
| booking-stress-test       | `php backend/tests/stubs/concurrent_booking_test.php` (50 concurrent)    | No double-bookings           | Yes                    |
| nplusone-detection        | `php artisan test tests/Feature/NPlusOneQueriesTest.php`                 | Query count within threshold | Yes                    |
| phpstan                   | `phpstan analyse --error-format=github` (Level 5)                        | 0 errors                     | Yes                    |
| psalm                     | `psalm --output-format=github` (Level 1)                                 | Advisory                     | No (continue-on-error) |
| pint                      | `vendor/bin/pint --test`                                                 | 0 style violations           | Yes                    |
| lint (PHP)                | `find app tests -name "*.php" \| xargs php -l`                           | 0 syntax errors              | Yes                    |
| composer-audit            | `composer audit`                                                         | 0 advisories                 | Yes                    |
| npm-audit                 | `pnpm audit --audit-level=high`                                          | Advisory                     | No (continue-on-error) |
| security-scan             | Gitleaks action                                                          | No exposed secrets           | Yes                    |
| frontend-typecheck        | `npx tsc --noEmit`                                                       | 0 type errors                | Yes                    |
| frontend-unit-tests       | `pnpm test:unit --coverage`                                              | 0 failures                   | Yes                    |
| frontend-lint             | `pnpm run build` + `pnpm run lint`                                       | 0 errors                     | Yes                    |
| docker-compose-validate   | `docker compose config`                                                  | Valid YAML                   | Yes                    |

### hygiene.yml (CI)

Triggers: PR to `main`/`dev`, push to `main`/`dev`.

| Job     | Commands                      | Expected          | Blocking |
| ------- | ----------------------------- | ----------------- | -------- |
| hygiene | `sh scripts/check-hygiene.sh` | H-01..H-05 pass   | Yes      |

### contract-lint.yml (CI)

Triggers: PR / push to `main`/`dev` when `docs/api/openapi.yaml`, `.spectral.yaml`, or `.github/workflows/contract-lint.yml` is touched.

| Job       | Commands                                                                                                   | Expected   | Blocking                         |
| --------- | ---------------------------------------------------------------------------------------------------------- | ---------- | -------------------------------- |
| spectral  | `spectral lint docs/api/openapi.yaml --ruleset .spectral.yaml --format pretty --fail-severity error`       | 0 errors   | Yes (`--fail-severity=error`)    |

Rationale: `spectral:oas` detects contract drift (missing schemas, duplicate `operationId`, unresolved `$ref`, unsafe markdown) before the runtime and the documented contract diverge. Style warnings remain visible in the workflow log but are non-blocking. Added 2026-04-17 via commit `4a33755`.

### deploy.yml (CD)

Triggers: push tags `v*`, manual workflow_dispatch.

| Job            | Gate                                      | Notes                              |
| -------------- | ----------------------------------------- | ---------------------------------- |
| backend-tests  | Pass before deploy                        | Re-runs tests with PG              |
| frontend-build | Build + lint                              | Uploads dist artifact              |
| e2e-tests      | Playwright (tags/manual only)             | Chromium, backend+frontend servers |
| docker-build   | Build + push to GHCR                      | Tags: semver, sha                  |
| trivy-scan     | Docker image vulnerability scan           | Advisory                           |
| deploy         | Zero-downtime to Forge/Render/Coolify/SSH | Health check after deploy          |
| release        | Semantic release (tags only)              | GitHub release                     |

## Quality Gates Summary

| Gate                | Command                                                                         | Expected   | Enforced by                             |
| ------------------- | ------------------------------------------------------------------------------- | ---------- | --------------------------------------- |
| Frontend typecheck  | `npx tsc --noEmit`                                                              | 0 errors   | pre-push hook, CI                       |
| Frontend unit tests | `npx vitest run`                                                                | 0 failures | pre-push hook, CI                       |
| Backend tests       | `php artisan test`                                                              | 0 failures | pre-push hook, CI                       |
| Docker validate     | `docker compose config`                                                         | Valid YAML | pre-push hook (if Docker available), CI |
| PHPStan             | `phpstan analyse`                                                               | 0 errors   | CI                                      |
| Backend coverage    | `--min-coverage-percentage=95`                                                  | >= 95%     | CI                                      |
| Gitleaks            | Gitleaks action                                                                 | No secrets | CI                                      |
| OpenAPI contract    | `spectral lint docs/api/openapi.yaml --ruleset .spectral.yaml --fail-severity error` | 0 errors   | CI (`contract-lint.yml`, path-triggered) |

## Bypassing Hooks

**When allowed**: only when risk is understood and intentional (per CONTRACT.md).

```bash
git push --no-verify     # Skip pre-push gates
git commit --no-verify   # Skip pre-commit checks
SKIP_HOOKS=1 git commit -m "chore: ..."   # Env var bypass (Linux/macOS/WSL2)
```

**Required**: document reason in commit message + notify team lead.

**Prohibited**: bypassing on `main`/production branches.
