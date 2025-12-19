# 🏨 Room Management

> Optimistic locking to prevent lost updates with atomic version check

## Overview

Room updates use **optimistic locking** via a `lock_version` column to detect concurrent modifications. This prevents the "lost update" problem where two users edit the same room and one overwrites the other's changes.

---

## The Problem: Lost Updates

```
T1: User A reads Room (price: $100, version: 5)
T2: User B reads Room (price: $100, version: 5)
T3: User A saves (price: $120) ✓ → version becomes 6
T4: User B saves (price: $150) ← Overwrites A's $120 change! ❌
```

## The Solution: Version Check

```
T1: User A reads Room (version: 5)
T2: User B reads Room (version: 5)
T3: User A saves with version=5 → ✓ Success (version → 6)
T4: User B saves with version=5 → ✗ 409 Conflict (version is now 6)
```

---

## Room Model

```php
/**
 * @property int $lock_version  Optimistic locking version (starts at 1)
 */
class Room extends Model
{
    protected $fillable = [
        'name', 'description', 'price', 'max_guests', 'status',
    ];

    // CRITICAL: lock_version is NOT fillable - managed internally
    protected $guarded = ['lock_version'];

    protected $casts = [
        'price' => 'decimal:2',
        'lock_version' => 'integer',
    ];

    // Handle legacy null values (migration compatibility)
    public function getLockVersionAttribute(?int $value): int
    {
        return $value ?? 1;
    }

    // Relationships
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function activeBookings(): HasMany
    {
        return $this->bookings()
            ->whereIn('status', Booking::ACTIVE_STATUSES);
    }
}
```

### Query Scopes

```php
Room::withCommonRelations();  // Load with booking count
Room::selectColumns();        // Select only needed columns
Room::active();               // Only active rooms
```

---

## RoomService - Optimistic Locking

```php
class RoomService
{
    /**
     * Update room with optimistic locking
     *
     * @throws OptimisticLockException if version mismatch
     */
    public function updateWithOptimisticLock(
        Room $room,
        array $data,
        ?int $version = null
    ): Room {
        $version ??= $room->lock_version;

        // Atomic update: only succeeds if version matches
        $affected = DB::table('rooms')
            ->where('id', $room->id)
            ->where('lock_version', $version)  // ← Version check
            ->update([
                ...$data,
                'lock_version' => DB::raw('lock_version + 1'),
                'updated_at' => now(),
            ]);

        if ($affected === 0) {
            // Version mismatch = someone else modified the room
            throw new OptimisticLockException(
                'Phòng đã được cập nhật bởi người khác. Vui lòng refresh và thử lại.'
            );
        }

        return $room->refresh();
    }
}
```

---

## API Usage

### Update Room

**Request:**

```http
PUT /api/rooms/1
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
  "message": "The room has been modified. Please refresh and try again."
}
```

---

## Client Integration

### TypeScript

```typescript
async function updateRoom(id: number, data: RoomData): Promise<Room> {
  const response = await fetch(`/api/rooms/${id}`, {
    method: "PUT",
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

## API Endpoints

| Method | Endpoint          | Description         |
| ------ | ----------------- | ------------------- |
| GET    | `/api/rooms`      | List all rooms      |
| POST   | `/api/rooms`      | Create room (Admin) |
| GET    | `/api/rooms/{id}` | View room           |
| PUT    | `/api/rooms/{id}` | Update room (Admin) |
| DELETE | `/api/rooms/{id}` | Delete room (Admin) |

---

## Validation

```php
'lock_version' => 'sometimes|nullable|integer|min:1'
```

> **Note:** `lock_version` is optional for backward compatibility.

---

## Tests

```bash
php artisan test --filter=RoomOptimisticLockingTest
```

| Test Category         | Count  |
| --------------------- | ------ |
| Unit (Model)          | 5      |
| Integration (Service) | 8      |
| API (HTTP)            | 6      |
| Edge Cases            | 3      |
| Exception             | 2      |
| **Total**             | **24** |

---

## Trade-offs

| Pros                | Cons                                   |
| ------------------- | -------------------------------------- |
| No deadlocks        | Requires client to send `lock_version` |
| Single atomic query | High-contention = frequent conflicts   |
| DB-agnostic         | No field-level merge                   |
