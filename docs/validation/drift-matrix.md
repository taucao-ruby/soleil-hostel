---
schema_version: 1.0
produced_by_batch: B10B
phase: Phase D
date: 2026-03-22
input_artifacts:
  - docs/cleanup/01-classification-matrix.md
  - docs/cleanup/03-rules-consolidation-report.md
  - foundation/00-master-contract.md
authority_order_applied: false
unresolved_count: 0
---

# Semantic Drift Matrix — Batch 10B

> Generated: 2026-03-22 | Branch: dev

## Drift Checks

| # | Topic | Files Checked | Result |
|---|-------|--------------|--------|
| 1 | Active overlap statuses (pending, confirmed) | CLAUDE.md, ARCHITECTURE_FACTS.md, DB_FACTS.md, INVARIANTS.md, booking-integrity.md | CONSISTENT |
| 2 | Booking status values (5 values) | ARCHITECTURE_FACTS.md, DB_FACTS.md, booking-integrity.md | CONSISTENT |
| 3 | Backend test count (1037) | COMPACT.md, PROJECT_STATUS.md | CONSISTENT |
| 4 | Frontend test count | COMPACT.md, PROJECT_STATUS.md, actual vitest run | **DRIFT** |
| 5 | Token lookup chain (token_identifier → token_hash) | CLAUDE.md, ARCHITECTURE_FACTS.md, auth-token-safety.md | CONSISTENT |
| 6 | lockForUpdate() scope | CLAUDE.md, ARCHITECTURE_FACTS.md, booking-integrity.md | CONSISTENT |
| 7 | Review column name (approved) | ARCHITECTURE_FACTS.md, DB_FACTS.md | CONSISTENT |
| 8 | RBAC middleware (role:moderator) | CLAUDE.md, PERMISSION_MATRIX.md | CONSISTENT |
| 9 | rooms.status type (VARCHAR) | ARCHITECTURE_FACTS.md, DB_FACTS.md | CONSISTENT |
| 10 | Frontend state pattern (useState+useEffect+AbortController) | CLAUDE.md, frontend-reviewer.md, FEATURES_LAYER.md | CONSISTENT |

## Drift Details

### Check 4: Frontend Test Count — DRIFT CONFIRMED

| Source | Count | Files | Verified Date |
|--------|-------|-------|---------------|
| COMPACT.md §1 | 226 | 21 | 2026-03-11 |
| PROJECT_STATUS.md (Test Results section) | 226 | 21 | 2026-03-11 |
| PROJECT_STATUS.md (Gates section) | 236 | 24 | 2026-03-22 |
| **Actual `npx vitest run`** | **236** | **24** | **2026-03-22** |

**Root cause**: Tests were added between March 11 and March 22 (+10 tests, +3 files). PROJECT_STATUS.md gates section was updated but the Test Results Summary section and COMPACT.md were not.

**Remediation**: Update COMPACT.md §1 and PROJECT_STATUS.md Test Results section to reflect 236 tests / 24 files.

## Summary

- **9/10 checks**: CONSISTENT (no drift)
- **1/10 checks**: DRIFT FOUND (frontend test count — stale by 10 tests)
- **Severity**: LOW (informational, no behavioral impact)
- **No contradictions** found in domain invariants, auth logic, or booking rules
