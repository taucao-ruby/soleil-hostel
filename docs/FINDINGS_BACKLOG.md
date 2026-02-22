# Findings Backlog — Soleil Hostel

Code issues found during the 2026-02-21 audit. **DO NOT FIX** — document only.

Severity guide:
- **Critical**: data integrity, security, booking invariant violation
- **High**: incorrect behavior visible to users
- **Medium**: tech debt, inconsistency, performance
- **Low**: style, minor inaccuracy

| ID | File:Line | Issue | Severity | Suggested Fix | Status |
|----|-----------|-------|----------|---------------|--------|
| F-01 | `docs/DATABASE.md:148` | Claims `CREATE TYPE room_status AS ENUM ('available', 'occupied', 'maintenance')` but no such CREATE TYPE exists. Rooms use `string('status')` (VARCHAR) in migration `2025_05_09_000000`. | Medium | Update DATABASE.md to reflect that room_status is a VARCHAR column, not a PG ENUM. Document actual valid values from application code. | **Fixed** (PR-4) |
| F-02 | `docs/README.md:162` | Tech Stack table says "Laravel 11 + PHP 8.3" but `composer.json` requires `laravel/framework:^12.0` and `php:^8.2`. | Low | Update to "Laravel 12 + PHP 8.2+". | Open |
| F-03 | `docs/README.md:155` | Project Status table says "142 frontend unit tests" but COMPACT.md reports 145 tests (13 suites) as of 2026-02-21. | Low | Update test count to 145. | Open |
| F-04 | `.github/workflows/tests.yml:8-9` | CI triggers on `push: branches: [main, develop]` but repo uses `dev` branch, not `develop`. Pushes to `dev` will NOT trigger CI. | High | Change `develop` to `dev` in both `on.pull_request.branches` and `on.push.branches`. | **Fixed** (PR-1) |
| F-05 | `.github/workflows/tests.yml` (multiple) | CI uses `pnpm` for frontend (install, test, lint, build) but local dev docs (AGENTS.md, COMPACT.md, docs/README.md Quick Start) reference `npm`. | Medium | Align docs to mention both, or standardize. Frontend `package.json` has both `npm` and no pnpm lockfile locally, but CI uses pnpm and a `pnpm-lock.yaml` exists. | **Fixed** (PR-4) |
| F-06 | `backend/database/migrations/*` | No `CHECK (check_out > check_in)` constraint on bookings table. DB_FACTS.md correctly notes this absence. | Medium | Add migration with `ALTER TABLE bookings ADD CONSTRAINT chk_bookings_dates CHECK (check_out > check_in)`. | **Fixed** (PR-2) |
| F-07 | `backend/database/migrations/*` | No `CHECK (rating BETWEEN 1 AND 5)` constraint on reviews table. DB_FACTS.md correctly notes this absence. | Medium | Add migration with `ALTER TABLE reviews ADD CONSTRAINT chk_reviews_rating CHECK (rating BETWEEN 1 AND 5)`. | **Fixed** (PR-2) |
| F-08 | `backend/database/migrations/*` | No `CHECK (price >= 0)` constraint on rooms table. | Low | Add migration with `ALTER TABLE rooms ADD CONSTRAINT chk_rooms_price CHECK (price >= 0)`. | **Fixed** (PR-2) |
| F-09 | `docs/DB_FACTS.md:29` | States "FK `reviews.booking_id -> bookings.id`: Not found in migrations" | Medium | Add foreign key constraint migration for `reviews.booking_id`. | **Fixed** (PR-3) |
| F-10 | `docs/KNOWN_LIMITATIONS.md:120` | Contains `// TODO: Integrate with Stripe` | Low | Track Stripe integration as a planned feature, not a TODO in docs. | **Fixed** (PR-4) |
| F-11 | `docs/frontend/PERFORMANCE_SECURITY.md:62` | Contains `analytics integration (TODO)` | Low | Track analytics as planned feature. | **Fixed** (PR-4) |
| F-12 | `docs/frontend/UTILS_LAYER.md:99` | Contains `Analytics service integration (TODO - currently commented out)` | Low | Same as F-11. | **Fixed** (PR-4) |
| F-13 | Booking status type | Booking `status` is VARCHAR, not a PG ENUM (unlike `user_role_enum`). Values `pending`, `confirmed`, `refund_pending`, `cancelled`, `refund_failed` are enforced only at app level. | Low | Consider creating `CREATE TYPE booking_status AS ENUM (...)` for DB-level enforcement, or document this as intentional. | **Fixed** (PR-4, documented as intentional) |
| F-14 | `docker-compose.yml:40` | Redis password default `soleil_redis_secret_2026` is hardcoded in docker-compose.yml (visible in repo). | Medium | Use env var without default, or document that this is local-dev-only and acceptable. | **Fixed** (PR-1) |
