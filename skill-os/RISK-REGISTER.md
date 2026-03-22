# Risk Register — Booking Skill OS

## Open Assumptions

### A-1: Exclusion constraint is active in production
**Assumption:** The `no_overlapping_bookings` exclusion constraint exists and is enforced on the production PostgreSQL database.
**Risk if wrong:** Database layer of overlap defense is absent. Only PHP application logic prevents double-bookings.
**Mitigation:** `verify-no-double-booking` skill verifies the constraint in migrations. To verify production state, run: `SELECT conname FROM pg_constraint WHERE conname = 'no_overlapping_bookings';`
**Status:** Unverified against production. Verified in migrations.

### A-2: btree_gist extension is installed in production
**Assumption:** The `btree_gist` extension is present on the production PostgreSQL instance.
**Risk if wrong:** Exclusion constraint cannot function. If the extension was removed or never installed, the constraint silently doesn't enforce.
**Mitigation:** Include `CREATE EXTENSION IF NOT EXISTS btree_gist` in database provisioning scripts. Verify with `SELECT * FROM pg_extension WHERE extname = 'btree_gist';`
**Status:** Verified in migration code. Not verified against production.

### A-3: SQLite tests do not verify constraint behavior
**Assumption:** The test suite runs on SQLite (or PostgreSQL depending on `phpunit.xml` config). SQLite does not support `EXCLUDE USING gist`.
**Risk if wrong:** If tests run on SQLite, constraint-dependent behavior is not tested. Tests pass but constraint violations are not caught.
**Mitigation:** Ensure `phpunit.xml` defaults to PostgreSQL (confirmed in memory: this is already the case as of 2026-03-17). Run booking-critical tests against PostgreSQL.
**Status:** Mitigated. PostgreSQL is the default test database.

### A-4: All booking status transitions are intentional
**Assumption:** The status values in `BookingStatus.php` enum represent the complete and correct set of statuses. No unofficial status values exist in the database.
**Risk if wrong:** Raw SQL updates or legacy code may have inserted status values not in the enum. The `chk_bookings_status` CHECK constraint (if active) would prevent this going forward.
**Mitigation:** Query production: `SELECT DISTINCT status FROM bookings;` and compare against enum values.
**Status:** CHECK constraint added in migration `2026_03_17_000003`. Historical data not verified.

### A-5: Cache invalidation covers all booking state mutations
**Assumption:** Every path that changes booking or room state triggers appropriate cache invalidation.
**Risk if wrong:** Stale cache serves outdated availability. Guest sees room as available after it's booked.
**Mitigation:** P2 skill `review-cache-invalidation` would verify this. Currently not covered by any P0 skill.
**Status:** Not verified. Deferred to P2.

## Skills Deferred from P0 to P1

### verify-rbac-enforcement
**Justification:** RBAC enforcement has been audited and hardened (commit `012ce40`, March 2026). The current state is documented in `PERMISSION_MATRIX.md` with 5 open follow-ups. While important, the immediate risk is lower than double-booking or schema drift because RBAC was recently reviewed. Promoted to P1 for next sprint.

### review-booking-logic-change
**Justification:** Overlap with `verify-no-double-booking`. The double-booking skill covers the most critical booking logic checks. A dedicated booking-logic-change review skill adds value for non-overlap changes (status transitions, payment flow) but is not as urgent as schema and release skills.

### verify-auth-dual-mode
**Justification:** Auth hardening was implemented with custom token columns and middleware checks. The dual-mode architecture is documented in ARCHITECTURE_FACTS.md. Auth changes are infrequent compared to booking and schema changes. Deferred to P1.

### capture-booking-incident
**Justification:** Incident capture is valuable for institutional memory but is reactive (triggered by failures). The P0 skills are preventive (triggered before failures). Prevention takes priority over capture in the initial rollout.

## Missing Context

### MC-1: Production database state
Skills verify against migration files, not the actual production schema. If migrations were skipped, manually applied, or partially rolled back in production, the verified state may not match reality.
**Impact:** Skills may report PASS while production has gaps.
**Resolution:** Run schema verification queries against production as part of Day 4 battle test.

### MC-2: CI pipeline coverage details
The `pre-release-verification` skill documents what CI does NOT check, but does not have full visibility into what CI DOES check (specific workflow steps, test filtering, parallel execution).
**Impact:** The skill may duplicate CI checks or miss opportunities to reference CI results.
**Resolution:** Review `.github/workflows/` in detail during Day 9–10.

### MC-3: Load testing results
No information about concurrent booking performance under load. The `lockForUpdate()` pattern is correct in theory but behavior under hundreds of concurrent requests is unknown.
**Impact:** `verify-no-double-booking` confirms the pattern but cannot verify runtime behavior.
**Resolution:** Load testing is an operational concern outside Skill OS scope. Document as a recommendation in the release readiness report.

## Next Expansions After Day 14

### Sprint 2 (Days 15–28)
1. Build P1 skills: `verify-rbac-enforcement`, `review-booking-logic-change`, `verify-auth-dual-mode`
2. Build `capture-booking-incident` skill with incident report template
3. Create automation scripts for common verification queries (constraint existence, FK policies, status values)
4. Integrate skill invocation hints into CLAUDE.md (trigger conditions as comments near relevant code references)

### Sprint 3 (Days 29–42)
1. Build P2 skills: `review-cache-invalidation`, `verify-api-version-compatibility`, `migration-deploy-checklist`
2. Build `update-gotchas` skill for structured lessons capture
3. Create test data fixtures for dry-running skills without production access
4. Retrospective on skill adoption rate and false positive/negative rates

### Long-term
- Automated skill execution via CI hooks (run `verify-no-double-booking` on every PR that touches booking files)
- Skill coverage dashboard (which invariants are verified by which skills, when last executed)
- Cross-project skill sharing (if other hostel/booking systems adopt the framework)
