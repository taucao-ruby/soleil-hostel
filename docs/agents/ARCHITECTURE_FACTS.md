# Architecture Facts — Soleil Hostel

Domain invariants last verified 2026-03-17. See [AUDIT_2026_02_21.md](../AUDIT_2026_02_21.md) for the original 2026-02-21 audit evidence.

## Booking Domain

### Overlap Prevention (Two-Layer Defense)

**Layer 1 — Application (PHP):**
- Half-open interval: `[check_in, check_out)` — same-day turnover is valid
- Overlap query: `existing.check_in < new.check_out AND existing.check_out > new.check_in`
- Active statuses for overlap: `pending`, `confirmed` only
- `lockForUpdate()` in transaction for booking creation/cancellation
- Source: `CancellationService.php`, `Booking.php` model scope

**Layer 2 — Database (PostgreSQL):**

```sql
ALTER TABLE bookings
ADD CONSTRAINT no_overlapping_bookings
EXCLUDE USING gist (
    room_id WITH =,
    daterange(check_in, check_out, '[)') WITH &&
)
WHERE (status IN ('pending', 'confirmed') AND deleted_at IS NULL);
```

Source: `2026_02_12_000001_fix_overlapping_bookings_constraint_soft_deletes.php`

Requires: `CREATE EXTENSION IF NOT EXISTS btree_gist;`

### Soft Delete & Cancellation Audit

- `deleted_at` + `deleted_by` (FK to users, ON DELETE SET NULL)
- `cancelled_at` + `cancelled_by` + `cancellation_reason`
- Soft-deleted bookings do NOT block availability (constraint filters `deleted_at IS NULL`)

### Payment / Refund Columns

On `bookings` table: `amount`, `payment_intent_id`, `refund_id`, `refund_status`, `refund_amount`, `refund_error`.

### Booking Status

**VARCHAR column, NOT a PostgreSQL ENUM.** Values enforced at application level (`App\Enums\BookingStatus`) AND DB CHECK constraint `chk_bookings_status` on PostgreSQL (migration `2026_03_17_000003`):
- `pending`, `confirmed`, `refund_pending`, `cancelled`, `refund_failed`

## Concurrency Control

### Optimistic Locking
- `lock_version` column on `rooms` (NOT NULL, default 1) — source: migration `2025_12_18_200000`
- `lock_version` column on `locations` (default 1) — source: migration `2026_02_09_000001`
- Compare-and-swap semantics in `EloquentRoomRepository`, `RoomService`

### Pessimistic Locking
- `SELECT ... FOR UPDATE` via `lockForUpdate()` in booking/cancellation flows
- Source: `CancellationService.php:118,318`, `Booking.php:340`

## Authentication

### Dual Mode (Both Active)
- **Bearer Token**: Standard Sanctum `Authorization: Bearer <token>` header
- **HttpOnly Cookie**: Custom cookie-based auth with `token_identifier` → `token_hash` DB lookup

### Custom Token Columns (personal_access_tokens)

Added across two migrations (`2025_11_20_000100` + `2025_11_21_150000`):

| Column | Type | Purpose |
|--------|------|---------|
| `token_identifier` | UUID, unique | Cookie-based token lookup |
| `token_hash` | string, indexed | Hash of identifier for fast lookup |
| `device_id` | UUID, nullable, indexed | Per-device token binding |
| `device_fingerprint` | string, nullable | Anti-theft device binding |
| `expires_at` | timestamp, nullable | Token expiration |
| `revoked_at` | timestamp, nullable | Token revocation |
| `refresh_count` | integer, default 0 | Rotation tracking |
| `last_rotated_at` | timestamp, nullable | Last rotation timestamp |
| `type` | string, nullable | Token type classification |

### Auth Enforcement
- Middleware checks: expiry, revocation, refresh abuse
- Controllers: `HttpOnlyTokenController`, `UnifiedAuthController`, `AuthController`

## Multi-Location

- `rooms.location_id` — NOT NULL, FK to locations (RESTRICT on delete) — location deletion blocked if rooms exist
- `bookings.location_id` — nullable, FK to locations (SET NULL on delete) — denormalized for analytics
- PostgreSQL trigger `trg_booking_set_location`: auto-sets `bookings.location_id` from `rooms.location_id` on insert/update
- `locations.is_active` gates room/booking visibility

## Enums

### user_role_enum (PostgreSQL ENUM)

```sql
CREATE TYPE user_role_enum AS ENUM ('user', 'moderator', 'admin');
```

PHP: `App\Enums\UserRole` (backed string enum). Default: `user`.

### room_status (NOT a PostgreSQL ENUM)

**The rooms `status` column is VARCHAR** (`$table->string('status')` in migration `2025_05_09_000000`). No `CREATE TYPE room_status` exists in migrations despite some docs claiming otherwise.

Application-level values: `available`, `occupied`, `maintenance` (verify in model/service code).

## Reviews

- One review per booking: `reviews_booking_id_unique` constraint
- `booking_id` is NOT NULL (migration `2026_02_10_000002`)
- `approved` column defaults to `false`
- FK `reviews.booking_id → bookings.id` added via migration (F-09 fix, PR-3 2026-02-21)

## Key Indexes

See [DB_FACTS.md](../DB_FACTS.md) Section 4 for complete index listing.

Critical indexes:
- `idx_bookings_availability` on `(room_id, status, check_in, check_out)`
- `idx_bookings_active_overlap` partial on `(room_id, check_in, check_out)` WHERE active statuses (PG only)
- `idx_bookings_deleted_at`, `idx_bookings_soft_delete_audit`
- Token indexes: `token_hash`, `device_id`, `expires_at`, `revoked_at` (Laravel-generated names)

## Admin Audit Log

- `admin_audit_logs` table (append-only): `actor_id`, `action`, `resource_type`, `resource_id`, `ip_address`, `metadata` (JSON)
- Written by `AdminAuditService`; integrated into `AdminBookingController`, `RoomController`, `ReviewController` (IMPLEMENTED)
- Force-delete records pre-deletion snapshot in `metadata` for forensic recovery
- Source: RBAC Phase 2, migration `2026_03_12_000001`

## Admin Resources

- Customer management: `GET /api/v1/admin/customers/*` (index, show, update, destroy) — gated by `role:moderator` middleware — `App\Http\Controllers\Admin\CustomerController`

## RBAC Permission Baseline

Canonical permission matrix: [docs/PERMISSION_MATRIX.md](../PERMISSION_MATRIX.md)

Current enforcement status: PASS WITH FOLLOW-UPS. Room CUD and admin booking endpoints use defense-in-depth (route middleware + controller-level gate/policy). Moderator role is ACTIVE: gates admin booking READ routes (`role:moderator` middleware, v1.php) and customer management endpoints (`/api/v1/admin/customers/*`). Open follow-ups: 5 — see PERMISSION_MATRIX.md.

## DB Constraints Added (formerly backlog)

All four previously absent constraints added via migrations (audit v2, PR-2 + PR-3, 2026-02-21):
- `CHECK (check_out > check_in)` on bookings — **added** (F-06 fixed)
- `CHECK (rating BETWEEN 1 AND 5)` on reviews — **added** (F-07 fixed)
- `CHECK (price >= 0)` on rooms — **added** (F-08 fixed)
- FK `reviews.booking_id -> bookings.id` — **added** (F-09 fixed)

## DB Hardening (2026-03-17)

FK delete policy hardening (migration `2026_03_17_000001`, PG-only, runtime-gated via `DB::getDriverName()`):
- `bookings.user_id → users.id`: CASCADE → **SET NULL** (booking history survives user deletion)
- `bookings.room_id → rooms.id`: CASCADE → **RESTRICT** (room deletion blocked if bookings exist)
- `reviews.user_id → users.id`: CASCADE → **SET NULL** (review survives user deletion)
- `reviews.room_id → rooms.id`: CASCADE → **SET NULL** (review survives room deletion)

Correction on record: prior operator baseline incorrectly claimed `reviews.user_id` was already SET NULL. Source `2026_02_09_000000_add_foreign_key_constraints.php` confirms original was `onDelete('cascade')`.

Additional CHECK constraints (PG-only, runtime-gated):
- `chk_rooms_max_guests CHECK (max_guests > 0)` — migration `2026_03_17_000002`
- `chk_bookings_status CHECK (status IN ('pending','confirmed','refund_pending','cancelled','refund_failed'))` — migration `2026_03_17_000003`

Deferred:
- `rooms.status` DB CHECK — room status values inconsistent across codebase; deferred pending normalization + stable `RoomStatus` enum
- Legacy migration `2026_02_09_000000` uses `config('database.default')` gating (weaker than `DB::getDriverName()`); cleanup deferred

Test coverage: `FkDeletePolicyTest.php` (5 tests), `CheckConstraintTest.php` (3 tests — covers `chk_rooms_max_guests` only; `chk_bookings_status` has no dedicated constraint test). Backend suite: 954 tests, 0 failures.

