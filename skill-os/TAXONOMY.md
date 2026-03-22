# Skill Taxonomy — Soleil Hostel Booking Skill OS

> Every category exists because a specific class of failure has occurred or is structurally likely in this codebase. Categories that cannot be tied to INV-1–INV-10 or a concrete operational risk are rejected.

## Categories

### 1. VERIFICATION

**Failure mode prevented:** Silent invariant violations that pass CI but corrupt business state.

**Why it exists:** Green CI does not verify domain semantics. A test suite can pass while the exclusion constraint is misconfigured, soft-deleted bookings leak into availability, or RBAC is enforced only at the UI layer. Verification skills perform domain-aware checks that CI cannot.

**Invariants covered:** INV-1, INV-2, INV-3, INV-4, INV-7, INV-8, INV-10

**Example skills:**
- `verify-no-double-booking` — validates overlap prevention across all three layers (PHP, SQL, constraint)
- `verify-docs-vs-code` — detects documentation drift that could mislead developers into violating invariants
- `verify-rbac-enforcement` — confirms API-layer authorization matches permission matrix
- `verify-auth-dual-mode` — confirms both Bearer and HttpOnly cookie paths enforce token lifecycle

---

### 2. REVIEW

**Failure mode prevented:** Schema or code changes that silently weaken constraints, break FK integrity, or introduce nullability drift on booking-critical columns.

**Why it exists:** Migrations are one-way in production. A dropped constraint, a changed FK cascade policy, or a nullability flip on `room_id` can cause data corruption that is expensive to reverse. Review skills enforce structured risk assessment before merge.

**Invariants covered:** INV-1, INV-3, INV-4, INV-5, INV-6

**Example skills:**
- `review-schema-change-risk` — risk-tiered assessment of migration files
- `review-booking-logic-change` — validates changes to overlap queries, status transitions, or locking patterns
- `review-cache-invalidation` — ensures cache eviction aligns with state mutations

---

### 3. RELEASE

**Failure mode prevented:** Deploying code that introduces double-booking regressions, breaks auth flows, or ships with stale migrations.

**Why it exists:** The release boundary is the last moment to catch domain-level regressions that slipped through code review. Release skills aggregate verification results into a go/no-go decision with explicit BLOCK criteria.

**Invariants covered:** All (INV-1 through INV-10)

**Example skills:**
- `pre-release-verification` — structured release gate with BLOCKED/CONDITIONAL criteria
- `migration-deploy-checklist` — ensures migration ordering, rollback safety, and PG-only guards

---

### 4. INCIDENT

**Failure mode prevented:** Repeating the same domain-level failure because institutional knowledge was not captured.

**Why it exists:** Booking-system failures (double-bookings, orphaned reviews, RBAC bleed) have specific root cause patterns. Without captured lessons, the same structural mistakes recur across contributors and AI sessions.

**Invariants covered:** Cross-cutting (captures violations of any invariant)

**Example skills:**
- `capture-booking-incident` — structured post-mortem for domain-level failures
- `update-gotchas` — adds new entries to the lessons file when a novel failure pattern is discovered

---

## Rejected Categories

| Proposed Category | Reason for Rejection |
|---|---|
| "Code Style" | Cannot be tied to any invariant or operational risk. Handled by linter. |
| "Performance Optimization" | Not a correctness risk. Addressed ad-hoc. |
| "Generic Testing" | Too broad. Verification skills cover domain-specific test gaps; generic testing is CI's job. |
| "Deployment Automation" | Infrastructure concern, not domain correctness. Handled by Docker Compose + CI. |
