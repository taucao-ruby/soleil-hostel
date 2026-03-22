# Prioritized Skill Backlog — Soleil Hostel Booking Skill OS

## P0 — Must exist before next release

```
name:            verify-no-double-booking
category:        verification
purpose:         Verify overlap prevention is intact across PHP, SQL, and exclusion constraint layers.
risk-if-missing: Double-booking reaches production; guest arrives to find room occupied.
artifacts:       SKILL.md, checklist.md
```

```
name:            review-schema-change-risk
category:        review
purpose:         Assess migration risk with explicit tier classification before merge.
risk-if-missing: FK cascade change silently deletes booking history on user deletion; nullability flip breaks overlap constraint.
artifacts:       SKILL.md, templates/migration-risk-review.md
```

```
name:            pre-release-verification
category:        release
purpose:         Execute structured release gate with BLOCKED/CONDITIONAL criteria.
risk-if-missing: Release ships with broken overlap constraint or stale migration; no structured go/no-go decision.
artifacts:       SKILL.md, templates/release-readiness-report.md
```

```
name:            verify-docs-vs-code
category:        verification
purpose:         Detect documentation drift that could mislead developers into violating invariants.
risk-if-missing: Developer reads docs claiming closed intervals, writes overlap query that allows same-day double-booking.
artifacts:       SKILL.md, examples/docs-drift-review-example.md
```

## P1 — High value, next sprint

```
name:            verify-rbac-enforcement
category:        verification
purpose:         Confirm every protected endpoint has API-layer authorization, not just UI gating.
risk-if-missing: Unauthorized user accesses admin booking endpoints via direct API call; RBAC is cosmetic.
artifacts:       SKILL.md, checklist.md
```

```
name:            review-booking-logic-change
category:        review
purpose:         Validate changes to overlap queries, status transitions, or locking patterns preserve invariants.
risk-if-missing: Refactor introduces closed-interval check; same-day turnover breaks; overlap query misses pending status.
artifacts:       SKILL.md
```

```
name:            verify-auth-dual-mode
category:        verification
purpose:         Confirm both Bearer token and HttpOnly cookie auth paths enforce expiry, revocation, and refresh limits.
risk-if-missing: Revoked token still grants access via cookie path; refresh abuse not detected on one auth mode.
artifacts:       SKILL.md, checklist.md
```

```
name:            capture-booking-incident
category:        incident
purpose:         Create structured post-mortem when a domain-level failure occurs.
risk-if-missing: Same double-booking regression recurs because root cause was not captured; no institutional memory.
artifacts:       SKILL.md, templates/incident-report.md
```

## P2 — Useful, not urgent

```
name:            review-cache-invalidation
category:        review
purpose:         Verify cache eviction events align with booking/room state mutations.
risk-if-missing: Stale room availability shown after booking confirmation; guest sees room as available after it's booked.
artifacts:       SKILL.md
```

```
name:            verify-api-version-compatibility
category:        verification
purpose:         Confirm v1 endpoints maintain backward compatibility when v2 is modified.
risk-if-missing: Frontend still calling v1 endpoint that was silently changed; booking creation fails in production.
artifacts:       SKILL.md
```

```
name:            migration-deploy-checklist
category:        release
purpose:         Ensure migration ordering, rollback safety, and PG-only runtime guards before deploy.
risk-if-missing: Migration runs on SQLite in CI but fails on PostgreSQL in production; rollback leaves orphaned columns.
artifacts:       SKILL.md, checklist.md
```

```
name:            update-gotchas
category:        incident
purpose:         Add new entries to lessons file when a novel failure pattern is discovered.
risk-if-missing: Hard-won debugging knowledge lost between sessions; same investigation repeated.
artifacts:       SKILL.md
```
