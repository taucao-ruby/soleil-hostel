# 🗄️ Database Schema & Indexes

> Complete database design for Soleil Hostel (18 migrations, 12 tables)

## ER Diagram

```
┌────────────────────┐       ┌────────────────────┐       ┌────────────────────┐
│       users        │       │       rooms        │       │      reviews       │
├────────────────────┤       ├────────────────────┤       ├────────────────────┤
│ id (PK)            │       │ id (PK)            │       │ id (PK)            │
│ name               │       │ name               │       │ room_id (FK)       │
│ email (UNIQUE)     │       │ description        │       │ user_id (FK)       │
│ password           │       │ price              │       │ title              │
│ role (ENUM)        │       │ max_guests         │       │ content            │
│ email_verified_at  │       │ status             │       │ guest_name         │
│ remember_token     │       │ lock_version       │◄──    │ rating (1-5)       │
│ created_at         │       │ created_at         │       │ approved           │
│ updated_at         │       │ updated_at         │       │ created_at         │
└────────────────────┘       └────────────────────┘       └────────────────────┘
          │                           │                            │
          │ 1:N                       │ 1:N                        │
          ▼                           ▼                            │
┌─────────────────────────────────────────────────┐               │
│                   bookings                       │◄──────────────┘
├─────────────────────────────────────────────────┤
│ id (PK)                                          │
│ user_id (FK → users)                             │
│ room_id (FK → rooms)                             │
│ guest_name, guest_email                          │
│ check_in (DATE), check_out (DATE)                │
│ status (pending/confirmed/cancelled)             │
│ deleted_at, deleted_by    ◄── Soft delete        │
│ created_at, updated_at                           │
└─────────────────────────────────────────────────┘

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

### rooms

| Column       | Type             | Constraints         |
| ------------ | ---------------- | ------------------- |
| id           | BIGSERIAL        | PRIMARY KEY         |
| name         | VARCHAR(255)     | NOT NULL            |
| description  | TEXT             | NULLABLE            |
| price        | DECIMAL(10,2)    | NOT NULL            |
| max_guests   | INTEGER          | NOT NULL            |
| status       | room_status ENUM | DEFAULT 'available' |
| lock_version | BIGINT UNSIGNED  | NOT NULL, DEFAULT 1 |
| created_at   | TIMESTAMP        |                     |
| updated_at   | TIMESTAMP        |                     |

**ENUM: room_status**

```sql
CREATE TYPE room_status AS ENUM ('available', 'occupied', 'maintenance');
```

### bookings

| Column      | Type         | Constraints              |
| ----------- | ------------ | ------------------------ |
| id          | BIGSERIAL    | PRIMARY KEY              |
| user_id     | BIGINT       | FK → users(id), NULLABLE |
| room_id     | BIGINT       | FK → rooms(id)           |
| guest_name  | VARCHAR(255) | NOT NULL                 |
| guest_email | VARCHAR(255) | NOT NULL                 |
| check_in    | DATE         | NOT NULL                 |
| check_out   | DATE         | NOT NULL                 |
| status      | VARCHAR      | DEFAULT 'pending'        |
| deleted_at  | TIMESTAMP    | NULLABLE (soft delete)   |
| deleted_by  | BIGINT       | NULLABLE, FK → users(id) |
| created_at  | TIMESTAMP    |                          |
| updated_at  | TIMESTAMP    |                          |

### reviews

| Column      | Type         | Constraints              |
| ----------- | ------------ | ------------------------ |
| id          | BIGSERIAL    | PRIMARY KEY              |
| room_id     | BIGINT       | NOT NULL, INDEX          |
| user_id     | BIGINT       | NULLABLE, INDEX          |
| title       | VARCHAR(255) | NOT NULL (purified)      |
| content     | TEXT         | NOT NULL (purified HTML) |
| guest_name  | VARCHAR(255) | NOT NULL (purified)      |
| guest_email | VARCHAR(255) | NULLABLE                 |
| rating      | TINYINT      | NOT NULL, CHECK (1-5)    |
| approved    | BOOLEAN      | DEFAULT TRUE             |
| created_at  | TIMESTAMP    |                          |
| updated_at  | TIMESTAMP    |                          |

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
| payload       | LONGTEXT     | NOT NULL        |
| last_activity | INTEGER      | NOT NULL, INDEX |

### password_reset_tokens

| Column     | Type         | Constraints |
| ---------- | ------------ | ----------- |
| email      | VARCHAR(255) | PRIMARY KEY |
| token      | VARCHAR(255) | NOT NULL    |
| created_at | TIMESTAMP    | NULLABLE    |

### cache / cache_locks

| Column     | Type       | Constraints |
| ---------- | ---------- | ----------- |
| key        | VARCHAR    | PRIMARY KEY |
| value      | MEDIUMTEXT | NOT NULL    |
| expiration | INTEGER    | NOT NULL    |

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
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

ALTER TABLE bookings
ADD CONSTRAINT fk_bookings_room
FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE;

ALTER TABLE bookings
ADD CONSTRAINT fk_bookings_deleted_by
FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE reviews
ADD CONSTRAINT fk_reviews_room
FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE;

ALTER TABLE reviews
ADD CONSTRAINT fk_reviews_user
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;
```

### Check Constraints

```sql
-- Check-out must be after check-in
ALTER TABLE bookings
ADD CONSTRAINT chk_bookings_dates
CHECK (check_out > check_in);

-- Price must be positive
ALTER TABLE rooms
ADD CONSTRAINT chk_rooms_price
CHECK (price >= 0);

-- Max guests must be positive
ALTER TABLE rooms
ADD CONSTRAINT chk_rooms_max_guests
CHECK (max_guests > 0);

-- Rating must be 1-5
ALTER TABLE reviews
ADD CONSTRAINT chk_reviews_rating
CHECK (rating >= 1 AND rating <= 5);
```

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

## PostgreSQL Exclusion Constraint (Optional)

For database-level overlap prevention:

```sql
CREATE EXTENSION IF NOT EXISTS btree_gist;

ALTER TABLE bookings
ADD CONSTRAINT excl_bookings_no_overlap
EXCLUDE USING gist (
    room_id WITH =,
    daterange(check_in, check_out, '[)') WITH &&
) WHERE (status IN ('pending', 'confirmed') AND deleted_at IS NULL);
```

---

## Migrations

### Migration History (18 files)

| Migration                                        | Description                     |
| ------------------------------------------------ | ------------------------------- |
| `0001_01_01_000000_create_users_table`           | users, sessions, password_reset |
| `0001_01_01_000001_create_cache_table`           | cache, cache_locks              |
| `0001_01_01_000002_create_jobs_table`            | jobs, job_batches, failed_jobs  |
| `2025_05_08_create_personal_access_tokens_table` | Sanctum base tokens             |
| `2025_05_09_create_bookings_table`               | bookings base                   |
| `2025_08_19_create_rooms_table`                  | rooms base                      |
| `2025_11_18_add_user_id_to_bookings`             | user_id FK + indexes            |
| `2025_11_18_add_is_admin_to_users`               | is_admin (deprecated)           |
| `2025_11_18_add_booking_constraints`             | unique_room_dates constraint    |
| `2025_11_20_add_token_expiration`                | revoked_at, type, device_id     |
| `2025_11_20_add_pessimistic_locking_indexes`     | idx_room_active, idx_room_dates |
| `2025_11_21_add_token_security_columns`          | token_identifier, token_hash    |
| `2025_11_24_create_reviews_table`                | reviews table                   |
| `2025_12_05_add_nplusone_fix_indexes`            | N+1 prevention indexes          |
| `2025_12_17_convert_role_to_enum`                | ENUM user_role, drop is_admin   |
| `2025_12_18_optimize_booking_indexes`            | idx_bookings_availability       |
| `2025_12_18_add_soft_deletes_to_bookings`        | deleted_at, deleted_by          |
| `2025_12_18_add_lock_version_to_rooms`           | Optimistic locking              |

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

| Seeder             | Description                    |
| ------------------ | ------------------------------ |
| `DatabaseSeeder`   | Main seeder (calls RoomSeeder) |
| `RoomSeeder`       | Sample rooms data              |
| `ReviewSeeder`     | Sample reviews                 |
| `RoomsTableSeeder` | Legacy rooms seeder            |

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

| Factory          | Model   | Usage         |
| ---------------- | ------- | ------------- |
| `UserFactory`    | User    | Test users    |
| `RoomFactory`    | Room    | Test rooms    |
| `BookingFactory` | Booking | Test bookings |

### Factory States

```php
// UserFactory states
User::factory()->admin()->create();      // role = admin
User::factory()->moderator()->create();  // role = moderator
User::factory()->user()->create();       // role = user
User::factory()->unverified()->create(); // email_verified_at = null
User::factory()->withRole(UserRole::ADMIN)->create();
User::factory()->withEmail('test@example.com')->create();

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

### Production (PostgreSQL 15)

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
User
├── hasMany → Booking (user_id)
│
Room
├── hasMany → Booking (room_id)
├── hasMany → Review (room_id)  // via query, no model relation
│
Booking
├── belongsTo → User (user_id)
├── belongsTo → Room (room_id)
├── belongsTo → User (deleted_by) // soft delete audit
│
Review
├── belongsTo → Room (room_id)
├── belongsTo → User (user_id)
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
