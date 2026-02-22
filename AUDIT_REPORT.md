# Audit Report — Soleil Hostel

**Last Updated:** February 22, 2026
**Branch:** `main`

## Audit History

| Audit | Date | Scope | Issues Found | Status |
| ----- | ---- | ----- | ------------ | ------ |
| v1 | Feb 9, 2026 | Full codebase | 61 | 61/61 resolved (100%) |
| v2 | Feb 10–11, 2026 | Deep code-level review | 98 | 98/98 resolved (100%) |
| v3 | Feb 21–22, 2026 | Repo-wide docs + governance + remediation | 14 | 12/14 resolved (86%) |

## Current Verification (February 22, 2026)

All quality gates pass:

```bash
cd backend && php artisan test
# 737 tests, 2071 assertions — PASS

cd frontend && npx tsc --noEmit
# 0 errors — PASS

cd frontend && npx vitest run
# 145 tests, 13 files, 0 failures — PASS

docker compose config
# Valid — PASS
```

## Audit v3 — Documentation, Governance & Remediation (February 21–22, 2026)

Full repo-wide documentation audit covering backend (Laravel), frontend (React/TS), all docs, CI/CD, MCP server, and git hooks. Remediation completed February 22.

### Methodology

- 15 targeted code searches (Tier 2)
- Key file reads: migrations, config, tailwind, CI workflows, MCP policy
- Cross-referenced docs claims against actual code
- Created governance framework (`docs/agents/`)

### Findings Summary

| ID | Severity | Issue | File | Status |
| -- | -------- | ----- | ---- | ------ |
| F-01 | Medium | DATABASE.md claims room_status is PG ENUM — actually VARCHAR | `docs/DATABASE.md` | **Fixed** (PR-4) |
| F-02 | Low | README says "Laravel 11" — actually Laravel 12 | `docs/README.md` | Open |
| F-03 | Low | Frontend test count 142 — actually 145 | `docs/README.md` | Open |
| F-04 | High | CI triggers on `develop` but repo uses `dev` branch | `.github/workflows/tests.yml` | **Fixed** (PR-1) |
| F-05 | Medium | CI uses pnpm, local docs reference npm | Multiple docs | **Fixed** (PR-4) |
| F-06 | Medium | Missing CHECK (check_out > check_in) on bookings | Migrations | **Fixed** (PR-2) |
| F-07 | Medium | Missing CHECK (rating BETWEEN 1 AND 5) on reviews | Migrations | **Fixed** (PR-2) |
| F-08 | Low | Missing CHECK (price >= 0) on rooms | Migrations | **Fixed** (PR-2) |
| F-09 | Medium | Missing FK reviews.booking_id -> bookings.id | Migrations | **Fixed** (PR-3) |
| F-10 | Low | TODO: Integrate with Stripe | `docs/KNOWN_LIMITATIONS.md` | **Fixed** (PR-4) |
| F-11 | Low | TODO: analytics integration | `docs/frontend/PERFORMANCE_SECURITY.md` | **Fixed** (PR-4) |
| F-12 | Low | TODO: analytics service commented out | `docs/frontend/UTILS_LAYER.md` | **Fixed** (PR-4) |
| F-13 | Low | Booking status is VARCHAR, not PG ENUM | Intentional — app-level | **Fixed** (PR-4, documented) |
| F-14 | Medium | Redis default password in docker-compose.yml | `docker-compose.yml` | **Fixed** (PR-1) |

Full details: [docs/FINDINGS_BACKLOG.md](./docs/FINDINGS_BACKLOG.md)

### Remediation PRs (February 22, 2026)

| PR Branch | Findings | Changes |
| --------- | -------- | ------- |
| `fix/auditv3-pr1-ci-redis` | F-04, F-14 | CI branch `develop`→`dev`; Redis conditional requirepass (no hardcoded password) |
| `fix/auditv3-pr2-checks` | F-06, F-07, F-08 | Migration: CHECK constraints on bookings.dates, reviews.rating, rooms.price (pgsql-only, SQLite guard) |
| `fix/auditv3-pr3-fk-reviews-bookings` | F-09 | Migration: FK `reviews.booking_id → bookings.id` ON DELETE RESTRICT (SQLite guard) |
| `docs/auditv3-pr4-docs-sync` | F-01, F-05, F-10–F-13 | Docs: room_status VARCHAR, npm→pnpm, TODO→Planned, booking status documented as intentional |

Post-remediation fixes:
- Pint style violations on new migrations + auth controllers/tests
- `pnpm.overrides` for minimatch >=10.2.1 (ReDoS vulnerability)

### Confirmed Architecture Truths

| Domain | Claim | Status |
| ------ | ----- | ------ |
| Booking overlap | Exclusion constraint with `daterange('[)')` + `deleted_at IS NULL` | Confirmed |
| Booking overlap | Half-open interval `[check_in, check_out)` | Confirmed |
| Pessimistic locking | `lockForUpdate()` in CancellationService + Booking model | Confirmed |
| Optimistic locking | `lock_version` on rooms + locations | Confirmed |
| Auth | Dual mode: Bearer + HttpOnly cookie | Confirmed |
| Token columns | 8 custom columns across 2 migrations | Confirmed |
| UserRole enum | `user \| moderator \| admin` (PG ENUM + PHP backed enum) | Confirmed |
| Multi-location | Trigger `trg_booking_set_location` auto-populates booking location | Confirmed |
| Soft delete | `deleted_at` + `deleted_by` on bookings | Confirmed |
| Cancellation audit | `cancelled_at` + `cancelled_by` + `cancellation_reason` | Confirmed |
| Brand tokens | 6 colors match in `tailwind.config.js` | Confirmed |
| BottomNav | 4 tabs: Trang chu, Phong, Dat phong, Tai khoan | Confirmed |
| Anti-pattern | "Cuon xuong" absent from rendered UI (regression test guards it) | Confirmed |

### Documentation Created

| Document | Purpose |
| -------- | ------- |
| `docs/agents/README.md` | AI agent framework index |
| `docs/agents/CONTRACT.md` | Definition of Done |
| `docs/agents/ARCHITECTURE_FACTS.md` | Domain invariants verified against code |
| `docs/agents/COMMANDS.md` | Verified command reference |
| `docs/AI_GOVERNANCE.md` | Operational checklists for AI agents |
| `docs/COMMANDS_AND_GATES.md` | Full commands + CI gate mapping |
| `docs/MCP.md` | MCP server documentation |
| `docs/HOOKS.md` | Hook enforcement docs |
| `docs/AUDIT_2026_02_21.md` | Detailed audit findings |
| `docs/FINDINGS_BACKLOG.md` | Code issues backlog |

### Documentation Updated

| Document | Changes |
| -------- | ------- |
| `docs/README.md` | Added AI agents section, high-risk callouts, all-docs index, fixed Laravel version |
| `docs/COMPACT.md` | Added key pointers + audit session entry |
| `skills/README.md` | Added "Adding a New Skill" section + governance links |
| `PROJECT_STATUS.md` | Updated test counts, added v3 audit summary |
| `README.md` (root) | Updated test counts, Laravel version |

## Audit v2 Summary (February 10–11, 2026)

98 issues found via deep code-level review. All resolved.

| Severity | Found | Fixed |
| -------- | ----- | ----- |
| P0 - Critical | 6 | 6 |
| P1 - High | 20 | 20 |
| P2 - Medium | 43 | 43 |
| P3 - Low | 29 | 29 |
| **Total** | **98** | **98** |

Critical issues resolved: cookie lifetime bug, revoked token bypass, APP_KEY regen, MySQL/PG mismatch, Redis password exposure.

Details: [AUDIT_FIX_PROMTS_V2.md](./AUDIT_FIX_PROMTS_V2.md)

## Audit v1 Summary (February 9, 2026)

61 issues found. All resolved across 16 batches.

Details: [AUDIT_FIX_PROMTS_V1.md](./AUDIT_FIX_PROMTS_V1.md)

## Remaining Open Findings

| ID | Severity | Issue | Notes |
| -- | -------- | ----- | ----- |
| F-02 | Low | `docs/README.md` says "Laravel 11" — actually Laravel 12 | Stale reference in docs/README.md Project Status table |
| F-03 | Low | `docs/README.md` says "142 frontend unit tests" — actually 145 | Stale test count in docs/README.md |

## Next Steps

1. Fix F-02 and F-03 in `docs/README.md`
2. Dashboard Phase 2 implementation
3. Wire SearchCard to real availability API
