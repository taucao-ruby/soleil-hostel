# Repository Structure Audit — 2026-03-12

Full monorepo structure audit of the Soleil Hostel repository.

## Audit Summary

| Metric | Value |
|--------|-------|
| Date | 2026-03-12 |
| Branch | `dev` |
| Mode | SOURCE_ONLY |
| Backend tracked files | 347 |
| Frontend tracked files | 150 |
| Docs tracked files | 84 |
| Total markdown files | 116 |
| Migrations | 35 |
| Backend tests | ~115 (88 Feature + 27 Unit) |
| Frontend tests | 21 unit + 1 E2E |
| Skills | 16 files across `laravel/`, `react/`, `ops/` |
| CI workflows | 2 (`tests.yml`, `deploy.yml`) |

## Scorecard

| Dimension | Score | Notes |
|-----------|-------|-------|
| Architecture Clarity | 7/10 | Strong backend layering, clean feature-sliced frontend |
| Repo Hygiene | 4/10 | Committed binary artifacts, output files, duplicate configs |
| Boundary Discipline | 7/10 | Good domain boundaries; some duplication in requests/controllers |
| Documentation Organization | 6/10 | Comprehensive but sprawling; ownership ambiguity between root and docs/ |
| Contributor Ergonomics | 5/10 | Dual lockfile, triple ESLint/Vite configs, 7 root markdown files |
| Operational Readiness | 6/10 | Solid hook infra and CI; stale .env divergence, tracked run-logs |

## Findings

### F-01 — Committed Test Databases and Output Files [Critical]

**Type:** hygiene
**Confidence:** High (VERIFIED FACT)

Tracked artifacts with no archival value:

| File | Size | Issue |
|------|------|-------|
| `backend/storage/testing.sqlite_test_1..4` | 4 x 224 KB | Parallel test database binaries |
| `backend/test_output.txt` | 123 lines | Test runner stdout |
| `backend/test_results.txt` | 3,932 lines | Full test results dump |
| `backend/composer_output.txt` | 43 lines | Composer install output |
| `backend/run-logs/backend.smoke.*.log` | small | Local smoke test output |
| `run-logs/*.log` (root) | 4 files | Local dev server logs |

**Why it matters:** Binary blobs permanently inflate git history. Output files create noise in diffs. `backend/.env.httponly-cookie` may contain secrets.

**Recommendation:**
```bash
git rm --cached backend/storage/testing.sqlite_test_*
git rm --cached backend/test_output.txt backend/test_results.txt backend/composer_output.txt
git rm --cached backend/run-logs/
git rm --cached run-logs/
```
Add to `.gitignore`:
```
*.sqlite_test_*
test_output.txt
test_results.txt
composer_output.txt
run-logs/
```

---

### F-02 — Frontend Dual Lockfile (npm + pnpm) [High]

**Type:** operational risk
**Confidence:** High (VERIFIED FACT)

Both `frontend/package-lock.json` (npm) and `frontend/pnpm-lock.yaml` (pnpm) are tracked. Root `package.json` scripts reference `pnpm run dev`.

**Why it matters:** Two lockfiles mean two possible dependency resolution outcomes. CI may use the wrong one. New contributors must guess the correct package manager.

**Recommendation:** Choose pnpm (matches root scripts). Delete `package-lock.json`. Add `"packageManager": "pnpm@..."` to `frontend/package.json`. Add `package-lock.json` to `frontend/.gitignore`.

---

### F-03 — Frontend Config File Triplication [High]

**Type:** ambiguity
**Confidence:** High (VERIFIED FACT)

**ESLint — 3 configs coexist:**
- `frontend/.eslintrc.cjs` (legacy CommonJS)
- `frontend/.eslintrc.json` (legacy JSON)
- `frontend/eslint.config.js` (flat config — ESLint 9+ canonical)

**Vite — 3 configs coexist:**
- `frontend/vite.config.ts` (TypeScript — canonical)
- `frontend/vite.config.js` (JavaScript duplicate)
- `frontend/vite.config.d.ts` (type declaration for the JS config)

**Why it matters:** Tools pick one config based on precedence rules. Dead configs create false confidence that changes to them have effect.

**Recommendation:** Keep `eslint.config.js` and `vite.config.ts`. Delete the other four.

---

### F-04 — Backend Request File Duplication [Medium]

**Type:** duplication
**Confidence:** High (VERIFIED FACT)

| Root namespace | Auth/ namespace |
|----------------|-----------------|
| `Requests/LoginRequest.php` (detailed docblock + custom messages) | `Requests/Auth/LoginRequest.php` (minimal) |
| `Requests/RegisterRequest.php` (with custom messages) | `Requests/Auth/RegisterRequest.php` (minimal) |

**Why it matters:** Two files with the same class name in different namespaces is a maintenance trap. Changes may be applied to the wrong file.

**Recommendation:** Consolidate to `Auth/` namespace (matches controller structure). Merge richer validation from root versions. Update all imports.

---

### F-05 — Root-Level Markdown Sprawl [Medium]

**Type:** docs sprawl
**Confidence:** High (VERIFIED FACT)

Seven project-management markdown files at root compete with `CLAUDE.md` and `README.md`:

| File | Size |
|------|------|
| `AI_Engineering_Capability_Assessment_Soleil_Hostel.md` | 36 KB |
| `BACKLOG.md` | 19 KB |
| `README.md` | 25 KB |
| `PRODUCT_GOAL.md` | 9 KB |
| `PROJECT_STATUS.md` | 7 KB |
| `AUDIT_REPORT.md` | 14 KB |
| `PROMPT_AUDIT_FIX.md` | 10 KB |
| `AGENTS.md` | 2 KB |

**Why it matters:** Root should contain only repository-constitution files. Project management artifacts at root create clutter on first clone.

**Recommendation:** Move `AGENTS.md`, `AI_Engineering_Capability_Assessment_Soleil_Hostel.md`, `AUDIT_REPORT.md`, `BACKLOG.md`, `PRODUCT_GOAL.md`, `PROJECT_STATUS.md`, `PROMPT_AUDIT_FIX.md` to `docs/project/`.

---

### F-06 — Tracked `.vscode/settings.json` and `run-logs/` [Medium]

**Type:** hygiene
**Confidence:** High (VERIFIED FACT)

`.vscode/settings.json` is tracked despite `.vscode/` in `.gitignore` (committed before the ignore rule was added). `run-logs/` (4 files) tracked with local server output.

**Recommendation:**
```bash
git rm --cached .vscode/settings.json
git rm --cached run-logs/
```
Add `run-logs/` to `.gitignore` (`.vscode/` already covered).

---

### F-07 — Root-Level Laravel Scaffold Residue [Medium]

**Type:** ambiguity
**Confidence:** High (VERIFIED FACT — activity is STRONG INFERENCE)

| Path | Status |
|------|--------|
| `database/database.sqlite` (root) | Not tracked, on disk only |
| `resources/views/reviews/index.blade.php` (root) | **Tracked** — no route points to it |
| `frontend/resources/css/app.css` + `frontend/resources/js/app.js` | **Tracked** — Laravel scaffold in React app |

**Recommendation:** `git rm` tracked files. Delete root `database/` and `resources/` directories. Remove `frontend/resources/`.

---

### F-08 — `.env` / `.env.example` Divergence [Medium]

**Type:** operational risk
**Confidence:** High (VERIFIED FACT)

Local `.env` (not tracked) references MySQL / `homestay` database. `.env.example` (tracked) correctly references PostgreSQL / `soleil_hostel`. Stack is declared as PostgreSQL.

**Why it matters:** A developer copying `.env` from a colleague or backup instead of `.env.example` will target the wrong database engine.

**Recommendation:** Delete stale local `.env`, regenerate from `.env.example`.

---

### F-09 — `frontend/tsconfig.node.tsbuildinfo` Committed [Low]

**Type:** hygiene
**Confidence:** High (VERIFIED FACT)

TypeScript incremental build cache file tracked in git. Changes on every build, creates unnecessary diffs.

**Recommendation:** `git rm --cached frontend/tsconfig.node.tsbuildinfo`. Add `*.tsbuildinfo` to `frontend/.gitignore`.

---

### F-10 — Documentation Canonical Ownership Ambiguity [Medium]

**Type:** docs sprawl
**Confidence:** Medium (STRONG INFERENCE — content overlap not fully verified)

| Concern | Location A | Location B |
|---------|-----------|-----------|
| Deployment docs | `docs/backend/DEPLOYMENT.md` | `docs/backend/guides/DEPLOYMENT.md` |
| Backlog | `BACKLOG.md` (root) | `docs/FINDINGS_BACKLOG.md` |
| Status tracking | `PROJECT_STATUS.md` (root) | `docs/COMPACT.md` |
| Audit reports | `AUDIT_REPORT.md` (root) | `docs/AUDIT_2026_02_21.md` |

**Why it matters:** When the same concern has two homes, updates go to one and the other drifts. Contributors cannot tell which is canonical.

**Recommendation:** Designate one canonical location per concern. Delete or redirect non-canonical copies. Document the map in `docs/README.md`.

---

### F-11 — Duplicate Health Test Directories [Low]

**Type:** duplication
**Confidence:** High (VERIFIED FACT)

- `backend/tests/Feature/Health/HealthEndpointTest.php`
- `backend/tests/Feature/HealthCheck/HealthControllerTest.php`

Two test directories for the same health endpoint concern.

**Recommendation:** Consolidate into `Health/`. Keep the more comprehensive test. Delete the other.

---

### F-12 — Duplicate Room Seeders [Low]

**Type:** duplication
**Confidence:** High (VERIFIED FACT)

- `backend/database/seeders/RoomSeeder.php`
- `backend/database/seeders/RoomsTableSeeder.php`

Both in the same namespace, both create rooms.

**Recommendation:** Keep `RoomSeeder.php` (matches Laravel convention). Delete `RoomsTableSeeder.php`. Update `DatabaseSeeder.php` if it references the old name.

---

### F-13 — Orphaned Root-Level Test File [Low]

**Type:** ambiguity
**Confidence:** High (VERIFIED FACT)

`backend/tests/Feature/RoomOptimisticLockingTest.php` lives at Feature root, while related room tests are in `backend/tests/Feature/Room/`.

**Recommendation:** Move into `backend/tests/Feature/Room/` to match directory structure.

---

### F-14 — `backend/.env.httponly-cookie` Committed [Medium]

**Type:** security exposure
**Confidence:** Medium (POSSIBLE RISK — contents not inspected for secrets)

An environment variant file tracked in git. May contain real credentials or tokens.

**Recommendation:** Inspect contents. If secrets present, remove from history with `git filter-repo`. If template only, rename to `.env.httponly-cookie.example`.

---

### F-15 — Redirect Stub `docs/DEVELOPMENT_HOOKS.md` [Low]

**Type:** hygiene
**Confidence:** High (VERIFIED FACT)

Three-line file that says "See HOOKS.md". Adds noise to the docs listing with no information value.

**Recommendation:** Delete. Use git history if anyone needs the redirect trail.

---

### F-16 — `console.warn` Without DEV Guard in AuthContext [Low]

**Type:** hygiene
**Confidence:** High (VERIFIED FACT — from frontend agent)

`frontend/src/features/auth/AuthContext.tsx:113` — `console.warn('Token validation failed:', status)` is NOT guarded by `import.meta.env.DEV`. Other console calls in the codebase are properly guarded.

**Recommendation:** Wrap with `if (import.meta.env.DEV) { console.warn(...) }`.

---

## Structural Strengths Worth Preserving

1. **Controller → Service → Repository with contracts** — Backend layering with `Repositories/Contracts/` interfaces prevents fat-controller regression.

2. **Feature-sliced frontend with co-located tests** — Test files sit next to components. Reduces cognitive overhead and encourages test writing.

3. **Versioned API routes** — `routes/api/v1.php`, `v2.php`, `legacy.php` with deprecation middleware. Production-mature API lifecycle management.

4. **Git hook infrastructure** — `.husky/` → `tools/hooks/` → `hook-policy.json` with declarative policy and programmable enforcement.

5. **Skills directory** — Domain-partitioned AI skill files (`laravel/`, `react/`, `ops/`) with central README. Scales well for AI-assisted development.

6. **Dual-auth documentation** — Bearer + HttpOnly cookie auth thoroughly documented in `ARCHITECTURE_FACTS.md` with migration-level evidence.

7. **Agent contract with Definition of Done** — `docs/agents/CONTRACT.md` formalizes quality gates per task type. Governance infrastructure most repos lack.

8. **Event-driven booking lifecycle** — `Events/` + `Listeners/` for booking state changes enable clean separation between domain logic and side effects.

9. **Comprehensive test coverage** — 115+ backend tests organized by domain; 21 frontend unit tests + E2E; tests cover concurrency, RBAC, security headers, rate limiting.

10. **Two-layer overlap prevention** — Application-level half-open interval check + PostgreSQL EXCLUDE USING gist constraint. Defense-in-depth for the most critical domain invariant.

## Remediation Plan

### Immediate Cleanup (< 1 hour)

| # | Action | Files |
|---|--------|-------|
| 1 | `git rm --cached` committed artifacts | `backend/storage/testing.sqlite_test_*`, `backend/test_output.txt`, `backend/test_results.txt`, `backend/composer_output.txt`, `backend/run-logs/`, `run-logs/`, `.vscode/settings.json`, `frontend/tsconfig.node.tsbuildinfo`, `resources/views/reviews/index.blade.php` |
| 2 | Update `.gitignore` | Add `*.sqlite_test_*`, `test_output.txt`, `test_results.txt`, `composer_output.txt`, `run-logs/`, `*.tsbuildinfo` |
| 3 | Delete stale local `.env` | Regenerate from `.env.example` |

### Near-Term Normalization (< 1 day)

| # | Action | Files |
|---|--------|-------|
| 4 | Resolve frontend lockfile | Delete `frontend/package-lock.json`, add `packageManager` field, update `frontend/.gitignore` |
| 5 | Delete dead frontend configs | `frontend/.eslintrc.cjs`, `frontend/.eslintrc.json`, `frontend/vite.config.js`, `frontend/vite.config.d.ts` |
| 6 | Delete stale frontend artifacts | `frontend/FINAL_PROJECT_COMPLETION.md`, `frontend/resources/` |
| 7 | Delete root scaffold residue | `database/`, `resources/` |
| 8 | Delete redirect stub | `docs/DEVELOPMENT_HOOKS.md` |
| 9 | Guard console.warn | `frontend/src/features/auth/AuthContext.tsx:113` |

### Medium-Term Architecture Hygiene (< 1 week)

| # | Action | Files |
|---|--------|-------|
| 10 | Consolidate request files | Merge root `LoginRequest.php` / `RegisterRequest.php` into `Auth/` namespace |
| 11 | Move root markdown to `docs/project/` | `AGENTS.md`, `AI_Engineering_Capability_Assessment_Soleil_Hostel.md`, `AUDIT_REPORT.md`, `BACKLOG.md`, `PRODUCT_GOAL.md`, `PROJECT_STATUS.md`, `PROMPT_AUDIT_FIX.md` |
| 12 | Resolve docs duplication | Designate canonical locations for deployment, backlog, status, audit docs |
| 13 | Consolidate duplicate tests | Merge `HealthCheck/` into `Health/`, move `RoomOptimisticLockingTest.php` into `Room/` |
| 14 | Consolidate duplicate seeders | Delete `RoomsTableSeeder.php`, keep `RoomSeeder.php` |
| 15 | Evaluate `.env.httponly-cookie` | Inspect for secrets; rename or purge from history |

### Governance / Policy Fixes

| # | Action |
|---|--------|
| 16 | Add CI check that fails on committed `*.sqlite*`, `*.log`, `test_output*`, `test_results*` |
| 17 | Add pre-commit hook check for dual lockfiles |
| 18 | Document in `README.dev.md`: required package manager, `.env` regeneration, canonical configs |

## "Do Not Overcorrect" Notes

- **`booking/` vs `bookings/` feature split** — Asymmetric naming but CLAUDE.md explicitly sanctions cross-imports. Reflects real domain distinction (write vs read). Leave as-is.
- **Root-level `CLAUDE.md` + `README.md` + `README.dev.md`** — Three README-class files serve distinct audiences (AI agent, public, developer). Justified.
- **Legacy `AuthController.php`** — Deprecated with sunset date (July 2026) and explicit migration path. Do not remove prematurely.
- **`backend/package.json`** — Standard Laravel 11+ for Vite asset compilation. Not a second Node project.
- **116 markdown files** — Volume reflects genuine domain complexity. Issue is organization, not quantity. Reorganize, do not mass-delete.
- **`tests/performance/` at root** — Tests integrated system, not a single component. Root-level placement is correct.
- **Two `phpunit.xml` variants** — SQLite (fast) and PostgreSQL (integration) serve different testing speeds. Both valuable.

## Final Verdict

The repository is **fundamentally healthy**. Backend architecture is well-layered, frontend is properly feature-sliced, documentation ambition is commendable, and the AI-agent tooling surface (skills, hooks, contracts, MCP) is more sophisticated than most production repos.

Issues are **moderate and compounding** — primarily hygiene debt (committed artifacts, duplicate configs) and organizational drift (docs sprawl, duplicate files). None are blocking, but left unaddressed they will progressively erode contributor confidence and onboarding speed.

**Top 3 next actions:**
1. Purge committed artifacts (F-01, F-06, F-09) — ~15 min, highest ROI
2. Resolve frontend lockfile conflict (F-02) — eliminates "works on my machine" risk
3. Delete dead frontend configs (F-03) — eliminates most visible contributor confusion
