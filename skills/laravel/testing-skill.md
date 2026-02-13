# Laravel Testing Skill

Use this skill when changing backend behavior and needing deterministic confidence before merge.

## When to Use This Skill

- You change controllers, requests, services, repositories, models, or migrations.
- You touch booking overlap, cancellations, auth tokens, or locking behavior.
- You update endpoint payloads that frontend tests depend on.
- You need to map local checks to CI expectations.

## Non-negotiables

- Keep baseline quality gates green:
  - `cd backend && php artisan test`
  - `cd frontend && npx tsc --noEmit`
  - `cd frontend && npx vitest run`
  - `docker compose config`
- Respect DB context:
  - Local PHPUnit defaults to SQLite in-memory.
  - PostgreSQL is production and CI backend target for critical behaviors.
- Add or update targeted tests whenever behavior changes.
  - Do not rely only on broad suite runs for high-risk areas.
- Preserve domain regression coverage:
  - Booking overlaps and same-day turnover.
  - Token expiry/revocation/refresh.
  - Room optimistic locking and stale version conflicts.

## Implementation Checklist

1. Identify impacted test domains before coding.
   - Feature tests for API behavior.
   - Unit tests for internal logic and edge cases.
2. Write or adjust tests with the change.
   - Prefer precise assertions for conflict and error semantics.
3. Run focused tests first.
   - Fast feedback on changed area.
4. Run full backend suite.
   - Catch coupling and hidden regressions.
5. Run frontend typecheck/unit tests if API shape changed.
6. For PostgreSQL-specific logic, verify on PostgreSQL path before merge.

## Verification / DoD

```bash
# Backend core
cd backend && php artisan test

# High-risk targeted examples
cd backend && php artisan test tests/Feature/CreateBookingConcurrencyTest.php
cd backend && php artisan test tests/Feature/TokenExpirationTest.php
cd backend && php artisan test tests/Feature/RoomOptimisticLockingTest.php

# Frontend and infra
cd frontend && npx tsc --noEmit
cd frontend && npx vitest run
docker compose config
```

Optional static analysis and style checks:

```bash
cd backend && vendor/bin/pint --test
cd backend && vendor/bin/phpstan analyse
cd backend && vendor/bin/psalm
cd frontend && npm run lint
```

## Common Failure Modes

- Changing behavior without adding targeted tests.
- Assuming SQLite test pass guarantees PostgreSQL constraint correctness.
- Skipping frontend validation after API payload shape changes.
- Missing concurrency test updates when lock behavior changes.
- Treating docs/status snapshots as current test output without rerunning.

## References

- `../../AGENTS.md`
- `../../PROJECT_STATUS.md`
- `../../backend/phpunit.xml`
- `../../backend/tests/Feature/CreateBookingConcurrencyTest.php`
- `../../backend/tests/Feature/TokenExpirationTest.php`
- `../../backend/tests/Feature/RoomOptimisticLockingTest.php`
- `../../backend/tests/Feature/Booking/ConcurrentBookingTest.php`
- `../../backend/tests/Feature/Database/TransactionIsolationIntegrationTest.php`
- `../../docs/backend/guides/TESTING.md`
- `../../.github/workflows/tests.yml`
