# Booking Invariant Gotchas

> Institutional knowledge from real and realistic failure patterns in the Soleil Hostel booking system. Each entry documents a specific way an invariant can be violated despite tests passing.

---

### GOTCHA-1: The "friendly" closed-interval refactor

**Invariant:** INV-1 (half-open `[check_in, check_out)`)

**Scenario:** A developer finds the overlap check "confusing" and refactors `<` / `>` to `<=` / `>=` to make it "more intuitive" — after all, if someone checks in on March 1 and checks out on March 5, they're "using the room on March 5," right?

**Symptom:** Same-day turnover bookings are rejected. A guest checking out on March 5 blocks another guest from checking in on March 5. Support tickets spike for "room unavailable" on turnover days.

**Root cause:** The developer changed the PHP overlap check to closed intervals but didn't touch the PostgreSQL exclusion constraint (which uses `[)`). Now the PHP layer is stricter than the constraint. Or worse, they "fixed" the constraint too, breaking the half-open semantics system-wide.

**Correct pattern:** Always use strict `<` / `>` in PHP overlap checks. The `[)` in the exclusion constraint means check_out is exclusive. Same-day turnover is a feature, not a bug.

**Detection:** `verify-no-double-booking` skill checks for `<=` / `>=` in overlap comparisons. Same-day turnover test catches this if it exists.

---

### GOTCHA-2: The "complete" status filter

**Invariant:** INV-2 (active statuses = `{pending, confirmed}`)

**Scenario:** A developer adds `refund_pending` to the overlap-blocking status filter, reasoning that "the booking hasn't been fully cancelled yet, so the room should still be reserved."

**Symptom:** Guests cannot rebook a room during the refund processing window (which can take 5–10 business days). Revenue loss on high-demand dates.

**Root cause:** `refund_pending` is a payment state, not an occupancy state. The guest has already cancelled — the room is physically available. Only `pending` and `confirmed` represent active reservations.

**Correct pattern:** Overlap-blocking statuses are exactly `{pending, confirmed}`. All other statuses (including `refund_pending`, `cancelled`, `refund_failed`) free the room for new bookings.

**Detection:** `verify-no-double-booking` checklist item 10 verifies the status filter. The exclusion constraint's WHERE clause is the definitive reference.

---

### GOTCHA-3: The forgotten `withTrashed()`

**Invariant:** INV-3 (soft-deleted bookings excluded from availability)

**Scenario:** A developer uses `Booking::withTrashed()` in an availability query to "be thorough" about checking for conflicts, not realizing that soft-deleted bookings should be invisible.

**Symptom:** Rooms appear permanently unavailable after a booking is soft-deleted. The only fix is to hard-delete the booking record, losing audit history.

**Root cause:** `withTrashed()` includes soft-deleted records. The exclusion constraint filters `deleted_at IS NULL`, so the database allows the booking, but the PHP layer rejects it. The layers disagree.

**Correct pattern:** Availability queries must exclude soft-deleted records. Use the default scope (which applies `whereNull('deleted_at')`) or explicitly add the filter.

**Detection:** `verify-no-double-booking` step 3 checks for soft-delete exclusion in the PHP scope. Grep for `withTrashed` in booking overlap/availability code paths.

---

### GOTCHA-4: The lock-then-validate gap

**Invariant:** INV-6 (pessimistic locking governs booking creation)

**Scenario:** A developer restructures the booking creation flow: acquires `lockForUpdate()`, validates the booking data (including date validation, guest count checks), then releases the lock by ending the transaction, then inserts the booking in a new transaction.

**Symptom:** Intermittent double-bookings under concurrent load. Two requests both acquire the lock sequentially, both pass validation, both release the lock, then both insert. The exclusion constraint catches one — but only if it's active on PostgreSQL. On SQLite (tests), both succeed.

**Root cause:** The lock must be held continuously from check to insert. Any gap between lock release and insert is a race condition window.

**Correct pattern:** Single transaction: `DB::transaction(function() { lockForUpdate(); checkOverlap(); insert(); })`. Lock, check, and insert are atomic.

**Detection:** `verify-no-double-booking` step 4 verifies no gap exists between lock acquisition and insert. Code review for transaction boundaries.

---

### GOTCHA-5: The UI-only RBAC gate

**Invariant:** INV-7 (RBAC enforcement at API layer)

**Scenario:** A new admin endpoint is added. The developer hides the button in the React UI for non-admin users and considers the endpoint "protected." No middleware or `Gate::authorize()` is added to the controller.

**Symptom:** Any authenticated user can call the endpoint directly via curl/Postman. A regular guest can access admin booking management, modify bookings, or view sensitive data.

**Root cause:** UI visibility is not authorization. The API is the trust boundary, not the frontend.

**Correct pattern:** Every admin endpoint must have both route-level middleware (`role:admin` or `role:moderator`) AND controller-level `Gate::authorize()`. UI hiding is a UX convenience, not a security control.

**Detection:** `verify-rbac-enforcement` skill (P1) checks every route for middleware. `pre-release-verification` step 5 verifies new endpoints have authorization.

---

### GOTCHA-6: The CASCADE time bomb

**Invariant:** INV-5 (location denormalization) + data integrity

**Scenario:** A migration adds a FK with `onDelete('cascade')` on `bookings.room_id → rooms.id`. Months later, an admin deletes a room. All bookings for that room are silently deleted, including confirmed future bookings and historical records needed for financial reporting.

**Symptom:** Guests arrive to find their booking doesn't exist. Financial reports show missing revenue. Audit trail is gone.

**Root cause:** `CASCADE` on this FK means room deletion cascades to booking deletion. The codebase hardened this to `RESTRICT` (migration `2026_03_17_000001`) specifically to prevent this — room deletion is blocked if bookings exist.

**Correct pattern:** Use `RESTRICT` on FKs where the child record has business value (bookings, reviews). Use `SET NULL` where the child should survive but the reference can be nullable (e.g., `bookings.user_id`). Never use `CASCADE` from rooms/users to bookings.

**Detection:** `review-schema-change-risk` checks all FK cascade policies against the hardening migration. Any `CASCADE` on booking-domain FKs is automatic HIGH risk.

---

### GOTCHA-7: The constraint-without-extension

**Invariant:** INV-4 (exclusion constraint is last defense)

**Scenario:** The exclusion constraint migration runs on a PostgreSQL instance where `btree_gist` extension was not created. The `CREATE EXTENSION IF NOT EXISTS btree_gist` line was accidentally removed or the migration order was changed so the constraint migration runs before the extension migration.

**Symptom:** Migration fails in production with `ERROR: data type date has no default operator class for access method "gist"`. If the error is suppressed or the migration is skipped, the database has no overlap constraint.

**Root cause:** `EXCLUDE USING gist` requires the `btree_gist` extension for non-GiST-native types like `date` and `integer`. Without it, the constraint cannot be created.

**Correct pattern:** The exclusion constraint migration must include `CREATE EXTENSION IF NOT EXISTS btree_gist` before the constraint creation. Migration timestamps must ensure this runs before any code that depends on the constraint.

**Detection:** `verify-no-double-booking` checklist item 5 verifies the extension creation. `review-schema-change-risk` checks migration ordering.

---

### GOTCHA-8: The enum-constraint desync

**Invariant:** INV-2 (active statuses) + INV-4 (exclusion constraint)

**Scenario:** A developer adds a new booking status `waitlisted` to the `BookingStatus` PHP enum and the `chk_bookings_status` CHECK constraint, but forgets to update the exclusion constraint's WHERE clause. The `waitlisted` status is intended to be overlap-blocking (it reserves the room pending confirmation).

**Symptom:** Two `waitlisted` bookings for the same room and dates can coexist because the exclusion constraint only filters `pending` and `confirmed`. The PHP overlap scope may or may not include `waitlisted` depending on whether the developer updated it. Either way, the layers are out of sync.

**Root cause:** The exclusion constraint, the PHP overlap scope, and the CHECK constraint are three independent definitions of "which statuses matter." Adding a status requires updating all three.

**Correct pattern:** When adding a new status: (1) add to `BookingStatus` enum, (2) update `chk_bookings_status` CHECK constraint, (3) decide if it's overlap-blocking, (4) if yes, update the exclusion constraint WHERE clause AND the PHP overlap scope. Document the decision.

**Detection:** `verify-no-double-booking` step 2 compares the enum to the overlap-blocking set. Step 7 cross-references constraint and application logic. `review-schema-change-risk` flags new status values.
