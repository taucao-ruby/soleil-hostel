# 🏗️ Architecture Overview

> System architecture and design decisions for Soleil Hostel

## Documentation Index

| Document                                       | Description                  |
| ---------------------------------------------- | ---------------------------- |
| [DATABASE.md](./DATABASE.md)                   | Schema, indexes, constraints |
| [API.md](./API.md)                             | Complete API reference       |
| [FRONTEND.md](./FRONTEND.md)                   | Frontend architecture        |
| [MIDDLEWARE.md](./MIDDLEWARE.md)               | Middleware pipeline          |
| [EVENTS.md](./EVENTS.md)                       | Events & listeners           |
| [POLICIES.md](./POLICIES.md)                   | Authorization policies       |
| [JOBS.md](./JOBS.md)                           | Queue jobs                   |
| [TRAITS_EXCEPTIONS.md](./TRAITS_EXCEPTIONS.md) | Traits, macros & exceptions  |

---

## System Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                           FRONTEND                                   │
│                    React 19 + TypeScript + Vite                     │
│                         (localhost:5173)                            │
└─────────────────────────────┬───────────────────────────────────────┘
                              │ HTTPS / REST API
                              ▼
┌─────────────────────────────────────────────────────────────────────┐
│                         BACKEND (Laravel 11)                        │
├─────────────────────────────────────────────────────────────────────┤
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐                 │
│  │ Controllers │→ │  Services   │→ │   Models    │                 │
│  └─────────────┘  └─────────────┘  └─────────────┘                 │
│         │                │                │                         │
│         ▼                ▼                ▼                         │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐                 │
│  │ Middleware  │  │   Events    │  │  Policies   │                 │
│  │ - Auth      │  │ - Booking*  │  │ - Booking   │                 │
│  │ - RBAC      │  │ - Cache*    │  │ - Room      │                 │
│  │ - RateLimit │  └─────────────┘  └─────────────┘                 │
│  │ - Security  │                                                    │
│  └─────────────┘                                                    │
└───────────────────────────┬─────────────────────────────────────────┘
                            │
          ┌─────────────────┼─────────────────┐
          ▼                 ▼                 ▼
   ┌────────────┐    ┌────────────┐    ┌────────────┐
   │ PostgreSQL │    │   Redis    │    │   Queue    │
   │  (Primary) │    │  (Cache)   │    │  (Jobs)    │
   └────────────┘    └────────────┘    └────────────┘
```

---

## Layer Responsibilities

### Controllers

- HTTP request/response handling
- Input validation via Form Requests
- Delegate business logic to Services

### Services

- Business logic implementation
- Database transactions
- Event dispatching

### Models

- Eloquent ORM entities
- Relationships
- Scopes and accessors

### Middleware

- Authentication (Sanctum)
- Authorization (RBAC)
- Rate limiting
- Security headers

### Policies

- Resource authorization
- Owner-based access control

---

## Key Design Patterns

### Service Layer Pattern

```php
// Controller delegates to Service
class BookingController
{
    public function __construct(
        private CreateBookingService $bookingService
    ) {}

    public function store(StoreBookingRequest $request)
    {
        $booking = $this->bookingService->create($request->validated());
        return new BookingResource($booking);
    }
}
```

### Event-Driven Cache Invalidation

```php
// Service dispatches event
BookingCreated::dispatch($booking);

// Listener invalidates cache
class InvalidateCacheOnBookingChange
{
    public function handle(BookingCreated $event)
    {
        Cache::tags(['rooms', "room-{$event->booking->room_id}"])->flush();
    }
}
```

### Optimistic Locking

```php
// Atomic compare-and-swap
$affected = DB::table('rooms')
    ->where('id', $room->id)
    ->where('lock_version', $currentVersion)
    ->update([...$data, 'lock_version' => DB::raw('lock_version + 1')]);

if ($affected === 0) {
    throw new OptimisticLockException();
}
```

### Pessimistic Locking (Double-Booking Prevention)

```php
DB::transaction(function () {
    // Lock overlapping bookings
    $overlapping = Booking::overlappingBookings($roomId, $checkIn, $checkOut)
        ->lockForUpdate()
        ->exists();

    if ($overlapping) {
        throw new BookingOverlapException();
    }

    // Safe to create
    return Booking::create($data);
});
```

---

## Authentication Flow

### Bearer Token

```
1. POST /api/auth/login → Token returned in response body
2. Client stores token in localStorage
3. Subsequent requests: Authorization: Bearer <token>
4. Token expires after 60 minutes
5. POST /api/auth/refresh → New token
```

### HttpOnly Cookie

```
1. POST /api/auth/login-httponly → Token set in HttpOnly cookie
2. Client cannot read token (XSS safe)
3. Cookie automatically sent with requests
4. CSRF token required for mutations
5. POST /api/auth/refresh-httponly → Cookie rotated
```

---

## Authorization (RBAC)

```
USER (default)
  ├── View own bookings
  ├── Create bookings
  └── Cancel own bookings

MODERATOR
  ├── All USER permissions
  ├── View all bookings
  └── Moderate content

ADMIN
  ├── All MODERATOR permissions
  ├── Manage users
  ├── Manage rooms
  ├── Restore/force-delete bookings
  └── Access admin endpoints
```

---

## Caching Strategy

| Resource          | TTL  | Invalidation                    |
| ----------------- | ---- | ------------------------------- |
| Room list         | 60s  | On room create/update/delete    |
| Room availability | 30s  | On booking create/update/delete |
| User bookings     | 300s | On user's booking change        |
| Single booking    | 600s | On booking update/delete        |

---

## Rate Limiting

| Endpoint      | Limit                 | Window         |
| ------------- | --------------------- | -------------- |
| Login         | 5 req/min per IP      | Sliding window |
| Login         | 20 req/hour per email | Sliding window |
| Booking       | 3 req/min per user    | Token bucket   |
| API (general) | 60 req/min per user   | Sliding window |

---

## Security Headers

```
Strict-Transport-Security: max-age=63072000; includeSubDomains; preload
X-Frame-Options: DENY
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: camera=(), microphone=(), geolocation=()
Content-Security-Policy: script-src 'nonce-xxx' 'strict-dynamic'
Cross-Origin-Opener-Policy: same-origin
Cross-Origin-Embedder-Policy: require-corp
Cross-Origin-Resource-Policy: same-origin
```

---

## Database Schema

See [DATABASE.md](./DATABASE.md) for complete schema and index documentation.

---

## API Reference

See [API.md](./API.md) for complete API documentation.
