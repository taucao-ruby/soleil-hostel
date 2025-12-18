# 🏨 Room Management

> Optimistic locking to prevent lost updates

## Overview

Room updates use **optimistic locking** via a `lock_version` column to detect concurrent modifications.

---

## The Problem: Lost Updates

```
T1: User A reads Room (price: $100, version: 5)
T2: User B reads Room (price: $100, version: 5)
T3: User A saves (price: $120) ✓
T4: User B saves (price: $150) ← Overwrites A's change!
```

## The Solution: Version Check

```
T1: User A reads Room (version: 5)
T2: User B reads Room (version: 5)
T3: User A saves with version=5 → ✓ (version → 6)
T4: User B saves with version=5 → ✗ 409 Conflict
```

---

## Implementation

### Database

```sql
ALTER TABLE rooms ADD lock_version BIGINT NOT NULL DEFAULT 1;
```

### Model

```php
class Room extends Model
{
    // Prevent mass assignment of lock_version
    protected $guarded = ['lock_version'];

    // Handle legacy null values
    public function getLockVersionAttribute(?int $value): int
    {
        return $value ?? 1;
    }
}
```

### Service

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
        throw new OptimisticLockException();
    }

    return $room->refresh();
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
