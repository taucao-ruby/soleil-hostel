# Global Invariants — Soleil Hostel

Cross-domain truths no agent may violate. Verified against source on 2026-04-07.

## Stable Memory

### Booking Overlap Prevention (two-layer defense)
- **Interval semantics**: half-open `[check_in, check_out)` — same-day turnover is valid
  - App-layer formula: `existing.check_in < new.check_out AND existing.check_out > new.check_in`
  - Source: `docs/agents/ARCHITECTURE_FACTS.md` § Booking Domain > Overlap Prevention
- **Active statuses**: only `pending` and `confirmed` participate in overlap checks
  - Source: `ARCHITECTURE_FACTS.md` + PostgreSQL constraint WHERE clause
- **Soft-delete filter**: `deleted_at IS NULL` — soft-deleted bookings do NOT block availability
  - Source: constraint definition in migration `2026_02_12_000001_fix_overlapping_bookings_constraint_soft_deletes.php`
- **PostgreSQL constraint**: `no_overlapping_bookings` EXCLUDE USING gist with `daterange(check_in, check_out, '[)')`, requires `btree_gist` extension
  - Role: last-resort DB guard; does NOT replace app-layer validation
- **App-layer locking**: `lockForUpdate()` inside `DB::transaction` on booking write paths
  - Confirmed at: `CancellationService.php:115–120` (transitionToRefundPending)
  - Scope method: `Booking.php:376` (scopeWithLock)

### Denormalization — `bookings.location_id` (three-layer defense)

`rooms.location_id` is canonical truth; `bookings.location_id` is a denormalized snapshot for analytics/history. The invariant is **`bookings.location_id` MUST equal `rooms.location_id` of the booked room** (not "app code must not set it").

PostgreSQL production path (root of trust):
1. **App-set** — `CreateBookingService::createBookingWithLocking` sets `'location_id' => $room->location_id` explicitly so the app path is self-sufficient (`backend/app/Services/CreateBookingService.php:337-348`).
2. **Observer** — `BookingObserver::creating` fills `location_id` from `room->location_id` when unset; `BookingObserver::updating` always restamps when `room_id` is dirty (`backend/app/Observers/BookingObserver.php`).
3. **PG trigger** — `trg_booking_set_location` (function `set_booking_location()`) fires `BEFORE INSERT OR UPDATE OF room_id` and unconditionally assigns `NEW.location_id := (SELECT location_id FROM rooms WHERE id = NEW.room_id)`. Auto-repair, not validation — silently overrides any app/observer value. Migration: `backend/database/migrations/2026_02_09_000006_add_booking_location_trigger.php`.

SQLite path (compensating control only):
- The PG trigger migration is guarded by `DB::getDriverName() === 'pgsql'` and is **absent on SQLite**. There is no SQLite-side trigger parity.
- The Observer is the only driver-independent layer. It auto-populates when missing and restamps on `room_id` change, but **does not police app-supplied wrong values on `creating`** (it only fills when `! $booking->location_id`).
- The default test harness (`backend/phpunit.xml`) runs on PostgreSQL, so the standard `php artisan test` exercises all three layers. SQLite usage is dev/opt-in only.
- Any claim of "raw-SQL drift cannot happen" applies to PostgreSQL only. On SQLite, raw `DB::table('bookings')->insert([...wrong location_id...])` bypasses the Observer and is not corrected by any DB trigger.

Rule:
- Application code MAY set `bookings.location_id` and SHOULD set it from `$room->location_id` (the app-set layer is intentional and tested).
- Production claims of database-level drift prevention require PostgreSQL.
- Booking-integrity invariants must be enforced at the lowest available layer; Observer-only paths are guards, not root of trust.

Known residual gap (not in scope for BL-5):
- The PG trigger fires on `INSERT OR UPDATE OF room_id` only. A raw `UPDATE bookings SET location_id = X WHERE ...` that does not touch `room_id` is not intercepted by the trigger (and the Observer's `updating` likewise only restamps when `room_id` is dirty). No app path performs such a write today; tracked here for future hardening.

Sources: `ARCHITECTURE_FACTS.md` § Multi-Location; `DB_FACTS.md` § Invariants > Multi-location truth; migration `2026_02_09_000006`; service/observer files cited above.

### Optimistic Locking
- `lock_version` column exists on `rooms` (NOT NULL, default 1) and `locations` (default 1)
- Compare-and-swap pattern in `EloquentRoomRepository` and `RoomService`
  - Source: `ARCHITECTURE_FACTS.md` § Concurrency Control

### RBAC Authority Model
- Backend is RBAC authority (middleware, policies, gates); frontend is UX only
- Role enum: `user` (1), `moderator` (2), `admin` (3) — `isAtLeast()` uses static levels
- `docs/PERMISSION_MATRIX.md` is the single source of truth for permissions
  - Source: `.agent/rules/backend-preserve-rbac-source-and-request-validation.md`

### Auth Token Chain
- Dual-mode: Bearer token + HttpOnly cookie
- Cookie lookup: `token_identifier` (UUID) → `token_hash` comparison (never raw token)
- Validity: `revoked_at IS NULL` AND `expires_at` check in middleware
- CSRF: `sessionStorage` → `X-XSRF-TOKEN` header + `withCredentials: true`
  - Source: `.agent/rules/auth-token-safety.md`, `ARCHITECTURE_FACTS.md` § Authentication

### Booking Status
- VARCHAR column (not ENUM), values enforced by app + DB CHECK constraint `chk_bookings_status`
- Values: `pending`, `confirmed`, `refund_pending`, `cancelled`, `refund_failed`
  - Source: `ARCHITECTURE_FACTS.md` § Booking Domain

## Learned Patterns

- Overlap checks MUST run inside the same transaction as the write, under lock — separating them creates TOCTOU
- `finalizeCancellation()` in CancellationService has a known race condition (F-33 in FINDINGS_BACKLOG.md) — no `lockForUpdate()` on the booking between refund success and status update
- Schema presence of constraints does NOT prove application-layer concurrency correctness

## Revalidation Notes

- After any change to `CancellationService`, `BookingService`, or overlap-related migrations: re-verify locking coverage
- After any change to `UserRole` enum or `isAtLeast()`: re-verify PERMISSION_MATRIX.md alignment
- After any auth middleware change: re-verify `token_identifier → token_hash` chain and `revoked_at`/`expires_at` checks
