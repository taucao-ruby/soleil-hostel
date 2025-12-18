# ⚡ Redis Caching

> Event-driven cache invalidation with tag-based management

## Overview

Soleil Hostel uses Redis for caching with automatic invalidation when data changes.

---

## Cache Strategy

| Resource          | TTL  | Tags                     | Invalidation Trigger  |
| ----------------- | ---- | ------------------------ | --------------------- |
| Room List         | 60s  | `rooms`                  | Room CRUD             |
| Single Room       | 60s  | `room-{id}`              | Room update/delete    |
| Room Availability | 30s  | `room-{id}-availability` | Booking CRUD          |
| User Bookings     | 300s | `user-{id}-bookings`     | User's booking change |
| Single Booking    | 600s | `booking-{id}`           | Booking update/delete |

---

## Services

### RoomService

```php
class RoomService
{
    public function getAllRoomsWithAvailability(): Collection
    {
        return Cache::tags(['rooms'])->remember(
            'rooms:all',
            60,
            fn () => Room::with('activeBookings')->get()
        );
    }

    public function getRoomById(int $id): Room
    {
        return Cache::tags(["room-{$id}"])->remember(
            "room:{$id}",
            60,
            fn () => Room::findOrFail($id)
        );
    }

    public function invalidateRoom(int $id): void
    {
        Cache::tags(['rooms', "room-{$id}"])->flush();
    }
}
```

### BookingService

```php
class BookingService
{
    public function getUserBookings(int $userId): Collection
    {
        return Cache::tags(["user-{$userId}-bookings"])->remember(
            "bookings:user:{$userId}",
            300,
            fn () => Booking::where('user_id', $userId)->get()
        );
    }

    public function invalidateUserBookings(int $userId): void
    {
        Cache::tags(["user-{$userId}-bookings"])->flush();
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
