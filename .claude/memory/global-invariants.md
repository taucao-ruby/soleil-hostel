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

### Denormalization
- `bookings.location_id` is set by PostgreSQL trigger `trg_booking_set_location` from `rooms.location_id`
- Application code must NOT set `bookings.location_id` directly
- `rooms.location_id` is canonical truth; `bookings.location_id` is denormalized for analytics/history
  - Source: `ARCHITECTURE_FACTS.md` § Multi-location model

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
