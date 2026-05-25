# docs/DB_FACTS.md - Soleil Hostel Database Facts

Operational database reference for backend and migration work.
Source of truth for migrations: `backend/database/migrations/*`.

> **Relationship to ARCHITECTURE_FACTS.md**: Domain invariants (booking overlap, auth, concurrency)
> are defined canonically in `docs/agents/ARCHITECTURE_FACTS.md`. This file provides the
> operational companion: table catalog, index strategy, query patterns, and migration rules.
> When invariants overlap, ARCHITECTURE_FACTS.md is authoritative.

## 1) Core Tables (authoritative)

- `locations`: Physical hostel locations; includes operational metadata and `lock_version`.
- `rooms`: Room inventory plus physical readiness (`readiness_status`) and comparability fields (`room_type_code`, `room_tier`); each room belongs to one location (`rooms.location_id`).
- `bookings`: Reservation records; owns stay dates, status, payment/refund, deposit lifecycle, cancellation, soft-delete audit, and denormalized `location_id`.
- `reviews`: Guest reviews tied to bookings; one review per booking rule.
- `users`: Accounts and roles used by bookings/reviews/auth flows.
- `personal_access_tokens`: Sanctum tokens plus hardened cookie-auth/security columns.
- `email_verification_codes`: OTP email-verification codes (added 2026-04-03, migration `2026_04_03_084257`). Stores SHA-256 `code_hash` (never raw code), `attempts`/`max_attempts` (brute-force guard), `expires_at`, `consumed_at` (NULL = unused), `last_sent_at`. FK `user_id → users.id` CASCADE. Indexes: `idx_evc_user_id`, `idx_evc_expires_consumed`.

Audit & integrity tables:
- `admin_audit_logs`: Append-only audit trail for sensitive admin operations (added 2026-03-12, migration `2026_03_12_000001`; actor snapshot columns added 2026-05-01 via `2026_05_01_000003`). Columns: `actor_id` FK→`users` SET NULL, `actor_email`/`actor_role`/`actor_display_name` (denormalized snapshot to survive user deletion), `action`, `resource_type`/`resource_id`, `metadata` JSON, `ip_address`, `created_at`. Indexes: `(action)`, `(resource_type, resource_id)`, `(actor_id, created_at)`. **DB user must NOT be granted UPDATE/DELETE on this table in production.**
- `deposit_events`: Append-only audit trail for deposit lifecycle transitions (CONC-005, added 2026-05-02, migration `2026_05_02_000002`). Columns: `booking_id` FK→`bookings` CASCADE, `from_status`/`to_status`, `refund_percent` (0..100), `refund_amount` (cents, nullable), `reason`, `actor_id` FK→`users` SET NULL, `actor_email`/`actor_role`, `metadata` JSON, `created_at`. **No `updated_at`, no UPDATE/DELETE path** — every `Deposit::transitionTo()` is a new row. CHECKs (pgsql): `chk_deposit_events_from_status`, `chk_deposit_events_to_status`, `chk_deposit_events_refund_percent BETWEEN 0 AND 100`.

Stripe integration tables:
- `stripe_webhook_events`: Stripe webhook ingestion ledger (added 2026-04-28, migration `2026_04_28_000001`). Columns: `stripe_event_id` UNIQUE, `type`, `status` (enum `processing`/`processed`/`failed`), `payload` JSONB, `processed_at`. Used to dedupe webhook deliveries at the event level.
- `stripe_refund_events`: Durable refund-event replay fence (added 2026-04-29, migration `2026_04_29_000003`; replaced ephemeral `IdempotencyGuard`). Columns: `stripe_refund_id` UNIQUE, `stripe_event_id`, `booking_id` FK→`bookings` SET NULL (decouples audit trail from booking lifecycle), `amount_refunded` (cents, cumulative — sourced from `charge.amount_refunded`, not `refunds[0].amount`), `currency`, `processed_at`. **The UNIQUE on `stripe_refund_id` is the canonical idempotency authority** — inside one transaction the booking row is locked `FOR UPDATE` and the refund event is `INSERT`ed under this constraint; a replayed delivery violates the UNIQUE and is caught (transaction rolls back, no duplicate side effects), eliminating the TOCTOU window present in the prior application-layer state check.

Operational domain tables (added 2026-03-20, see `docs/DOMAIN_LAYERS.md`):
- `stays`: Operational occupancy lifecycle per booking (`stay_status`). One per booking (UNIQUE `booking_id`). `cancelled` is a terminal state since 2026-05-03 (`2026_05_03_000001`, OPS-004).
- `room_assignments`: Physical room allocation history per stay. Partial unique index prevents two active assignments for same stay (PostgreSQL only).
- `service_recovery_cases`: Incident, compensation, and settlement-tracking audit trail. `stay_id` nullable.

AI harness tables (see `docs/HARNESS_ENGINEERING.md`):
- `policy_documents` (added 2026-04-09, migration `2026_04_09_000001`): Hostel policy content for AI FAQ grounding. UUID PK, `slug` (unique), `title`, `content` (text), `category`, `language` (default `vi`), `is_active`, `version`. Indexes: `(slug, is_active, language)`, `(category, is_active)`.
- `ai_proposal_events` (added 2026-04-11, migration `2026_04_11_000001`; FK relaxed + actor snapshot added 2026-04-29 via `2026_04_29_000001`): Audit trail for AI booking proposal confirm/decline/shown/error decisions. `user_id` is now **nullable** with FK `users(id) ON DELETE SET NULL` (was CASCADE — relaxation preserves audit trail past user deletion). Denormalized actor snapshot: `actor_email` (255), `actor_role` (32), `actor_display_name` (255). Other columns: `proposal_hash` (64-char, indexed), `action_type`, `user_decision` (`confirmed`/`declined`/`shown`/`errored`), `downstream_result` (nullable text; structured JSON for execution failures). Indexes: `(proposal_hash)`, `(proposal_hash, user_decision)`.
- `ai_proposals` (added 2026-05-01, migration `2026_05_01_000001`, AI-005/AI-006): Durable proposal contract — the cache envelope is fast but cannot revalidate at confirm time. Columns: `proposal_hash` UNIQUE (64), `user_id` FK→`users` SET NULL (nullable), `action_type`, booking-shape fields (`room_id` FK SET NULL, `check_in`, `check_out`, `quoted_price_cents` — all nullable because cancellation proposals carry no triplet), `context_version` (64-char hash of room price + availability state at proposal generation, recomputed at confirm time for drift detection), `proposed_params` JSON (mirror of cached envelope; survives TTL eviction), `risk_assessment` JSON, `expires_at`, `shown_at`, `decision` (`confirmed`/`declined`/`errored`), `decided_at`. Indexes: `(user_id, created_at)`, `(expires_at)`, `(proposal_hash, decision)`.

Other framework tables exist (`sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `password_reset_tokens`) but are out of scope for booking/auth invariants.

## 2) Invariants (must always hold)

- Booking overlap invariant: treat date ranges as half-open intervals `[check_in, check_out)`.
  - Overlap predicate: `existing.check_in < new.check_out AND existing.check_out > new.check_in`.
  - Adjacent stays are valid: checkout on day `D` and new checkin on day `D` do not overlap.
- Active statuses used for overlap checks: `pending`, `confirmed`.
  - [DB] PostgreSQL exclusion constraint filters exactly these statuses.
  - [APP] `Booking::ACTIVE_STATUSES` uses the same set.
- Soft-delete rule: soft-deleted bookings must not block availability.
  - [DB] `no_overlapping_bookings` excludes rows with `deleted_at IS NOT NULL`.
  - [APP] availability queries should keep `deleted_at IS NULL` in active-booking checks.
- Review integrity:
  - [DB] `reviews.booking_id` is `NOT NULL` (migration `2026_02_10_000002`).
  - [DB] `reviews_booking_id_unique` enforces one review per booking.
  - [DB] FK `reviews.booking_id -> bookings.id` (`fk_reviews_booking_id`, ON DELETE RESTRICT): Added in migration `2026_02_22_000002` (pgsql-only, SQLite guard).
- Multi-location truth:
  - [DB] `rooms.location_id` is `NOT NULL` after backfill migration (`2026_02_09_000005`).
  - [DB] `bookings.location_id` is denormalized and nullable (`ON DELETE SET NULL` FK).
  - [DB] PostgreSQL trigger `trg_booking_set_location` auto-populates `bookings.location_id` from `rooms.location_id`.
  - [APP] room location is the canonical source; booking location is a historical/analytics snapshot.
- Room readiness / comparability truth:
  - [DB] `rooms.readiness_status` is the canonical physical room state.
  - [DB] `rooms.room_type_code` is the equivalence key for swap candidates.
  - [DB] `rooms.room_tier` is the upgrade comparison field.
  - [APP] `rooms.status` remains legacy availability/admin semantics and is not the readiness truth.
- Optimistic locking:
  - [DB] `rooms.lock_version` exists (`NOT NULL`, default `1`).
  - [DB] `locations.lock_version` exists (default `1`).
  - [APP] compare-and-swap update semantics are required to enforce optimistic lock behavior.
- Payment/refund/cancellation audit:
  - [DB] `amount`, `payment_intent_id`, `refund_id`, `refund_status`, `refund_amount`, `refund_error`.
  - [DB] `deposit_amount`, `deposit_collected_at`, `deposit_status`.
  - [DB] cancellation fields: `cancelled_at`, `cancelled_by` (FK `users.id` SET NULL).
  - [DB] **immutable cancellation actor snapshot** (added 2026-05-01, `2026_05_01_000002`): `cancelled_by_email` (255), `cancelled_by_role` (50), `cancelled_by_display` (255). Populated synchronously by `CancellationService` so the cancellation row attribution survives user deletion.
  - [DB] `cancellation_reason` (TEXT).
  - [DB] soft-delete audit: `deleted_at`, `deleted_by`.
- Stripe webhook idempotency (durable, replaces in-memory `IdempotencyGuard`):
  - [DB] `stripe_webhook_events.stripe_event_id` UNIQUE — event-level dedupe (added 2026-04-28).
  - [DB] `stripe_refund_events.stripe_refund_id` UNIQUE — refund-level replay fence; the refund event is `INSERT`ed under this constraint inside the same transaction that locks the booking `FOR UPDATE`, so a replayed delivery violates the UNIQUE and is caught with no duplicate side effects (added 2026-04-29). Source: `StripeWebhookController::handleChargeRefunded`.
  - [APP] `StripeWebhookController::handleChargeRefunded` sources `amount_refunded` from `charge.amount_refunded` (cumulative), not `refunds[0].amount` — correctly handles partial-then-full refund sequences.
- AI proposal durability:
  - [DB] `ai_proposals.proposal_hash` UNIQUE (added 2026-05-01) — durable contract that survives Cache TTL; `context_version` enables drift detection at confirm time.
  - [DB] `ai_proposal_events.user_id` FK→`users` is **SET NULL** (was CASCADE before 2026-04-29) so the audit trail is preserved across user deletion. Denormalized actor snapshot: `actor_email`, `actor_role`, `actor_display_name`.
- Admin audit log integrity:
  - [DB] `admin_audit_logs.actor_id` FK→`users` SET NULL.
  - [DB] **immutable actor snapshot** (added 2026-05-01, `2026_05_01_000003`): `actor_email`, `actor_role`, `actor_display_name`.
  - [DB user] **MUST NOT** have UPDATE/DELETE on `admin_audit_logs` in production. Append-only by convention; integrity depends on this DB-grant constraint.
- Deposit / settlement semantics:
  - [APP] `deposit_amount` is unearned revenue / liability until stay fulfillment; not recognized revenue at collection time.
  - [DB/APP] `settlement_status` is operational financial tracking only; not authoritative accounting / GL.
  - [DB] `deposit_events` (added 2026-05-02) is the append-only ledger for every deposit-status transition (CONC-005). One row per `Deposit::transitionTo()` call. **No `updated_at`.**

## 3) PostgreSQL Guarantees (defense in depth)

- `btree_gist` extension is required before the exclusion constraint:

```sql
CREATE EXTENSION IF NOT EXISTS btree_gist;
```

- Anti-double-booking constraint (`no_overlapping_bookings`):

```sql
ALTER TABLE bookings
ADD CONSTRAINT no_overlapping_bookings
EXCLUDE USING gist (
    room_id WITH =,
    daterange(check_in, check_out, '[)') WITH &&
)
WHERE (status IN ('pending', 'confirmed') AND deleted_at IS NULL);
```

- Partial active-booking index present on PostgreSQL:
  - `idx_bookings_active_overlap` on `(room_id, check_in, check_out)` with `WHERE status IN ('pending', 'confirmed')`.
- PostgreSQL trigger safety net for booking location denormalization:
  - Function: `set_booking_location()`
  - Trigger: `trg_booking_set_location` on insert/update of `bookings.room_id`.
- SQLite/test fallback:
  - Exclusion constraints and PostgreSQL triggers are not available.
  - Migration `2026_02_09_000000_add_foreign_key_constraints.php` intentionally skips FK adds when default DB is SQLite.
  - Result: overlap/location integrity must also be enforced in application logic and tests.
- Additional CHECK constraints (added in migration `2026_02_22_000001`, pgsql-only, SQLite guard):
  - DB `CHECK (check_out > check_in)` on `bookings` (`chk_bookings_dates`).
  - DB `CHECK (rating BETWEEN 1 AND 5)` on `reviews` (`chk_reviews_rating`).
  - DB `CHECK (price >= 0)` on `rooms` (`chk_rooms_price`).
- Additional CHECK constraints (added in migrations `2026_03_17_000002` and `2026_03_17_000003`, pgsql-only):
  - DB `CHECK (max_guests > 0)` on `rooms` (`chk_rooms_max_guests`).
  - DB `CHECK (status IN ('pending','confirmed','refund_pending','cancelled','refund_failed'))` on `bookings` (`chk_bookings_status`).
  - Note: `rooms.status` DB CHECK was deferred at this point in time; **finalized 2026-04-29** as `rooms_status_deprecated CHECK (status IN ('available','unavailable'))` — see §"Deprecation: rooms.status".
- Additional CHECK constraints (added in `2026_03_23_*`, pgsql-only):
  - DB `CHECK (readiness_status IN ('ready','occupied','dirty','cleaning','inspected','out_of_service'))` on `rooms` (`chk_rooms_readiness_status`).
  - DB `CHECK (room_tier IS NULL OR room_tier > 0)` on `rooms` (`chk_rooms_room_tier_positive`).
  - DB `CHECK (deposit_status IN ('none','collected','applied','refunded'))` on `bookings` (`chk_bookings_deposit_status`) — **extended 2026-05-02** (`2026_05_02_000001`) to also accept `'partial_refund'` and `'forfeited'` (CancellationService terminal states; deposit must never linger in `collected` after cancel).
  - DB `CHECK (settlement_status IN ('unsettled','partially_settled','settled','written_off'))` on `service_recovery_cases` (`chk_src_settlement_status`).
- CHECK constraint changes after Apr 2026 (pgsql-only):
  - DB `CHECK (status IN ('available','unavailable'))` on `rooms` (`rooms_status_deprecated`, added `2026_04_29_000002`). The legacy `rooms.status` is now narrowly-enforced to two values during the deprecation window — see §"Deprecation: rooms.status" below.
  - DB `CHECK (stay_status IN ('expected','in_house','late_checkout','checked_out','no_show','relocated_internal','relocated_external','cancelled'))` on `stays` (`chk_stays_stay_status`, refreshed `2026_05_03_000001` to add `'cancelled'`). Required by OPS-004 stay cancellation propagation.
  - DB `CHECK (from_status IN ('none','collected','applied','refunded','partial_refund','forfeited'))` and same for `to_status` on `deposit_events` (`chk_deposit_events_from_status`, `chk_deposit_events_to_status`, added `2026_05_02_000002`).
  - DB `CHECK (refund_percent BETWEEN 0 AND 100)` on `deposit_events` (`chk_deposit_events_refund_percent`).
- FK delete policies hardened (migration `2026_03_17_000001`, pgsql-only):
  - `bookings.user_id → users.id`: CASCADE → SET NULL (booking history survives user deletion)
  - `bookings.room_id → rooms.id`: CASCADE → RESTRICT (room deletion blocked if bookings exist)
  - `reviews.user_id → users.id`: CASCADE → SET NULL (review survives user deletion)
  - `reviews.room_id → rooms.id`: corrected to RESTRICT in `2026_03_23_000005` because `reviews.room_id` is NOT NULL in schema
- DB hardening test coverage (added `2026_03_17`):
  - `tests/Feature/Database/FkDeletePolicyTest.php` — 5 tests: RESTRICT blocks, SET NULL nullifies
  - `tests/Feature/Database/CheckConstraintTest.php` — 3 tests: max_guests boundary cases

## 4) Index Strategy (names that matter)

Bookings: availability and overlap

- `idx_bookings_availability` on `(room_id, status, check_in, check_out)`.
- `idx_bookings_active_overlap` on `(room_id, check_in, check_out)` partial (PostgreSQL only).
- `no_overlapping_bookings` (constraint, not index) is the hard overlap guard.

Bookings: user history and reports

- `idx_bookings_user_history` on `(user_id, created_at)`.
- `idx_bookings_status_period` on `(status, check_in)`.

Bookings: location analytics

- `idx_bookings_location_id` on `(location_id)`.
- `idx_bookings_location_dates` on `(location_id, check_in, check_out)`.
- `idx_bookings_location_status` on `(location_id, status)`.

Bookings: payment/refund/cancellation

- `idx_bookings_refund_status` on `(refund_status)`.
- `idx_bookings_payment_intent` on `(payment_intent_id)`.
- `idx_bookings_cancellation` on `(status, cancelled_at)`.
- `idx_bookings_deposit_status_check_in` on `(deposit_status, check_in)`.

Bookings: soft delete and audit

- `idx_bookings_deleted_at` on `(deleted_at)`.
- `idx_bookings_soft_delete_audit` on `(deleted_at, deleted_by)`.

Locations and rooms

- `idx_locations_active` on `(is_active)`.
- `idx_locations_city_district` on `(city, district)`.
- `idx_locations_coordinates` on `(latitude, longitude)` partial (PostgreSQL only).
- `idx_rooms_location_id` on `(location_id)`.
- `idx_rooms_location_status` on `(location_id, status)`.
- `idx_rooms_location_price` on `(location_id, price)`.
- `idx_rooms_status` on `(status)`.
- `idx_rooms_location_readiness` on `(location_id, readiness_status)`.
- `idx_rooms_readiness_status` on `(readiness_status)`.
- `idx_rooms_type_location` on `(room_type_code, location_id)`.
- `idx_rooms_tier_location` on `(room_tier, location_id)`.
- `idx_rooms_type_tier` on `(room_type_code, room_tier)`.
- `idx_rooms_location_room_number` unique partial on `(location_id, room_number)` where `room_number IS NOT NULL` (PostgreSQL only).

Service recovery financial tracking

- `idx_src_settlement_status_settled_at` on `(settlement_status, settled_at)`.

AI harness

- `policy_documents(slug, is_active, language)` composite index.
- `policy_documents(category, is_active)` composite index.
- `ai_proposal_events(proposal_hash)` index.
- `ai_proposal_events(proposal_hash, user_decision)` composite index.
- `ai_proposals(proposal_hash)` UNIQUE constraint (idempotency surface).
- `ai_proposals(user_id, created_at)` composite index.
- `ai_proposals(expires_at)` index (TTL sweeps).
- `ai_proposals(proposal_hash, decision)` composite index.

Admin audit & deposit lifecycle

- `admin_audit_logs(action)` index.
- `admin_audit_logs(resource_type, resource_id)` composite index.
- `admin_audit_logs(actor_id, created_at)` composite index.
- `deposit_events(booking_id)` index (`idx_deposit_events_booking_id`).
- `deposit_events(created_at)` index (`idx_deposit_events_created_at`).

Stripe ingestion

- `stripe_webhook_events(stripe_event_id)` UNIQUE — event-level dedupe.
- `stripe_refund_events(stripe_refund_id)` UNIQUE (`idx_stripe_refund_events_refund_id`) — durable replay fence.

Email verification

- `email_verification_codes(user_id)` index (`idx_evc_user_id`).
- `email_verification_codes(expires_at, consumed_at)` composite index (`idx_evc_expires_consumed`).

Reviews and tokens

- `reviews_booking_id_unique` unique constraint on `reviews(booking_id)`.
- Token hardening indexes exist for `token_hash`, `expires_at`, `revoked_at`, `device_id`, and `(tokenable_id, tokenable_type, type)`; names are Laravel-generated (not explicitly named in migrations).

Legacy index reconciliation (intentional, idempotent)

- `bookings_room_id_index`
- `bookings_user_id_index`
- `bookings_status_index`
- `bookings_room_id_check_in_check_out_index`
- `bookings_user_id_check_in_index`
- `bookings_status_check_out_index`
- `rooms_status_index`

## 5) Common Query Patterns (copy/paste ready)

Availability overlap check (active, non-deleted bookings only):

```sql
SELECT 1
FROM bookings
WHERE room_id = :room_id
  AND status IN ('pending', 'confirmed')
  AND deleted_at IS NULL
  AND check_in < :new_check_out
  AND check_out > :new_check_in
LIMIT 1;
```

Availability overlap check while updating an existing booking:

```sql
... AND id <> :booking_id_to_exclude
```

Revenue per location (pseudo SQL, date-bounded):

```sql
SELECT location_id, SUM(amount) AS gross_amount_cents
FROM bookings
WHERE location_id = :location_id
  AND check_in >= :from_date
  AND check_in < :to_date
  AND deleted_at IS NULL
GROUP BY location_id;
```

Bookings by location and date range (pseudo SQL):

```sql
SELECT *
FROM bookings
WHERE location_id = :location_id
  AND check_in < :range_end
  AND check_out > :range_start;
```

Refund reconciliation predicate:

```sql
SELECT *
FROM bookings
WHERE payment_intent_id IS NOT NULL
  AND (
    refund_status IN ('pending', 'succeeded', 'failed')
    OR status IN ('refund_pending', 'refund_failed', 'cancelled')
  );
```

## 6) Do / Dont for future migrations

Do:

- Use PostgreSQL exclusion constraints for overlap prevention.
- Keep overlap filters aligned across DB and app (`status IN ('pending','confirmed')` and `deleted_at IS NULL`).
- Keep multi-location denormalization consistent (`bookings.location_id` from `rooms.location_id`).
- Use idempotent/index-reconciliation patterns when production may already have legacy index order/state.

Dont:

- Do not use `UNIQUE(room_id, check_in, check_out)` as overlap protection.
- Do not rely on SQLite to enforce PostgreSQL-only invariants (exclusion constraints, PG triggers).
- FK `reviews.booking_id → bookings.id` exists (migration `2026_02_22_000002`, ON DELETE RESTRICT, pgsql-only). ON DELETE RESTRICT is intentional — bookings use soft-delete.

## Deprecation: rooms.status

`rooms.status` is a **DEPRECATED** legacy field. The canonical physical room state is `rooms.readiness_status` (added in migration `2026_03_23_000001`). Bookability is `Room::scopeBookable()`.

**Current state (post Phase 3 finalization, 2026-04-29 — `2026_04_29_000002`):**
- `rooms.status` remains in schema and in `Room.php` `$fillable`
- DB CHECK constraint `rooms_status_deprecated CHECK (status IN ('available', 'unavailable'))` is now **ENFORCED** in PostgreSQL — the column can no longer carry ad-hoc strings during the transition window
- `2026_04_29_000002` also backfills `readiness_status` from any rows where the legacy column was `available` (→ `ready`) or anything else (→ `out_of_service`)
- `Room::scopeBookable()` is the canonical bookability predicate (Batch 1); it consults `rooms.status='available'` but the constraint above guarantees the column carries one of the two valid values
- `rooms.readiness_status` is the canonical physical-state field with its own CHECK constraint and enum backing
- Both fields appear in `Room::$fillable` and are selected in queries

**Deprecation timeline:**
- **~~Phase 1 (deferred CHECK)~~** — superseded 2026-04-29.
- **Phase 2 (current):** `rooms.status` reads still allowed for legacy admin/sellable semantics with a `# DEPRECATED` comment. New code must use `readiness_status` for physical room state. Migrate remaining `rooms.status` reads to a dedicated sellable-status field.
- **Phase 3 (post-migration):** Drop `rooms.status` column via migration. Remove from `$fillable`, `@property`, and all scopes. Drop `rooms_status_deprecated` constraint.

**Enforcement:**
- DB CHECK `rooms_status_deprecated` blocks any value outside `{available, unavailable}` (pgsql)
- `scripts/verify-control-plane.sh` warns if backend files reference `rooms.status`
- New code referencing `rooms.status` without `# DEPRECATED` comment should be flagged in PR review

## AI Rules for DB-Related Changes

- Never use `UNIQUE(room_id, check_in, check_out)` as overlap prevention.
- In PostgreSQL, prefer `EXCLUDE USING gist` with `daterange(check_in, check_out, '[)')` and filter:
  - `status IN ('pending','confirmed') AND deleted_at IS NULL`
- If running SQLite tests, ensure application-level overlap checks and tests still enforce the invariant.
- Keep active-status definitions consistent across:
  - DB constraint/filter predicates
  - Query scopes
  - Service/business logic
- Any migration that changes indexes/constraints must be production-safe:
  - idempotent where possible
  - accompanied by a verification query or a related test update plan
- When in doubt, re-read migrations before editing docs or proposing schema changes.
