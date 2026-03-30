# Agent Self-Learning Memory — Illustrative Examples

> **CRITICAL READ-BEFORE-USE NOTICE**
>
> These entries are ILLUSTRATIVE EXAMPLES for training agents on correct schema format and
> entry quality. They are NOT historical facts. They are NOT evidence of past failures.
> They must NOT be read as operational learnings. Agents must NOT cite them.
>
> Rule G-06 (AGENT_LEARNINGS_OPERATING_RULES.md) governs this file: it must never be read
> as operational learnings, and example entries must never be cited as historical failures.
> This file is for schema training only.

---

## EX-01 — Booking Overlap: Closed Interval Instead of Half-Open

> [ILLUSTRATIVE EXAMPLE — NOT A REAL EVENT]

---
id: SL-EX-01
date: 2026-03-30
status: ACTIVE
confidence: CONFIRMED
area: booking
task_type: "booking availability check"
trigger: "Availability query returned false positive conflict for same-day turnover — agent constructed WHERE clause using closed interval [a,b] instead of half-open [a,b)."
mistake: "Agent used `existing.check_in <= new.check_out AND existing.check_out >= new.check_in` (closed interval) when constructing the overlap WHERE clause, incorrectly treating same-day checkout/check-in as a conflict."
impact: "Valid same-day turnover reservation was rejected. Guest B's check-in on the day Guest A checks out was blocked by the query even though no actual night overlap existed."
evidence: "[ILLUSTRATIVE] app/Services/BookingService.php:94 — overlap query constructed with `<=` and `>=` comparators; ARCHITECTURE_FACTS.md line 11 specifies half-open interval `[check_in, check_out)`"
applicability: "Any task that constructs or modifies the booking availability WHERE clause or the overlap detection query."
related_invariants: [INV-01, INV-02]
related_commands:
  - "php artisan test --filter=BookingOverlapTest"
  - "php artisan test --filter=SameDayTurnoverTest"
stale_after: 2026-06-28
promotion_rule: "If confirmed in 3+ separate tasks, migrate to ARCHITECTURE_FACTS.md section: Booking Domain > Overlap Prevention."
review_status: PEER_REVIEWED
tags: [booking, overlap, interval, half-open, availability]
---

### Incorrect Pattern
```php
// WRONG — closed interval: blocks valid same-day turnover
$query->where('check_in', '<=', $newCheckOut)
      ->where('check_out', '>=', $newCheckIn);
```

### Corrected Pattern
```php
// CORRECT — half-open interval [check_in, check_out): same-day turnover is valid
// existing.check_in < new.check_out AND existing.check_out > new.check_in
$query->where('check_in', '<', $newCheckOut)
      ->where('check_out', '>', $newCheckIn);
```

### Notes
The PostgreSQL exclusion constraint also uses `daterange(check_in, check_out, '[)')` — the half-open semantics must be consistent between the PHP query and the DB constraint. Verify both layers when changing overlap logic.

---

## EX-02 — SQLite Test Used to Confirm PostgreSQL Exclusion Constraint

> [ILLUSTRATIVE EXAMPLE — NOT A REAL EVENT]

---
id: SL-EX-02
date: 2026-03-30
status: ACTIVE
confidence: CONFIRMED
area: testing
task_type: "booking overlap constraint verification"
trigger: "Agent reported 'overlap check passes' after a booking domain change, citing a SQLite-backed test run as proof the PostgreSQL exclusion constraint was intact."
mistake: "Agent ran the test suite against the SQLite driver and cited a passing `BookingOverlapTest` as confirmation that the PostgreSQL exclusion constraint (`no_overlapping_bookings`) was functioning correctly."
impact: "The PostgreSQL exclusion constraint was not verified. A deploy to a PostgreSQL environment could have revealed the constraint was missing or misconfigured, allowing double-bookings that PHP-layer overlap logic failed to catch."
evidence: "[ILLUSTRATIVE] phpunit.xml driver=sqlite; migration `2026_02_12_000001_fix_overlapping_bookings_constraint_soft_deletes.php` uses PostgreSQL-only `EXCLUDE USING gist` syntax which is skipped under SQLite"
applicability: "Any task that adds, modifies, or verifies the booking exclusion constraint or overlap detection logic."
related_invariants: [INV-03, INV-10]
related_commands:
  - "docker compose up -d db"
  - "php artisan test --filter=BookingOverlapTest"
  - "psql -c \"\\d+ bookings\""
stale_after: 2026-06-28
promotion_rule: "If confirmed in 3+ tasks, migrate to CONTRACT.md section: DoD: Booking Domain Changes."
review_status: PEER_REVIEWED
tags: [testing, postgresql, overlap, constraint, sqlite]
---

### Incorrect Pattern
```bash
# WRONG — SQLite run does not prove PostgreSQL constraint behavior
php artisan test --filter=BookingOverlapTest
# PASS reported — but DB_CONNECTION=sqlite, exclusion constraint was never exercised
```

### Corrected Pattern
```bash
# CORRECT — run against PostgreSQL to verify exclusion constraint
docker compose up -d db
DB_CONNECTION=pgsql php artisan test --filter=BookingOverlapTest
# Also verify constraint exists in schema:
psql -U soleil_user -d soleil_hostel -c "\d+ bookings"
# Expected: "no_overlapping_bookings" exclusion constraint listed
```

### Notes
`phpunit.xml` in this repo defaults to PostgreSQL (H-06). Always run `docker compose up -d db` before the test suite. The SQLite configuration (`phpunit.pgsql.xml`) remains available as opt-in but must not be used to claim PostgreSQL constraint coverage.

---

## EX-03 — RBAC Verified Only at React Route Guard Layer

> [ILLUSTRATIVE EXAMPLE — NOT A REAL EVENT]

---
id: SL-EX-03
date: 2026-03-30
status: ACTIVE
confidence: CONFIRMED
area: rbac
task_type: "RBAC middleware verification"
trigger: "Agent confirmed an admin-only endpoint was 'protected' by reviewing only the React `AdminRoute` component, without checking the Laravel middleware stack on the corresponding API route."
mistake: "Agent verified RBAC enforcement solely by reading the React route guard (`AdminRoute.tsx`) and concluded the endpoint was protected, without inspecting the Laravel `role:moderator` middleware assignment in `routes/v1.php`."
impact: "An authorization gap remained open: the API endpoint was callable directly (bypassing the React SPA) without any server-side role check. Any client with a valid Bearer token could reach the admin-only resource."
evidence: "[ILLUSTRATIVE] routes/v1.php — admin booking routes missing `role:moderator` middleware group; frontend/src/features/auth/AdminRoute.tsx — React guard present but decorative at API level"
applicability: "Any task that adds, modifies, or verifies access control on API endpoints."
related_invariants: [INV-08]
related_commands:
  - "php artisan route:list --path=api/v1/admin"
  - "php artisan test --filter=AdminBookingAuthorizationTest"
stale_after: 2026-06-28
promotion_rule: "If confirmed in 3+ tasks, migrate to docs/PERMISSION_MATRIX.md as a verification checklist step."
review_status: PEER_REVIEWED
tags: [rbac, authorization, middleware, frontend, api-contract]
---

### Incorrect Pattern
```tsx
// WRONG — checking only the React guard and concluding RBAC is enforced
// AdminRoute.tsx redirects non-admin users in the SPA...
// ...but this does NOT protect the API endpoint from direct HTTP calls
<AdminRoute>
  <AdminDashboard />
</AdminRoute>
```

### Corrected Pattern
```php
// CORRECT — verify RBAC at the Laravel middleware layer in routes/v1.php
Route::middleware(['auth:sanctum', 'role:moderator'])->group(function () {
    Route::get('/admin/bookings', [AdminBookingController::class, 'index']);
});
// Also verify with:
// php artisan route:list --path=api/v1/admin
// Confirm middleware column shows: sanctum, role:moderator
```

### Notes
The React route guard is decorative from a security perspective. The authoritative RBAC enforcement is the Laravel middleware stack. Both layers must be present, but only the backend layer constitutes actual protection.

---

## EX-04 — Optimistic Locking Applied Inside a Booking Write Path

> [ILLUSTRATIVE EXAMPLE — NOT A REAL EVENT]

---
id: SL-EX-04
date: 2026-03-30
status: ACTIVE
confidence: CONFIRMED
area: locking
task_type: "booking-write mutation"
trigger: "Agent implemented a booking creation flow using `lock_version` compare-and-swap semantics, applying the pattern from room/location edits to booking writes."
mistake: "Agent applied optimistic locking (`lock_version` compare-and-swap) inside the booking creation transaction instead of pessimistic locking (`lockForUpdate()`), using the wrong concurrency pattern for the booking domain."
impact: "Under concurrent booking attempts for the same room, both requests could read the same `lock_version`, pass the compare-and-swap check, and both proceed — the PostgreSQL exclusion constraint would catch the conflict at DB level, but the application would surface a constraint violation exception instead of a clean business-level conflict response."
evidence: "[ILLUSTRATIVE] ARCHITECTURE_FACTS.md lines 129-136 — optimistic locking is for rooms/locations; pessimistic locking (lockForUpdate) is for booking creation/cancellation; CancellationService.php:118"
applicability: "Any task that writes to the bookings table, including creation, cancellation, and status transitions."
related_invariants: [INV-04, INV-05]
related_commands:
  - "php artisan test --filter=ConcurrentBookingTest"
  - "grep -n 'lockForUpdate' backend/app/Services/BookingService.php"
stale_after: 2026-06-28
promotion_rule: "If confirmed in 3+ tasks, migrate to ARCHITECTURE_FACTS.md section: Concurrency Control."
review_status: PEER_REVIEWED
tags: [locking, booking, transaction, pessimistic-lock, concurrency]
---

### Incorrect Pattern
```php
// WRONG — optimistic locking (compare-and-swap) in a booking write path
DB::transaction(function () use ($data, $lockVersion) {
    $booking = Booking::where('id', $data['id'])
        ->where('lock_version', $lockVersion)
        ->firstOrFail();
    // ... proceed with write
    $booking->increment('lock_version');
});
```

### Corrected Pattern
```php
// CORRECT — pessimistic locking (SELECT FOR UPDATE) in booking writes
DB::transaction(function () use ($roomId, $checkIn, $checkOut) {
    // Lock the room row to prevent concurrent overlap
    $room = Room::where('id', $roomId)->lockForUpdate()->firstOrFail();
    // Check overlap after acquiring lock
    $overlap = Booking::overlapping($checkIn, $checkOut)
        ->where('room_id', $roomId)
        ->exists();
    if ($overlap) {
        throw new BookingConflictException();
    }
    // ... create booking
});
```

### Notes
Optimistic locking (`lock_version`) is the correct pattern for `rooms` and `locations` edits where contention is low. Pessimistic locking (`lockForUpdate()`) is required for booking writes where the cost of a conflict is a double-booking. Never swap these patterns.

---

## EX-05 — Learning Entry Duplicated an Invariant Already in ARCHITECTURE_FACTS.md

> [ILLUSTRATIVE EXAMPLE — NOT A REAL EVENT]

---
id: SL-EX-05
date: 2026-03-30
status: ACTIVE
confidence: CONFIRMED
area: docs
task_type: "learning entry authoring"
trigger: "Agent wrote a new AGENT_LEARNINGS entry stating 'booking overlap uses half-open interval [check_in, check_out)' — content already owned and stated in ARCHITECTURE_FACTS.md."
mistake: "Agent wrote an ACTIVE learning entry restating the half-open interval invariant from ARCHITECTURE_FACTS.md, creating a divergent copy that future agents might cite instead of the canonical source."
impact: "Two sources now claim to own the same truth. If ARCHITECTURE_FACTS.md is ever updated and the AGENT_LEARNINGS entry is not, agents reading the stale copy will operate on wrong invariant state."
evidence: "[ILLUSTRATIVE] ARCHITECTURE_FACTS.md line 11: 'Half-open interval: [check_in, check_out)'; proposed AGENT_LEARNINGS entry SL-NNN body contained identical claim with no additional operational context"
applicability: "Before writing any learning entry: verify the content is not already stated in ARCHITECTURE_FACTS.md or CONTRACT.md."
related_invariants: [INV-01]
related_commands:
  - "grep -n 'half-open\\|check_in\\|check_out' docs/agents/ARCHITECTURE_FACTS.md"
stale_after: 2026-06-28
promotion_rule: "Not applicable — this entry is about the write process itself, not a domain failure. Archive after 90 days if no recurrence."
review_status: PEER_REVIEWED
tags: [docs, overlap, booking, duplicate-content, write-rules]
---

### Incorrect Pattern
```markdown
<!-- WRONG — writing a new AGENT_LEARNINGS entry that restates an invariant -->
## Active Entries

---
id: SL-042
mistake: "Agent used closed interval when overlap check requires half-open [a,b)."
<!-- This fact is already in ARCHITECTURE_FACTS.md. Writing it here creates a
     divergent copy and violates W-02(b). -->
```

### Corrected Pattern
```markdown
<!-- CORRECT — reference the existing canonical source instead -->
# Do NOT write an entry if the learning is already in ARCHITECTURE_FACTS.md.
# Per W-02(b): "The learning is already stated in ARCHITECTURE_FACTS.md or CONTRACT.md
#              — reference it there, do not duplicate."
# Instead: point to ARCHITECTURE_FACTS.md in your task summary.
```

### Notes
Rule G-01 states ARCHITECTURE_FACTS.md always wins when AGENT_LEARNINGS.md conflicts with it. Writing a duplicate accelerates drift. If the entry adds genuinely new operational context beyond the invariant (e.g., a specific incorrect code path that caused a failure), it may be justified — but the entry must not re-own the invariant claim.

---

## EX-06 — Redis Cache Read After Booking Mutation Reported Stale State as Current

> [ILLUSTRATIVE EXAMPLE — NOT A REAL EVENT]

---
id: SL-EX-06
date: 2026-03-30
status: ACTIVE
confidence: CONFIRMED
area: cache
task_type: "booking state read after mutation"
trigger: "After a booking cancellation, agent read booking state from Redis cache to confirm the cancellation succeeded and reported the booking as still 'confirmed' — cache had not been invalidated."
mistake: "Agent read booking state from Redis cache immediately after a cancellation mutation and reported the pre-mutation state as current, because the cache invalidation step had not yet executed."
impact: "Agent reported booking status as 'confirmed' when it had been cancelled. Any downstream logic relying on this read (e.g., overlap checks, availability reports) would have operated on stale state."
evidence: "[ILLUSTRATIVE] redis-cli GET booking:42:status → 'confirmed' (stale); database SELECT status FROM bookings WHERE id=42 → 'cancelled'; cache TTL not yet expired"
applicability: "Any task that reads booking state after a booking write mutation. Redis cache is non-authoritative for booking state."
related_invariants: [INV-06]
related_commands:
  - "php artisan test --filter=BookingCancellationTest"
  - "redis-cli GET booking:{id}:status"
stale_after: 2026-06-28
promotion_rule: "If confirmed in 3+ tasks, add a note to ARCHITECTURE_FACTS.md: Redis is non-authoritative for booking state; always read from PostgreSQL for post-mutation verification."
review_status: PEER_REVIEWED
tags: [cache, booking, redis, stale-state, invalidation]
---

### Incorrect Pattern
```php
// WRONG — reading booking state from Redis after a mutation
$status = Cache::get("booking:{$bookingId}:status");
// Returns stale 'confirmed' even after cancellation — cache not yet invalidated
if ($status === 'confirmed') {
    // ... acts on stale data
}
```

### Corrected Pattern
```php
// CORRECT — read from the database (authoritative source) after any booking mutation
$booking = Booking::find($bookingId); // direct DB read
if ($booking->status === 'cancelled') {
    // ... act on authoritative state
}
// Redis cache should be invalidated by the service layer immediately after mutation.
// Never use cache as the read source for post-mutation state verification.
```

### Notes
Redis is non-authoritative for booking state. After any booking write (creation, confirmation, cancellation), always read from PostgreSQL to verify the mutation result. Cache invalidation is a separate concern from authoritative state reads.

---

## EX-07 — "Review System Complete" Reported Without Verifying booking_id FK Constraint

> [ILLUSTRATIVE EXAMPLE — NOT A REAL EVENT]

---
id: SL-EX-07
date: 2026-03-30
status: ACTIVE
confidence: CONFIRMED
area: review_system
task_type: "migration authoring"
trigger: "Agent reported 'review system implementation complete' after writing the reviews table migration, without verifying that the `booking_id` foreign key constraint and NOT NULL requirement were present."
mistake: "Agent reported the review feature as complete after creating the `reviews` table migration, but did not verify that `booking_id` was NOT NULL with a foreign key to `bookings.id` — the DB-level enforcement of one-review-per-confirmed-booking."
impact: "Reviews could have been created without a booking reference, violating the invariant that every review must be linked to a booking. The missing FK and NOT NULL constraint would not have been caught until a review was submitted without a booking ID."
evidence: "[ILLUSTRATIVE] migration file created without `$table->foreignId('booking_id')->constrained()->comment('...')`; ARCHITECTURE_FACTS.md line 189: 'booking_id is NOT NULL (migration 2026_02_10_000002)'"
applicability: "Any task that creates or modifies the reviews table migration, or adds review creation logic."
related_invariants: [INV-07]
related_commands:
  - "php artisan migrate:status"
  - "psql -c \"\\d+ reviews\""
  - "php artisan test --filter=ReviewMigrationTest"
stale_after: 2026-06-28
promotion_rule: "If confirmed in 3+ tasks, add to CONTRACT.md DoD: Migration Changes as a reviews-specific checklist item."
review_status: PEER_REVIEWED
tags: [review_system, migrations, booking, foreign-key, constraint]
---

### Incorrect Pattern
```php
// WRONG — reviews migration missing booking_id FK and NOT NULL
Schema::create('reviews', function (Blueprint $table) {
    $table->id();
    $table->foreignId('room_id')->constrained();
    $table->foreignId('user_id')->constrained();
    $table->integer('rating');
    $table->text('comment')->nullable();
    // booking_id missing — no link to a booking, no DB-level enforcement
    $table->timestamps();
});
```

### Corrected Pattern
```php
// CORRECT — booking_id is NOT NULL with FK; unique constraint enforces one review per booking
Schema::create('reviews', function (Blueprint $table) {
    $table->id();
    $table->foreignId('booking_id')->constrained()->comment('One review per booking; NOT NULL');
    $table->foreignId('room_id')->constrained()->onDelete('restrict');
    $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
    $table->integer('rating');
    $table->text('comment')->nullable();
    $table->boolean('approved')->default(false);
    $table->timestamps();
    $table->unique('booking_id'); // one review per booking
});
```

### Notes
The `booking_id` NOT NULL + FK + unique constraint is the DB-level enforcement of INV-07. All three components (NOT NULL, FK, unique) are required. Verify with `psql -c "\d+ reviews"` after running the migration.

---

## EX-08 — API Contract Inferred from TypeScript Interface, Laravel Resource Had Diverged

> [ILLUSTRATIVE EXAMPLE — NOT A REAL EVENT]

---
id: SL-EX-08
date: 2026-03-30
status: ACTIVE
confidence: CONFIRMED
area: api_contract
task_type: "frontend API contract sync"
trigger: "Agent updated a frontend feature using the TypeScript `BookingResource` interface as the source of truth for the API response shape, without reading the corresponding Laravel `BookingResource` class."
mistake: "Agent inferred the API response shape from the TypeScript interface `BookingResource` in `booking.api.ts` without verifying it against the Laravel `BookingResource` PHP class — the two had diverged after a backend refactor."
impact: "Frontend sent a request body with field name `room_id` (per TypeScript interface) but the Laravel Resource expected `roomId` (camelCase — introduced during a refactor). The API returned a 422 validation error in production."
evidence: "[ILLUSTRATIVE] frontend/src/features/booking/booking.api.ts:BookingResource — field `room_id`; app/Http/Resources/BookingResource.php:toArray() — field `roomId`; git log shows Resource refactor in commit abc1234 not reflected in TypeScript types"
applicability: "Any task that modifies, reads, or verifies the shape of API request/response objects for the booking, room, or location domains."
related_invariants: [INV-10]
related_commands:
  - "php artisan test --filter=BookingResourceTest"
  - "npx tsc --noEmit"
  - "grep -n 'room_id\\|roomId' backend/app/Http/Resources/BookingResource.php frontend/src/features/booking/booking.api.ts"
stale_after: 2026-06-28
promotion_rule: "If confirmed in 3+ tasks, add to docs/frontend/SERVICES_LAYER.md: always verify Laravel Resource against TypeScript interface before frontend work."
review_status: PEER_REVIEWED
tags: [api-contract, frontend, type-safety, booking, resource-sync]
---

### Incorrect Pattern
```typescript
// WRONG — inferring API shape from TypeScript interface without checking Laravel Resource
// booking.api.ts — TypeScript interface (potentially stale)
interface BookingResource {
  room_id: number;   // <-- may have diverged from Laravel Resource
  check_in: string;
  check_out: string;
}
// Agent used this interface as source of truth without reading BookingResource.php
```

### Corrected Pattern
```php
// CORRECT — check the Laravel Resource first (authoritative API shape)
// app/Http/Resources/BookingResource.php
public function toArray(Request $request): array
{
    return [
        'roomId'   => $this->room_id,   // <-- camelCase as returned by API
        'checkIn'  => $this->check_in,
        'checkOut' => $this->check_out,
    ];
}
```
```typescript
// Then update the TypeScript interface to match exactly
interface BookingResource {
  roomId: number;    // matches Laravel Resource
  checkIn: string;
  checkOut: string;
}
```

### Notes
The Laravel Resource class is always authoritative for the API response shape. The TypeScript interface is a consumer-side representation that must be kept in sync manually. When both exist, read the PHP Resource first, then verify the TypeScript interface matches.
