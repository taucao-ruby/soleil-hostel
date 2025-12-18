# 🗄️ Database Schema & Indexes

> Database design for Soleil Hostel

## ER Diagram

```
┌────────────────────┐       ┌────────────────────┐
│       users        │       │       rooms        │
├────────────────────┤       ├────────────────────┤
│ id (PK)            │       │ id (PK)            │
│ name               │       │ name               │
│ email (UNIQUE)     │       │ description        │
│ password           │       │ price              │
│ role (ENUM)        │       │ max_guests         │
│ created_at         │       │ status (ENUM)      │
│ updated_at         │       │ lock_version       │◄── Optimistic locking
└────────────────────┘       │ created_at         │
          │                  │ updated_at         │
          │                  └────────────────────┘
          │                           │
          │ 1:N                       │ 1:N
          ▼                           ▼
┌─────────────────────────────────────────────────┐
│                   bookings                       │
├─────────────────────────────────────────────────┤
│ id (PK)                                          │
│ user_id (FK → users)                             │
│ room_id (FK → rooms)                             │
│ guest_name                                       │
│ check_in (DATE)                                  │
│ check_out (DATE)                                 │
│ status (ENUM: pending, confirmed, cancelled)    │
│ deleted_at (TIMESTAMP)        ◄── Soft delete   │
│ deleted_by (FK → users)       ◄── Audit trail   │
│ created_at                                       │
│ updated_at                                       │
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

| Column     | Type                | Constraints              |
| ---------- | ------------------- | ------------------------ |
| id         | BIGSERIAL           | PRIMARY KEY              |
| user_id    | BIGINT              | FK → users(id)           |
| room_id    | BIGINT              | FK → rooms(id)           |
| guest_name | VARCHAR(255)        | NOT NULL                 |
| check_in   | DATE                | NOT NULL                 |
| check_out  | DATE                | NOT NULL                 |
| status     | booking_status ENUM | DEFAULT 'pending'        |
| deleted_at | TIMESTAMP           | NULLABLE (soft delete)   |
| deleted_by | BIGINT              | NULLABLE, FK → users(id) |
| created_at | TIMESTAMP           |                          |
| updated_at | TIMESTAMP           |                          |

**ENUM: booking_status**

```sql
CREATE TYPE booking_status AS ENUM ('pending', 'confirmed', 'cancelled');
```

---

## Indexes

### Booking Indexes (Optimized for Availability)

```sql
-- Primary availability query
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
