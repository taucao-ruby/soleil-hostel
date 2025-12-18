# Optimistic Locking for Rooms

> **Status:** ✅ Complete | **Tests:** 24 passed (58 assertions) | **Date:** December 18, 2025

## Table of Contents

1. [Quick Start](#quick-start)
2. [Overview](#overview)
3. [Architecture](#architecture)
4. [API Reference](#api-reference)
5. [Client Integration](#client-integration)
6. [Testing](#testing)
7. [Trade-offs](#trade-offs)
8. [Appendix](#appendix)

---

## Quick Start

```bash
# Run migration
cd backend && php artisan migrate

# Verify
php artisan tinker
>>> App\Models\Room::first()->lock_version
=> 1

# Run tests
php artisan test --filter=RoomOptimisticLockingTest
```

---

## Overview

### Problem: Lost Updates

```
┌─────────────────────────────────────────────────────────────┐
│ T1: User A reads Room (price: $100)                         │
│ T2: User B reads Room (price: $100)                         │
│ T3: User A saves (price: $120) ✓                            │
│ T4: User B saves (price: $150) ✓ ← Overwrites A's change!   │
└─────────────────────────────────────────────────────────────┘
```

### Solution: Version-Based Conflict Detection

```
┌─────────────────────────────────────────────────────────────┐
│ T1: User A reads Room (version: 5)                          │
│ T2: User B reads Room (version: 5)                          │
│ T3: User A saves with version=5 → ✓ (version → 6)           │
│ T4: User B saves with version=5 → ✗ 409 Conflict            │
└─────────────────────────────────────────────────────────────┘
```

**Key Benefits:**

-   ✅ No pessimistic locks / deadlocks
-   ✅ DB-agnostic (MySQL, PostgreSQL, SQLite)
-   ✅ Zero external dependencies
-   ✅ Single atomic query

---

## Architecture

### Files Changed

| File                                                                  | Type     | Description            |
| --------------------------------------------------------------------- | -------- | ---------------------- |
| `database/migrations/2025_12_18_200000_add_lock_version_to_rooms.php` | Created  | 3-step safe migration  |
| `app/Exceptions/OptimisticLockException.php`                          | Created  | Domain exception (409) |
| `tests/Feature/RoomOptimisticLockingTest.php`                         | Created  | 24 PHPUnit tests       |
| `app/Models/Room.php`                                                 | Modified | `$guarded`, accessor   |
| `app/Services/RoomService.php`                                        | Modified | Atomic update methods  |
| `app/Http/Controllers/RoomController.php`                             | Modified | Service integration    |
| `app/Http/Requests/RoomRequest.php`                                   | Modified | Validation rules       |
| `app/Http/Resources/RoomResource.php`                                 | Modified | Include `lock_version` |
| `bootstrap/app.php`                                                   | Modified | Exception renderer     |

### Core Implementation

**Atomic Compare-and-Swap** (`RoomService.php`):

```php
public function updateWithOptimisticLock(Room $room, array $data, ?int $version = null): Room
{
    $version ??= $room->lock_version;

    $affected = DB::table('rooms')
        ->where('id', $room->id)
        ->where('lock_version', $version)
        ->update([
            ...$data,
            'lock_version' => DB::raw('lock_version + 1'),
            'updated_at' => now(),
        ]);

    if ($affected === 0) {
        throw OptimisticLockException::forRoom($room, $version, $room->refresh()->lock_version);
    }

    return $room->refresh();
}
```

**Exception Class** (`OptimisticLockException.php`):

```php
class OptimisticLockException extends RuntimeException
{
    public const HTTP_STATUS_CODE = 409;

    public function __construct(
        string $message = 'Resource modified by another user.',
        public readonly ?Model $model = null,
        public readonly ?int $expectedVersion = null,
        public readonly ?int $actualVersion = null,
    ) {
        parent::__construct($message, self::HTTP_STATUS_CODE);
    }
}
```

**Migration Strategy** (3-step for large tables):

```php
// Step 1: Add nullable (fast, no lock)
$table->unsignedBigInteger('lock_version')->nullable();

// Step 2: Backfill
DB::table('rooms')->whereNull('lock_version')->update(['lock_version' => 1]);

// Step 3: Make NOT NULL
$table->unsignedBigInteger('lock_version')->default(1)->nullable(false)->change();
```

---

## API Reference

### Update Room

```http
PUT /api/rooms/{id}
Content-Type: application/json

{
    "name": "Luxury Suite",
    "price": 200.00,
    "lock_version": 5
}
```

**Success (200):**

```json
{
    "data": {
        "id": 1,
        "name": "Luxury Suite",
        "price": 200.0,
        "lock_version": 6
    }
}
```

**Conflict (409):**

```json
{
    "error": "resource_out_of_date",
    "message": "The room has been modified by another user. Please refresh and try again."
}
```

### Validation Rules

```php
'lock_version' => 'sometimes|nullable|integer|min:1'
```

> **Note:** `lock_version` is optional for backward compatibility.

---

## Client Integration

### TypeScript Example

```typescript
async function updateRoom(id: number, data: RoomData): Promise<Room> {
    const response = await fetch(`/api/rooms/${id}`, {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ ...data, lock_version: data.lock_version }),
    });

    if (response.status === 409) {
        const fresh = await fetchRoom(id);
        showConflictDialog(fresh, data);
        return fresh;
    }

    return response.json();
}
```

### Retry Strategy (Background Jobs)

```php
function updateWithRetry(Room $room, array $data, int $maxAttempts = 3): Room
{
    for ($i = 1; $i <= $maxAttempts; $i++) {
        try {
            return $this->roomService->updateWithOptimisticLock(
                $room->refresh(), $data, $room->lock_version
            );
        } catch (OptimisticLockException $e) {
            if ($i === $maxAttempts) throw $e;
            usleep(100000 * $i); // Exponential backoff
        }
    }
}
```

---

## Testing

### Test Summary

| Category    | Count  | Coverage                   |
| ----------- | ------ | -------------------------- |
| Unit        | 5      | Model, accessor, guarded   |
| Integration | 8      | Service layer, CRUD        |
| API         | 4      | HTTP 200/409 responses     |
| Edge Cases  | 4      | Large versions, boundaries |
| Exception   | 3      | Message, properties        |
| **Total**   | **24** | **58 assertions**          |

### Run Tests

```bash
php artisan test --filter=RoomOptimisticLockingTest

# Output:
# Tests:    24 passed (58 assertions)
# Duration: 1.21s
```

---

## Trade-offs

| Pros                | Cons                                   |
| ------------------- | -------------------------------------- |
| No deadlocks        | Requires client to send `lock_version` |
| Single atomic query | High-contention = frequent conflicts   |
| DB-agnostic         | No field-level merge                   |
| Zero dependencies   | —                                      |

### When NOT to Use

1. **High contention** (100+ updates/sec on same row) → Use queue-based processing
2. **Field-level merging needed** → Consider field-level versioning

---

## Appendix

### Design Decisions

| Decision                   | Rationale                                 |
| -------------------------- | ----------------------------------------- |
| `$guarded` not `$fillable` | Prevent mass assignment of `lock_version` |
| `RuntimeException`         | Domain exception, not system exception    |
| 3-step migration           | Avoid table locks in PostgreSQL < 11      |
| Accessor for null          | Backward compatibility with legacy data   |

### References

-   [Martin Fowler: Optimistic Offline Lock](https://martinfowler.com/eaaCatalog/optimisticOfflineLock.html)
-   [HTTP 409 Conflict](https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/409)
