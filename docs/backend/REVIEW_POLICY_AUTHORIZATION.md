# ReviewPolicy Authorization Design Document

**Status**: Production-Ready Design  
**Target**: Laravel 11 — Soleil Hostel Management System  
**Scope**: Backend Authorization ONLY (Review CRUD via Policy)

---

## High-Level Summary

- **Policy-first design**: All Review auth logic lives in `ReviewPolicy`; zero auth in Controllers/Services
- **Enum-only checks**: `BookingStatus::CONFIRMED` and `UserRole::ADMIN` — no string literals anywhere
- **Admin bypass via `before()`**: Full delete access; explicitly denied create (no fake reviews)
- **Zero DB queries in policy**: Ownership, status, and uniqueness checks use pre-loaded relations only
- **Uniqueness via pre-loaded relation**: `$booking->review` relation (hasOne) checked for existence — no query
- **Completed booking = checkout has passed**: `check_out < now()` — not just status
- **`Illuminate\Auth\Access\Response` for denials**: Explicit failure messages for debugging/logging
- **DB-level unique constraint as final guard**: `UNIQUE(booking_id)` catches race conditions the policy cannot prevent
- **Testable in isolation**: Policy methods accept model instances with pre-set properties — no Eloquent dependency

---

## 1. Laravel Provides vs. You Configure vs. Do Not Touch

### Out-of-Box (Framework Provides)

| Feature                                  | Location                                       | Notes                                                    |
| ---------------------------------------- | ---------------------------------------------- | -------------------------------------------------------- |
| Policy hook methods                      | `before()`, `create()`, `update()`, `delete()` | Automatic invocation via Gate                            |
| Policy auto-discovery                    | `App\Policies\{Model}Policy` naming convention | Laravel 11 auto-registers if naming matches              |
| `Response::allow()` / `Response::deny()` | `Illuminate\Auth\Access\Response`              | Rich authorization responses                             |
| `$this->authorize()` in controllers      | `AuthorizesRequests` trait                     | Delegates to Gate → Policy                               |
| Gate supportsClass                       | Automatic model-to-policy resolution           | No explicit mapping needed if naming convention followed |

### You Must Configure

| Item                                  | When                                          | Implementation                                                 |
| ------------------------------------- | --------------------------------------------- | -------------------------------------------------------------- |
| `AuthServiceProvider::$policies`      | If policy class name deviates from convention | Not required here — `ReviewPolicy` follows convention          |
| Pre-loading `booking.review` relation | Before policy invocation                      | Controller/Service responsibility                              |
| `before()` hook logic                 | Admin bypass rules                            | Custom — blocks admin create, allows admin delete              |
| DB unique constraint on `booking_id`  | Migration                                     | Race condition guard — policy is advisory, DB is authoritative |

### Do Not Touch (Anti-Customization)

| Item                                    | Why                                                                                 |
| --------------------------------------- | ----------------------------------------------------------------------------------- |
| Gate internals                          | Framework-tested; customization breaks caching & response handling                  |
| Overriding `HandlesAuthorization` trait | Breaks `Response` object expectations; framework uses it for middleware integration |
| Policy constructor DI for queries       | Violates testability requirement; policies must be stateless re: DB                 |

---

## 2. Business Rules → Policy Method Mapping

### Rule Matrix

| Rule                                | Policy Method                 | Check                                                         | Failure Response                                            |
| ----------------------------------- | ----------------------------- | ------------------------------------------------------------- | ----------------------------------------------------------- |
| Auth user only                      | Implicit (Gate requires auth) | `$user !== null`                                              | 401 (middleware level)                                      |
| Own the booking                     | `create()`                    | `$booking->user_id === $user->id`                             | `Response::deny('You do not own this booking.')`            |
| Booking completed (checkout passed) | `create()`                    | `$booking->check_out->isPast()`                               | `Response::deny('Cannot review before checkout.')`          |
| Booking status is CONFIRMED         | `create()`                    | `$booking->status === BookingStatus::CONFIRMED`               | `Response::deny('Booking must be confirmed.')`              |
| No existing review                  | `create()`                    | `$booking->review === null`                                   | `Response::deny('Review already exists for this booking.')` |
| Owner can update                    | `update()`                    | `$review->user_id === $user->id`                              | `Response::deny('You do not own this review.')`             |
| Owner or Admin can delete           | `delete()`                    | Ownership check OR admin bypass                               | `Response::deny('You cannot delete this review.')`          |
| Admin cannot create fake reviews    | `before()`                    | Return `null` (defer) for create; admin check only for delete | `Response::deny('Admins cannot create reviews.')`           |

### Pre-Loaded Relation Strategy

```
Booking model:
  - hasOne(Review::class, 'booking_id') → $booking->review
  - user_id (ownership)
  - status (BookingStatus enum)
  - check_out (Carbon date)

Review model:
  - belongsTo(Booking::class) → $review->booking
  - user_id (review author)
```

**Caller responsibility**: Load `$booking->load('review')` before calling `Gate::allows('create', [Review::class, $booking])`.

The policy NEVER calls `$booking->review()->exists()` — it checks `$booking->review === null` on the already-loaded relation.

---

## 3. Full Lifecycle Flow

### Sequence: Create Review (Success Path)

```
User → Controller@store
  │
  ├─ Controller: $booking = Booking::with('review')->findOrFail($id)
  │
  ├─ Controller: $this->authorize('create', [Review::class, $booking])
  │     │
  │     └─ Gate → ReviewPolicy::before($user, 'create')
  │           │   → User is not ADMIN → returns null (defer)
  │           │
  │           └─ ReviewPolicy::create($user, $booking)
  │                 ├─ Check: $booking->user_id === $user->id ✓
  │                 ├─ Check: $booking->status === CONFIRMED ✓
  │                 ├─ Check: $booking->check_out->isPast() ✓
  │                 ├─ Check: $booking->review === null ✓
  │                 └─ Return: Response::allow()
  │
  ├─ Controller: $review = $reviewService->create($booking, $data)
  │     └─ DB INSERT (unique constraint on booking_id is final guard)
  │
  └─ Return: 201 Created
```

### Sequence: Delete Review (Admin Bypass)

```
Admin → Controller@destroy
  │
  ├─ Controller: $review = Review::findOrFail($id)
  │
  ├─ Controller: $this->authorize('delete', $review)
  │     │
  │     └─ Gate → ReviewPolicy::before($user, 'delete')
  │           │   → User is ADMIN + ability is 'delete'
  │           └─ Return: true (bypass all further checks)
  │
  ├─ Controller: $reviewService->delete($review)
  │
  └─ Return: 204 No Content
```

### Sequence: Create Review (Failure — Already Exists)

```
User → Controller@store
  │
  ├─ Controller: $booking = Booking::with('review')->findOrFail($id)
  │                         → $booking->review is NOT null
  │
  ├─ Controller: $this->authorize('create', [Review::class, $booking])
  │     │
  │     └─ Gate → ReviewPolicy::create($user, $booking)
  │           └─ Check: $booking->review === null ✗
  │           └─ Return: Response::deny('Review already exists...')
  │
  ├─ Gate throws AuthorizationException
  │
  └─ Return: 403 Forbidden + message
```

---

## 4. Implementation (Step-by-Step)

### Step 1: Add `review` Relation to Booking Model

**File**: [backend/app/Models/Booking.php](backend/app/Models/Booking.php)

```php
// Add after existing relationships (around line 95)
use Illuminate\Database\Eloquent\Relations\HasOne;

public function review(): HasOne
{
    return $this->hasOne(Review::class, 'booking_id');
}
```

**Rationale**: Enables `$booking->review` check without query when pre-loaded. HasOne enforces cardinality at ORM level.

---

### Step 2: Add `booking` Relation to Review Model (if missing)

**File**: [backend/app/Models/Review.php](backend/app/Models/Review.php)

```php
// Add after user() relationship
public function booking(): BelongsTo
{
    return $this->belongsTo(Booking::class);
}
```

**Rationale**: Enables reverse navigation for update/delete ownership checks via `$review->booking->user_id`.

---

### Step 3: Create ReviewPolicy

**File**: `backend/app/Policies/ReviewPolicy.php`

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Review;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ReviewPolicy
{
    /**
     * Admin bypass for delete only. Admins CANNOT create fake reviews.
     */
    public function before(User $user, string $ability): ?bool
    {
        if (!$user->isAdmin()) {
            return null; // Defer to specific method
        }

        // Admin can delete any review
        if ($ability === 'delete') {
            return true;
        }

        // Admin cannot create reviews (prevent fake reviews)
        if ($ability === 'create') {
            return false; // Explicit denial
        }

        return null; // Defer update to normal ownership check
    }

    /**
     * Create: Owner + confirmed + checkout passed + no existing review.
     *
     * @param User $user Authenticated user
     * @param Booking $booking Pre-loaded with 'review' relation
     */
    public function create(User $user, Booking $booking): Response
    {
        // Ownership check
        if ($booking->user_id !== $user->id) {
            return Response::deny('You do not own this booking.');
        }

        // Status check (must be CONFIRMED, not pending/cancelled)
        if ($booking->status !== BookingStatus::CONFIRMED) {
            return Response::deny('Booking must be confirmed to leave a review.');
        }

        // Temporal check (checkout must have passed)
        if (!$booking->check_out->isPast()) {
            return Response::deny('Cannot review before checkout date.');
        }

        // Uniqueness check via pre-loaded relation (NO QUERY)
        if ($booking->relationLoaded('review') && $booking->review !== null) {
            return Response::deny('Review already exists for this booking.');
        }

        return Response::allow();
    }

    /**
     * Update: Owner only (admin defers here from before()).
     */
    public function update(User $user, Review $review): Response
    {
        if ($review->user_id !== $user->id) {
            return Response::deny('You do not own this review.');
        }

        return Response::allow();
    }

    /**
     * Delete: Owner only (admin bypasses via before()).
     */
    public function delete(User $user, Review $review): Response
    {
        if ($review->user_id !== $user->id) {
            return Response::deny('You do not own this review.');
        }

        return Response::allow();
    }

    /**
     * View: Public (or auth-required based on business needs).
     */
    public function view(?User $user, Review $review): bool
    {
        return true; // Reviews are public
    }

    /**
     * ViewAny: Public index of reviews.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }
}
```

**Rationale**:

- `before()` returns `false` for admin create (not `null`) — explicit denial prevents fallthrough
- `relationLoaded()` guard ensures we don't accidentally trigger lazy-load query
- All checks use enum comparisons, never strings

---

### Step 4: Register Policy (Optional — Convention Works)

**File**: [backend/app/Providers/AuthServiceProvider.php](backend/app/Providers/AuthServiceProvider.php)

```php
// Add to $policies array (around line 20)
use App\Models\Review;
use App\Policies\ReviewPolicy;

protected $policies = [
    Booking::class => BookingPolicy::class,
    Room::class => RoomPolicy::class,
    Review::class => ReviewPolicy::class, // Explicit for clarity
];
```

**Rationale**: Laravel 11 auto-discovers `ReviewPolicy` for `Review` model, but explicit registration aids code navigation and removes ambiguity.

---

### Step 5: Add DB Unique Constraint (Migration)

**File**: New migration or modify existing

```php
// In up() method
Schema::table('reviews', function (Blueprint $table) {
    $table->unique('booking_id', 'reviews_booking_id_unique');
});
```

**Rationale**: Policy is advisory (app-level); DB constraint is authoritative (catches race conditions). If two concurrent creates pass policy, only one INSERT succeeds.

---

## 5. Failure Modes & Edge Cases

| Scenario                            | Outcome                                                              | Handler                                                  |
| ----------------------------------- | -------------------------------------------------------------------- | -------------------------------------------------------- |
| Unauthenticated user                | 401 Unauthorized                                                     | `auth` middleware (before policy)                        |
| Non-owner attempts create           | 403 + "You do not own this booking"                                  | Policy `create()`                                        |
| Booking status PENDING              | 403 + "Booking must be confirmed"                                    | Policy `create()`                                        |
| Booking status CANCELLED            | 403 + "Booking must be confirmed"                                    | Policy `create()`                                        |
| Checkout in future                  | 403 + "Cannot review before checkout"                                | Policy `create()`                                        |
| Review already exists               | 403 + "Review already exists"                                        | Policy `create()`                                        |
| Admin attempts create               | 403 (from `before()` returning `false`)                              | Policy `before()`                                        |
| Non-owner attempts update           | 403 + "You do not own this review"                                   | Policy `update()`                                        |
| Non-owner (non-admin) delete        | 403 + "You do not own this review"                                   | Policy `delete()`                                        |
| Soft-deleted booking                | Depends on query scope; if `withTrashed()` used, policy still checks | Caller responsibility                                    |
| Soft-deleted user (review author)   | Review persists; `$review->user` may be null                         | Handle in UI/API layer                                   |
| Race condition (concurrent creates) | One succeeds, one fails at DB level                                  | DB unique constraint → catch `QueryException` in service |
| Booking relation not pre-loaded     | `relationLoaded()` guard prevents query; policy passes (unsafe)      | Caller MUST pre-load                                     |

### Allowed Intermediate States

- Booking CONFIRMED + checkout not passed → Review cannot be created (temporal constraint)
- Booking CANCELLED after review created → Review persists (orphan allowed for historical record)
- User deleted after review → Review persists with `user_id` intact (nullable join)

### Why Some Checks Must NOT Be Transactional

The uniqueness check (`$booking->review === null`) is **intentionally non-transactional** in the policy:

- Policy runs before INSERT; it's a gate, not a lock
- DB unique constraint is the transactional guard
- Acquiring row locks in policy methods violates single-responsibility and testability

---

## 6. Testing Strategy

### Manual Verification Checklist

- [ ] Create review as booking owner after checkout → 201
- [ ] Create review before checkout → 403 + correct message
- [ ] Create review on non-owned booking → 403
- [ ] Create duplicate review → 403
- [ ] Admin create review → 403 (before hook denial)
- [ ] Admin delete any review → 204 (bypass)
- [ ] Owner delete own review → 204
- [ ] Non-owner delete → 403
- [ ] Concurrent create (two tabs) → one 201, one 500 (DB constraint)

### Unit Test: ReviewPolicyTest

**File**: `backend/tests/Unit/Policies/ReviewPolicyTest.php`

```php
<?php

namespace Tests\Unit\Policies;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Review;
use App\Models\User;
use App\Policies\ReviewPolicy;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class ReviewPolicyTest extends TestCase
{
    private ReviewPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new ReviewPolicy();
    }

    public function test_create_allowed_for_owner_with_completed_booking(): void
    {
        $user = $this->makeUser(1, UserRole::USER);
        $booking = $this->makeBooking(1, BookingStatus::CONFIRMED, Carbon::yesterday());
        $booking->setRelation('review', null);

        $response = $this->policy->create($user, $booking);

        $this->assertTrue($response->allowed());
    }

    public function test_create_denied_for_non_owner(): void
    {
        $user = $this->makeUser(1, UserRole::USER);
        $booking = $this->makeBooking(2, BookingStatus::CONFIRMED, Carbon::yesterday());
        $booking->setRelation('review', null);

        $response = $this->policy->create($user, $booking);

        $this->assertFalse($response->allowed());
        $this->assertStringContainsString('do not own', $response->message());
    }

    public function test_create_denied_before_checkout(): void
    {
        $user = $this->makeUser(1, UserRole::USER);
        $booking = $this->makeBooking(1, BookingStatus::CONFIRMED, Carbon::tomorrow());
        $booking->setRelation('review', null);

        $response = $this->policy->create($user, $booking);

        $this->assertFalse($response->allowed());
        $this->assertStringContainsString('checkout', $response->message());
    }

    public function test_create_denied_when_review_exists(): void
    {
        $user = $this->makeUser(1, UserRole::USER);
        $booking = $this->makeBooking(1, BookingStatus::CONFIRMED, Carbon::yesterday());
        $booking->setRelation('review', new Review());

        $response = $this->policy->create($user, $booking);

        $this->assertFalse($response->allowed());
        $this->assertStringContainsString('already exists', $response->message());
    }

    public function test_admin_create_denied_via_before(): void
    {
        $user = $this->makeUser(1, UserRole::ADMIN);

        $result = $this->policy->before($user, 'create');

        $this->assertFalse($result);
    }

    public function test_admin_delete_allowed_via_before(): void
    {
        $user = $this->makeUser(1, UserRole::ADMIN);

        $result = $this->policy->before($user, 'delete');

        $this->assertTrue($result);
    }

    // Helper: Create fake User without DB
    private function makeUser(int $id, UserRole $role): User
    {
        $user = new User();
        $user->id = $id;
        $user->role = $role;
        return $user;
    }

    // Helper: Create fake Booking without DB
    private function makeBooking(int $userId, BookingStatus $status, Carbon $checkOut): Booking
    {
        $booking = new Booking();
        $booking->user_id = $userId;
        $booking->status = $status;
        $booking->check_out = $checkOut;
        return $booking;
    }
}
```

### What Is NOT Unit-Tested (and Why)

| Item                     | Reason                                                    |
| ------------------------ | --------------------------------------------------------- |
| DB unique constraint     | Integration concern; tested in Feature tests with real DB |
| `relationLoaded()` guard | Edge case for caller error; covered by integration test   |
| Full Gate → Policy flow  | Feature test with `$this->actingAs()->post()`             |
| Soft-delete scenarios    | Requires DB; Feature test territory                       |

---

## 7. Decision Log

### Policy vs Middleware vs Gates

**Decision**: Policy wins for domain-specific authorization.

- **Middleware**: Appropriate for cross-cutting concerns (auth, rate-limit). Cannot access model instances cleanly.
- **Gates**: Good for simple role checks (`Gate::allows('admin')`). No model context for ownership.
- **Policy**: Receives `User + Model`, encapsulates all domain rules in one place. Testable. Laravel convention.

### `before()` Hook vs Per-Method Admin Checks

**Decision**: Use `before()` for admin bypass on delete; explicit denial for create.

- **Alternative**: Check `$user->isAdmin()` in each method.
- **Problem**: Duplication; easy to forget in new methods.
- **Solution**: `before()` centralizes admin logic. Return `null` to defer, `true` to allow, `false` to deny.
- **Critical**: Admin create returns `false`, not `null` — prevents accidental fallthrough to `create()`.

### Uniqueness Without DB Query

**Decision**: Rely on pre-loaded `$booking->review` relation.

- **Alternative**: `Review::where('booking_id', $booking->id)->exists()` in policy.
- **Problem**: Violates "no DB in policy" rule; breaks unit testability; N+1 risk.
- **Solution**: Caller pre-loads relation. Policy checks `$booking->review === null`.
- **Guard**: `relationLoaded()` check prevents accidental lazy-load; if not loaded, policy is permissive (fail-open) — caller's fault.
- **Final Guard**: DB unique constraint catches any slip-through.

### Why This Survives High Load

- **No DB in policy**: Policy execution is O(1) CPU-only.
- **Pre-loading**: Single query with `with('review')` batches relation fetch.
- **DB constraint**: Authoritative uniqueness at transaction level.
- **Stateless**: Policy has no injected services; trivially horizontally scalable.

### Future Extension: Review Moderation

Adding `moderate()` ability:

```php
public function moderate(User $user, Review $review): bool
{
    return $user->isModerator();
}
```

- No changes to existing methods.
- `before()` hook doesn't interfere (returns `null` for non-admin, defers to method).

### Rejected Alternatives

| Alternative                               | Fatal Flaw                                                        |
| ----------------------------------------- | ----------------------------------------------------------------- |
| Auth logic in Controller                  | Violates SoC; untestable; audit nightmare                         |
| Middleware for ownership                  | No model context; would require route-model binding coupling      |
| Event-based auth (`ReviewCreating` event) | No failure recovery; event listeners can't block creation cleanly |
| Custom Gate macro for review rules        | Loses Policy organization; harder to maintain                     |
| Query in policy for uniqueness            | DB dependency; testability loss; N+1 risk                         |

---

## 8. State Model & Invariants

### Review-Eligible Booking States

```
BookingStatus::CONFIRMED + check_out.isPast() → Review ALLOWED
BookingStatus::CONFIRMED + check_out.isFuture() → Review DENIED
BookingStatus::PENDING | CANCELLED | REFUND_* → Review DENIED
```

### Invariants

| Invariant                                              | Enforcement                                              |
| ------------------------------------------------------ | -------------------------------------------------------- |
| Review exists → Booking was confirmed at creation time | Policy `create()` check                                  |
| Review exists → Checkout had passed at creation time   | Policy `create()` temporal check                         |
| Review exists → Author owned booking                   | Policy `create()` ownership check                        |
| One review per booking                                 | Policy (advisory) + DB unique constraint (authoritative) |
| Admin cannot author reviews                            | Policy `before()` returns `false` for create             |

### Non-Transactional Checks

The policy performs **point-in-time checks** on pre-loaded data. Between policy check and INSERT:

- Booking could be cancelled (rare; review orphaned but valid historical record)
- Another review could be inserted (caught by DB constraint)
- User could be deleted (FK constraint or soft-delete policy determines outcome)

These are acceptable because:

1. Review is a post-facto record; booking state changes don't invalidate already-authorized action.
2. DB constraint is the transaction-level authority for uniqueness.
3. Policy is advisory/UX-focused; it prevents obvious errors, not Byzantine failures.

---

## Production Recommendations

- **Logging**: Log `Response::deny()` messages at INFO level for audit trail.
- **Monitoring**: Alert on >1% 403 rate for create — may indicate UI bug or abuse.
- **Registration**: Explicit policy registration aids IDE navigation; prefer it over convention-only.
- **Relation Loading**: Enforce via custom query scope: `Booking::forReviewCreation($id)` returns `with('review')`.
- **API Response**: Serialize `Response::message()` in 403 JSON body for client debugging.
- **Rate Limit**: Consider per-user rate limit on review creation (1/minute) at middleware level — separate from policy.
