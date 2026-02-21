# Copilot Instructions for Soleil Hostel

## Read First (before suggesting DB changes)
- `docs/DB_FACTS.md` (canonical DB invariants and constraints)
- `docs/DATABASE.md` (detailed schema/index reference, if present)

## Database Rules Copilot Must Follow
- Booking overlap logic must use half-open interval: `[check_in, check_out)`.
- PostgreSQL overlap guard must remain:
  - Constraint: `no_overlapping_bookings`
  - Filter: `status IN ('pending','confirmed') AND deleted_at IS NULL`
- Soft-deleted bookings must not block availability checks.
- Reviews must require `booking_id`, and there must be one review per booking (`reviews_booking_id_unique`).
- Multi-location model:
  - `rooms.location_id` is `NOT NULL` and is canonical truth.
  - `bookings.location_id` is denormalized (analytics/history).
- Optimistic locking uses `lock_version` on `rooms` and `locations`.
- Token hardening for cookie auth must preserve:
  - `token_identifier` (cookie identifier) + `token_hash` lookup
  - revocation/expiry checks (`revoked_at`, `expires_at`)
  - device binding/rotation fields (`device_id`, `device_fingerprint`, `refresh_count`, `last_rotated_at`, `type`)

## Validation Commands (for DB-related changes)
- `cd backend && php artisan test`
- `cd frontend && npx tsc --noEmit && npx vitest run`
- `docker compose config` (if Docker/config/migration wiring is touched)

## When Uncertain
- Search migrations first; do not assume schema from memory.
- Prefer migrations as source-of-truth over potentially outdated docs.
