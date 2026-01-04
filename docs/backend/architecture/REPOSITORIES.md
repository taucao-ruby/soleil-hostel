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

---

## Interface Methods

### Basic CRUD

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
```

### Soft Delete Operations

```php
public function getTrashed(array $relations = []): Collection;
public function findTrashedById(int $id, array $relations = []): ?Booking;
public function restore(Booking $booking): bool;
public function forceDelete(Booking $booking): bool;
public function getTrashedOlderThan(Carbon $cutoffDate): Collection;
```

### Admin/Listing

```php
public function getAllWithTrashed(array $relations = []): Collection;
public function getWithCommonRelations(): Collection;
public function query(): Builder;
```

---

## Service Container Binding

In `app/Providers/AppServiceProvider.php`:

```php
public function register(): void
{
    $this->app->bind(
        BookingRepositoryInterface::class,
        EloquentBookingRepository::class
    );
}
```

---

## Usage Example

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

### Find All Usages

```bash
grep -rn "Booking::" backend/app --include="*.php" \
  | grep -v "Booking::class" \
  | grep -v "Booking::STATUS" \
  | grep -v "Booking::ACTIVE_STATUSES"
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
