# Optimistic Locking for Room Concurrency Control

> **Last Updated:** January 2, 2026 | **Tests:** 24 tests | **Status:** Production Ready ✅

## Table of Contents

1. [Overview](#overview)
2. [Root Cause Analysis](#root-cause-analysis)
3. [Implementation Architecture](#implementation-architecture)
4. [Entity Model](#entity-model)
5. [Service Layer](#service-layer)
6. [Exception Handling](#exception-handling)
7. [Controller Integration](#controller-integration)
8. [Database Migration](#database-migration)
9. [Testing Strategy](#testing-strategy)
10. [API Usage](#api-usage)
11. [Edge Cases & Best Practices](#edge-cases--best-practices)

---

## Overview

The Soleil Hostel booking system implements **optimistic locking** to prevent race conditions during concurrent room updates. This pattern is critical for hostel management where multiple staff members may update room status, pricing, or availability simultaneously.

### The "Lost Update" Problem

```text
Timeline:
┌─────────────────────────────────────────────────────────────────────┐
│ User A reads Room (price: $100)                              t=0   │
│ User B reads Room (price: $100)                              t=1   │
│ User B updates price to $120 → Saved                         t=2   │
│ User A updates price to $110 → Overwrites B's change! ❌     t=3   │
└─────────────────────────────────────────────────────────────────────┘

Result: User B's update to $120 is silently lost.
```

### With Optimistic Locking

```text
Timeline:
┌─────────────────────────────────────────────────────────────────────┐
│ User A reads Room (price: $100, lock_version: 5)             t=0   │
│ User B reads Room (price: $100, lock_version: 5)             t=1   │
│ User B updates with version=5 → Success, version=6           t=2   │
│ User A updates with version=5 → 409 Conflict ✅              t=3   │
│ User A refreshes, sees $120, decides to retry or adjust      t=4   │
└─────────────────────────────────────────────────────────────────────┘

Result: Conflict detected, no silent data loss.
```

---

## Root Cause Analysis

### Why Not Pessimistic Locking?

| Aspect                | Pessimistic (Row Locks)                 | Optimistic (Version Check)     |
| --------------------- | --------------------------------------- | ------------------------------ |
| **Throughput**        | Low - blocks readers                    | High - no blocking             |
| **Read Pattern**      | Hostel apps are read-heavy (80%+ reads) | ✅ Ideal for read-heavy        |
| **Lock Duration**     | Held during entire user "think time"    | No locks - check at write only |
| **Deadlock Risk**     | Possible with multiple tables           | None                           |
| **Conflict Handling** | Queued (user waits)                     | Immediate feedback             |
| **Scalability**       | Limited by lock contention              | Excellent                      |

### Why Optimistic Locking Fits Hostel Systems

1. **Low Conflict Rate**: Rarely do two staff update the _same_ room simultaneously
2. **Read-Heavy Workload**: Dashboard views, availability checks, listings
3. **Acceptable Retry UX**: Staff can easily refresh and re-apply changes
4. **No Lock Timeouts**: Avoids complexity of lock expiration
5. **Microservices Ready**: Stateless - no lock state to manage across services

### Micro-Level Analysis: Transaction Isolation

The fundamental issue is a **TOCTOU (Time-of-Check-Time-of-Use)** race:

```sql
-- VULNERABLE (separate operations):
SELECT * FROM rooms WHERE id = 1;        -- Check
-- ... time passes, another process updates ...
UPDATE rooms SET price = 120 WHERE id = 1; -- Use (overwrites!)
```

```sql
-- SAFE (atomic operation):
UPDATE rooms
SET price = 120, lock_version = lock_version + 1
WHERE id = 1 AND lock_version = 5;  -- Rows affected = 0 if version changed
```

---

## Implementation Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                          CLIENT (Frontend)                          │
│  1. GET /api/rooms/1 → receives {data, lock_version: 5}            │
│  2. PUT /api/rooms/1 → sends {data, lock_version: 5}               │
└───────────────────────────────┬─────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────────┐
│                        RoomController                               │
│  - Extracts lock_version from request                              │
│  - Delegates to RoomService                                        │
│  - Catches OptimisticLockException → 409 Conflict                  │
└───────────────────────────────┬─────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────────┐
│                         RoomService                                 │
│  updateWithOptimisticLock($room, $data, $expectedVersion)          │
│  - Atomic UPDATE with WHERE lock_version = $expected               │
│  - If rowsAffected = 0 → throw OptimisticLockException            │
│  - If rowsAffected = 1 → success, version incremented             │
└───────────────────────────────┬─────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────────┐
│                          Database                                   │
│  rooms table: [id, name, price, ..., lock_version, updated_at]     │
│  Single atomic UPDATE query - no race window                       │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Entity Model

### Room.php

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Room Model with Optimistic Locking
 *
 * @property int    $id
 * @property string $name
 * @property string $description
 * @property string $price
 * @property int    $max_guests
 * @property string $status
 * @property int    $lock_version  Optimistic locking version (starts at 1)
 */
class Room extends Model
{
    use HasFactory;

    /**
     * Mass assignable attributes.
     * Note: lock_version is NOT fillable - managed internally.
     */
    protected $fillable = [
        'name',
        'description',
        'price',
        'max_guests',
        'status',
    ];

    /**
     * Guarded attributes - never mass assignable.
     * lock_version is internal concurrency control.
     */
    protected $guarded = [
        'lock_version',
    ];

    /**
     * Attribute casts.
     */
    protected $casts = [
        'price' => 'decimal:2',
        'lock_version' => 'integer',
    ];

    /**
     * Accessor with fallback for legacy data.
     * If lock_version is null (pre-migration data), treat as version 1.
     */
    public function getLockVersionAttribute(?int $value): int
    {
        return $value ?? 1;
    }

    // ... relationships, scopes, etc.
}
```

### Key Design Decisions

| Decision                          | Rationale                                |
| --------------------------------- | ---------------------------------------- |
| `lock_version` NOT in `$fillable` | Prevents accidental mass assignment      |
| `lock_version` in `$guarded`      | Explicit protection                      |
| Default accessor returns `1`      | Backward compatibility with legacy data  |
| Cast to `integer`                 | Type safety, prevents string comparisons |
| Column type: `UNSIGNED BIGINT`    | Overflow-safe (2^64 updates per room)    |

---

## Service Layer

### RoomService.php - Core Method

```php
<?php

namespace App\Services;

use App\Models\Room;
use App\Exceptions\OptimisticLockException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RoomService
{
    /**
     * Update a room with optimistic concurrency control.
     *
     * Implements "compare-and-swap" pattern:
     * 1. Attempt UPDATE with WHERE lock_version = expectedVersion
     * 2. If rowsAffected = 0 → version mismatch → throw exception
     * 3. If rowsAffected = 1 → success → version incremented
     *
     * Why atomic update instead of SELECT + compare + UPDATE?
     * - Avoids TOCTOU race condition
     * - Single query = no window for concurrent modification
     * - No pessimistic locks needed
     *
     * @param Room     $room            The Room model to update
     * @param array    $data            Validated data from request
     * @param int|null $currentVersion  Expected lock_version from client
     *
     * @return Room Updated Room with new lock_version
     * @throws OptimisticLockException On version mismatch
     */
    public function updateWithOptimisticLock(
        Room $room,
        array $data,
        ?int $currentVersion = null
    ): Room {
        // Backward compatibility: if no version provided, use current
        if ($currentVersion === null) {
            $currentVersion = $room->lock_version;
            Log::warning('Room update without lock_version', [
                'room_id' => $room->id,
                'current_version' => $currentVersion,
            ]);
        }

        // Remove lock_version from data - we manage it here
        $updateData = collect($data)->except(['lock_version', 'id'])->toArray();

        // ATOMIC: Update only if version matches, increment in same query
        $rowsAffected = DB::table('rooms')
            ->where('id', $room->id)
            ->where('lock_version', $currentVersion)
            ->update(array_merge($updateData, [
                'lock_version' => DB::raw('lock_version + 1'),
                'updated_at' => now(),
            ]));

        // No rows updated = version mismatch = concurrent modification
        if ($rowsAffected === 0) {
            $room->refresh();
            $actualVersion = $room->lock_version;

            Log::warning('Optimistic lock conflict', [
                'room_id' => $room->id,
                'expected_version' => $currentVersion,
                'actual_version' => $actualVersion,
            ]);

            throw OptimisticLockException::forRoom(
                $room,
                $currentVersion,
                $actualVersion
            );
        }

        // Refresh model to get new version and updated data
        $room->refresh();

        // Invalidate cache (if using caching layer)
        $this->invalidateRoom($room->id);

        Log::info('Room updated with optimistic lock', [
            'room_id' => $room->id,
            'old_version' => $currentVersion,
            'new_version' => $room->lock_version,
        ]);

        return $room;
    }

    /**
     * Delete with optimistic lock check.
     */
    public function deleteWithOptimisticLock(Room $room, ?int $expectedVersion = null): bool
    {
        if ($expectedVersion === null) {
            $expectedVersion = $room->lock_version;
        }

        $rowsAffected = DB::table('rooms')
            ->where('id', $room->id)
            ->where('lock_version', $expectedVersion)
            ->delete();

        if ($rowsAffected === 0) {
            $room->refresh();
            throw OptimisticLockException::forRoom(
                $room,
                $expectedVersion,
                $room->lock_version
            );
        }

        $this->invalidateRoom($room->id);
        return true;
    }
}
```

### Why Not Use Eloquent's save()?

Eloquent's `save()` does `UPDATE ... WHERE id = ?` without version check, creating a race window:

```php
// UNSAFE - race condition possible:
$room = Room::find(1);
$room->price = 120;
$room->save();  // No version check!
```

```php
// SAFE - atomic version check:
DB::table('rooms')
    ->where('id', 1)
    ->where('lock_version', 5)  // Check in same query
    ->update([
        'price' => 120,
        'lock_version' => DB::raw('lock_version + 1'),
    ]);
```

---

## Exception Handling

### OptimisticLockException.php

```php
<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Illuminate\Database\Eloquent\Model;

/**
 * Thrown when optimistic lock conflict detected.
 *
 * HTTP Status: 409 Conflict
 */
class OptimisticLockException extends RuntimeException
{
    public const HTTP_STATUS_CODE = 409;

    public function __construct(
        string $message = 'The resource has been modified by another user. Please refresh and try again.',
        public readonly ?Model $model = null,
        public readonly ?int $expectedVersion = null,
        public readonly ?int $actualVersion = null,
    ) {
        parent::__construct($message, self::HTTP_STATUS_CODE);
    }

    /**
     * Detailed message for logging.
     */
    public function getDetailedMessage(): string
    {
        $details = [];

        if ($this->model) {
            $details[] = sprintf(
                'Model: %s (ID: %s)',
                get_class($this->model),
                $this->model->getKey()
            );
        }

        if ($this->expectedVersion !== null) {
            $details[] = "Expected: {$this->expectedVersion}";
        }

        if ($this->actualVersion !== null) {
            $details[] = "Actual: {$this->actualVersion}";
        }

        return $this->getMessage() . ' | ' . implode(' | ', $details);
    }

    /**
     * Factory for Room-specific exceptions.
     */
    public static function forRoom(
        Model $room,
        ?int $expectedVersion = null,
        ?int $actualVersion = null
    ): self {
        return new self(
            "Room '{$room->name}' was modified by another user. Please refresh and try again.",
            $room,
            $expectedVersion,
            $actualVersion
        );
    }
}
```

### Global Exception Handler

In `app/Exceptions/Handler.php`:

```php
use App\Exceptions\OptimisticLockException;

public function register(): void
{
    $this->renderable(function (OptimisticLockException $e, $request) {
        return response()->json([
            'success' => false,
            'error' => 'conflict',
            'message' => $e->getMessage(),
            'data' => [
                'expected_version' => $e->expectedVersion,
                'actual_version' => $e->actualVersion,
            ],
        ], OptimisticLockException::HTTP_STATUS_CODE);
    });
}
```

---

## Controller Integration

### RoomController.php

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoomRequest;
use App\Http\Resources\RoomResource;
use App\Models\Room;
use App\Services\RoomService;
use Illuminate\Http\JsonResponse;

class RoomController extends Controller
{
    public function __construct(
        private readonly RoomService $roomService
    ) {}

    /**
     * Update a room with optimistic locking.
     *
     * PUT /api/rooms/{id}
     *
     * Request body must include lock_version from last read.
     * Returns 409 Conflict if version mismatch.
     */
    public function update(RoomRequest $request, $id): JsonResponse
    {
        $room = Room::findOrFail($id);
        $this->authorize('update', $room);

        // Extract version from request
        $lockVersion = $request->getLockVersion();

        // Get validated data (excluding lock_version)
        $data = collect($request->validated())
            ->except(['lock_version'])
            ->toArray();

        // Service handles version check - throws OptimisticLockException on conflict
        $updatedRoom = $this->roomService->updateWithOptimisticLock(
            $room,
            $data,
            $lockVersion
        );

        return response()->json([
            'success' => true,
            'message' => 'Room updated successfully',
            'data' => new RoomResource($updatedRoom),
        ]);
    }
}
```

### RoomRequest.php

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RoomRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'price' => 'sometimes|required|numeric|min:0',
            'max_guests' => 'sometimes|required|integer|min:1',
            'status' => 'sometimes|required|in:available,maintenance,occupied',
            'lock_version' => 'sometimes|integer|min:1',
        ];
    }

    /**
     * Get lock_version from request, cast to int or null.
     */
    public function getLockVersion(): ?int
    {
        return $this->has('lock_version')
            ? (int) $this->input('lock_version')
            : null;
    }
}
```

---

## Database Migration

### Production-Safe Migration Strategy

```php
<?php

/**
 * Migration: Add lock_version for Optimistic Concurrency Control
 *
 * Production-safe approach for large tables:
 * 1. Add column as NULLABLE (fast, no table lock on PostgreSQL 11+)
 * 2. Backfill existing rows with version 1
 * 3. Alter column to NOT NULL with default
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Add nullable column (fast, minimal locking)
        Schema::table('rooms', function (Blueprint $table) {
            $table->unsignedBigInteger('lock_version')
                ->nullable()
                ->after('status')
                ->comment('Optimistic locking version - increments on each update');
        });

        // Step 2: Backfill existing rows
        DB::table('rooms')
            ->whereNull('lock_version')
            ->update(['lock_version' => 1]);

        // Step 3: Make NOT NULL with default
        Schema::table('rooms', function (Blueprint $table) {
            $table->unsignedBigInteger('lock_version')
                ->default(1)
                ->nullable(false)
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('lock_version');
        });
    }
};
```

### PostgreSQL Notes

- Adding `NULLABLE` column is instant (no table rewrite)
- `DEFAULT 1` is cheap on PostgreSQL 11+ (stored in catalog)
- No `EXCLUSIVE LOCK` - reads continue unblocked

---

## Testing Strategy

### Test Categories

| Category          | Focus                     | Count |
| ----------------- | ------------------------- | ----- |
| Unit Tests        | Service layer logic       | 12    |
| Integration Tests | API endpoints             | 8     |
| Concurrent Tests  | Race condition simulation | 4     |

### Test Examples

```php
<?php

namespace Tests\Feature;

use App\Exceptions\OptimisticLockException;
use App\Models\Room;
use App\Services\RoomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoomOptimisticLockingTest extends TestCase
{
    use RefreshDatabase;

    private RoomService $roomService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->roomService = app(RoomService::class);
    }

    /** @test */
    public function new_room_starts_with_lock_version_1(): void
    {
        $room = Room::factory()->create();
        $this->assertEquals(1, $room->lock_version);
    }

    /** @test */
    public function successful_update_increments_version(): void
    {
        $room = Room::factory()->create(['name' => 'Original']);
        $originalVersion = $room->lock_version;

        $updatedRoom = $this->roomService->updateWithOptimisticLock(
            $room,
            ['name' => 'Updated', ...],
            $originalVersion
        );

        $this->assertEquals($originalVersion + 1, $updatedRoom->lock_version);
    }

    /** @test */
    public function stale_version_throws_exception(): void
    {
        $room = Room::factory()->create();
        $staleVersion = $room->lock_version;

        // Simulate another user updating first
        DB::table('rooms')
            ->where('id', $room->id)
            ->update(['lock_version' => DB::raw('lock_version + 1')]);

        $this->expectException(OptimisticLockException::class);

        $this->roomService->updateWithOptimisticLock(
            $room,
            ['name' => 'My Update', ...],
            $staleVersion
        );
    }

    /** @test */
    public function concurrent_updates_one_succeeds_one_fails(): void
    {
        $room = Room::factory()->create();
        $sharedVersion = $room->lock_version;

        // User A updates first - succeeds
        $this->roomService->updateWithOptimisticLock(
            $room,
            ['name' => 'User A'],
            $sharedVersion
        );

        // User B tries with same version - fails
        $this->expectException(OptimisticLockException::class);
        $room->refresh();
        $this->roomService->updateWithOptimisticLock(
            $room,
            ['name' => 'User B'],
            $sharedVersion
        );
    }

    /** @test */
    public function api_returns_409_on_conflict(): void
    {
        $admin = User::factory()->admin()->create();
        $room = Room::factory()->create();

        // Update to create version mismatch
        DB::table('rooms')
            ->where('id', $room->id)
            ->update(['lock_version' => 5]);

        $response = $this->actingAs($admin)
            ->putJson("/api/rooms/{$room->id}", [
                'name' => 'Test',
                'lock_version' => 1,  // Stale
            ]);

        $response->assertStatus(409);
        $response->assertJson([
            'success' => false,
            'error' => 'conflict',
        ]);
    }

    /** @test */
    public function api_response_includes_lock_version(): void
    {
        $response = $this->getJson('/api/rooms/1');

        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'lock_version',  // Must be included
            ],
        ]);
    }
}
```

### Running Tests

```bash
# All optimistic locking tests
php artisan test --filter=RoomOptimisticLocking

# Specific test
php artisan test --filter="concurrent_updates_one_succeeds"

# With coverage
php artisan test --filter=RoomOptimisticLocking --coverage
```

---

## API Usage

### Read a Room

```http
GET /api/rooms/1
```

Response includes `lock_version`:

```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Ocean View Suite",
    "price": "150.00",
    "status": "available",
    "lock_version": 5
  }
}
```

### Update a Room

```http
PUT /api/rooms/1
Content-Type: application/json
Authorization: Bearer <token>

{
  "name": "Updated Room Name",
  "price": 175.00,
  "lock_version": 5
}
```

**Success Response (200):**

```json
{
  "success": true,
  "message": "Room updated successfully",
  "data": {
    "id": 1,
    "name": "Updated Room Name",
    "price": "175.00",
    "lock_version": 6
  }
}
```

**Conflict Response (409):**

```json
{
  "success": false,
  "error": "conflict",
  "message": "Room 'Ocean View Suite' was modified by another user. Please refresh and try again.",
  "data": {
    "expected_version": 5,
    "actual_version": 7
  }
}
```

### Client Handling (TypeScript Example)

```typescript
async function updateRoom(roomId: number, data: RoomUpdateData): Promise<Room> {
  try {
    const response = await api.put(`/rooms/${roomId}`, {
      ...data,
      lock_version: data.lockVersion,
    });
    return response.data.data;
  } catch (error) {
    if (error.response?.status === 409) {
      // Show conflict dialog
      const result = await showConflictDialog(
        "This room was modified by another user. Refresh to see changes?"
      );

      if (result === "refresh") {
        const freshRoom = await getRoom(roomId);
        // Re-render form with fresh data
        return freshRoom;
      }
    }
    throw error;
  }
}
```

---

## Edge Cases & Best Practices

### 1. Version Overflow

```php
// UNSIGNED BIGINT = 18,446,744,073,709,551,615 max
// At 1 update/second: 584 billion years before overflow
// → Not a practical concern
```

### 2. Cascading to Child Entities (Bookings)

For booking updates that affect room state, two strategies:

#### A. Parent Version Check (Recommended for Soleil)

```php
// When creating a booking, check room hasn't changed
public function createBooking(Room $room, array $data, int $roomVersion): Booking
{
    // First verify room is still in expected state
    $currentRoom = Room::where('id', $room->id)
        ->where('lock_version', $roomVersion)
        ->first();

    if (!$currentRoom) {
        throw new OptimisticLockException('Room was modified');
    }

    return Booking::create([...]);
}
```

**B. Separate Version per Entity**

Add `lock_version` to Booking model if direct booking updates need protection.

### 3. Performance: Avoid Unnecessary Loads

```php
// INEFFICIENT - loads model just to get version
$room = Room::find($id);
$this->updateWithOptimisticLock($room, $data, $room->lock_version);

// EFFICIENT - client sends version from previous read
$room = Room::find($id);
$this->updateWithOptimisticLock($room, $data, $request->lock_version);
```

### 4. Caching Consistency

```php
// After update, invalidate cached room data
$room->refresh();
Cache::forget("room:{$room->id}");
Cache::forget('rooms:all');
```

### 5. Batch Updates

```php
// UNSAFE - no version control on batch
Room::where('status', 'old')->update(['status' => 'new']);

// Consider: Do you need optimistic locking for admin bulk operations?
// Often acceptable to skip for admin-only batch operations
```

### 6. Read-Modify-Write Pattern (Avoid)

```php
// UNSAFE - even with optimistic locking, this has race window:
$room = Room::find(1);
$room->price = $room->price + 10;  // Read current
// Gap here - another process could update
$this->updateWithOptimisticLock($room, ['price' => $room->price], $version);

// SAFER - atomic increment:
DB::table('rooms')
    ->where('id', 1)
    ->where('lock_version', $version)
    ->update([
        'price' => DB::raw('price + 10'),
        'lock_version' => DB::raw('lock_version + 1'),
    ]);
```

---

## Summary

| Component           | Implementation                                            |
| ------------------- | --------------------------------------------------------- |
| **Version Column**  | `lock_version UNSIGNED BIGINT DEFAULT 1`                  |
| **Increment Logic** | `DB::raw('lock_version + 1')` in atomic UPDATE            |
| **Conflict Check**  | `WHERE lock_version = ?` in UPDATE clause                 |
| **Exception Type**  | `OptimisticLockException` (HTTP 409)                      |
| **Client Contract** | Must send `lock_version` on updates                       |
| **Fallback**        | Legacy calls without version use current DB value         |
| **Test Coverage**   | 24 tests covering unit, integration, concurrent scenarios |

This implementation follows ACID principles, is thread-safe, and scales excellently for read-heavy hostel management workloads.
