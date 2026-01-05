# 📦 Repository Pattern

> Data access abstraction for decoupling business logic from Eloquent

## Overview

The Repository Pattern provides a clean separation between business logic (Services) and data access (Eloquent). This enables:

- **Improved testability**: Easy mocking in unit tests
- **Future flexibility**: Alternate implementations, caching decorators
- **Clear boundaries**: Services contain business logic, Repositories contain data access
- **Incremental adoption**: Can be adopted gradually without breaking existing code

---

## Current Implementation

### BookingRepository

The Booking domain is the first (and most critical) domain to use the Repository pattern.

| File           | Location                                                    |
| -------------- | ----------------------------------------------------------- |
| Interface      | `app/Repositories/Contracts/BookingRepositoryInterface.php` |
| Implementation | `app/Repositories/EloquentBookingRepository.php`            |
| Binding        | `AppServiceProvider::register()`                            |

### RoomRepository

The Room domain is critical for availability and inventory management.

| File           | Location                                                 |
| -------------- | -------------------------------------------------------- |
| Interface      | `app/Repositories/Contracts/RoomRepositoryInterface.php` |
| Implementation | `app/Repositories/EloquentRoomRepository.php`            |
| Binding        | `AppServiceProvider::register()`                         |

---

## Interface Methods

### BookingRepository - Basic CRUD

```php
public function findById(int $id): ?Booking;
public function findByIdOrFail(int $id): Booking;
public function findByIdWithRelations(int $id, array $relations): ?Booking;
public function create(array $data): Booking;
public function update(Booking $booking, array $data): bool;
public function delete(Booking $booking): bool;
```

### User Bookings

```php
public function getByUserId(int $userId, array $columns = ['*'], array $relations = []): Collection;
public function getByUserIdOrderedByCheckIn(int $userId, array $columns = ['*'], array $relations = []): Collection;
```

### Overlap/Conflict Detection (Critical for Double-Booking Prevention)

```php
public function findOverlappingBookings(int $roomId, $checkIn, $checkOut, ?int $excludeBookingId = null): Collection;
public function hasOverlappingBookings(int $roomId, $checkIn, $checkOut, ?int $excludeBookingId = null): bool;
public function findOverlappingBookingsWithLock(int $roomId, $checkIn, $checkOut, ?int $excludeBookingId = null): Collection;
public function hasOverlappingBookingsWithLock(int $roomId, $checkIn, $checkOut, ?int $excludeBookingId = null): bool;
```

### Soft Delete Operations

```php
public function getTrashed(array $relations = []): Collection;
public function findTrashedById(int $id, array $relations = []): ?Booking;
public function restore(Booking $booking): bool;
public function forceDelete(Booking $booking): bool;
public function getTrashedOlderThan(Carbon $cutoffDate): Collection;
```

### BookingRepository - Admin/Listing

```php
public function getAllWithTrashed(array $relations = []): Collection;
public function getWithCommonRelations(): Collection;  // Relies on existing Booking::withCommonRelations() scope
public function query(): Builder;
```

> **Note:** `getWithCommonRelations()` relies on the existing `Booking::withCommonRelations()` scope
> defined in `App\Models\Booking`. Currently not actively used in services/controllers, but kept
> for consistency with documented model scope usage patterns.

---

## RoomRepository Interface Methods

### Query Methods

```php
public function findByIdWithBookings(int $roomId): ?Room;
public function findByIdWithConfirmedBookings(int $roomId): ?Room;
public function getAllOrderedByName(): Collection;
```

### Availability Check

```php
// Throws \Error if room does not exist (preserves original behavior)
public function hasOverlappingConfirmedBookings(int $roomId, string $checkIn, string $checkOut): bool;
```

### Create/Update/Delete with Optimistic Locking

```php
public function create(array $data): Room;
public function updateWithVersionCheck(int $roomId, int $expectedVersion, array $data): int;
public function deleteWithVersionCheck(int $roomId, int $expectedVersion): int;
public function refresh(Room $room): Room;
```

---

## Service Container Binding

In `app/Providers/AppServiceProvider.php`:

```php
public function register(): void
{
    // Booking Repository
    $this->app->bind(
        BookingRepositoryInterface::class,
        EloquentBookingRepository::class
    );

    // Room Repository
    $this->app->bind(
        RoomRepositoryInterface::class,
        EloquentRoomRepository::class
    );
}
```

---

## Usage Example - Booking

### Before (Direct Eloquent)

```php
class CreateBookingService
{
    public function createBookingWithLocking(...): Booking
    {
        return DB::transaction(function () use (...) {
            // Direct Eloquent call - tightly coupled
            $existingBookings = Booking::query()
                ->overlappingBookings($roomId, $checkIn, $checkOut)
                ->withLock()
                ->get();

            if ($existingBookings->isNotEmpty()) {
                throw new RuntimeException('Phòng đã được đặt...');
            }

            // Direct Eloquent call
            return Booking::create([
                'room_id' => $roomId,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'guest_name' => $guestName,
                'guest_email' => $guestEmail,
                'status' => Booking::STATUS_PENDING,
                'user_id' => $userId,
            ]);
        });
    }
}
```

### After (Repository Injection)

```php
class CreateBookingService
{
    public function __construct(
        private BookingRepositoryInterface $bookingRepository
    ) {}

    public function createBookingWithLocking(...): Booking
    {
        return DB::transaction(function () use (...) {
            // Repository method - decoupled, mockable
            $existingBookings = $this->bookingRepository
                ->findOverlappingBookingsWithLock($roomId, $checkIn, $checkOut);

            if ($existingBookings->isNotEmpty()) {
                throw new RuntimeException('Phòng đã được đặt...');
            }

            // Repository method
            return $this->bookingRepository->create([
                'room_id' => $roomId,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'guest_name' => $guestName,
                'guest_email' => $guestEmail,
                'status' => Booking::STATUS_PENDING,
                'user_id' => $userId,
            ]);
        });
    }
}
```

---

## Usage Example - Room

### Before (Direct Eloquent in RoomService)

```php
class RoomService
{
    private function fetchRoomsFromDB(): Collection
    {
        // Direct Eloquent call - tightly coupled
        return Room::select(['id', 'name', 'description', 'price', 'max_guests', 'status', 'created_at', 'updated_at'])
            ->orderBy('name')
            ->get();
    }

    private function checkOverlappingBookings(int $roomId, string $checkIn, string $checkOut): bool
    {
        // Direct Eloquent call
        return !Room::find($roomId)
            ->bookings()
            ->where('status', 'confirmed')
            ->where('check_in', '<', $checkOut)
            ->where('check_out', '>', $checkIn)
            ->exists();
    }

    public function updateWithOptimisticLock(Room $room, array $data, ?int $currentVersion = null): Room
    {
        // Direct DB call - tightly coupled
        $rowsAffected = DB::table('rooms')
            ->where('id', $room->id)
            ->where('lock_version', $currentVersion)
            ->update(...);
        // ...
    }
}
```

### After (Repository Injection)

```php
class RoomService
{
    public function __construct(
        private readonly RoomRepositoryInterface $roomRepository
    ) {}

    private function fetchRoomsFromDB(): Collection
    {
        // Repository method - decoupled, mockable
        return $this->roomRepository->getAllOrderedByName();
    }

    private function checkOverlappingBookings(int $roomId, string $checkIn, string $checkOut): bool
    {
        // Repository method
        return !$this->roomRepository->hasOverlappingConfirmedBookings($roomId, $checkIn, $checkOut);
    }

    public function updateWithOptimisticLock(Room $room, array $data, ?int $currentVersion = null): Room
    {
        // Repository method
        $rowsAffected = $this->roomRepository->updateWithVersionCheck(
            $room->id,
            $currentVersion,
            $updateData
        );
        // ...
    }
}
```

---

## Design Principles

### DO ✅

- Return Eloquent models/collections (no DTOs)
- Mirror existing Booking model usage patterns
- Respect global scopes and soft delete behavior
- Throw same exceptions as Eloquent (e.g., `ModelNotFoundException`)
- Keep methods thin (simple pass-through to Eloquent)

### DON'T ❌

- Add business logic in repository
- Add validation in repository
- Handle transactions in repository (Services handle transactions)
- Transform data (return raw Eloquent results)
- Invent new abstractions or generic CRUD

---

## Testing with Repository

### Unit Test (Mocked Repository)

```php
class CreateBookingServiceTest extends TestCase
{
    public function test_it_throws_when_overlap_exists(): void
    {
        // Create mock
        $mockRepo = $this->mock(BookingRepositoryInterface::class);

        // Setup expectation - return non-empty collection
        $mockRepo->shouldReceive('findOverlappingBookingsWithLock')
            ->once()
            ->andReturn(collect([new Booking()]));

        $service = new CreateBookingService($mockRepo);

        $this->expectException(RuntimeException::class);
        $service->create(...);
    }
}
```

### Feature Test (Real Repository)

```php
class BookingFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_booking_when_no_overlap(): void
    {
        // Uses real EloquentBookingRepository via container
        $response = $this->postJson('/api/bookings', [...]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('bookings', [...]);
    }
}
```

---

## Migration Plan

### Files with Direct Booking:: Usage to Migrate

| File                                               | Usage Count | Priority |
| -------------------------------------------------- | ----------- | -------- |
| `Services/BookingService.php`                      | 7           | HIGH     |
| `Services/CreateBookingService.php`                | 4           | HIGH     |
| `Controllers/AdminBookingController.php`           | 3           | MEDIUM   |
| `Controllers/BookingControllerExample.php`         | 2           | LOW      |
| `Console/Commands/PruneOldSoftDeletedBookings.php` | 1           | LOW      |

### Files with Direct Room:: Usage to Migrate

| File                             | Status  | Notes                                  |
| -------------------------------- | ------- | -------------------------------------- |
| `Services/RoomService.php`       | ✅ DONE | Fully refactored to use RoomRepository |
| `Controllers/RoomController.php` | PENDING | Uses RoomService, low priority         |

### Find All Booking Usages

```bash
grep -rn "Booking::" backend/app --include="*.php" \
  | grep -v "Booking::class" \
  | grep -v "Booking::STATUS" \
  | grep -v "Booking::ACTIVE_STATUSES"
```

### Find All Room Usages

```bash
grep -rn "Room::" backend/app --include="*.php" \
  | grep -v "Room::class" \
  | grep -v "Repository"
```

### Incremental Rollout

1. **Phase 1**: Services (highest value for testability)
2. **Phase 2**: Controllers
3. **Phase 3**: Console commands and jobs

---

## Future Extensions

Once all consumers use the repository:

- **CachingBookingRepository**: Decorator that caches results
- **LoggingBookingRepository**: Decorator that logs queries
- **ReadReplicaBookingRepository**: Routes reads to replica database

```php
// Example: Caching decorator
class CachingBookingRepository implements BookingRepositoryInterface
{
    public function __construct(
        private EloquentBookingRepository $inner,
        private CacheManager $cache
    ) {}

    public function findById(int $id): ?Booking
    {
        return $this->cache->remember(
            "booking:{$id}",
            600,
            fn() => $this->inner->findById($id)
        );
    }
}
```
