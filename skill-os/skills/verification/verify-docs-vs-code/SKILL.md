# SKILL: verify-docs-vs-code

> Category: Verification | Priority: P0 | Blast Radius: HIGH (indirect — causes developers to violate invariants)
> Last updated: 2026-03-22

## Purpose

Detect documentation that contradicts the codebase. Classify each divergence by severity. Prevent the scenario where a developer reads documentation, follows it faithfully, and introduces a booking-system invariant violation because the docs were wrong.

## Trigger Conditions

Run this skill when ANY of the following occur:

1. A documentation-only PR is submitted for review
2. A code change modifies behavior that is documented (API endpoints, date range semantics, RBAC rules, auth flows)
3. Before a release, as part of `pre-release-verification`
4. After a schema migration that changes column names, types, or constraints
5. When onboarding a new contributor who will rely on docs
6. Periodically (recommended: every 2 weeks) as a drift sweep

## Required Inputs

- Documentation files to verify (see Verification Pass Order below)
- Access to the corresponding source code files
- `docs/agents/ARCHITECTURE_FACTS.md` as the reference truth document
- Access to `backend/routes/v1.php` and `backend/routes/api.php` for API endpoint verification
- Access to migration files for schema verification

## Execution Steps

1. **Establish verification pass order.** Always verify in this sequence — earlier layers are more authoritative:
   ```
   Schema (migrations) → Models (Eloquent) → Services → Controllers → Routes → Documentation
   ```
   When a doc contradicts a migration, the migration is correct. When a doc contradicts a model, the model is correct. This order is derived from INV-10: code wins over docs.

2. **For each documentation file under review, execute these checks:**

   **2a. API Endpoint Verification**
   - Read the documented endpoint (method, path, parameters, response)
   - Find the corresponding route in `backend/routes/v1.php` or `backend/routes/api.php`
   - Verify: method matches, path matches, middleware matches, controller method matches
   - Check: does the documented request/response format match the controller's validation rules and resource output?
   - Flag: documented endpoints that don't exist in routes; routes that aren't documented

   **2b. Date Range Semantics Verification**
   - Search docs for mentions of "check_in", "check_out", date ranges, intervals, overlap
   - Verify: all references describe half-open `[check_in, check_out)` semantics
   - Flag: any mention of "inclusive" end dates, closed intervals `[check_in, check_out]`, or `<=` comparisons for checkout dates
   - Severity: DANGEROUS if docs describe closed intervals (would cause developer to write `<=` overlap check)

   **2c. RBAC Documentation Verification**
   - Read RBAC/permission docs
   - Cross-reference with `backend/routes/v1.php` middleware declarations
   - Cross-reference with controller `Gate::authorize()` and policy checks
   - Verify: documented role requirements match actual middleware (`role:admin`, `role:moderator`)
   - Flag: docs claiming UI-only gating is sufficient; docs with wrong role names
   - Severity: DANGEROUS if docs would lead developer to skip API-layer authorization

   **2d. Auth Mode Verification**
   - Read auth documentation
   - Verify: both Bearer token and HttpOnly cookie modes are documented
   - Verify: token lifecycle (expiry, revocation, refresh) documented correctly
   - Cross-reference with `HttpOnlyTokenController`, `UnifiedAuthController`, middleware
   - Flag: docs that describe only one auth mode; docs with wrong token column names

   **2e. Schema/Column Verification**
   - Read docs that reference table columns, types, constraints
   - Cross-reference with actual migrations
   - Flag: wrong column names (e.g., `is_approved` when column is `approved`), wrong types, missing constraints
   - Flag: docs referencing PostgreSQL ENUMs for columns that are VARCHAR

   **2f. Status Value Verification**
   - Read docs that list booking/room/stay statuses
   - Cross-reference with `BookingStatus.php`, `StayStatus.php`, and CHECK constraints
   - Flag: missing statuses, extra statuses, wrong status names

3. **Classify each divergence** using the severity scale below.

4. **Check the "docs win" exception list** — cases where the documentation reveals that the code is wrong, not the other way around.

5. **Produce a findings report** with each divergence, its severity, and recommended remediation.

## Drift Severity Scale

### STALE
Documentation is outdated but not misleading. A developer reading it would not make an incorrect decision, but would have incomplete information.

**Examples:**
- Doc lists 4 booking statuses but a 5th (`refund_failed`) was added
- Doc references an old file path that has been renamed
- Test count in docs doesn't match current count

**Action:** Update in next docs pass. No urgency.

### MISLEADING
Documentation actively contradicts the codebase in a way that could cause confusion or wasted time, but would not directly cause an invariant violation.

**Examples:**
- Doc says column is `is_approved` when it's `approved`
- Doc says `target_type` when column is `resource_type`
- Doc describes a PostgreSQL ENUM for `rooms.status` when it's actually VARCHAR

**Action:** Correct before next PR that touches the affected area. Flag in review.

### DANGEROUS
Documentation would directly cause a developer to violate an invariant (INV-1 through INV-10) if they followed it.

**Examples:**
- Doc describes booking overlap using closed intervals `[check_in, check_out]` → developer writes `<=` comparison → same-day turnover breaks (INV-1)
- Doc says RBAC is enforced via frontend role checks only → developer skips `Gate::authorize()` → unauthorized API access (INV-7)
- Doc says `cancelled` bookings block inventory → developer includes `cancelled` in overlap query → cancelled rooms can't be rebooked (INV-2)
- Doc says to use `withTrashed()` in availability queries → soft-deleted bookings block new bookings (INV-3)

**Action:** Fix immediately. Block any PR that introduces DANGEROUS drift. Escalate if DANGEROUS drift is found in production docs.

## "Docs Win" Exception List

In rare cases, documentation is intentionally ahead of code (design docs, planned behavior). Docs "win" when:

1. **Design decision documents** (`docs/DOMAIN_LAYERS.md`, `docs/PRODUCT_GOAL.md`) describe intended behavior that hasn't been implemented yet — this is not drift, it's a roadmap
2. **Migration notes** in ARCHITECTURE_FACTS.md describe constraints that should exist but don't yet (documented in FINDINGS_BACKLOG.md as open items)
3. **Sunset notices** — docs marking endpoints as deprecated before code removal

In all other cases, code wins (INV-10).

## Invariant Check

| Invariant | What to verify in docs |
|---|---|
| **INV-1** | All date range references use half-open `[)` semantics |
| **INV-2** | Overlap-blocking statuses listed as exactly `{pending, confirmed}` |
| **INV-7** | RBAC enforcement described at API layer, not just UI |
| **INV-8** | Review-booking relationship documented as one-to-one with `booking_id` NOT NULL |
| **INV-9** | Both auth modes documented; neither conflated |
| **INV-10** | No doc contradicts verified code behavior |

## Expected Output

A drift report with:
- Total files reviewed
- Divergence count by severity (STALE / MISLEADING / DANGEROUS)
- Each finding: file, line/section, what docs say, what code says, severity, remediation
- Overall drift risk: CLEAN / ACCEPTABLE (STALE only) / NEEDS ATTENTION (MISLEADING) / CRITICAL (any DANGEROUS)

## Verification Checklist

1. [ ] All API endpoint docs verified against `routes/v1.php`
2. [ ] All date range references use half-open `[)` semantics
3. [ ] RBAC docs match middleware declarations in routes
4. [ ] Auth docs cover both Bearer and HttpOnly cookie modes
5. [ ] Column names in docs match actual migration column names
6. [ ] Status value lists match application enums
7. [ ] FK cascade policies in docs match hardening migration
8. [ ] Constraint descriptions match actual constraint SQL
9. [ ] No DANGEROUS drift found (or all DANGEROUS items remediated)
10. [ ] "Docs win" exceptions identified and justified
11. [ ] Verification pass followed schema → model → controller → docs order
12. [ ] Drift report produced with severity classifications

## Anti-Patterns

### AP-1: Assuming docs are correct because they're detailed
**What:** Reading well-formatted documentation and trusting it without cross-referencing code.
**Why it fails:** The most dangerous docs are the ones that look authoritative but contain subtle invariant violations. A detailed doc describing closed intervals is more dangerous than a missing doc.

### AP-2: Fixing code to match docs without verification
**What:** Finding a divergence and changing the code to match documentation.
**Why it fails:** Violates INV-10. The code is the source of truth. The doc may describe an incorrect design that was intentionally changed during implementation. Always verify the code's behavior is correct before deciding which side to fix.

### AP-3: Marking DANGEROUS drift as STALE
**What:** Downgrading severity because the incorrect information is "just in docs."
**Why it fails:** A developer reading DANGEROUS docs will write code that violates invariants. The docs become a vector for introducing bugs.

### AP-4: Verifying only changed docs
**What:** Only checking docs that were modified in the current PR.
**Why it fails:** Code changes can make unchanged docs incorrect. A migration that renames a column makes every doc referencing the old name instantly stale or misleading.

## Edge Cases

### EC-1: Doc describes behavior that exists in v2 but not v1
API v2 may have different behavior than v1. Verify which version the doc references. A doc describing v2 behavior is not wrong — it's version-specific.

### EC-2: Doc references a test file that was deleted
The test may have been refactored or merged. Check if equivalent coverage exists under a different name before flagging.

### EC-3: ARCHITECTURE_FACTS.md contradicts a migration
ARCHITECTURE_FACTS.md is a curated truth document. If it contradicts a migration, investigate: either the doc needs updating or the migration has a bug. Do not auto-assume code wins — this specific doc has elevated authority.

### EC-4: Multiple docs disagree with each other
Establish which doc is closer to code in the verification hierarchy. The doc that matches code is correct; update the other.

## References

| Reference | Path |
|---|---|
| Architecture facts (reference truth) | `docs/agents/ARCHITECTURE_FACTS.md` |
| API routes v1 | `backend/routes/v1.php` |
| API routes legacy | `backend/routes/api.php` |
| RBAC permission matrix | `docs/PERMISSION_MATRIX.md` |
| Booking status enum | `backend/app/Enums/BookingStatus.php` |
| Stay status enum | `backend/app/Enums/StayStatus.php` |
| DB facts | `docs/DB_FACTS.md` |
| Findings backlog | `docs/FINDINGS_BACKLOG.md` |
| Docs drift example | `skill-os/examples/docs-drift-review-example.md` |

## Changelog

| Date | Change |
|---|---|
| 2026-03-22 | Initial skill creation. Covers API, date range, RBAC, auth, schema, and status verification. |
