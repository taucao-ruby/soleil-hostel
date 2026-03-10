# 🛡️ Authorization Policies

> Laravel Policies for resource-based authorization

## Overview

Soleil Hostel uses Laravel Policies to authorize actions on resources:

| Policy        | Model   | Purpose                    |
| ------------- | ------- | -------------------------- |
| BookingPolicy | Booking | Booking CRUD authorization |
| RoomPolicy    | Room    | Room CRUD authorization    |

---

## BookingPolicy

Authorizes booking operations based on ownership and role.

```php
// App\Policies\BookingPolicy

class BookingPolicy
{
    public function view(User $user, Booking $booking): bool
    {
        return $user->isAtLeast(UserRole::MODERATOR)
            || $user->id === $booking->user_id;
    }

    public function update(User $user, Booking $booking): bool
    {
        return $user->isAdmin()
            || $user->id === $booking->user_id;
    }

    public function delete(User $user, Booking $booking): bool
    {
        return $user->isAdmin()
            || $user->id === $booking->user_id;
    }

    public function create(User $user): bool
    {
        return true; // All authenticated users
    }

    public function viewAny(User $user): bool
    {
        return $user->isAtLeast(UserRole::MODERATOR);
    }

    public function viewTrashed(User $user): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Booking $booking): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Booking $booking): bool
    {
        return $user->isAdmin();
    }

    public function confirm(User $user, Booking $booking): bool
    {
        return $user->isAdmin();
    }

    /**
     * Cancellation policy:
     * - Owner or admin can cancel
     * - Already cancelled = allowed (idempotency)
     * - Must be in cancellable state (pending, confirmed, refund_failed)
     * - Regular users cannot cancel after check-in started (unless config allows)
     */
    public function cancel(User $user, Booking $booking): bool
    {
        $isOwner = $user->id === $booking->user_id;
        $isAdmin = $user->isAdmin();

        if (!$isOwner && !$isAdmin) {
            return false;
        }

        // Idempotency: already cancelled
        if ($booking->status === BookingStatus::CANCELLED) {
            return true;
        }

        if (!$booking->status->isCancellable()) {
            return false;
        }

        // Regular users cannot cancel after check-in
        if (!$isAdmin && $booking->isStarted()) {
            return config('booking.cancellation.allow_after_checkin', false);
        }

        return true;
    }

    /**
     * Force cancel bypasses refund and sets to cancelled immediately.
     */
    public function forceCancel(User $user, Booking $booking): bool
    {
        return $user->isAdmin() && !$booking->status->isTerminal();
    }
}
```

### Authorization Matrix

> **For the canonical enforced permission matrix (route + gate + policy combined), see [docs/PERMISSION_MATRIX.md](../../PERMISSION_MATRIX.md).**
> The table below shows policy-level grants only. Some grants (e.g., `viewAny` for moderator) are LATENT-SHADOWED by route middleware and do not represent enforced permissions.

| Action       | User | Moderator | Admin | Notes |
| ------------ | ---- | --------- | ----- | ----- |
| view (own)   | ✅   | ✅        | ✅    | |
| view (any)   | ❌   | ✅        | ✅    | |
| update (own) | ✅   | ✅        | ✅    | |
| update (any) | ❌   | ❌        | ✅    | |
| delete (own) | ✅   | ✅        | ✅    | |
| delete (any) | ❌   | ❌        | ✅    | |
| confirm      | ❌   | ❌        | ✅    | |
| cancel (own) | ✅   | ✅        | ✅    | Subject to BR-1, BR-2, BR-3 business rules |
| cancel (any) | ❌   | ❌        | ✅    | |
| forceCancel  | ❌   | ❌        | ✅    | |
| viewAny      | ❌   | ✅        | ✅    | **LATENT-SHADOWED** — route `role:admin` + `Gate::authorize('admin')` fire first |
| viewTrashed  | ❌   | ❌        | ✅    | |
| restore      | ❌   | ❌        | ✅    | |
| forceDelete  | ❌   | ❌        | ✅    | |

---

## RoomPolicy

Authorizes room management operations.

```php
// App\Policies\RoomPolicy

class RoomPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // Everyone can list rooms
    }

    public function view(User $user, Room $room): bool
    {
        return true; // Everyone can view details
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Room $room): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Room $room): bool
    {
        return $user->isAdmin();
    }
}
```

### Authorization Matrix

| Action  | User | Moderator | Admin |
| ------- | ---- | --------- | ----- |
| viewAny | ✅   | ✅        | ✅    |
| view    | ✅   | ✅        | ✅    |
| create  | ❌   | ❌        | ✅    |
| update  | ❌   | ❌        | ✅    |
| delete  | ❌   | ❌        | ✅    |

---

## Usage in Controllers

### Using authorize()

```php
// BookingController.php

public function show(Booking $booking): JsonResponse
{
    $this->authorize('view', $booking);

    return response()->json(['data' => $booking]);
}

public function update(Request $request, Booking $booking): JsonResponse
{
    $this->authorize('update', $booking);

    // ... update logic
}

public function destroy(Booking $booking): JsonResponse
{
    $this->authorize('delete', $booking);

    // ... delete logic
}
```

### Using Gate

```php
use Illuminate\Support\Facades\Gate;

if (Gate::allows('update', $booking)) {
    // User can update
}

if (Gate::denies('delete', $booking)) {
    abort(403);
}
```

### Using @can Blade Directive

```blade
@can('update', $booking)
    <button>Edit Booking</button>
@endcan

@can('delete', $booking)
    <button>Cancel Booking</button>
@endcan
```

---

## Policy Registration

Policies are auto-discovered by Laravel, but explicitly registered for clarity:

```php
// App\Providers\AuthServiceProvider.php

protected $policies = [
    Booking::class => BookingPolicy::class,
    Review::class => ReviewPolicy::class,
    Room::class => RoomPolicy::class,
];
```

---

## Gates vs Policies

| Feature | Gates               | Policies              |
| ------- | ------------------- | --------------------- |
| Scope   | Global actions      | Model-specific        |
| Example | `manage-rooms`      | `BookingPolicy::view` |
| Use     | Admin-only features | Resource CRUD         |

### Existing Gates

7 gates defined in `AuthServiceProvider.php`. For invocation status (CURRENT vs LATENT-UNUSED), see [docs/PERMISSION_MATRIX.md](../../PERMISSION_MATRIX.md).

| Gate | Check | Level |
|------|-------|-------|
| `admin` | `$user->isAdmin()` | EXACT-MATCH |
| `moderator` | `$user->isModerator()` | HIERARCHY |
| `manage-users` | `$user->isAdmin()` | EXACT-MATCH |
| `moderate-content` | `$user->isModerator()` | HIERARCHY |
| `view-all-bookings` | `$user->isModerator()` | HIERARCHY |
| `manage-rooms` | `$user->isAdmin()` | EXACT-MATCH |
| `view-queue-monitoring` | `$user->isAdmin()` | EXACT-MATCH |

---

## Exception Handling

When authorization fails, Laravel throws `AuthorizationException`:

```php
// Handled in Handler.php
public function render($request, Throwable $exception)
{
    if ($exception instanceof AuthorizationException) {
        return response()->json([
            'success' => false,
            'message' => 'You do not have permission to perform this action.'
        ], 403);
    }
}
```

---

## Testing Policies

```php
// tests/Feature/Policies/BookingPolicyTest.php

public function test_user_can_view_own_booking()
{
    $user = User::factory()->create();
    $booking = Booking::factory()->create(['user_id' => $user->id]);

    $this->assertTrue($user->can('view', $booking));
}

public function test_user_cannot_view_other_booking()
{
    $user = User::factory()->create();
    $other = User::factory()->create();
    $booking = Booking::factory()->create(['user_id' => $other->id]);

    $this->assertFalse($user->can('view', $booking));
}

public function test_admin_can_view_any_booking()
{
    $admin = User::factory()->admin()->create();
    $booking = Booking::factory()->create();

    $this->assertTrue($admin->can('view', $booking));
}
```
