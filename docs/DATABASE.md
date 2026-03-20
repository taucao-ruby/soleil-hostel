# 🗄️ Database Schema & Indexes

> Complete database design for Soleil Hostel (38 migrations, 16 tables)

## ER Diagram

```
┌────────────────────┐
│     locations      │
├────────────────────┤
│ id (PK)            │
│ name (UNIQUE)      │
│ slug (UNIQUE)      │
│ address            │
│ city, district     │
│ latitude/longitude │
│ amenities (JSONB)  │
│ images (JSONB)     │
│ is_active          │
│ total_rooms        │
│ lock_version       │
│ created_at         │
│ updated_at         │
└────────────────────┘
          │
          │ 1:N
          ▼
┌────────────────────┐       ┌────────────────────┐       ┌────────────────────┐
│       users        │       │       rooms        │       │      reviews       │
├────────────────────┤       ├────────────────────┤       ├────────────────────┤
│ id (PK)            │       │ id (PK)            │       │ id (PK)            │
│ name               │       │ location_id (FK)   │       │ booking_id (FK)    │
│ email (UNIQUE)     │       │ name               │       │ room_id (FK)       │
│ password           │       │ room_number        │       │ user_id (FK)       │
│ role (ENUM)        │       │ description        │       │ title              │
│ email_verified_at  │       │ price              │       │ content            │
│ remember_token     │       │ max_guests         │       │ guest_name         │
│ created_at         │       │ status             │       │ rating (1-5)       │
│ updated_at         │       │ lock_version       │◄──    │ approved           │
└────────────────────┘       │ created_at         │       │ created_at         │
          │                  │ updated_at         │       └────────────────────┘
          │ 1:N              └────────────────────┘                │
          ▼                           │ 1:N                        │
┌─────────────────────────────────────────────────┐               │
│                   bookings                       │◄──────────────┘
├─────────────────────────────────────────────────┤
│ id (PK)                                          │
│ user_id (FK → users)                             │
│ room_id (FK → rooms)                             │
│ location_id (FK → locations)  ◄── Denormalized   │
│ guest_name, guest_email                          │
│ check_in (DATE), check_out (DATE)                │
│ status (pending/confirmed/cancelled)             │
│ cancellation_reason          ◄── Cancellation    │
│ deleted_at, deleted_by       ◄── Soft delete     │
│ created_at, updated_at                           │
└─────────────────────────────────────────────────┘

┌────────────────────────────┐
│     contact_messages       │ ◄── Contact form submissions
├────────────────────────────┤
│ id (PK)                    │
│ name, email                │
│ subject (nullable)         │
│ message                    │
│ read_at                    │
│ created_at, updated_at     │
└────────────────────────────┘

┌─────────────────────────────────────────────────┐
│            personal_access_tokens                │ ◄── Sanctum + Custom
├─────────────────────────────────────────────────┤
│ id, tokenable_type, tokenable_id                 │
│ name, token (UNIQUE), abilities                  │
│ token_identifier, token_hash   ◄── HttpOnly      │
│ expires_at, revoked_at         ◄── Expiration    │
│ type (short_lived/long_lived)                    │
│ device_id, device_fingerprint  ◄── Device bind   │
│ refresh_count, last_rotated_at ◄── Rotation      │
│ last_used_at, created_at, updated_at             │
└─────────────────────────────────────────────────┘
```

---

## Tables

### users

| Column         | Type           | Constraints      |
| -------------- | -------------- | ---------------- |
| id             | BIGSERIAL      | PRIMARY KEY      |
| name           | VARCHAR(255)   | NOT NULL         |
| email          | VARCHAR(255)   | NOT NULL, UNIQUE |
| password       | VARCHAR(255)   | NOT NULL         |
| role           | user_role ENUM | DEFAULT 'user'   |
| remember_token | VARCHAR(100)   | NULLABLE         |
| created_at     | TIMESTAMP      |                  |
| updated_at     | TIMESTAMP      |                  |

**ENUM: user_role**

```sql
CREATE TYPE user_role AS ENUM ('user', 'moderator', 'admin');
```

### locations

| Column       | Type             | Constraints         |
| ------------ | ---------------- | ------------------- |
| id           | BIGSERIAL        | PRIMARY KEY         |
| name         | VARCHAR(255)     | NOT NULL, UNIQUE    |
| slug         | VARCHAR(255)     | NOT NULL, UNIQUE    |
| address      | TEXT             | NOT NULL            |
| city         | VARCHAR(100)     | NOT NULL            |
| district     | VARCHAR(100)     | NULLABLE            |
| ward         | VARCHAR(100)     | NULLABLE            |
| postal_code  | VARCHAR(20)      | NULLABLE            |
| latitude     | DECIMAL(10,8)    | NULLABLE            |
| longitude    | DECIMAL(11,8)    | NULLABLE            |
| phone        | VARCHAR(20)      | NULLABLE            |
| email        | VARCHAR(255)     | NULLABLE            |
| description  | TEXT             | NULLABLE            |
| amenities    | JSONB            | NULLABLE            |
| images       | JSONB            | NULLABLE            |
| is_active    | BOOLEAN          | DEFAULT TRUE        |
| total_rooms  | INTEGER UNSIGNED | DEFAULT 0           |
| lock_version | BIGINT UNSIGNED  | NOT NULL, DEFAULT 1 |
| created_at   | TIMESTAMP        |                     |
| updated_at   | TIMESTAMP        |                     |

### rooms

| Column       | Type            | Constraints              |
| ------------ | --------------- | ------------------------ |
| id           | BIGSERIAL       | PRIMARY KEY              |
| location_id  | BIGINT          | NOT NULL, FK → locations |
| name         | VARCHAR(255)    | NOT NULL                 |
| room_number  | VARCHAR(50)     | NULLABLE                 |
| description  | TEXT            | NULLABLE                 |
| price        | DECIMAL(10,2)   | NOT NULL                 |
| max_guests   | INTEGER         | NOT NULL                 |
| status       | VARCHAR         | DEFAULT 'available'      |
| lock_version | BIGINT UNSIGNED | NOT NULL, DEFAULT 1      |
| created_at   | TIMESTAMP       |                          |
| updated_at   | TIMESTAMP       |                          |

**room_status: VARCHAR** (intentional — not a PostgreSQL ENUM).
Allowed values enforced at application layer (`App\Models\Room`, `App\Enums\RoomStatus` if present):
`available`, `occupied`, `maintenance`.
Using VARCHAR instead of a DB ENUM allows adding new statuses without a schema migration.

### bookings

| Column              | Type         | Constraints                         |
| ------------------- | ------------ | ----------------------------------- |
| id                  | BIGSERIAL    | PRIMARY KEY                         |
| user_id             | BIGINT       | FK → users(id), NULLABLE            |
| room_id             | BIGINT       | FK → rooms(id)                      |
| location_id         | BIGINT       | FK → locations(id), NULLABLE        |
| guest_name          | VARCHAR(255) | NOT NULL                            |
| guest_email         | VARCHAR(255) | NOT NULL                            |
| check_in            | DATE         | NOT NULL                            |
| check_out           | DATE         | NOT NULL                            |
| status              | VARCHAR      | DEFAULT 'pending'                   |
| amount              | BIGINT       | NULLABLE (cents)                    |
| payment_intent_id   | VARCHAR(255) | NULLABLE (Stripe PaymentIntent ID)  |
| refund_id           | VARCHAR(255) | NULLABLE (Stripe Refund ID)         |
| refund_status       | VARCHAR      | NULLABLE (pending/succeeded/failed) |
| refund_amount       | BIGINT       | NULLABLE (cents)                    |
| refund_error        | TEXT         | NULLABLE                            |
| cancelled_at        | TIMESTAMP    | NULLABLE                            |
| cancelled_by        | BIGINT       | NULLABLE, FK → users(id)            |
| cancellation_reason | TEXT         | NULLABLE                            |
| deleted_at          | TIMESTAMP    | NULLABLE (soft delete)              |
| deleted_by          | BIGINT       | NULLABLE, FK → users(id)            |
| created_at          | TIMESTAMP    |                                     |
| updated_at          | TIMESTAMP    |                                     |

**booking_status: VARCHAR** (intentional — not a PostgreSQL ENUM).
VARCHAR chosen over DB ENUM for migration flexibility (adding new statuses requires no schema change).
Allowed values (from `App\Enums\BookingStatus`):

```
pending         → Initial state, awaiting payment/confirmation
confirmed       → Payment received, booking active
refund_pending  → Cancellation initiated, refund processing
cancelled       → Terminal state, refund completed or not required
refund_failed   → Refund failed, awaiting retry or manual intervention
```

State transitions (see `App\Enums\BookingStatus::canTransitionTo()`):

- PENDING → CONFIRMED, REFUND_PENDING, CANCELLED
- CONFIRMED → REFUND_PENDING, CANCELLED
- REFUND_PENDING → CANCELLED, REFUND_FAILED
- CANCELLED → (terminal)
- REFUND_FAILED → REFUND_PENDING (retry), CANCELLED

Enforcement: application layer (`App\Enums\BookingStatus`) + DB CHECK constraint `chk_bookings_status` on PostgreSQL (migration `2026_03_17_000003`).

### reviews

| Column      | Type         | Constraints                     |
| ----------- | ------------ | ------------------------------- |
| id          | BIGSERIAL    | PRIMARY KEY                     |
| booking_id  | BIGINT       | NOT NULL, UNIQUE, FK → bookings |
| room_id     | BIGINT       | NOT NULL, INDEX                 |
| user_id     | BIGINT       | NULLABLE, INDEX                 |
| title       | VARCHAR(255) | NOT NULL (purified)             |
| content     | TEXT         | NOT NULL (purified HTML)        |
| guest_name  | VARCHAR(255) | NOT NULL (purified)             |
| guest_email | VARCHAR(255) | NULLABLE                        |
| rating      | TINYINT      | NOT NULL, CHECK (1-5)           |
| approved    | BOOLEAN      | DEFAULT FALSE                   |
| created_at  | TIMESTAMP    |                                 |
| updated_at  | TIMESTAMP    |                                 |

### personal_access_tokens

| Column             | Type         | Constraints            |
| ------------------ | ------------ | ---------------------- |
| id                 | BIGSERIAL    | PRIMARY KEY            |
| tokenable_type     | VARCHAR(255) | NOT NULL (polymorphic) |
| tokenable_id       | BIGINT       | NOT NULL (polymorphic) |
| name               | VARCHAR(255) | NOT NULL               |
| token              | VARCHAR(64)  | NOT NULL, UNIQUE       |
| token_identifier   | UUID         | NULLABLE, UNIQUE       |
| token_hash         | VARCHAR(255) | NULLABLE, INDEX        |
| abilities          | TEXT         | NULLABLE (JSON)        |
| type               | VARCHAR      | DEFAULT 'short_lived'  |
| device_id          | UUID         | NULLABLE, INDEX        |
| device_fingerprint | VARCHAR(255) | NULLABLE               |
| expires_at         | TIMESTAMP    | NULLABLE, INDEX        |
| revoked_at         | TIMESTAMP    | NULLABLE, INDEX        |
| refresh_count      | INTEGER      | DEFAULT 0              |
| last_used_at       | TIMESTAMP    | NULLABLE               |
| last_rotated_at    | TIMESTAMP    | NULLABLE               |
| created_at         | TIMESTAMP    |                        |
| updated_at         | TIMESTAMP    |                        |

### sessions

| Column        | Type         | Constraints     |
| ------------- | ------------ | --------------- |
| id            | VARCHAR(255) | PRIMARY KEY     |
| user_id       | BIGINT       | NULLABLE, INDEX |
| ip_address    | VARCHAR(45)  | NULLABLE        |
| user_agent    | TEXT         | NULLABLE        |
| payload       | TEXT         | NOT NULL        |
| last_activity | INTEGER      | NOT NULL, INDEX |

### password_reset_tokens

| Column     | Type         | Constraints |
| ---------- | ------------ | ----------- |
| email      | VARCHAR(255) | PRIMARY KEY |
| token      | VARCHAR(255) | NOT NULL    |
| created_at | TIMESTAMP    | NULLABLE    |

### cache / cache_locks

| Column     | Type    | Constraints |
| ---------- | ------- | ----------- |
| key        | VARCHAR | PRIMARY KEY |
| value      | TEXT    | NOT NULL    |
| expiration | INTEGER | NOT NULL    |

### contact_messages

| Column     | Type         | Constraints |
| ---------- | ------------ | ----------- |
| id         | BIGSERIAL    | PRIMARY KEY |
| name       | VARCHAR(255) | NOT NULL    |
| email      | VARCHAR(255) | NOT NULL    |
| subject    | VARCHAR(255) | NULLABLE    |
| message    | TEXT         | NOT NULL    |
| read_at    | TIMESTAMP    | NULLABLE    |
| created_at | TIMESTAMP    |             |
| updated_at | TIMESTAMP    |             |

**Indexes:** `email`, `read_at`, `created_at`

### jobs / job_batches / failed_jobs

Queue tables for background job processing. See Laravel Queue documentation.

---

## Indexes

### Booking Indexes (Optimized for Availability)

```sql
-- Primary availability query (composite)
CREATE INDEX idx_bookings_availability
ON bookings (room_id, status, check_in, check_out);

-- User booking history
CREATE INDEX idx_bookings_user_history
ON bookings (user_id, created_at DESC);

-- Status-based queries
CREATE INDEX idx_bookings_status_period
ON bookings (status, check_in);

-- Soft delete queries
CREATE INDEX idx_bookings_deleted_at
ON bookings (deleted_at);

-- Audit trail
CREATE INDEX idx_bookings_soft_delete_audit
ON bookings (deleted_at, deleted_by);

-- Pessimistic locking (SELECT FOR UPDATE)
CREATE INDEX idx_room_active_bookings
ON bookings (room_id, status);

CREATE INDEX idx_room_dates_overlap
ON bookings (room_id, check_in, check_out);

CREATE INDEX idx_check_in ON bookings (check_in);
CREATE INDEX idx_check_out ON bookings (check_out);

-- Reconciliation indexes (idempotent, added if missing)
CREATE INDEX bookings_room_id_index ON bookings (room_id);
CREATE INDEX bookings_user_id_index ON bookings (user_id);
CREATE INDEX bookings_status_index ON bookings (status);
CREATE INDEX bookings_room_id_check_in_check_out_index ON bookings (room_id, check_in, check_out);
CREATE INDEX bookings_user_id_check_in_index ON bookings (user_id, check_in);
CREATE INDEX bookings_status_check_out_index ON bookings (status, check_out);
```

### Room Indexes

```sql
-- Status filter
CREATE INDEX idx_rooms_status ON rooms (status);

-- Price range
CREATE INDEX idx_rooms_price ON rooms (price);
```

### User Indexes

```sql
-- Email lookup (already UNIQUE)
-- Role filter
CREATE INDEX idx_users_role ON users (role);
```

### Review Indexes

```sql
-- Room reviews lookup
CREATE INDEX idx_reviews_room_id ON reviews (room_id);

-- User reviews
CREATE INDEX idx_reviews_user_id ON reviews (user_id);
```

### Contact Message Indexes

```sql
CREATE INDEX idx_contact_messages_email ON contact_messages (email);
CREATE INDEX idx_contact_messages_read_at ON contact_messages (read_at);
CREATE INDEX idx_contact_messages_created_at ON contact_messages (created_at);
```

### Token Indexes (HttpOnly Cookie Support)

```sql
-- Token identifier for cookie lookup
CREATE UNIQUE INDEX idx_pat_token_identifier
ON personal_access_tokens (token_identifier);

-- Token hash for validation
CREATE INDEX idx_pat_token_hash
ON personal_access_tokens (token_hash);

-- Device session lookup
CREATE INDEX idx_pat_device_id
ON personal_access_tokens (device_id);

-- Expired tokens cleanup
CREATE INDEX idx_pat_expires_at
ON personal_access_tokens (expires_at);

-- Revoked tokens cleanup
CREATE INDEX idx_pat_revoked_at
ON personal_access_tokens (revoked_at);
```

---

## Constraints

### Foreign Keys

```sql
ALTER TABLE bookings
ADD CONSTRAINT fk_bookings_user
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;
-- Changed from CASCADE → SET NULL in migration 2026_03_17_000001
-- Rationale: booking history must survive user deletion (financial audit)

ALTER TABLE bookings
ADD CONSTRAINT fk_bookings_room
FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE RESTRICT;
-- Changed from CASCADE → RESTRICT in migration 2026_03_17_000001
-- Rationale: room deletion blocked if bookings exist (prevents data loss)

ALTER TABLE bookings
ADD CONSTRAINT fk_bookings_deleted_by
FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE bookings
ADD CONSTRAINT fk_bookings_location
FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL;

ALTER TABLE bookings
ADD CONSTRAINT fk_bookings_cancelled_by
FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE rooms
ADD CONSTRAINT fk_rooms_location
FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE RESTRICT;
-- Already RESTRICT in migration 2026_02_09_000002

ALTER TABLE reviews
ADD CONSTRAINT fk_reviews_room
FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL;
-- Changed from CASCADE → SET NULL in migration 2026_03_17_000001
-- Rationale: review survives room deletion

ALTER TABLE reviews
ADD CONSTRAINT fk_reviews_user
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;
-- Changed from CASCADE → SET NULL in migration 2026_03_17_000001
-- Rationale: review survives user deletion

ALTER TABLE reviews
ADD CONSTRAINT fk_reviews_booking_id
FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE RESTRICT;
-- RESTRICT: bookings use soft-delete; CASCADE would destroy reviews.
-- Added in migration 2026_02_22_000002. Skipped on SQLite (tests).
```

### Check Constraints

Enforced at DB layer (PostgreSQL). App-layer validation also exists (see `StoreBookingRequest`, `UpdateBookingRequest`, `StoreReviewRequest`, `StoreRoomRequest`).
Added in migration `2026_02_22_000001_add_check_constraints_bookings_reviews_rooms.php`. Skipped on SQLite (tests).

```sql
-- Check-out must be after check-in
ALTER TABLE bookings
ADD CONSTRAINT chk_bookings_dates
CHECK (check_out > check_in);

-- Price must be non-negative
ALTER TABLE rooms
ADD CONSTRAINT chk_rooms_price
CHECK (price >= 0);

-- Rating must be 1-5
ALTER TABLE reviews
ADD CONSTRAINT chk_reviews_rating
CHECK (rating >= 1 AND rating <= 5);

-- Max guests must be positive (added 2026-03-17)
ALTER TABLE rooms
ADD CONSTRAINT chk_rooms_max_guests
CHECK (max_guests > 0);
-- Added in migration 2026_03_17_000002. PostgreSQL only.

-- Booking status must be a known value (added 2026-03-17)
ALTER TABLE bookings
ADD CONSTRAINT chk_bookings_status
CHECK (status IN ('pending', 'confirmed', 'refund_pending', 'cancelled', 'refund_failed'));
-- Added in migration 2026_03_17_000003. PostgreSQL only.
-- Values match App\Enums\BookingStatus. Adding a new status requires a migration.
```

> **Note:** `rooms.status` DB-level CHECK is **not present**. Room status values are inconsistent across application code (`available`, `occupied`, `maintenance`, `booked`, `active`). Enforcement deferred pending normalization and a stable `RoomStatus` enum.

---

## Half-Open Interval Logic

For booking overlap detection, we use **half-open intervals** `[check_in, check_out)`:

```sql
-- Two bookings overlap if:
-- booking1.check_in < booking2.check_out AND booking1.check_out > booking2.check_in

-- This query finds overlapping bookings:
SELECT * FROM bookings
WHERE room_id = :room_id
  AND status IN ('pending', 'confirmed')
  AND check_in < :new_check_out
  AND check_out > :new_check_in;
```

**Benefit:** Same-day check-out and check-in are allowed:

- Guest A: Jan 1-5 (checks out Jan 5)
- Guest B: Jan 5-10 (checks in Jan 5) ✅ No conflict

---

## PostgreSQL Exclusion Constraint

Database-level overlap prevention (active in production):

```sql
CREATE EXTENSION IF NOT EXISTS btree_gist;

ALTER TABLE bookings
ADD CONSTRAINT no_overlapping_bookings
EXCLUDE USING gist (
    room_id WITH =,
    daterange(check_in, check_out, '[)') WITH &&
) WHERE (status IN ('pending', 'confirmed') AND deleted_at IS NULL);
```

**Note:** The `deleted_at IS NULL` clause was added in migration `2026_02_12` to prevent false conflicts from soft-deleted bookings.

---

## Migrations

### Migration History (38 files)

| Migration                                                 | Description                                       |
| --------------------------------------------------------- | ------------------------------------------------- |
| `0001_01_01_000000_create_users_table`                    | users, sessions, password_reset                   |
| `0001_01_01_000001_create_cache_table`                    | cache, cache_locks                                |
| `0001_01_01_000002_create_jobs_table`                     | jobs, job_batches, failed_jobs                    |
| `2025_05_08_create_personal_access_tokens_table`          | Sanctum base tokens                               |
| `2025_05_09_000000_create_rooms_table`                    | rooms base                                        |
| `2025_05_09_create_bookings_table`                        | bookings base                                     |
| `2025_11_18_000000_add_user_id_to_bookings`               | user_id FK + indexes                              |
| `2025_11_18_000001_add_is_admin_to_users`                 | is_admin (deprecated)                             |
| `2025_11_18_000002_add_booking_constraints`               | unique_room_dates constraint                      |
| `2025_11_20_000100_add_token_expiration`                  | revoked_at, type, device_id                       |
| `2025_11_20_100000_add_pessimistic_locking_indexes`       | idx_room_active, idx_room_dates                   |
| `2025_11_21_add_token_security_columns`                   | token_identifier, token_hash                      |
| `2025_11_24_create_reviews_table`                         | reviews table                                     |
| `2025_12_05_add_nplusone_fix_indexes`                     | N+1 prevention indexes                            |
| `2025_12_17_convert_role_to_enum`                         | ENUM user_role, drop is_admin                     |
| `2025_12_18_000000_optimize_booking_indexes`              | idx_bookings_availability                         |
| `2025_12_18_100000_add_soft_deletes_to_bookings`          | deleted_at, deleted_by                            |
| `2025_12_18_200000_add_lock_version_to_rooms`             | Optimistic locking                                |
| `2026_01_11_000001_add_payment_fields_to_bookings`        | Stripe payment fields                             |
| `2026_01_12_add_booking_id_unique_to_reviews`             | booking_id unique on reviews                      |
| `2026_02_09_000000_add_foreign_key_constraints`           | FK constraints                                    |
| `2026_02_09_000001_create_locations_table`                | locations table                                   |
| `2026_02_09_000002_add_location_id_to_rooms_table`        | location_id on rooms                              |
| `2026_02_09_000003_add_location_id_to_bookings_table`     | location_id on bookings                           |
| `2026_02_09_000004_seed_initial_locations`                | Seed 5 locations                                  |
| `2026_02_09_000005_assign_rooms_to_locations`             | Assign rooms + backfill bookings                  |
| `2026_02_09_000006_add_booking_location_trigger`          | PostgreSQL trigger                                |
| `2026_02_10_000001_create_contact_messages_table`         | contact_messages table                            |
| `2026_02_10_000002_make_booking_id_non_nullable`          | booking_id non-nullable on reviews                |
| `2026_02_10_add_cancellation_reason_to_bookings`          | cancellation_reason column                        |
| `2026_02_11_reconcile_legacy_index_ordering`              | Idempotent index reconciliation                   |
| `2026_02_12_fix_overlapping_bookings_constraint`          | Exclusion constraint excludes soft deletes        |
| `2026_02_22_add_check_constraints_bookings_reviews_rooms` | CHECK constraints: dates, rating, price (PG only) |
| `2026_02_22_add_fk_reviews_booking_id`                    | FK reviews.booking_id → bookings.id (RESTRICT)    |
| `2026_02_28_add_cashier_columns_to_users_table`           | Cashier: stripe_id, pm_type, pm_last_four, trial  |
| `2026_03_17_000001_harden_fk_delete_policies`             | FK hardening: 4 FKs CASCADE→SET NULL/RESTRICT (PG)|
| `2026_03_17_000002_add_check_constraint_rooms_max_guests` | CHECK (max_guests > 0) on rooms (PG only)         |
| `2026_03_17_000003_add_check_constraint_bookings_status`  | CHECK (status IN (...)) on bookings (PG only)      |

### Commands

```bash
# Run all migrations
php artisan migrate

# Rollback last batch
php artisan migrate:rollback

# Fresh start (drop all + migrate)
php artisan migrate:fresh

# With seed data
php artisan migrate:fresh --seed
```

---

## Seeders

| Seeder             | Description                                     |
| ------------------ | ----------------------------------------------- |
| `DatabaseSeeder`   | Main seeder (calls LocationSeeder + RoomSeeder) |
| `LocationSeeder`   | 5 Soleil brand locations (Hue, Quang Dien)      |
| `RoomSeeder`       | Sample rooms data                               |
| `ReviewSeeder`     | Sample reviews                                  |
| `RoomsTableSeeder` | Legacy rooms seeder                             |

### Sample Data (RoomSeeder)

| Name          | Price   | Max Guests | Status    |
| ------------- | ------- | ---------- | --------- |
| Deluxe Room   | $150.00 | 2          | available |
| Suite Room    | $250.00 | 4          | available |
| Standard Room | $100.00 | 1          | available |

### Commands

```bash
# Run seeders
php artisan db:seed

# Run specific seeder
php artisan db:seed --class=RoomSeeder
```

---

## Factories

| Factory           | Model    | Usage          |
| ----------------- | -------- | -------------- |
| `UserFactory`     | User     | Test users     |
| `LocationFactory` | Location | Test locations |
| `RoomFactory`     | Room     | Test rooms     |
| `BookingFactory`  | Booking  | Test bookings  |

### Factory States

```php
// UserFactory states
User::factory()->admin()->create();      // role = admin
User::factory()->moderator()->create();  // role = moderator
User::factory()->user()->create();       // role = user
User::factory()->unverified()->create(); // email_verified_at = null
User::factory()->withRole(UserRole::ADMIN)->create();
User::factory()->withEmail('test@example.com')->create();

// LocationFactory states
Location::factory()->create();                   // random location
Location::factory()->inactive()->create();       // is_active = false
Location::factory()->withoutCoordinates()->create(); // no lat/lng
Location::factory()->withSlug('my-hostel')->create();
Location::factory()->inHue()->create();          // Hue coordinates

// BookingFactory states
Booking::factory()->confirmed()->create();  // status = confirmed
Booking::factory()->cancelled()->create();  // status = cancelled
Booking::factory()->pending()->create();    // status = pending
Booking::factory()->forRoom($room)->create();
Booking::factory()->forUser($user)->create();
Booking::factory()->forDates($checkIn, $checkOut)->create();
Booking::factory()->todayCheckIn()->create();
```

### Basic Usage

```php
// Create single record
User::factory()->create();

// Create multiple records
Room::factory()->count(10)->create();

// Create with specific attributes
Booking::factory()->create([
    'status' => 'confirmed',
]);
```

---

## Database Configuration

### Production (PostgreSQL 16)

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=soleil_hostel
DB_USERNAME=postgres
DB_PASSWORD=secret
```

### Testing (SQLite in-memory)

```env
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
```

**Note:** PostgreSQL supports ENUM types và exclusion constraints. SQLite dùng cho parallel testing.

---

## Model Relationships

```
Location
├── hasMany → Room (location_id)
├── hasMany → Booking (location_id)  // denormalized for analytics
│
User
├── hasMany → Booking (user_id)
│
Room
├── belongsTo → Location (location_id)
├── hasMany → Booking (room_id)
├── hasMany → Review (room_id)
│
Booking
├── belongsTo → User (user_id)
├── belongsTo → Room (room_id)
├── belongsTo → Location (location_id)  // denormalized
├── belongsTo → User (cancelled_by)     // cancellation audit
├── belongsTo → User (deleted_by)       // soft delete audit
├── hasOne → Review (booking_id)        // one review per booking
│
Review
├── belongsTo → Room (room_id)
├── belongsTo → User (user_id)
├── belongsTo → Booking (booking_id)    // required, non-nullable
│
ContactMessage (standalone, no FK relationships)
```

---

## Locking Strategies

### Optimistic Locking (Rooms)

Sử dụng `lock_version` để detect concurrent updates:

```php
// Room model has HasLockVersion trait
$room = Room::find(1);
$room->price = 200;
$room->save(); // Auto increments lock_version

// If another process updated, throws StaleModelLockException
```

### Pessimistic Locking (Bookings)

Sử dụng `SELECT FOR UPDATE` để lock rows:

```php
DB::transaction(function () {
    // Lock overlapping bookings
    $conflicts = Booking::where('room_id', $roomId)
        ->whereIn('status', ['pending', 'confirmed'])
        ->where('check_in', '<', $checkOut)
        ->where('check_out', '>', $checkIn)
        ->lockForUpdate()  // SELECT FOR UPDATE
        ->exists();

    if (!$conflicts) {
        Booking::create([...]);
    }
});
```
