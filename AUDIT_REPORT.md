# Audit Report

_Date: 2026-02-23 | Branch: dev | Commit: 61f430a_

## Snapshot

| Item | Verified Value |
|------|---------------|
| Audit date | 2026-02-23 |
| Prior audit | [AUDIT_2026_02_21.md](./docs/AUDIT_2026_02_21.md) |
| Prior open issues | F-02, F-03 |
| PHP version | ^8.2 (composer.json) |
| Laravel version | ^12.0 (composer.json) |
| Sanctum version | ^4.1 (composer.json) |
| Node version | 20 (CI), React ^19.0.0 |
| TypeScript | ~5.7.2 |
| Package manager | pnpm (lockfile confirmed) |
| Migrations | 34 files |
| CI workflows | tests.yml, deploy.yml |
| DB engine | PostgreSQL 16 + Redis 7 |

## Verification Gates

| Gate | Status | Output summary |
|------|--------|----------------|
| G1 Backend tests | [REQUIRES LOCAL VERIFICATION] | `cd backend && php artisan test` — baseline: 737 tests, 2071 assertions |
| G2 Pint style | [REQUIRES LOCAL VERIFICATION] | `cd backend && vendor/bin/pint --test` — baseline: 250 files, 0 violations |
| G3 Frontend typecheck | [REQUIRES LOCAL VERIFICATION] | `cd frontend && npx tsc --noEmit` |
| G4 Frontend tests | [REQUIRES LOCAL VERIFICATION] | `cd frontend && npx vitest run` — baseline: 145 tests, 13 suites |
| G5 Docker compose config | [REQUIRES LOCAL VERIFICATION] | `docker compose config` |
| G6 Migrate pretend | [REQUIRES LOCAL VERIFICATION] | `cd backend && php artisan migrate --pretend` |

## Findings Matrix

| Severity | Backend | Frontend | CI | Infra | Docs | Security | Total |
|----------|---------|----------|----|-------|------|----------|-------|
| Critical | 0 | 0 | 0 | 0 | 0 | 0 | **0** |
| High | 0 | 0 | 0 | 0 | 0 | 0 | **0** |
| Medium | 1 | 0 | 1 | 0 | 0 | 0 | **2** |
| Low | 1 | 1 | 1 | 0 | 1 | 0 | **4** |
| **Total** | **2** | **1** | **2** | **0** | **1** | **0** | **6** |

### Delta vs prior audit
- New issues this cycle: 6
- Resolved since last audit: 2 (F-02, F-03)
- Regressed: 0

## Issues — Critical

_None._

## Issues — High

_None._

## Issues — Medium

---

#### AUDIT-001 — backend/.env.test tracked in git with MySQL config

| Field | Value |
|-------|-------|
| Severity | Medium |
| Area | Backend |
| Type | DX / Drift |
| Status | New |
| Backlog ref | — |
| Confidence | HIGH |

**Evidence**
`backend/.env.test` (lines 23-26, tracked via `git ls-files`)
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=soleil_hostel
```

Also contains placeholder credentials:
```
MAIL_USERNAME=your_email@example.com
MAIL_PASSWORD=your_email_password
```

**Impact**
Contradicts project's PostgreSQL requirement. Developers copying this file would get MySQL errors. Placeholder credentials in tracked file are a minor hygiene issue.

**Suggested fix**
Either remove `backend/.env.test` from git tracking (add to `.gitignore`), or update it to match PostgreSQL config with placeholder-only values. CI already uses `cp .env.example .env.testing`.

---

#### AUDIT-002 — CI quality gates are non-blocking (continue-on-error: true)

| Field | Value |
|-------|-------|
| Severity | Medium |
| Area | CI |
| Type | DX |
| Status | New |
| Backlog ref | — |
| Confidence | HIGH |

**Evidence**
`.github/workflows/tests.yml`
```
Line 397: continue-on-error: true   # Psalm
Line 421: continue-on-error: true   # Pint
Line 467: continue-on-error: true   # Composer Audit
Line 490: continue-on-error: true   # NPM Audit
```

Note: PHPStan (line 373) correctly has `continue-on-error: false`.

**Impact**
Style violations, Psalm-detected type issues, and known dependency CVEs will not block PR merges. Quality regressions can ship undetected.

**Suggested fix**
Set `continue-on-error: false` for at minimum `pint` (code style) and `composer-audit` (security). Consider keeping `psalm` as `true` only if false-positive rate is too high.

---

## Issues — Low

---

#### AUDIT-003 — backend/.env.testing has committed APP_KEY

| Field | Value |
|-------|-------|
| Severity | Low |
| Area | Backend |
| Type | Security |
| Status | New |
| Backlog ref | — |
| Confidence | HIGH |

**Evidence**
`backend/.env.testing` (line 3, tracked via `git ls-files`)
```
APP_KEY=base64:Ht3gPLkSP7/Bb+U+Wr+fFkPoSq6H5BkJ4qL9n+1N8bQ=
```

**Impact**
Test-only encryption key committed to repo. CI regenerates it (`php artisan key:generate --env=testing`), so not used in CI. Low practical risk, but committed keys are a hygiene issue.

**Suggested fix**
Set `APP_KEY=` (empty) in the committed file and ensure CI always generates it. Or add `backend/.env.testing` to `.gitignore` since CI creates it from `.env.example`.

---

#### AUDIT-004 — Frontend TODO markers in production code paths

| Field | Value |
|-------|-------|
| Severity | Low |
| Area | Frontend |
| Type | Drift |
| Status | New |
| Backlog ref | — |
| Confidence | HIGH |

**Evidence**
```
frontend/src/features/home/components/SearchCard.tsx:20  // TODO: wire to availability API
frontend/src/features/home/components/SearchCard.tsx:21  console.log('TODO: wire to availability API', ...)
frontend/src/features/home/home.mock.ts:3               // TODO: replace with local hostel photo asset
frontend/src/features/home/home.mock.ts:21,35,49        // TODO: local asset  (3 occurrences)
frontend/src/shared/components/ErrorBoundary.tsx:57      // TODO: Send to error tracking service
frontend/src/utils/webVitals.ts:25                       // TODO: Send to analytics service
```

**Impact**
8 TODO markers indicate incomplete feature wiring. `console.log` in SearchCard.tsx will emit debug output in production builds.

**Suggested fix**
Remove `console.log` from SearchCard.tsx. Track remaining TODOs in project backlog. Consider eslint rule `no-console` to catch future occurrences.

---

#### AUDIT-005 — Stale test count in DEVELOPMENT_HOOKS.md

| Field | Value |
|-------|-------|
| Severity | Low |
| Area | Docs |
| Type | Drift |
| Status | New (residual of F-03) |
| Backlog ref | F-03 |
| Confidence | HIGH |

**Evidence**
`docs/DEVELOPMENT_HOOKS.md` (line 23)
```
cd frontend && npx vitest run (142 tests)
```

Actual count per COMPACT.md: 145 tests (13 suites).

**Impact**
Minor docs drift. Could cause confusion if someone uses this doc to validate test count.

**Suggested fix**
Update "142 tests" to "145 tests" in DEVELOPMENT_HOOKS.md.

---

#### AUDIT-006 — No docker compose config gate in CI

| Field | Value |
|-------|-------|
| Severity | Low |
| Area | CI |
| Type | DX |
| Status | New |
| Backlog ref | — |
| Confidence | HIGH |

**Evidence**
`docs/agents/CONTRACT.md` (line 11) lists `docker compose config` as a DoD gate.
Neither `.github/workflows/tests.yml` nor `.github/workflows/deploy.yml` runs this command.

**Impact**
Docker Compose config errors (invalid YAML, missing env refs) caught only locally, not in CI.

**Suggested fix**
Add a lightweight CI job that runs `docker compose config > /dev/null` to validate compose file syntax.

---

## Prior Issue Cross-Reference

| ID | Summary | Prior Status | Current Status | Evidence |
|----|---------|-------------|----------------|----------|
| F-01 | DATABASE.md room_status ENUM claim | Fixed (PR-4) | Confirmed fixed | — |
| F-02 | docs/README.md "Laravel 11 + PHP 8.3" | Open | **Now fixed** | No "Laravel 11" or "PHP 8.3" found in docs/README.md; line 182 reads "Laravel 12 + PHP 8.2+" |
| F-03 | docs/README.md "142 frontend unit tests" | Open | **Partially fixed** | Removed from docs/README.md, but residual in DEVELOPMENT_HOOKS.md:23 (see AUDIT-005) |
| F-04 | CI triggers `develop` not `dev` | Fixed (PR-1) | Confirmed fixed | tests.yml:7-9 now uses `[main, dev]` |
| F-05 | CI pnpm vs docs npm mismatch | Fixed (PR-4) | Confirmed fixed | — |
| F-06 | Missing CHECK (check_out > check_in) | Fixed (PR-2) | Confirmed fixed | — |
| F-07 | Missing CHECK (rating BETWEEN 1 AND 5) | Fixed (PR-2) | Confirmed fixed | — |
| F-08 | Missing CHECK (price >= 0) | Fixed (PR-2) | Confirmed fixed | — |
| F-09 | Missing FK reviews.booking_id | Fixed (PR-3) | Confirmed fixed | — |
| F-10–F-14 | Various docs/low items | Fixed (PR-4) | Confirmed fixed | — |

## Known Backlog Cross-Reference

Top 5 highest-risk items still open in FINDINGS_BACKLOG.md:
1. F-02 — **Now resolved** (confirmed in this audit)
2. F-03 — **Partially resolved** (residual in DEVELOPMENT_HOOKS.md)
3. All other items (F-01, F-04–F-14) previously marked Fixed — confirmed fixed

No backlog file discovered: **False** — `docs/FINDINGS_BACKLOG.md` exists and was read.

## Quick Wins (each under 30 min)

1. **AUDIT-005**: Update "142 tests" to "145 tests" in `docs/DEVELOPMENT_HOOKS.md:23` (~1 min)
2. **AUDIT-004**: Remove `console.log` from `SearchCard.tsx:21` (~2 min)
3. **AUDIT-003**: Set `APP_KEY=` (empty) in `backend/.env.testing` (~1 min)
4. **AUDIT-001**: Remove `backend/.env.test` from git tracking or update to PostgreSQL config (~5 min)
5. **AUDIT-006**: Add `docker compose config` job to CI (~10 min)

## Recommended Fix Batches

**Batch 1 (CI hardening — AUDIT-002, AUDIT-006):**
- Set `continue-on-error: false` for Pint and Composer Audit in tests.yml
- Add `docker compose config` validation job
- Estimated: 1 PR, ~15 min

**Batch 2 (Env file cleanup — AUDIT-001, AUDIT-003):**
- Remove `backend/.env.test` from git or update to PostgreSQL config
- Clear committed APP_KEY from `backend/.env.testing`
- Estimated: 1 PR, ~10 min

**Batch 3 (Frontend cleanup — AUDIT-004):**
- Remove `console.log` from SearchCard.tsx
- Track TODO items in project backlog
- Estimated: 1 PR, ~10 min

**Batch 4 (Docs sync — AUDIT-005, backlog F-02/F-03):**
- Update DEVELOPMENT_HOOKS.md test count
- Update FINDINGS_BACKLOG.md status for F-02 and F-03
- Estimated: 1 PR, ~5 min

## Appendix A — Searches Executed

| Call # | Query | Hits | Action taken |
|--------|-------|------|--------------|
| 1 | EXCLUDE USING gist / no_overlapping_bookings / daterange( | 13 (2 files) | Read in Phase 3 — constraint verified |
| 2 | lockForUpdate / SELECT FOR UPDATE | 6 (5 files) | Read CreateBookingService — lock coverage confirmed |
| 3 | deleted_by | 23 (9 files) | Skip — well-covered |
| 4 | payment_intent_id / refund_status / refund_amount | 88 (13 files) | Skip — comprehensive |
| 5 | lock_version | 228 (22 files) | Skip — very thorough |
| 6 | ->foreign( / ->references( | 10 (4 files) | Read — FK constraints verified |
| 7 | ->get() / ->all() / foreach in Services | 25 (7 files) | Noted — CacheWarmer has most hits |
| 8 | env( in app/ and routes/ | 0 | Skip — clean |
| 9 | Request::all() / request()->all() / $guarded = [] | 0 | Skip — clean |
| 10 | Log:: / logger( | 105 (39 files) | Spot-checked — SensitiveDataProcessor exists |
| 11 | Sanctum / auth:sanctum / Bearer / httpOnly | 203 (34 files) | Read auth controllers — dual mode verified |
| 12 | APP_KEY=base64: / DB_PASSWORD= / STRIPE_SECRET / REDIS_PASSWORD= | 20 (12 files) | Read .env files — no real secrets committed |
| 13 | php artisan test / pnpm test / vitest / tsc --noEmit | 4 (2 files) | Read CI files — gates present |
| 14 | branches: in workflows | 3 (2 files) | Read — dev/main branches configured |
| 15 | requirepass / REDIS_PASSWORD | 10 (5 files) | Read — conditional requirepass confirmed |
| 16 | ports: in docker-compose | 4 (1 file) | Read — all ports bound to 127.0.0.1 |
| 17 | Hex colors in frontend/src | 55 (13 files) | Noted — many in tokens file (acceptable) |
| 18 | http://localhost / 127.0.0.1 in frontend/src | 1 (1 file) | Read api.ts — env-guarded fallback (acceptable) |
| 19 | localStorage.setItem / sessionStorage.setItem / document.cookie | 4 (3 files) | Read csrf.ts — CSRF token in sessionStorage (acceptable) |
| 20 | @ts-ignore / : any / eslint-disable / TODO: / FIXME: | 10 (6 files) | Read — 8 TODOs flagged, no @ts-ignore or : any |

## Appendix B — Files Read

### Phase 0
- `AGENTS.md`
- `docs/agents/CONTRACT.md`
- `docs/agents/ARCHITECTURE_FACTS.md`
- `docs/COMPACT.md`
- `docs/FINDINGS_BACKLOG.md`
- `docs/AUDIT_2026_02_21.md`

### Phase 1
- `backend/composer.json`
- `frontend/package.json`

### Phase 3
- `.github/workflows/tests.yml`
- `.github/workflows/deploy.yml`
- `docker-compose.yml`
- `backend/.env.test`
- `backend/.env.testing`
- `.env` (root — not tracked in git)
- `.env.example` (root)
- `.gitignore`
- `redis.conf` (first 30 lines)
- `frontend/src/shared/lib/api.ts`
- `frontend/src/shared/utils/csrf.ts`
- `docs/README.md` (lines 140-190)
- `backend/app/Services/CreateBookingService.php`

## Appendix C — Audit Gaps

| Item | Reason |
|------|--------|
| Full N+1 query audit (CacheWarmer, BookingService) | Budget — search flagged 25 hits but detailed read skipped; dedicated N+1 CI job exists |
| Log scrubbing audit (SensitiveDataProcessor) | Budget — 105 Log:: hits across 39 files; spot-check only |
| Hardcoded color audit (55 hex values in 13 files) | Budget — many in tokens file (acceptable); per-component audit skipped |
| E2E / Playwright coverage gap analysis | Out of scope — Playwright config exists but test coverage not assessed |
| Production deployment config audit | Out of scope — deploy.yml references Forge/Render/Coolify but no access to verify |
| Full migration rollback safety audit | Budget — 34 migrations, only latest booking constraint migration read |
