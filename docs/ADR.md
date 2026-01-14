# Architecture Decision Records (ADR)

> Documenting significant architectural decisions and their rationale

## Overview

This document captures important architectural decisions made during the development of Soleil Hostel. Each decision follows the ADR format:

- **Status**: Proposed | Accepted | Deprecated | Superseded
- **Context**: What is the issue?
- **Decision**: What is the change?
- **Consequences**: What are the trade-offs?

---

## ADR Index

| ID                                                              | Title                                         | Status   | Date     |
| --------------------------------------------------------------- | --------------------------------------------- | -------- | -------- |
| [ADR-001](#adr-001-laravel-notifications-over-custom-mailables) | Laravel Notifications over Custom Mailables   | Accepted | Jan 2026 |
| [ADR-002](#adr-002-repository-pattern-for-data-access)          | Repository Pattern for Data Access            | Accepted | Jan 2026 |
| [ADR-003](#adr-003-pessimistic-locking-for-bookings)            | Pessimistic Locking for Bookings              | Accepted | Dec 2025 |
| [ADR-004](#adr-004-optimistic-locking-for-rooms)                | Optimistic Locking for Rooms                  | Accepted | Jan 2026 |
| [ADR-005](#adr-005-enum-based-rbac-over-boolean-flags)          | Enum-based RBAC over Boolean Flags            | Accepted | Dec 2025 |
| [ADR-006](#adr-006-dual-authentication-modes)                   | Dual Authentication Modes (Bearer + HttpOnly) | Accepted | Dec 2025 |
| [ADR-007](#adr-007-event-driven-cache-invalidation)             | Event-driven Cache Invalidation               | Accepted | Dec 2025 |
| [ADR-008](#adr-008-markdown-email-templates)                    | Markdown Email Templates                      | Accepted | Jan 2026 |
| [ADR-009](#adr-009-soft-deletes-for-bookings)                   | Soft Deletes for Bookings                     | Accepted | Dec 2025 |
| [ADR-010](#adr-010-redis-with-database-fallback)                | Redis with Database Cache Fallback            | Accepted | Dec 2025 |
| [ADR-011](#adr-011-form-request-validation)                     | Form Request Validation Classes               | Accepted | Jan 2026 |
| [ADR-012](#adr-012-api-versioning-strategy)                     | API Versioning Strategy                       | Accepted | Dec 2025 |

---

## ADR-001: Laravel Notifications over Custom Mailables

**Status**: Accepted  
**Date**: January 2026  
**Deciders**: Development Team

### Context

We need to send transactional emails for booking events (confirmed, cancelled, updated). Options:

1. Custom Mailable classes
2. Laravel Notifications
3. Third-party email service (SendGrid templates)

### Decision

Use **Laravel Notifications** with Markdown templates.

### Rationale

| Criteria       | Notifications           | Custom Mailables | Third-party |
| -------------- | ----------------------- | ---------------- | ----------- |
| Queueing       | Built-in                | Manual           | External    |
| Multi-channel  | Easy (mail, SMS, Slack) | Email only       | Varies      |
| Code volume    | Less                    | More             | Less        |
| Vendor lock-in | None                    | None             | High        |
| Cost           | Free                    | Free             | Paid        |

### Consequences

**Positive:**

- Automatic queueing with `ShouldQueue`
- Easy to add SMS/Slack channels later
- Consistent API across notification types
- Less code to maintain

**Negative:**

- Markdown templates less flexible than pure HTML
- Learning curve for Blade mail components

### Related

- [EMAIL_NOTIFICATIONS.md](./backend/guides/EMAIL_NOTIFICATIONS.md)
- [EMAIL_TEMPLATES.md](./backend/features/EMAIL_TEMPLATES.md)

---

## ADR-002: Repository Pattern for Data Access

**Status**: Accepted  
**Date**: January 2026  
**Deciders**: Development Team

### Context

As the codebase grows, we need to:

- Unit test services without database
- Swap data sources (e.g., API instead of DB)
- Reduce coupling between business logic and Eloquent

### Decision

Implement **Repository Pattern** with interfaces and Eloquent implementations.

```
Contracts/
├── BookingRepositoryInterface.php
└── RoomRepositoryInterface.php

Repositories/
├── EloquentBookingRepository.php
└── EloquentRoomRepository.php
```

### Rationale

- **Testability**: Mock repositories in unit tests
- **Flexibility**: Swap implementations without changing services
- **Single Responsibility**: Repositories handle data, services handle logic

### Consequences

**Positive:**

- 53 repository unit tests with zero DB dependency
- Services can be tested in isolation
- Clear separation of concerns

**Negative:**

- More files to maintain
- Extra abstraction layer
- Must keep interface in sync with implementation

### Related

- [REPOSITORIES.md](./backend/architecture/REPOSITORIES.md)

---

## ADR-003: Pessimistic Locking for Bookings

**Status**: Accepted  
**Date**: December 2025  
**Deciders**: Development Team

### Context

Under high load (100-500 requests/second), concurrent booking requests for the same room and dates can cause double-booking despite validation.

### Decision

Use **pessimistic locking** (`SELECT ... FOR UPDATE`) when creating bookings.

```php
DB::transaction(function () {
    $room = Room::lockForUpdate()->findOrFail($roomId);

    $hasOverlap = Booking::where('room_id', $roomId)
        ->lockForUpdate()
        ->overlapping($checkIn, $checkOut)
        ->exists();

    if ($hasOverlap) {
        throw new RuntimeException('Room not available');
    }

    return Booking::create([...]);
});
```

### Rationale

| Approach             | Pros                 | Cons                               |
| -------------------- | -------------------- | ---------------------------------- |
| Pessimistic (chosen) | Zero race conditions | Blocking, potential deadlocks      |
| Optimistic           | Non-blocking         | Retry logic needed, can still race |
| Queue-based          | Serialized           | Added latency, complexity          |

### Consequences

**Positive:**

- Guaranteed no double-booking
- Simple to understand and debug
- Works with any transaction isolation level

**Negative:**

- Blocking: concurrent requests wait
- Deadlock risk (mitigated with retry logic)
- Higher latency under contention

### Deadlock Mitigation

```php
private const DEADLOCK_RETRY_ATTEMPTS = 3;
private const DEADLOCK_RETRY_DELAY_MS = 100; // Exponential backoff
```

### Related

- [BOOKING.md](./backend/features/BOOKING.md)
- [CreateBookingService](./backend/architecture/SERVICES.md#createbookingservice)

---

## ADR-004: Optimistic Locking for Rooms

**Status**: Accepted  
**Date**: January 2026  
**Deciders**: Development Team

### Context

Room updates by admins don't need serialized access like bookings, but we need to prevent lost updates when two admins edit the same room.

### Decision

Use **optimistic locking** with `lock_version` column for room updates.

```php
// Request must include current lock_version
$room->updateWithLock([
    'name' => 'New Name',
    'lock_version' => $currentVersion,
]);

// Throws OptimisticLockException if version mismatch
```

### Rationale

| Criteria        | Optimistic        | Pessimistic          |
| --------------- | ----------------- | -------------------- |
| Contention      | Low (admin edits) | High (user bookings) |
| Blocking        | No                | Yes                  |
| User experience | Retry on conflict | Wait for lock        |

### Consequences

**Positive:**

- Non-blocking for low-contention scenarios
- Clear conflict detection
- No deadlock risk

**Negative:**

- Client must handle version conflicts
- Extra `lock_version` column
- Requires frontend integration for retry UX

### Related

- [OPTIMISTIC_LOCKING.md](./backend/features/OPTIMISTIC_LOCKING.md)

---

## ADR-005: Enum-based RBAC over Boolean Flags

**Status**: Accepted  
**Date**: December 2025  
**Deciders**: Development Team

### Context

Original design used `is_admin` boolean. As roles grew (USER, MODERATOR, ADMIN), boolean flags became unwieldy (`is_admin`, `is_moderator`).

### Decision

Replace boolean flags with **PHP 8.1 backed enum** for roles.

```php
enum UserRole: string
{
    case USER = 'user';
    case MODERATOR = 'moderator';
    case ADMIN = 'admin';

    public function isAtLeast(self $role): bool { ... }
}
```

### Rationale

- Type safety (no string typos)
- IDE autocomplete
- Extensible (add roles without schema changes)
- Database stores string value (PostgreSQL ENUM)

### Consequences

**Positive:**

- 47 RBAC tests with type-safe assertions
- Helper methods: `isAdmin()`, `isModerator()`, `isAtLeast()`
- Middleware: `EnsureUserHasRole`

**Negative:**

- Migration required to remove old columns
- PostgreSQL ENUM type complexity

### Related

- [RBAC.md](./backend/features/RBAC.md)

---

## ADR-006: Dual Authentication Modes

**Status**: Accepted  
**Date**: December 2025  
**Deciders**: Development Team

### Context

Different clients need different auth mechanisms:

- **SPAs**: Prefer HttpOnly cookies (XSS protection)
- **Mobile/API**: Prefer Bearer tokens (flexibility)

### Decision

Support **both** Bearer tokens AND HttpOnly cookies via Sanctum.

```
POST /api/auth/login-v2        → Bearer token response
POST /api/auth/login-httponly  → HttpOnly cookie set
```

### Rationale

| Mode     | Security                         | Use Case            |
| -------- | -------------------------------- | ------------------- |
| Bearer   | Token in localStorage (XSS risk) | Mobile, API clients |
| HttpOnly | Cookie immune to JS (CSRF risk)  | Browser SPAs        |

### Consequences

**Positive:**

- Flexible for all client types
- HttpOnly provides better XSS protection for web
- Token refresh and rotation for both modes

**Negative:**

- Dual code paths to maintain
- CORS configuration complexity
- More test scenarios

### Related

- [AUTHENTICATION.md](./backend/features/AUTHENTICATION.md)

---

## ADR-007: Event-driven Cache Invalidation

**Status**: Accepted  
**Date**: December 2025  
**Deciders**: Development Team

### Context

Cached data (rooms, availability) must stay fresh. Options:

1. Time-based expiration only
2. Manual invalidation in services
3. Event-driven invalidation

### Decision

Use **events** to trigger cache invalidation automatically.

```php
// When booking created
event(new BookingCreated($booking));

// Listener invalidates cache
class InvalidateCacheOnBookingChange
{
    public function handle(BookingCreated $event): void
    {
        Cache::tags(['availability'])->flush();
    }
}
```

### Rationale

- Decoupled: services don't know about caching
- Consistent: all booking changes trigger same invalidation
- Extensible: add more listeners without changing services

### Consequences

**Positive:**

- Clean separation of concerns
- Easy to add new cache invalidation rules
- Testable in isolation

**Negative:**

- Async events may cause brief stale data
- Must remember to dispatch events
- Debug complexity (invisible connections)

### Related

- [EVENTS.md](./backend/architecture/EVENTS.md)
- [CACHING.md](./backend/features/CACHING.md)

---

## ADR-008: Markdown Email Templates

**Status**: Accepted  
**Date**: January 2026  
**Deciders**: Development Team

### Context

Booking notifications need professional appearance. Options:

1. Plain text emails
2. Fluent MailMessage (generic look)
3. Custom HTML templates
4. Markdown templates (Laravel components)

### Decision

Use **Markdown templates** with custom theme (`soleil.css`).

### Rationale

| Approach          | Branding | Maintainability | Complexity |
| ----------------- | -------- | --------------- | ---------- |
| Plain text        | None     | Easy            | Low        |
| Fluent            | Limited  | Easy            | Low        |
| Custom HTML       | Full     | Hard            | High       |
| Markdown (chosen) | Good     | Medium          | Medium     |

### Consequences

**Positive:**

- Brand consistency via `config/email-branding.php`
- Laravel components (buttons, panels, tables)
- Responsive design built-in
- 13 unit tests for templates

**Negative:**

- Less control than raw HTML
- Must publish vendor views for customization

### Related

- [EMAIL_TEMPLATES.md](./backend/features/EMAIL_TEMPLATES.md)

---

## ADR-009: Soft Deletes for Bookings

**Status**: Accepted  
**Date**: December 2025  
**Deciders**: Development Team

### Context

Deleted bookings need to be recoverable for:

- Audit trails
- Accidental deletion recovery
- Reporting on cancelled bookings

### Decision

Use Laravel's **soft deletes** for bookings.

```php
class Booking extends Model
{
    use SoftDeletes;
}
```

### Consequences

**Positive:**

- `deleted_at` timestamp for audit
- `withTrashed()` for admin queries
- `restore()` for recovery

**Negative:**

- Queries must consider soft deletes
- Storage grows (never truly deleted)
- Unique constraints more complex

### Related

- [BOOKING.md](./backend/features/BOOKING.md)

---

## ADR-010: Redis with Database Cache Fallback

**Status**: Accepted  
**Date**: December 2025  
**Deciders**: Development Team

### Context

Redis provides tag-based caching but may not be available in all environments (dev, CI, some hosting).

### Decision

Use **Redis as primary** with **database cache fallback**.

```php
trait HasCacheTagSupport
{
    protected function supportsTags(): bool
    {
        return Cache::supportsTags();
    }
}
```

### Consequences

**Positive:**

- Works in any environment
- Graceful degradation
- CI/CD tests pass without Redis

**Negative:**

- Database cache slower
- No tag support in fallback mode
- Must test both code paths

### Related

- [CACHING.md](./backend/features/CACHING.md)

---

## ADR-011: Form Request Validation Classes

**Status**: Accepted  
**Date**: January 2026  
**Deciders**: Development Team

### Context

Inline validation in controllers (`$request->validate([...])`) works but:

- Duplicates rules across methods
- Harder to test validation in isolation
- Mixes HTTP and validation concerns

### Decision

Extract validation into **Form Request classes**.

```php
// Before
public function register(Request $request)
{
    $request->validate(['email' => 'required|email|unique:users']);
}

// After
public function register(RegisterRequest $request)
{
    $validated = $request->validated();
}
```

### Consequences

**Positive:**

- Reusable validation rules
- Testable in isolation
- Clean controllers
- Custom error messages in one place

**Negative:**

- More files
- Slight indirection

### Related

- [Auth/RegisterRequest](./backend/app/Http/Requests/Auth/RegisterRequest.php)
- [Auth/LoginRequest](./backend/app/Http/Requests/Auth/LoginRequest.php)

---

## ADR-012: API Versioning Strategy

**Status**: Accepted  
**Date**: December 2025  
**Deciders**: Development Team

### Context

As API evolves, we need to:

- Support multiple client versions
- Deprecate old endpoints gracefully
- Avoid breaking changes

### Decision

Use **URL path versioning** with suffix pattern for iterations.

```
/api/auth/login           → Legacy (v1 implicit)
/api/auth/login-v2        → Bearer token version
/api/auth/login-httponly  → HttpOnly cookie version
```

### Rationale

| Strategy          | Pros             | Cons                   |
| ----------------- | ---------------- | ---------------------- |
| URL path (chosen) | Clear, cacheable | URL pollution          |
| Header            | Clean URLs       | Hidden, harder to test |
| Query param       | Flexible         | Not RESTful            |

### Consequences

**Positive:**

- Explicit versioning in URL
- Easy to route and document
- Cacheable by CDN

**Negative:**

- Multiple routes to maintain
- Must document which version to use

### Related

- [API.md](./backend/architecture/API.md)
- [API_DEPRECATION.md](./API_DEPRECATION.md)

---

## Template for New ADRs

```markdown
## ADR-XXX: Title

**Status**: Proposed | Accepted | Deprecated | Superseded by ADR-YYY  
**Date**: Month Year  
**Deciders**: Who was involved

### Context

What is the issue? What forces are at play?

### Decision

What is the change being proposed?

### Rationale

Why was this decision made? Include alternatives considered.

### Consequences

**Positive:**

- Benefit 1
- Benefit 2

**Negative:**

- Drawback 1
- Drawback 2

### Related

- Links to related docs
```

---

## Decision Log History

| Date     | ADR     | Action | Notes                        |
| -------- | ------- | ------ | ---------------------------- |
| Jan 2026 | ADR-008 | Added  | Markdown email templates     |
| Jan 2026 | ADR-011 | Added  | Form request validation      |
| Jan 2026 | ADR-002 | Added  | Repository pattern           |
| Jan 2026 | ADR-004 | Added  | Optimistic locking           |
| Dec 2025 | ADR-001 | Added  | Notifications over Mailables |
| Dec 2025 | ADR-003 | Added  | Pessimistic locking          |
| Dec 2025 | ADR-005 | Added  | Enum-based RBAC              |
| Dec 2025 | ADR-006 | Added  | Dual auth modes              |
| Dec 2025 | ADR-007 | Added  | Event-driven cache           |
| Dec 2025 | ADR-009 | Added  | Soft deletes                 |
| Dec 2025 | ADR-010 | Added  | Redis fallback               |
| Dec 2025 | ADR-012 | Added  | API versioning               |
