# Verification Framework — Soleil Hostel Booking Skill OS

## Philosophy

Verification in a booking system is not about confirming that code compiles or tests pass. It is about confirming that the business invariants — the rules that prevent double-bookings, preserve financial records, and enforce access control — are intact across all layers simultaneously.

A booking system has a unique verification challenge: correctness depends on alignment between the database schema, the application logic, and the constraint system. Each layer can be independently "correct" while the system as a whole is broken. A PHP overlap check using closed intervals passes its unit tests. A PostgreSQL exclusion constraint using half-open intervals passes migration validation. But together, they disagree on whether same-day turnover is valid — and the system behaves inconsistently depending on which layer processes the request first.

**The goal of verification is to confirm cross-layer alignment, not per-layer correctness.**

## Verification Layers

### Layer 1: Schema Verification
**What:** Database schema matches documented invariants — constraints exist, FK policies are correct, column types and nullability are as specified.
**How:** Read migrations, query `information_schema`, compare against `ARCHITECTURE_FACTS.md`.
**Authority:** Highest. Schema is the ground truth for data integrity.

### Layer 2: Application Verification
**What:** PHP models, services, and repositories implement the same rules as the schema — overlap scopes match constraint WHERE clauses, locking patterns are correct, status filters are aligned.
**How:** Read model scopes, service methods, repository queries. Compare against schema.
**Authority:** High. Application logic must agree with schema but is the less durable enforcement.

### Layer 3: API Verification
**What:** Routes, middleware, and controllers enforce authorization and validate input correctly — RBAC at the API layer, not just UI; input validation in Request classes.
**How:** Read routes, middleware declarations, controller authorization calls. Cross-reference with permission matrix.
**Authority:** Medium. API layer is the trust boundary for external access.

### Layer 4: Documentation Verification
**What:** Docs accurately describe what the code actually does — no closed-interval descriptions, no wrong role names, no missing statuses.
**How:** Run `verify-docs-vs-code` skill. Compare each doc statement against the corresponding code.
**Authority:** Lowest for describing current behavior (code wins per INV-10). Elevated for design intent (docs may reveal code bugs).

### Layer 5: Operational Verification
**What:** The system behaves correctly in production-like conditions — concurrent booking attempts are handled, cache invalidation fires, auth tokens expire correctly.
**How:** Integration tests, load tests, manual testing in staging.
**Authority:** Empirical. Catches issues that static analysis misses.

## Verification Hierarchy

When sources conflict, this hierarchy determines which source is correct:

```
PostgreSQL schema/constraints (most authoritative)
  ↓
Laravel migrations (define schema)
  ↓
Eloquent models (implement schema in PHP)
  ↓
Service classes (implement business logic)
  ↓
Controllers (enforce API contracts)
  ↓
Routes/middleware (enforce access control)
  ↓
Documentation (describes intent)
  ↓
UI behavior (least authoritative)
```

**Exception:** `ARCHITECTURE_FACTS.md` has elevated authority as a curated truth document. If it contradicts a migration, investigate both — the migration may have a bug.

## Domain-Specific Checks

### Booking Overlap
- Constraint interval matches PHP scope interval (both `[)`)
- Constraint status filter matches PHP scope status filter (both `{pending, confirmed}`)
- Constraint soft-delete filter matches PHP scope soft-delete handling
- `lockForUpdate()` wraps the check-then-insert atomically

### State Machine
- Status transitions follow the defined state machine (not arbitrary)
- `BookingStatus` enum values match `chk_bookings_status` CHECK constraint
- New statuses have explicit overlap-blocking classification (blocking or non-blocking)

### RBAC
- Every admin endpoint has route middleware (`role:admin` or `role:moderator`)
- Every admin controller method has `Gate::authorize()` or policy check
- Permission matrix reflects actual middleware, not intended middleware

### Auth Modes
- Both Bearer token and HttpOnly cookie paths enforce: expiry, revocation, refresh limits
- Token columns (`token_identifier`, `token_hash`, `revoked_at`, `expires_at`) are indexed and used correctly
- No auth path bypasses middleware checks

## False Confidence Patterns

These are situations that feel verified but are not. Each is specific to the Soleil Hostel booking system.

### False Confidence 1: "All 1000+ tests pass, so overlap logic is correct"

**Why it's false:** Tests may cover overlap rejection without testing same-day turnover allowance. Tests may use mocked dates that don't exercise boundary conditions. Tests may run on SQLite (no exclusion constraint) while production uses PostgreSQL.

**What's actually needed:** Specific tests for: (1) overlap rejection, (2) same-day turnover allowance, (3) cancelled-then-rebooked, (4) soft-deleted-then-rebooked. AND verification that the PHP scope matches the constraint SQL.

### False Confidence 2: "The exclusion constraint exists, so double-booking is impossible"

**Why it's false:** The constraint only prevents double-booking for its filtered statuses. If a new status is added to the overlap-blocking set in PHP but not in the constraint, the PHP layer rejects some valid bookings while the constraint allows some invalid ones. The layers are out of sync.

**What's actually needed:** Cross-layer alignment verification — the constraint's WHERE clause must exactly match the PHP scope's status filter and soft-delete handling.

### False Confidence 3: "RBAC middleware is on the route, so the endpoint is protected"

**Why it's false:** Middleware checks role. But within the controller, the method may access resources belonging to other users without a policy/gate check. `role:moderator` lets a moderator in, but nothing stops them from viewing bookings they shouldn't see unless a policy also checks ownership or scope.

**What's actually needed:** Route middleware (authentication + role) AND controller-level authorization (gate/policy checks for resource-level access).

## Verification Tiers

### SUFFICIENT
The verification is complete for the context. All layers have been checked and are aligned.

**Applies when:**
- `verify-no-double-booking` passes all 4 invariant checks AND all 26 checklist items
- `review-schema-change-risk` produces a completed template with risk tier
- `pre-release-verification` produces a verdict with no BLOCKED flags

### CONDITIONAL
The verification covers the primary risk but has known gaps that must be tracked.

**Applies when:**
- Core invariant checks pass but test coverage is thin (<3 tests for a specific scenario)
- Documentation drift is STALE but not DANGEROUS
- A new feature is covered by unit tests but not integration tests

### INSUFFICIENT
The verification does not adequately cover the risk. Additional work is required before the change can be considered safe.

**Applies when:**
- Any invariant check fails
- Cross-layer alignment has not been verified (e.g., PHP scope checked but constraint not compared)
- RBAC middleware exists but controller-level authorization has not been verified
- DANGEROUS documentation drift is detected

## Integration with Skills

Skills invoke this framework by:

1. **Declaring which verification layers they cover.** Each skill states: "This skill verifies layers 1 and 2" (schema + application). This makes coverage gaps explicit.

2. **Using the hierarchy to resolve conflicts.** When a skill finds a discrepancy between PHP and SQL, it uses the hierarchy to determine which is correct (SQL wins), then flags the other for correction.

3. **Classifying their output using verification tiers.** Each skill run produces a tier: SUFFICIENT, CONDITIONAL, or INSUFFICIENT. The `pre-release-verification` skill aggregates tier results across all sub-skills.

4. **Feeding findings into lessons.** When a skill discovers a new failure pattern, the finding is captured in `lessons/booking-invariant-gotchas.md` for institutional knowledge.

### Skill Coverage Map

| Skill | Layers Covered | Primary Invariants |
|---|---|---|
| `verify-no-double-booking` | Schema, Application | INV-1, INV-2, INV-3, INV-4 |
| `review-schema-change-risk` | Schema | INV-1, INV-3, INV-4, INV-5, INV-6 |
| `verify-docs-vs-code` | Documentation (against all layers) | INV-1, INV-2, INV-7, INV-9, INV-10 |
| `pre-release-verification` | All layers (aggregator) | INV-1 through INV-10 |
