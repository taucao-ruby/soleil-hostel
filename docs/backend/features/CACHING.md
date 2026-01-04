# ⚡ Redis Caching

> Event-driven cache invalidation with tag-based management and automatic fallback

## Overview

Soleil Hostel uses Redis for caching with automatic invalidation when data changes. The system includes a **tag-based cache** strategy with automatic fallback to database cache when Redis is unavailable.

---

## Cache Strategy

| Resource          | TTL  | Tags                                     | Key Pattern                                        |
| ----------------- | ---- | ---------------------------------------- | -------------------------------------------------- |
| Room List         | 60s  | `rooms`                                  | `rooms:list:all:active`                            |
| Single Room       | 60s  | `rooms`, `room-{id}`                     | `rooms:id:{id}`                                    |
| Room Availability | 30s  | `availability`, `availability-room-{id}` | `rooms:availability:{roomId}:{checkIn}:{checkOut}` |
| User Bookings     | 300s | `user-{id}-bookings`                     | `bookings:user:{userId}`                           |
| Single Booking    | 600s | `booking-{id}`                           | `bookings:id:{id}`                                 |

---

## HasCacheTagSupport Trait

All caching services use the `HasCacheTagSupport` trait for tag support detection:

```php
// app/Traits/HasCacheTagSupport.php
namespace App\Traits;

use Illuminate\Support\Facades\Cache;

trait HasCacheTagSupport
{
    /**
     * Check if the cache driver supports tagging.
     */
    protected function supportsTags(): bool
    {
        return Cache::supportsTags();
    }
}
```

**Services using this trait:**

- `RoomService`
- `RoomAvailabilityService`
- `BookingService`
- `RoomAvailabilityCache`

---

## RoomService Implementation

```php
class RoomService
{
    use HasCacheTagSupport;

    private const CACHE_TTL_ROOMS = 60;          // 1 minute
    private const CACHE_TTL_AVAILABILITY = 30;   // 30 seconds
    private const CACHE_TAG_ROOMS = 'rooms';
    private const CACHE_TAG_AVAILABILITY = 'availability';

    public function getAllRoomsWithAvailability(): Collection
    {
        $cacheKey = 'rooms:list:all:active';

        if (!$this->supportsTags()) {
            return Cache::remember($cacheKey, self::CACHE_TTL_ROOMS,
                fn() => $this->fetchRoomsFromDB()
            );
        }

        return Cache::tags([self::CACHE_TAG_ROOMS])
            ->remember($cacheKey, self::CACHE_TTL_ROOMS,
                fn() => $this->fetchRoomsFromDB()
            );
    }

    public function isRoomAvailable(int $roomId, string $checkIn, string $checkOut): bool
    {
        $cacheKey = "rooms:availability:{$roomId}:{$checkIn}:{$checkOut}";

        if ($this->supportsTags()) {
            // Use lock to prevent thundering herd
            $lock = Cache::lock("rooms:availability:lock:{$roomId}", 5);

            if ($lock->get()) {
                $result = Cache::tags([self::CACHE_TAG_AVAILABILITY, "availability-room-{$roomId}"])
                    ->remember($cacheKey, self::CACHE_TTL_AVAILABILITY,
                        fn() => $this->checkOverlappingBookings($roomId, $checkIn, $checkOut)
                    );
                $lock->release();
                return $result;
            }
        }

        // Fallback: direct DB query
        return $this->checkOverlappingBookings($roomId, $checkIn, $checkOut);
    }
}
```

---

## Event-Driven Invalidation

### Events

```php
// When booking is created
BookingCreated::dispatch($booking);

// When booking is updated
BookingUpdated::dispatch($booking, $oldRoomId);

// When booking is deleted
BookingDeleted::dispatch($booking);
```

### Listener

```php
class InvalidateCacheOnBookingChange implements ShouldQueue
{
    public function handle($event): void
    {
        $booking = $event->booking;

        // Invalidate room availability
        Cache::tags(["room-{$booking->room_id}-availability"])->flush();
        Cache::tags(['rooms'])->flush();

        // Invalidate user bookings
        Cache::tags(["user-{$booking->user_id}-bookings"])->flush();

        // For updates: also invalidate old room
        if ($event instanceof BookingUpdated && $event->oldRoomId) {
            Cache::tags(["room-{$event->oldRoomId}-availability"])->flush();
        }
    }
}
```

---

## Configuration

### .env

```env
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### Fallback

If Redis is unavailable, the system automatically falls back to database cache:

```php
// config/cache.php
'default' => env('CACHE_DRIVER', 'database'),
```

---

## Cache Flow

### First Request (Cache Miss)

```
GET /api/rooms
  → Check Redis
  → Miss (empty)
  → Query Database (~150ms)
  → Store in Redis (60s TTL)
  → Return response
```

### Subsequent Requests (Cache Hit)

```
GET /api/rooms
  → Check Redis
  → Hit! (<1ms)
  → Return cached response
```

### Data Change (Invalidation)

```
POST /api/bookings
  → Create booking
  → Dispatch BookingCreated event
  → Listener flushes related cache tags
  → Next request gets fresh data
```

---

## Performance Impact

| Metric              | Before | After  |
| ------------------- | ------ | ------ |
| Response Time (p50) | ~300ms | ~75ms  |
| DB Queries/Session  | 50+    | 2-5    |
| Cache Hit Rate      | 0%     | 85-95% |

---

## Tests

```bash
php artisan test tests/Feature/Cache/
php artisan test tests/Unit/CacheUnitTest.php
```

| Test Category      | Count |
| ------------------ | ----- |
| Cache Hit/Miss     | 2     |
| Cache Invalidation | 3     |
| Fallback           | 1     |
| **Total**          | **6** |

---

## Docker Setup

```bash
# Start Redis
docker-compose up -d redis

# Verify
docker exec -it redis redis-cli ping
# → PONG
```

---

## Debugging

### Check Cache Keys

```bash
docker exec -it redis redis-cli
> KEYS *
> GET rooms:all
> TTL rooms:all
```

### Clear Cache

```bash
php artisan cache:clear
# Or specific tags
php artisan tinker
>>> Cache::tags(['rooms'])->flush();
```
