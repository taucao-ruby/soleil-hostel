# Architecture Decision Records (ADR)

> Documenting significant architectural decisions and their rationale

## Overview

This document captures important architectural decisions made during the development of Soleil Hostel. Each decision follows the ADR format:

- **Status**: Proposed | Accepted | Deprecated | Superseded
- **Context**: What specific technical pressure or constraint forced this decision?
- **Decision**: What was chosen and where it applies.
- **Rationale**: Why this option over named alternatives, with explicit tradeoffs.
- **Consequences**: Positive outcomes AND operational costs — both required.
- **Related**: ADR cross-links and canonical docs only.

---

## ADR Index

| ID                                                                         | Title                                         | Status   | Date      |
| -------------------------------------------------------------------------- | --------------------------------------------- | -------- | --------- |
| [ADR-001](#adr-001-laravel-notifications-over-custom-mailables)            | Laravel Notifications over Custom Mailables   | Accepted | Jan 2026  |
| [ADR-002](#adr-002-repository-pattern-for-data-access)                    | Repository Pattern for Data Access            | Accepted | Jan 2026  |
| [ADR-003](#adr-003-pessimistic-locking-for-booking-write-paths)           | Pessimistic Locking for Booking Write Paths   | Accepted | Dec 2025  |
| [ADR-004](#adr-004-optimistic-locking-for-admin-entity-edits)             | Optimistic Locking for Admin Entity Edits     | Accepted | Jan 2026  |
| [ADR-005](#adr-005-enum-based-rbac-over-boolean-flags)                    | Enum-based RBAC over Boolean Flags            | Accepted | Dec 2025  |
| [ADR-006](#adr-006-dual-authentication-modes)                             | Dual Authentication Modes (Bearer + HttpOnly) | Accepted | Dec 2025  |
| [ADR-007](#adr-007-event-driven-cache-invalidation)                       | Event-driven Cache Invalidation               | Accepted | Dec 2025  |
| [ADR-008](#adr-008-markdown-email-templates)                              | Markdown Email Templates                      | Accepted | Jan 2026  |
| [ADR-009](#adr-009-soft-deletes-for-bookings)                             | Soft Deletes for Bookings                     | Accepted | Dec 2025  |
| [ADR-010](#adr-010-redis-with-database-cache-fallback)                    | Redis with Database Cache Fallback            | Accepted | Dec 2025  |
| [ADR-011](#adr-011-form-request-validation-classes)                       | Form Request Validation Classes               | Accepted | Jan 2026  |
| [ADR-012](#adr-012-url-path-api-versioning-apiv1)                         | URL Path API Versioning (/api/v1/)            | Accepted | Dec 2025  |
| [ADR-013](#adr-013-multi-location-architecture)                           | Multi-Location Architecture                   | Accepted | Feb 2026  |

---

## ADR-001: Laravel Notifications over Custom Mailables

**Status**: Accepted
**Date**: January 2026
**Deciders**: Development Team

### Context

The booking system fires multiple transactional email events (booking confirmed, cancelled, updated). Each event needed to be queued asynchronously, consistent in appearance, and potentially extensible to non-email channels (SMS, database) without rewriting notification dispatch sites. Custom Mailable classes handle email only and require manual queue integration.

**Scope:** All booking-triggered transactional emails. Does not cover marketing or bulk email.

### Decision

Use **Laravel Notifications** with Markdown templates for all booking-triggered transactional emails. Template rendering specifics are governed by ADR-008.

### Rationale

| Criteria       | Notifications           | Custom Mailables         | Third-party Service |
| -------------- | ----------------------- | ------------------------ | ------------------- |
| Queueing       | Built-in (`ShouldQueue`)| Manual setup required    | External API call   |
| Multi-channel  | Native (mail, SMS, DB)  | Email only               | Varies by vendor    |
| Vendor lock-in | None                    | None                     | High                |
| Cost           | None                    | None                     | Paid                |

Custom Mailables were rejected: adding a non-email channel would require structural refactoring of every dispatch site. Third-party services were rejected due to vendor dependency and ongoing cost for a feature set Laravel covers natively.

### Consequences

**Positive:**

- Queueing is opt-in per notification class via `ShouldQueue` — no manual queue plumbing required
- Adding a second notification channel (e.g., SMS) requires adding a listener, not changing dispatch sites
- Notification contract is consistent across all booking event types

**Negative:**

- All notification rendering is bound to the Laravel Notification contract — migrating to an external notification platform requires replacing notification classes, not just templates
- Template flexibility is constrained by Markdown rendering (see ADR-008 for template-specific tradeoffs)

### Related

- [ADR-008](#adr-008-markdown-email-templates) — template rendering strategy
- [EMAIL_NOTIFICATIONS.md](./backend/guides/EMAIL_NOTIFICATIONS.md)

---

## ADR-002: Repository Pattern for Data Access

**Status**: Accepted
**Date**: January 2026
**Deciders**: Development Team

### Context

Services consuming Eloquent models directly created two blockers: unit tests required a live database (or fragile Eloquent partial mocking), and query construction was entangled with business logic inside service methods. As the booking and room domains gained complexity, both testability and separation became active problems.

**Scope:** Backend data access layer for the booking and room domains. Does not apply to ad-hoc admin reporting queries that do not belong to a bounded service.

### Decision

Implement **Repository Pattern** with interfaces and Eloquent implementations. Services depend on interfaces; Eloquent implementations are bound in the service container.

```
Contracts/
├── BookingRepositoryInterface.php
└── RoomRepositoryInterface.php

Repositories/
├── EloquentBookingRepository.php
└── EloquentRoomRepository.php
```

### Rationale

| Approach                       | Testability          | Service coupling | Added complexity |
| ------------------------------ | -------------------- | ---------------- | ---------------- |
| Direct Eloquent in service     | Requires DB fixture  | High             | None             |
| Query Objects                  | Partial isolation    | Medium           | Medium           |
| Repository with interface (chosen) | Mock-friendly    | Low              | Medium           |

Direct Eloquent calls in services were rejected: service unit tests would require a database fixture or fragile Eloquent static mocking. Query Objects were considered but provide no interface boundary for dependency injection. The Repository interface provides a clear seam for both test doubles and potential future data source changes.

### Consequences

**Positive:**

- Service unit tests use mock repositories with zero database dependency
- Query logic is isolated per domain entity — Eloquent internals do not surface in service tests
- Controller → Service → Repository layering is enforced structurally, not just by convention

**Negative:**

- Each new query capability requires an interface method, an implementation, and a test double update — three touch points per change
- The interface must stay in sync with the implementation; divergence causes subtle runtime failures, not compile-time errors
- Added indirection makes simple queries more verbose than a direct Eloquent call

### Related

- [REPOSITORIES.md](./backend/architecture/REPOSITORIES.md)

---

## ADR-003: Pessimistic Locking for Booking Write Paths

**Status**: Accepted
**Date**: December 2025
**Deciders**: Development Team

### Context

Concurrent booking creation and cancellation requests for the same room can produce double-bookings despite application-level validation. Under any meaningful concurrency, two requests can pass the availability check simultaneously before either inserts. The cancellation path carries the same race risk: a room freed by a cancellation must not be double-allocated before the cancellation commits.

**Scope:** Booking creation and cancellation write paths only. Does not apply to low-contention admin edits (see ADR-004).

### Decision

Use **pessimistic locking** (`SELECT ... FOR UPDATE` via `lockForUpdate()`) within database transactions for booking creation and booking cancellation. Both the room row and the overlapping bookings query are locked for the duration of the transaction.

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

| Approach                  | Double-booking prevention | Blocking cost                  | Complexity |
| ------------------------- | ------------------------- | ------------------------------ | ---------- |
| Pessimistic (chosen)      | Guaranteed                | Requests serialize under contention | Low   |
| Optimistic                | Probabilistic under race  | None                           | Retry logic required |
| Queue-based serialization | Guaranteed                | Added latency always           | High       |

Optimistic locking was rejected for this path: the check-then-insert window is a genuine TOCTOU race; optimistic locking would require retry logic that still cannot guarantee atomicity without further coordination. Queue-based serialization was rejected as disproportionate complexity for a transactional, short-lived write path.

### Consequences

**Positive:**

- No double-booking is possible while the transaction holds the lock — correctness is guaranteed, not probabilistic
- The logic is simple and auditable: lock, check, create or abort
- Works at any PostgreSQL transaction isolation level (the lock is explicit, not isolation-level dependent)

**Negative:**

- Concurrent write requests for the same room serialize — throughput for that room is bounded by lock hold time, an accepted operational cost
- Deadlock risk exists when multiple resources are locked in different orders; mitigated by a retry policy with exponential backoff:

```php
private const DEADLOCK_RETRY_ATTEMPTS = 3;
private const DEADLOCK_RETRY_DELAY_MS = 100; // base delay; exponential backoff applied
```

- Higher P99 latency under contention is an accepted consequence
- This strategy applies to `CancellationService` and booking creation — not to room or location admin edits (ADR-004)

### Related

- [ADR-004](#adr-004-optimistic-locking-for-admin-entity-edits) — complementary strategy for low-contention admin edit paths
- [BOOKING.md](./backend/features/BOOKING.md)
- [ARCHITECTURE_FACTS.md](./agents/ARCHITECTURE_FACTS.md) — confirms `lockForUpdate()` in `CancellationService.php` and `Booking.php`

---

## ADR-004: Optimistic Locking for Admin Entity Edits

**Status**: Accepted
**Date**: January 2026
**Deciders**: Development Team

### Context

Admins occasionally edit room and location records simultaneously. These edits are low-frequency and concurrent conflicts are rare. Serializing them with pessimistic locks — as required for booking writes — would add unnecessary blocking overhead and does not match the risk profile. However, lost updates (two admins overwriting each other silently) must still be prevented.

**Scope:** Admin edits to `rooms` and `locations` entities only. Does not replace pessimistic locking for booking or cancellation flows (ADR-003).

### Decision

Use **optimistic locking** with a `lock_version` column on `rooms` and `locations` for admin entity edits. The current `lock_version` must be included in every update request; a version mismatch raises an `OptimisticLockException` (surfaced as HTTP 409 Conflict). Callers are responsible for handling the 409 and presenting a retry or refresh UX.

```php
// Request must include current lock_version
$room->updateWithLock([
    'name' => 'New Name',
    'lock_version' => $currentVersion,
]);

// Throws OptimisticLockException (→ HTTP 409) if version mismatch
```

**Schema:**
- `rooms.lock_version` — NOT NULL, default 1 (migration `2025_12_18_200000`)
- `locations.lock_version` — default 1 (migration `2026_02_09_000001`)

### Rationale

| Criteria        | Optimistic (chosen) | Pessimistic          |
| --------------- | ------------------- | -------------------- |
| Contention      | Low (admin edits)   | High (user bookings) |
| Blocking        | No                  | Yes                  |
| Deadlock risk   | None                | Present; requires retry |
| Conflict UX     | Client retries on 409 | Client waits for lock |

Pessimistic locking was rejected for admin edits: admin UI conflicts are rare, and a 409 retry is preferable to holding a lock that blocks other readers. Using the same strategy as booking paths would add lock contention across unrelated write flows.

### Consequences

**Positive:**

- Non-blocking for the common case (no concurrent edit)
- Conflict detection is explicit: the caller receives a 409 and knows the record changed underneath them
- No deadlock risk

**Negative:**

- `lock_version` is part of the write contract — every update endpoint for rooms and locations must include it; omitting it silently breaks conflict detection unless the endpoint validates its presence
- Clients must handle 409 responses and present a retry or reload UX — an accepted coupling between server conflict detection and client behaviour
- Two additional columns (`lock_version` on rooms and locations) must be incremented on every update and included in API payloads

### Related

- [ADR-003](#adr-003-pessimistic-locking-for-booking-write-paths) — pessimistic strategy for high-contention booking write paths
- [OPTIMISTIC_LOCKING.md](./backend/features/OPTIMISTIC_LOCKING.md)

---

## ADR-005: Enum-based RBAC over Boolean Flags

**Status**: Accepted
**Date**: December 2025
**Deciders**: Development Team

### Context

The original user model used an `is_admin` boolean. Adding a moderator role required a second boolean (`is_moderator`), with no expressible hierarchy between roles. Enforcement logic scattered across controllers had no single type-safe authority. Extending to a third role would have added a third column and another round of scattered conditionals.

### Decision

Replace all boolean role flags with a **PHP 8.1 backed string enum** (`App\Enums\UserRole`), stored as a PostgreSQL ENUM type (`user_role_enum`). Role enforcement is applied at the Gate and middleware layer — not as conditional UI rendering alone.

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

| Criteria        | Enum (chosen)              | Boolean flags                      |
| --------------- | -------------------------- | ---------------------------------- |
| Type safety     | PHP compile-level          | String literals or runtime         |
| Hierarchy       | `isAtLeast()` method       | Manual multi-flag and-chain        |
| Adding a role   | `ALTER TYPE` + policy update | New boolean column + migration + scattered conditionals |
| IDE support     | Full autocomplete          | None                               |

Boolean flags were replaced because role hierarchy (admin > moderator > user) cannot be expressed without manual comparison chains, and every new role required a new boolean column. The enum approach requires no boolean column additions for new roles.

### Consequences

**Positive:**

- Role checks are type-safe — no string typo risk; invalid role values are rejected at the PHP enum level
- Adding a new role requires a new enum case, an `ALTER TYPE user_role_enum ADD VALUE` migration (lightweight DDL, no data migration), and updated Gate/policy rules — no boolean column addition
- Enforcement via `EnsureUserHasRole` middleware and Laravel Gates applies at the request boundary, not only at the UI layer — a role check cannot be bypassed by manipulating frontend rendering
- Helper methods (`isAdmin()`, `isModerator()`, `isAtLeast()`) centralise role comparison logic in one place

**Negative:**

- PostgreSQL ENUM types require an `ALTER TYPE` DDL migration for each new role value — a schema operation that must be coordinated in production deployments and cannot be rolled back trivially
- Removing or renaming an enum value is a multi-step migration to avoid constraint violations on existing rows
- Migrating from old boolean columns required a data backfill to populate the `role` column for existing users

### Related

- [RBAC.md](./backend/features/RBAC.md)
- [PERMISSION_MATRIX.md](./PERMISSION_MATRIX.md) — canonical RBAC permission baseline and enforcement status

---

## ADR-006: Dual Authentication Modes

**Status**: Accepted
**Date**: December 2025
**Deciders**: Development Team

### Context

The system serves two distinct client classes with incompatible auth requirements:

- **Browser SPA**: Auth tokens must be inaccessible to JavaScript to mitigate XSS exfiltration. HttpOnly cookies satisfy this requirement.
- **Mobile / API clients**: Cannot participate in an HttpOnly cookie session without a browser context. Bearer tokens delivered in the response body are the standard mechanism for these clients.

A single auth mode would either accept XSS exposure for the SPA (Bearer-only) or break non-browser clients (HttpOnly-only).

### Decision

Support **both** Bearer token auth and HttpOnly cookie auth via Sanctum. Each mode is served by a dedicated login endpoint. Both modes enforce token expiry (`expires_at`) and explicit revocation (`revoked_at`). The HttpOnly cookie path uses a custom token lookup — `token_identifier` (UUID stored in cookie) → `token_hash` DB lookup — rather than direct Sanctum token comparison.

```
POST /api/auth/login-v2        → Returns Bearer token in response body
POST /api/auth/login-httponly  → Sets HttpOnly cookie; no token in response body
```

### Rationale

| Mode          | XSS posture                       | Client class    | Mechanism             |
| ------------- | --------------------------------- | --------------- | --------------------- |
| HttpOnly cookie | Token inaccessible to JS        | Browser SPA     | Cookie + CSRF header  |
| Bearer token  | Token accessible to JS (client storage risk is client's responsibility) | Mobile, API | `Authorization` header |

A single-mode system was rejected in both directions: HttpOnly-only blocks mobile and API integrations; Bearer-only requires accepting XSS token exfiltration risk for the SPA.

### Consequences

**Positive:**

- Browser SPA tokens are inaccessible to injected JavaScript — XSS cannot exfiltrate the auth token from the HttpOnly cookie
- Bearer mode is compatible with standard HTTP clients and mobile SDKs without browser session overhead
- Token expiry and revocation are enforced for both modes

**Negative:**

- Two distinct auth code paths to maintain: `HttpOnlyTokenController`, `UnifiedAuthController`, `AuthController`
- The HttpOnly cookie path requires CSRF protection: a CSRF token is stored in `sessionStorage` and sent as the `X-XSRF-TOKEN` header on all mutating requests — bypassing this header breaks CSRF defence
- Custom token columns (`token_identifier`, `token_hash`, `device_id`, `device_fingerprint`, `expires_at`, `revoked_at`, `refresh_count`, `last_rotated_at`, `type`) extend the `personal_access_tokens` schema — any Sanctum upgrade must be verified against these additions
- Every auth-sensitive endpoint requires test coverage under both modes — test matrix doubles
- CORS configuration must permit cookie mode for the SPA origin without over-permitting for API consumers

### Related

- [AUTHENTICATION.md](./backend/features/AUTHENTICATION.md)
- [ARCHITECTURE_FACTS.md](./agents/ARCHITECTURE_FACTS.md) — full custom token column inventory and enforcement middleware

---

## ADR-007: Event-driven Cache Invalidation

**Status**: Accepted
**Date**: December 2025
**Deciders**: Development Team

### Context

Room availability data is cached to reduce database load. When a booking is created or cancelled, cached availability for that room becomes stale. Explicit invalidation calls inside each service method would couple cache management to business logic and create a maintenance liability as the number of booking-affecting operations grows.

**Scope:** Cache invalidation strategy — which events trigger invalidation and how. Cache backend selection is ADR-010.

### Decision

Trigger cache invalidation via **Laravel events**. Services dispatch domain events (e.g., `BookingCreated`, `BookingCancelled`); dedicated listeners handle cache invalidation.

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

**Note:** `Cache::tags()` requires Redis. When the database cache fallback is active (ADR-010), tag-based invalidation is unavailable — stale entries persist until TTL expiry.

### Rationale

| Approach                        | Service coupling | Consistency guarantee   | Debuggability          |
| ------------------------------- | ---------------- | ----------------------- | ---------------------- |
| Time-based expiry only          | None             | TTL-bounded staleness   | Easy                   |
| Manual invalidation in service  | High             | Immediate               | Easy                   |
| Event-driven (chosen)           | Low              | Immediate               | Harder (indirect)      |

Manual invalidation in services was rejected: every service modifying booking availability would need explicit cache calls — a cross-cutting concern that leaks into domain logic. Time-based expiry alone was rejected: stale availability data directly harms the booking UX.

### Consequences

**Positive:**

- Services dispatch events for domain reasons; caching is a listener concern — no coupling between service logic and cache strategy
- New invalidation rules (e.g., invalidate on location update) require adding a listener, not modifying any service
- Listeners are independently testable

**Negative:**

- Event dispatch must not be omitted — a service that modifies booking availability without dispatching an event silently produces stale cache entries with no error signal
- Indirect connections between event dispatch and cache state make debugging cache inconsistencies harder than direct service calls
- Tag-based invalidation (`Cache::tags()`) requires Redis to be active; when the database fallback is in use, this path fails unless a fallback invalidation path is implemented (see ADR-010)

### Related

- [ADR-010](#adr-010-redis-with-database-cache-fallback) — cache backend; determines whether tag-based invalidation is available
- [EVENTS.md](./backend/architecture/EVENTS.md)
- [CACHING.md](./backend/features/CACHING.md)

---

## ADR-008: Markdown Email Templates

**Status**: Accepted
**Date**: January 2026
**Deciders**: Development Team

### Context

Booking notification emails (dispatched via Laravel Notifications, ADR-001) need to be visually distinguishable from the default Laravel Notification styling. The alternative — custom HTML email templates — requires maintaining inline CSS for email client compatibility, which is a significant ongoing maintenance burden.

**Scope:** Visual presentation layer for booking notification emails only.

### Decision

Use **Markdown templates** with a custom theme for all booking notification emails.

### Rationale

| Approach                | Branding control | Maintenance cost          | Email client compat      |
| ----------------------- | ---------------- | ------------------------- | ------------------------ |
| Plain text              | None             | Minimal                   | Universal                |
| Fluent MailMessage      | Limited          | Minimal                   | Good                     |
| Custom HTML             | Full             | High (inline CSS per client) | Requires per-client testing |
| Markdown with theme (chosen) | Good        | Medium                    | Handled by Laravel       |

Custom HTML templates were rejected: maintaining inline CSS for Outlook, Gmail, Apple Mail, and mobile clients is a recurring cost disproportionate for transactional notifications. The Markdown approach provides sufficient branding control.

### Consequences

**Positive:**

- Laravel handles email client compatibility for Markdown-rendered HTML
- Branding is applied via a custom theme — Blade component customisation in one place
- Template structure (buttons, panels, tables) uses Laravel's built-in Markdown mail components

**Negative:**

- Complex layouts (multi-column, conditional sections) are not achievable in Markdown — any non-standard layout requires publishing and modifying vendor Blade components
- The custom theme must be republished after Blade vendor updates to avoid reverting to defaults

### Related

- [ADR-001](#adr-001-laravel-notifications-over-custom-mailables) — notification dispatch strategy
- [EMAIL_TEMPLATES.md](./backend/features/EMAIL_TEMPLATES.md)

---

## ADR-009: Soft Deletes for Bookings

**Status**: Accepted
**Date**: December 2025
**Deciders**: Development Team

### Context

Booking records are referenced by admin audit logs, financial records, and review foreign key constraints. Hard-deleting a booking would break these references, eliminate audit trail, and make accidental deletion unrecoverable. Cancelled bookings must remain queryable by admins for reporting and dispute resolution.

**Scope:** Booking records only. Does not cover room or user records.

### Decision

Use Laravel's **soft deletes** (`SoftDeletes` trait) for bookings. A deleted booking sets `deleted_at` and `deleted_by` rather than removing the row. Cancellation additionally sets `cancelled_at`, `cancelled_by`, and `cancellation_reason`.

```php
class Booking extends Model
{
    use SoftDeletes;
}
```

### Rationale

| Approach                        | Audit trail | Recovery       | Query complexity                   |
| ------------------------------- | ----------- | -------------- | ---------------------------------- |
| Hard delete                     | None        | Impossible     | Low                                |
| Archive table (separate schema) | Full        | Possible       | High (cross-table joins)           |
| Status-only cancellation        | Partial     | N/A            | Medium                             |
| Soft delete (chosen)            | Full        | Via `restore()`| Medium (scope required everywhere) |

Hard delete was rejected: review foreign keys and financial audit records reference booking rows. Archive tables were rejected as requiring a parallel schema and cross-table joins in all admin queries. Status-only cancellation was rejected: it does not remove bookings from the default query scope, requiring additional status filters on top of existing overlap logic without reducing data volume.

### Consequences

**Positive:**

- `deleted_at` + `deleted_by` provide a complete deletion audit record
- `cancelled_at`, `cancelled_by`, `cancellation_reason` provide cancellation-specific forensic context
- Admin queries use `withTrashed()` to access deleted bookings; `restore()` enables recovery
- Review and financial foreign key references are not orphaned by soft-deleted rows

**Negative:**

- Soft deletes do **not** simplify the overlap prevention logic. The PostgreSQL exclusion constraint must explicitly filter `deleted_at IS NULL` to prevent soft-deleted bookings from blocking room availability:

  ```sql
  EXCLUDE USING gist (
      room_id WITH =,
      daterange(check_in, check_out, '[)') WITH &&
  )
  WHERE (status IN ('pending', 'confirmed') AND deleted_at IS NULL)
  ```

  Omitting the `deleted_at IS NULL` predicate would cause cancelled and deleted bookings to block future bookings for the same dates.

- Every query touching bookings must apply the `deleted_at` scope — the `SoftDeletes` global scope handles this for standard queries, but raw queries or scopes that bypass it will silently include deleted records
- Storage grows over time: rows are never physically removed; a data retention policy is an accepted operational gap unless explicitly implemented
- Unique constraints involving booking data must account for soft-deleted state (e.g., `reviews.booking_id` uniqueness remains enforced even for soft-deleted bookings)

### Related

- [BOOKING.md](./backend/features/BOOKING.md)
- [ARCHITECTURE_FACTS.md](./agents/ARCHITECTURE_FACTS.md) — confirms exclusion constraint `WHERE deleted_at IS NULL` and full audit column inventory

---

## ADR-010: Redis with Database Cache Fallback

**Status**: Accepted
**Date**: December 2025
**Deciders**: Development Team

### Context

Redis provides tag-based cache invalidation required by the event-driven invalidation strategy (ADR-007). However, Redis is not available in all environments — local development without Docker, CI pipelines, and some hosting configurations may only have database access. A hard Redis dependency would break these environments entirely.

### Decision

Use **Redis as primary** with **database cache as automatic fallback**. Code that uses tag-based operations detects availability at runtime and degrades accordingly.

```php
trait HasCacheTagSupport
{
    protected function supportsTags(): bool
    {
        return Cache::supportsTags();
    }
}
```

### Rationale

A Redis-only approach was rejected due to environment portability. A database-only approach was rejected because it cannot support tag-based invalidation (ADR-007). The fallback pattern allows the application to run in all environments while accepting a documented degradation in cache invalidation behaviour when Redis is absent.

### Consequences

**Positive:**

- Application runs in local development, CI, and production without environment-specific cache configuration
- Graceful degradation: when Redis is unavailable, the database cache handles basic TTL-based caching
- CI/CD tests pass without a Redis dependency

**Negative:**

- When the database fallback is active, `Cache::tags()` is unavailable — the event-driven invalidation strategy (ADR-007) degrades to TTL-only expiry; stale availability entries persist until their TTL expires naturally, not on the booking event
- Both code paths (Redis and database fallback) must be explicitly tested; the Redis path does not exercise the fallback
- Database cache is slower and adds load to the primary database under cache-miss conditions
- The degraded invalidation behaviour in fallback mode is silent — no warning is emitted when tag-based invalidation is skipped

### Related

- [ADR-007](#adr-007-event-driven-cache-invalidation) — invalidation strategy that depends on Redis tag support; degrades in fallback mode
- [CACHING.md](./backend/features/CACHING.md)

---

## ADR-011: Form Request Validation Classes

**Status**: Accepted
**Date**: January 2026
**Deciders**: Development Team

### Context

Inline validation in controller methods (`$request->validate([...])`) was being duplicated across store and update methods, mixing HTTP-layer concerns with rule definitions, and could not be unit-tested in isolation. Controllers in the booking and auth flows had grown to where validation noise obscured business logic.

**Scope:** HTTP request input validation only. Domain-level invariants (overlap checks, constraint enforcement) remain in services and the database layer.

### Decision

Extract validation into **Form Request classes** for all booking and auth controller methods. Controllers receive pre-validated data via `$request->validated()`; validation rules live in `*Request.php` classes.

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

### Rationale

Inline `$request->validate()` was rejected: rules were duplicated between store and update methods; testing validation required a full HTTP test rather than a unit test; and the controller method carried both HTTP plumbing and validation concerns. Form Request classes make validation independently testable and localise rule changes to one class.

### Consequences

**Positive:**

- Validation rules for a given request type are in one class — changes to rules have one touch point
- Controllers receive validated data and delegate to the service — no validation noise in handler logic
- Custom error messages and per-request authorisation live in the Form Request, not scattered across controllers

**Negative:**

- Each endpoint adds a corresponding `*Request.php` file — a small but real surface increase per endpoint
- Developers must look in two places (controller + Form Request) to understand what data flows into a handler
- Form Request `authorize()` can be confused with Gate/policy authorisation — both must be understood to reason about a request's full access control chain

### Related

- [Auth/RegisterRequest](./backend/app/Http/Requests/Auth/RegisterRequest.php)
- [Auth/LoginRequest](./backend/app/Http/Requests/Auth/LoginRequest.php)

---

## ADR-012: URL Path API Versioning (/api/v1/)

**Status**: Accepted
**Date**: December 2025
**Deciders**: Development Team

### Context

The initial API release shipped unversioned endpoints (e.g., `/api/bookings`, `/api/rooms`). As the system evolved, some endpoints required breaking changes that could not be deployed without coordinating with all existing consumers simultaneously. Without an explicit versioning scheme, every breaking change was a risk to existing integrations and the frontend.

### Decision

Route all current stable API endpoints under the **`/api/v1/` URL path prefix**. Unversioned legacy endpoints remain active during a deprecation window **expiring July 2026**, after which they will be removed. The frontend exclusively uses `/api/v1/` endpoints.

```
/api/v1/rooms       → Current stable
/api/v1/bookings    → Current stable
/api/v1/locations   → Current stable

/api/bookings       → Legacy, deprecated, sunset July 2026
/api/rooms          → Legacy, deprecated, sunset July 2026
```

URL path versioning is the routing decision. Authentication mechanism variants (Bearer vs. HttpOnly cookie) are a separate concern documented in ADR-006 and do not constitute API versioning.

### Rationale

| Strategy          | Version visibility        | Cache-friendly | Client complexity |
| ----------------- | ------------------------- | -------------- | ----------------- |
| URL path (chosen) | Explicit in every request | Yes            | Low               |
| Accept header     | Hidden from URL           | Harder         | Medium            |
| Query parameter   | Visible but non-idiomatic | Partial        | Low               |

Header-based versioning was rejected: the version is invisible in logs, browser history, CDN rules, and network traces. Query parameter versioning was rejected: it conflates versioning with filtering and is not idiomatic for REST resource endpoints.

### Consequences

**Positive:**

- Version is explicit in every request URL — visible in logs, CDN configurations, and client code
- CDN and reverse proxy routing by version requires no header inspection
- Breaking changes can be introduced under a new prefix without affecting existing `/api/v1/` consumers

**Negative:**

- Legacy unversioned endpoints must be maintained until July 2026 — two routing trees coexist, increasing test and documentation surface during the deprecation window
- Consumers of legacy endpoints must be migrated before the sunset date; no automatic compatibility shim exists
- Each future major version (e.g., `/api/v2/`) requires duplicating route definitions and actively managing drift between version namespaces

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

The specific technical pressure or constraint that forced a decision.
No background theory. No "in software systems, X is important."

### Decision

One or two sentences. Explicit. Bounded.
What was chosen and where it applies.

### Rationale

Why this option over named alternatives.
Tradeoffs must be explicit. No "this is best practice."

### Consequences

**Positive:**

- Benefit 1

**Negative:**

- Cost 1 (operational costs required — not just benefits)

### Related

- ADR cross-links or canonical docs only. No invented filenames.
```

---

## ADR-013: Multi-Location Architecture

**Status**: Accepted
**Date**: February 2026
**Deciders**: Development Team

### Context

The system initially modelled a single physical property. Expanding to multiple locations introduced rooms with independent inventories per location, location-specific availability queries, and the need for cross-location analytics. A location-aware data model was required before further booking features could be safely built.

### Decision

Introduce **Location as a first-class domain entity** — not a tag or attribute on rooms — with a one-to-many relationship to Rooms. `bookings.location_id` is intentionally denormalised (auto-set from `rooms.location_id` at write time) to preserve historical location context and simplify analytics queries. `locations.is_active` gates room and booking visibility in read queries.

```text
Location (1) ──→ (N) Room (1) ──→ (N) Booking
Location (1) ──→ (N) Booking  (denormalised; auto-set by PostgreSQL trigger)
```

API endpoints:

```text
GET  /api/v1/locations               → Active locations list
GET  /api/v1/locations/{slug}        → Location detail + rooms
GET  /api/v1/locations/{slug}/availability → Room availability by location
GET  /api/v1/rooms?location_id=X     → Filter rooms by location
```

### Rationale

| Approach                              | Analytics simplicity          | Historical integrity              | Operational complexity |
| ------------------------------------- | ----------------------------- | --------------------------------- | ---------------------- |
| Denormalised location_id (chosen)     | High — no join through rooms  | Preserved after room deletion     | Low                    |
| Fully normalised                      | Low — join required on every report | Lost if room deleted        | Low                    |
| Separate database per location        | Full isolation                | Full                              | Very high              |

Fully normalised was rejected: analytics queries aggregating bookings by location would require a join through the rooms table on every query, including for historical bookings where the originating room has since been deleted or reassigned. Separate databases per location were rejected as operationally disproportionate at hostel scale.

### Consequences

**Positive:**

- Analytics and reporting filter by `bookings.location_id` without joining through rooms — including for historical bookings after a room is deleted
- Slug-based URL routing (`/locations/soleil-hue-center`) enables SEO-friendly endpoints
- `locations.is_active = false` suppresses a location from availability queries without deleting it — inactive locations retain historical booking data

**Negative:**

- `bookings.location_id` sync with `rooms.location_id` is controlled by PostgreSQL trigger `trg_booking_set_location`, which fires on booking INSERT and UPDATE. The trigger is the authoritative sync mechanism — no application-level sync code is required — but it introduces a dependency on PostgreSQL-specific DDL that cannot be replicated in SQLite test environments
- `rooms.location_id` is NOT NULL with CASCADE on location delete — deleting a location cascades to all its rooms
- `bookings.location_id` is nullable with SET NULL on location delete — historical booking records are preserved but lose their location reference; analytics queries must account for NULL `location_id` in historical data
- The sync risk (location_id drift if the trigger is dropped or bypassed) is accepted and controlled at the database layer

### Related

- [ARCHITECTURE_FACTS.md](./agents/ARCHITECTURE_FACTS.md) — confirms trigger `trg_booking_set_location`, `is_active` gating, cascade behaviours, and `lock_version` on locations
- [BOOKING.md](./backend/features/BOOKING.md)

---

## Decision Log History

| Date     | ADR     | Action   | Notes                                                   |
| -------- | ------- | -------- | ------------------------------------------------------- |
| Mar 2026 | ALL     | Reviewed | Governance audit — corrected ADR-003, -004, -005, -006, -009, -012, -013 |
| Feb 2026 | ADR-013 | Added    | Multi-location architecture                             |
| Jan 2026 | ADR-008 | Added    | Markdown email templates                                |
| Jan 2026 | ADR-011 | Added    | Form request validation                                 |
| Jan 2026 | ADR-002 | Added    | Repository pattern                                      |
| Jan 2026 | ADR-004 | Added    | Optimistic locking                                      |
| Dec 2025 | ADR-001 | Added    | Notifications over Mailables                            |
| Dec 2025 | ADR-003 | Added    | Pessimistic locking                                     |
| Dec 2025 | ADR-005 | Added    | Enum-based RBAC                                         |
| Dec 2025 | ADR-006 | Added    | Dual auth modes                                         |
| Dec 2025 | ADR-007 | Added    | Event-driven cache                                      |
| Dec 2025 | ADR-009 | Added    | Soft deletes                                            |
| Dec 2025 | ADR-010 | Added    | Redis fallback                                          |
| Dec 2025 | ADR-012 | Added    | API versioning                                          |
