# 🗄️ Database Schema & Indexes

> Complete database design for Soleil Hostel (49 migrations, 20 tables)

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
│ updated_at         │       │ readiness_status   │◄──    │ approved           │
└────────────────────┘       │ room_type_code     │       │ created_at         │
          │                  │ room_tier          │       └────────────────────┘
          │                  │ lock_version       │
          │                  │ created_at         │
          │                  │ updated_at         │
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
│ status (commercial-only)                         │
│ deposit_*                  ◄── Liability state   │
│ cancellation_reason          ◄── Cancellation    │
│ deleted_at, deleted_by       ◄── Soft delete     │
│ created_at, updated_at                           │
└─────────────────────────────────────────────────┘

                │ 1:1 operational lifecycle
                ▼
┌────────────────────────────┐
│           stays            │
├────────────────────────────┤
│ id (PK)                    │
│ booking_id (FK, UNIQUE)    │
│ stay_status                │
│ scheduled_check_in_at      │
│ scheduled_check_out_at     │
│ actual_check_in_at         │
│ actual_check_out_at        │
│ late_checkout_minutes      │
│ late_checkout_fee_amount   │
│ no_show_at                 │
│ checked_in_by / out_by     │
│ created_at, updated_at     │
└────────────────────────────┘
          │ 1:N                           │ 1:N
          ▼                               ▼
┌────────────────────────────┐   ┌──────────────────────────────┐
│      room_assignments      │   │   service_recovery_cases    │
├────────────────────────────┤   ├──────────────────────────────┤
│ id (PK)                    │   │ id (PK)                      │
│ booking_id (FK)            │   │ booking_id (FK)              │
│ stay_id (FK)               │   │ stay_id (FK, nullable)       │
│ room_id (FK)               │   │ incident_type                │
│ assignment_type            │   │ severity / case_status       │
│ assignment_status          │   │ compensation_type            │
│ assigned_from              │   │ refund_amount                │
│ assigned_until             │   │ voucher_amount               │
│ assigned_by                │   │ cost_delta_absorbed          │
│ reason_code, notes         │   │ settlement_*                 │
│ created_at, updated_at     │   │ opened_at / resolved_at      │
│                            │   │ handled_by, notes            │
└────────────────────────────┘   └──────────────────────────────┘

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

┌────────────────────────────┐   ┌────────────────────────────┐
│     policy_documents       │   │    ai_proposal_events      │ ◄── AI Harness
├────────────────────────────┤   ├────────────────────────────┤
│ id (UUID PK)               │   │ id (PK)                    │
│ slug (UNIQUE)              │   │ user_id (FK → users)       │
│ title                      │   │ proposal_hash (64-char)    │
│ content (TEXT)             │   │ action_type                │
│ category                   │   │ user_decision              │
│ language (default 'vi')    │   │ downstream_result (TEXT)   │
│ is_active                  │   │ created_at, updated_at     │
│ last_verified_at           │   └────────────────────────────┘
│ version                    │
│ created_at, updated_at     │
└────────────────────────────┘
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
| readiness_status | VARCHAR     | NOT NULL, DEFAULT 'ready' |
| readiness_updated_at | TIMESTAMP | NULLABLE               |
| readiness_updated_by | BIGINT  | NULLABLE, FK → users(id) |
| room_type_code | VARCHAR(50)   | NULLABLE                |
| room_tier    | SMALLINT        | NULLABLE, DEFAULT 1      |
| lock_version | BIGINT UNSIGNED | NOT NULL, DEFAULT 1      |
| created_at   | TIMESTAMP       |                          |
| updated_at   | TIMESTAMP       |                          |

**room_status: VARCHAR** (intentional — not a PostgreSQL ENUM).
`rooms.status` is the legacy availability/admin field and remains intentionally separate from
physical readiness. Values in application code are still inconsistent (`available`, `occupied`,
`maintenance`, `booked`, `active`), so DB enforcement for `rooms.status` remains deferred.

Canonical physical room state lives on `rooms.readiness_status`:
`ready`, `occupied`, `dirty`, `cleaning`, `inspected`, `out_of_service`.

Room comparability lives on:
- `room_type_code` = equivalence key for swap candidates
- `room_tier` = numeric upgrade comparison (higher = better)

`room_type_code` and `room_tier` remain nullable until populated by operators.

### bookings

| Column               | Type         | Constraints                         |
| -------------------- | ------------ | ----------------------------------- |
| id                   | BIGSERIAL    | PRIMARY KEY                         |
| user_id              | BIGINT       | FK → users(id), NULLABLE            |
| room_id              | BIGINT       | FK → rooms(id)                      |
| location_id          | BIGINT       | FK → locations(id), NULLABLE        |
| guest_name           | VARCHAR(255) | NOT NULL                            |
| guest_email          | VARCHAR(255) | NOT NULL                            |
| check_in             | DATE         | NOT NULL                            |
| check_out            | DATE         | NOT NULL                            |
| status               | VARCHAR      | DEFAULT 'pending'                   |
| amount               | BIGINT       | NULLABLE (cents)                    |
| payment_intent_id    | VARCHAR(255) | NULLABLE (Stripe PaymentIntent ID)  |
| refund_id            | VARCHAR(255) | NULLABLE (Stripe Refund ID)         |
| refund_status        | VARCHAR      | NULLABLE (pending/succeeded/failed) |
| refund_amount        | BIGINT       | NULLABLE (cents)                    |
| refund_error         | TEXT         | NULLABLE                            |
| deposit_amount       | BIGINT       | NULLABLE (cents)                    |
| deposit_collected_at | TIMESTAMP    | NULLABLE                            |
| deposit_status       | VARCHAR      | NOT NULL, DEFAULT 'none'            |
| cancelled_at         | TIMESTAMP    | NULLABLE                            |
| cancelled_by         | BIGINT       | NULLABLE, FK → users(id)            |
| cancellation_reason  | TEXT         | NULLABLE                            |
| deleted_at           | TIMESTAMP    | NULLABLE (soft delete)              |
| deleted_by           | BIGINT       | NULLABLE, FK → users(id)            |
| created_at           | TIMESTAMP    |                                     |
| updated_at           | TIMESTAMP    |                                     |

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

`bookings.status` remains the **commercial reservation state only**. Operational occupancy state is derived from `stays.stay_status`, not from booking status or a static user flag.

Deposit lifecycle values (`App\Enums\DepositStatus`):
- `none`
- `collected`
- `applied`
- `refunded`

`deposit_amount` is operational liability tracking only. It is **unearned revenue / liability**
until the stay is fulfilled. This schema does **not** represent authoritative accounting or GL.

### stays

| Column                   | Type         | Constraints                      |
| ------------------------ | ------------ | -------------------------------- |
| id                       | BIGSERIAL    | PRIMARY KEY                      |
| booking_id               | BIGINT       | NOT NULL, UNIQUE, FK → bookings  |
| stay_status              | VARCHAR      | DEFAULT 'expected'               |
| scheduled_check_in_at    | TIMESTAMP    | NULLABLE                         |
| scheduled_check_out_at   | TIMESTAMP    | NULLABLE                         |
| actual_check_in_at       | TIMESTAMP    | NULLABLE                         |
| actual_check_out_at      | TIMESTAMP    | NULLABLE                         |
| late_checkout_minutes    | INTEGER      | NOT NULL, DEFAULT 0              |
| late_checkout_fee_amount | BIGINT       | NULLABLE (cents)                 |
| no_show_at               | TIMESTAMP    | NULLABLE                         |
| checked_in_by            | BIGINT       | NULLABLE, FK → users(id)         |
| checked_out_by           | BIGINT       | NULLABLE, FK → users(id)         |
| created_at               | TIMESTAMP    |                                  |
| updated_at               | TIMESTAMP    |                                  |

**stay_status: VARCHAR** (intentional — not a PostgreSQL ENUM).
Allowed values are enforced via `App\Enums\StayStatus` and PostgreSQL CHECK constraint `chk_stays_stay_status`.

Active in-house guest is derived from `stays.stay_status IN ('in_house', 'late_checkout')`.
A static `users.active` flag is **not** the source of truth.

### room_assignments

| Column            | Type         | Constraints                      |
| ----------------- | ------------ | -------------------------------- |
| id                | BIGSERIAL    | PRIMARY KEY                      |
| booking_id        | BIGINT       | NOT NULL, FK → bookings          |
| stay_id           | BIGINT       | NOT NULL, FK → stays             |
| room_id           | BIGINT       | NOT NULL, FK → rooms             |
| assignment_type   | VARCHAR      | NOT NULL                         |
| assignment_status | VARCHAR      | DEFAULT 'active'                 |
| assigned_from     | TIMESTAMP    | NOT NULL                         |
| assigned_until    | TIMESTAMP    | NULLABLE                         |
| assigned_by       | BIGINT       | NULLABLE, FK → users(id)         |
| reason_code       | VARCHAR(255) | NULLABLE                         |
| notes             | TEXT         | NULLABLE                         |
| created_at        | TIMESTAMP    |                                  |
| updated_at        | TIMESTAMP    |                                  |

`assigned_until IS NULL` means the assignment is currently active.
PostgreSQL partial unique index `udx_room_assignments_one_active_per_stay`
enforces at most one active room assignment per stay.

### service_recovery_cases

| Column                     | Type         | Constraints                      |
| -------------------------- | ------------ | -------------------------------- |
| id                         | BIGSERIAL    | PRIMARY KEY                      |
| booking_id                 | BIGINT       | NOT NULL, FK → bookings          |
| stay_id                    | BIGINT       | NULLABLE, FK → stays             |
| incident_type              | VARCHAR      | NOT NULL                         |
| severity                   | VARCHAR      | DEFAULT 'medium'                 |
| case_status                | VARCHAR      | DEFAULT 'open'                   |
| action_taken               | TEXT         | NULLABLE                         |
| external_hotel_name        | VARCHAR(255) | NULLABLE                         |
| external_booking_reference | VARCHAR(255) | NULLABLE                         |
| compensation_type          | VARCHAR      | DEFAULT 'none'                   |
| refund_amount              | BIGINT       | NULLABLE (cents)                 |
| voucher_amount             | BIGINT       | NULLABLE (cents)                 |
| cost_delta_absorbed        | BIGINT       | NULLABLE (cents)                 |
| settlement_status          | VARCHAR      | NOT NULL, DEFAULT 'unsettled'    |
| settled_amount             | BIGINT       | NULLABLE (cents)                 |
| settled_at                 | TIMESTAMP    | NULLABLE                         |
| settlement_notes           | TEXT         | NULLABLE                         |
| handled_by                 | BIGINT       | NULLABLE, FK → users(id)         |
| opened_at                  | TIMESTAMP    | NOT NULL                         |
| resolved_at                | TIMESTAMP    | NULLABLE                         |
| notes                      | TEXT         | NULLABLE                         |
| created_at                 | TIMESTAMP    |                                  |
| updated_at                 | TIMESTAMP    |                                  |

All compensation amounts are stored in **cents** (BIGINT) to match `bookings.amount`.
`stay_id` remains nullable because incidents may be recorded before the stay row exists.
`settlement_status` is operational financial tracking only and is **not** authoritative accounting.

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
| approved    | BOOLEAN      | DEFAULT TRUE                    |
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
| remember_token_id  | UUID         | NULLABLE               |
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

### policy_documents (AI Harness)

Hostel policy content used for AI FAQ grounding. Added `2026_04_09_000001`.

| Column           | Type         | Constraints                |
| ---------------- | ------------ | -------------------------- |
| id               | UUID         | PRIMARY KEY                |
| slug             | VARCHAR(255) | NOT NULL, UNIQUE           |
| title            | VARCHAR(255) | NOT NULL                   |
| content          | TEXT         | NOT NULL                   |
| category         | VARCHAR(255) | NOT NULL                   |
| language         | VARCHAR(10)  | NOT NULL, DEFAULT 'vi'     |
| is_active        | BOOLEAN      | NOT NULL, DEFAULT true     |
| last_verified_at | TIMESTAMP    | NULLABLE                   |
| version          | VARCHAR(255) | NOT NULL                   |
| created_at       | TIMESTAMP    |                            |
| updated_at       | TIMESTAMP    |                            |

**Indexes:** `(slug, is_active, language)`, `(category, is_active)`

### ai_proposal_events (AI Harness)

Audit trail for AI booking proposal confirm/decline decisions. Added `2026_04_11_000001`.

| Column            | Type         | Constraints                          |
| ----------------- | ------------ | ------------------------------------ |
| id                | BIGSERIAL    | PRIMARY KEY                          |
| user_id           | BIGINT       | FK → users(id) CASCADE ON DELETE     |
| proposal_hash     | VARCHAR(64)  | NOT NULL, indexed                    |
| action_type       | VARCHAR(30)  | NOT NULL                             |
| user_decision     | VARCHAR(20)  | NOT NULL (confirmed/declined/shown)  |
| downstream_result | TEXT         | NULLABLE                             |
| created_at        | TIMESTAMP    |                                      |
| updated_at        | TIMESTAMP    |                                      |

**Indexes:** `(proposal_hash)`, `(proposal_hash, user_decision)`

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

-- Deposit lifecycle reporting
CREATE INDEX idx_bookings_deposit_status_check_in
ON bookings (deposit_status, check_in);
```

### Room Indexes

```sql
-- Status filter
CREATE INDEX idx_rooms_status ON rooms (status);

-- Physical readiness boards
CREATE INDEX idx_rooms_location_readiness ON rooms (location_id, readiness_status);
CREATE INDEX idx_rooms_readiness_status ON rooms (readiness_status);

-- Swap / upgrade candidate lookups
CREATE INDEX idx_rooms_type_location ON rooms (room_type_code, location_id);
CREATE INDEX idx_rooms_tier_location ON rooms (room_tier, location_id);
CREATE INDEX idx_rooms_type_tier ON rooms (room_type_code, room_tier);

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

### Operational Stay Indexes

```sql
CREATE INDEX idx_stays_stay_status ON stays (stay_status);
CREATE INDEX idx_stays_scheduled_check_in_at ON stays (scheduled_check_in_at);
CREATE INDEX idx_stays_scheduled_check_out_at ON stays (scheduled_check_out_at);
```

### Room Assignment Indexes

```sql
CREATE INDEX idx_room_assignments_stay_active
ON room_assignments (stay_id, assigned_until);

CREATE INDEX idx_room_assignments_room_window
ON room_assignments (room_id, assigned_from, assigned_until);

CREATE UNIQUE INDEX udx_room_assignments_one_active_per_stay
ON room_assignments (stay_id)
WHERE assigned_until IS NULL;
```

### Service Recovery Case Indexes

```sql
CREATE INDEX idx_src_case_status_severity
ON service_recovery_cases (case_status, severity);

CREATE INDEX idx_src_booking_id ON service_recovery_cases (booking_id);
CREATE INDEX idx_src_opened_at ON service_recovery_cases (opened_at);
CREATE INDEX idx_src_stay_id ON service_recovery_cases (stay_id);
CREATE INDEX idx_src_settlement_status_settled_at
ON service_recovery_cases (settlement_status, settled_at);
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

ALTER TABLE rooms
ADD CONSTRAINT fk_rooms_readiness_updated_by
FOREIGN KEY (readiness_updated_by) REFERENCES users(id) ON DELETE SET NULL;
-- Added in migration 2026_03_23_000001

ALTER TABLE reviews
ADD CONSTRAINT fk_reviews_room
FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE RESTRICT;
-- `reviews.room_id` is NOT NULL in schema, so SET NULL was invalid source truth.
-- Corrected in migration 2026_03_23_000005.

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

-- Physical room readiness states (added 2026-03-23)
ALTER TABLE rooms
ADD CONSTRAINT chk_rooms_readiness_status
CHECK (readiness_status IN (
    'ready', 'occupied', 'dirty', 'cleaning', 'inspected', 'out_of_service'
));

-- Room tier must remain positive if populated (added 2026-03-23)
ALTER TABLE rooms
ADD CONSTRAINT chk_rooms_room_tier_positive
CHECK (room_tier IS NULL OR room_tier > 0);

-- Booking status must be a known value (added 2026-03-17)
ALTER TABLE bookings
ADD CONSTRAINT chk_bookings_status
CHECK (status IN ('pending', 'confirmed', 'refund_pending', 'cancelled', 'refund_failed'));
-- Added in migration 2026_03_17_000003. PostgreSQL only.
-- Values match App\Enums\BookingStatus. Adding a new status requires a migration.

-- Deposit lifecycle must use known operational states (added 2026-03-23)
ALTER TABLE bookings
ADD CONSTRAINT chk_bookings_deposit_status
CHECK (deposit_status IN ('none', 'collected', 'applied', 'refunded'));

-- Stay lifecycle must use known operational states (added 2026-03-20)
ALTER TABLE stays
ADD CONSTRAINT chk_stays_stay_status
CHECK (stay_status IN (
    'expected', 'in_house', 'late_checkout', 'checked_out',
    'no_show', 'relocated_internal', 'relocated_external'
));

-- Room assignment classification (added 2026-03-20)
ALTER TABLE room_assignments
ADD CONSTRAINT chk_room_assignments_assignment_type
CHECK (assignment_type IN (
    'original', 'equivalent_swap', 'complimentary_upgrade',
    'maintenance_move', 'overflow_relocation'
));

ALTER TABLE room_assignments
ADD CONSTRAINT chk_room_assignments_assignment_status
CHECK (assignment_status IN ('active', 'closed', 'cancelled'));

-- Service recovery classifications (added 2026-03-20)
ALTER TABLE service_recovery_cases
ADD CONSTRAINT chk_src_incident_type
CHECK (incident_type IN (
    'late_checkout_blocking_arrival', 'room_unavailable_maintenance',
    'overbooking_no_room', 'internal_relocation', 'external_relocation'
));

ALTER TABLE service_recovery_cases
ADD CONSTRAINT chk_src_severity
CHECK (severity IN ('low', 'medium', 'high', 'critical'));

ALTER TABLE service_recovery_cases
ADD CONSTRAINT chk_src_case_status
CHECK (case_status IN (
    'open', 'investigating', 'action_in_progress',
    'compensated', 'resolved', 'closed'
));

ALTER TABLE service_recovery_cases
ADD CONSTRAINT chk_src_compensation_type
CHECK (compensation_type IN (
    'none', 'refund_partial', 'refund_full', 'voucher',
    'complimentary_upgrade', 'refund_plus_voucher'
));

-- Operational settlement tracking states (added 2026-03-23)
ALTER TABLE service_recovery_cases
ADD CONSTRAINT chk_src_settlement_status
CHECK (settlement_status IN (
    'unsettled', 'partially_settled', 'settled', 'written_off'
));
```

> **Note:** `rooms.status` DB-level CHECK is still **not present**. That field remains a legacy availability/admin signal. Physical readiness is now enforced separately through `rooms.readiness_status`.

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

### Migration History (47 files)

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
| `2026_03_17_000003_add_check_constraint_bookings_status`  | CHECK (status IN (...)) on bookings (PG only)     |
| `2026_03_20_000001_create_stays_table`                    | stays table + operational lifecycle indexes       |
| `2026_03_20_000002_create_room_assignments_table`         | room assignment history + partial active index    |
| `2026_03_20_000003_create_service_recovery_cases_table`   | incident + compensation audit trail               |
| `2026_03_23_000001_add_room_readiness_to_rooms_table`     | room readiness status + audit fields              |
| `2026_03_23_000002_add_room_classification_to_rooms_table`| room equivalence and upgrade comparability fields |
| `2026_03_23_000003_add_deposit_lifecycle_to_bookings_table` | deposit / advance lifecycle tracking            |
| `2026_03_23_000004_add_settlement_lifecycle_to_service_recovery_cases_table` | settlement tracking on recovery cases |
| `2026_03_23_000005_fix_reviews_room_fk_delete_policy`     | correct `reviews.room_id` FK to RESTRICT          |
| `2026_04_03_084257_create_email_verification_codes_table` | email OTP verification (SHA-256 code_hash)        |
| `2026_04_09_000001_create_policy_documents_table`         | AI harness policy content for FAQ grounding       |
| `2026_04_11_000001_create_ai_proposal_events_table`       | AI proposal confirm/decline audit trail           |

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
| `PolicyDocumentSeeder` | AI harness policy content (FAQ grounding)    |

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
| `StayFactory`     | Stay     | Operational stays |
| `RoomAssignmentFactory` | RoomAssignment | Assignment history |
| `ServiceRecoveryCaseFactory` | ServiceRecoveryCase | Incident audit |

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

// StayFactory states
Stay::factory()->expected()->create();
Stay::factory()->inHouse()->create();
Stay::factory()->lateCheckout()->create();
Stay::factory()->checkedOut()->create();
Stay::factory()->noShow()->create();

// RoomAssignmentFactory states
RoomAssignment::factory()->active()->create();
RoomAssignment::factory()->closed()->create();

// ServiceRecoveryCaseFactory states
ServiceRecoveryCase::factory()->open()->create();
ServiceRecoveryCase::factory()->resolved()->create();
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
├── hasOne → Stay (booking_id)          // operational lifecycle
├── hasOne → Review (booking_id)        // one review per booking
│
Stay
├── belongsTo → Booking (booking_id)
├── belongsTo → User (checked_in_by)
├── belongsTo → User (checked_out_by)
├── hasMany → RoomAssignment (stay_id)
├── hasMany → ServiceRecoveryCase (stay_id)
│
RoomAssignment
├── belongsTo → Booking (booking_id)
├── belongsTo → Stay (stay_id)
├── belongsTo → Room (room_id)
├── belongsTo → User (assigned_by)
│
ServiceRecoveryCase
├── belongsTo → Booking (booking_id)
├── belongsTo → Stay (stay_id, nullable)
├── belongsTo → User (handled_by)
│
Review
├── belongsTo → Room (room_id)
├── belongsTo → User (user_id)
├── belongsTo → Booking (booking_id)    // required, non-nullable
│
ContactMessage (standalone, no FK relationships)
│
PolicyDocument (standalone, no FK relationships)
│
AiProposalEvent
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
