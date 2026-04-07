# DB Investigator — Subagent Memory

Role-scoped memory for schema, constraints, indexes, FK behavior, query paths, N+1.

## Stable Memory

### PostgreSQL-Specific Constraints
- `no_overlapping_bookings` EXCLUDE USING gist requires `btree_gist` extension — does not exist in SQLite
- Migration: `2026_02_12_000001_fix_overlapping_bookings_constraint_soft_deletes.php`
- Constraint WHERE clause: `status IN ('pending', 'confirmed') AND deleted_at IS NULL`
- `trg_booking_set_location` trigger sets `bookings.location_id` from `rooms.location_id` — app code must not set it directly

### Optimistic Locking Columns
- `rooms.lock_version` — NOT NULL, default 1 (migration `2025_12_18_200000`)
- `locations.lock_version` — default 1 (migration `2026_02_09_000001`)
- Compare-and-swap in `EloquentRoomRepository`, `RoomService`

### Key Indexes (from DB_FACTS.md)
- `idx_bookings_availability` — availability query path
- `idx_bookings_active_overlap` — overlap check path
- Source: `docs/DB_FACTS.md` § Index Strategy

### Token Table Schema
- `personal_access_tokens`: `token_identifier` (UUID, unique), `token_hash` (indexed), `device_id` (UUID, indexed), `expires_at`, `revoked_at`, `last_rotated_at`, `refresh_count`, `type` (default `'short_lived'`)
- Migrations: `2025_11_20_000100`, `2025_11_21_150000`

### Deprecated Column
- `rooms.status` is DEPRECATED → use `rooms.readiness_status`
- Source: `docs/DB_FACTS.md` § Deprecation

## Learned Patterns

- Schema presence does NOT prove application-layer concurrency correctness — `lockForUpdate()` call-site correctness is security-reviewer scope
- Soft-delete restore can reintroduce overlap if overlap re-check is not transactional
- Migration `down()` methods must be real rollbacks, not empty — see `.agent/rules/migration-safety.md`
- PG-only features (EXCLUDE, triggers, ENUM) must be guarded: `DB::getDriverName() === 'pgsql'`

## Revalidation Notes

- After new migration touching `bookings`: verify `no_overlapping_bookings` constraint still matches ARCHITECTURE_FACTS.md spec
- After adding new indexes: update DB_FACTS.md § Index Strategy
- After `rooms.status` column is dropped: remove deprecation entry above
