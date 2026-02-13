# Backend Testing Guide

> Scope: backend tests in `backend/tests` and suite configuration in `backend/phpunit.xml`.
>
> Last Updated: February 12, 2026
>
> Runtime test counts/assertions not verified in this update.

## Quick Commands

```bash
cd backend

# Run all tests
php artisan test

# Run by PHPUnit suite
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit

# Run a specific file
php artisan test tests/Feature/Auth/AuthenticationTest.php

# Run by class or method pattern
php artisan test --filter=AuthenticationTest
php artisan test --filter=test_login_success_with_valid_credentials
```

## PHPUnit Suites

Defined in `backend/phpunit.xml`:

| Suite | Directory |
| --- | --- |
| Unit | `tests/Unit` |
| Feature | `tests/Feature` |

Test environment defaults from `phpunit.xml`:

- `APP_ENV=testing`
- `DB_CONNECTION=sqlite`
- `DB_DATABASE=:memory:`
- `CACHE_STORE=array`
- `QUEUE_CONNECTION=sync`
- `SESSION_DRIVER=array`

## Test Summary

| Metric | Count | Notes |
| --- | --- | --- |
| Files under `backend/tests` | 62 | Includes suites, base classes, traits, and stub script |
| Files matching `*Test.php` | 58 | Includes `tests/stubs/concurrent_booking_test.php` |
| Feature test files | 42 | Under `tests/Feature` |
| Unit test files | 15 | Under `tests/Unit` |
| Non-`*Test.php` support files | 4 | `TestCase.php`, `UnitTestCase.php`, and 2 traits |

Feature breakdown (`tests/Feature`):

| Group | Files |
| --- | --- |
| `_root` (directly under `Feature/`) | 10 |
| `Auth` | 4 |
| `Authorization` | 1 |
| `Booking` | 4 |
| `Cache` | 3 |
| `Database` | 1 |
| `HealthCheck` | 1 |
| `Listeners` | 1 |
| `Middleware` | 1 |
| `Notifications` | 1 |
| `Policies` | 1 |
| `Queue` | 1 |
| `RateLimiting` | 4 |
| `Room` | 4 |
| `Security` | 3 |
| `User` | 1 |
| `Validation` | 1 |
| **Total** | **42** |

Unit breakdown (`tests/Unit`):

| Group | Files |
| --- | --- |
| `_root` (directly under `Unit/`) | 4 |
| `Database` | 2 |
| `Enums` | 1 |
| `Mail` | 1 |
| `Models` | 2 |
| `Notifications` | 1 |
| `Policies` | 1 |
| `RateLimiting` | 1 |
| `Repositories` | 2 |
| **Total** | **15** |

## Test Structure

```text
tests/
|-- Feature/
|   |-- Auth/
|   |   |-- AuthConsolidationTest.php
|   |   |-- AuthenticationTest.php
|   |   |-- EmailVerificationTest.php
|   |   `-- PasswordResetTest.php
|   |-- Authorization/
|   |   `-- GateTest.php
|   |-- Booking/
|   |   |-- BookingPolicyTest.php
|   |   |-- BookingServiceSelectFieldsTest.php
|   |   |-- BookingSoftDeleteTest.php
|   |   `-- ConcurrentBookingTest.php
|   |-- Cache/
|   |   |-- CacheInvalidationOnBookingTest.php
|   |   |-- CacheWarmupTest.php
|   |   `-- RoomAvailabilityCacheTest.php
|   |-- Database/
|   |   `-- TransactionIsolationIntegrationTest.php
|   |-- HealthCheck/
|   |   `-- HealthControllerTest.php
|   |-- Listeners/
|   |   `-- BookingNotificationListenerTest.php
|   |-- Middleware/
|   |   `-- EnsureUserHasRoleTest.php
|   |-- Notifications/
|   |   `-- BookingNotificationTest.php
|   |-- Policies/
|   |   `-- RoomPolicyTest.php
|   |-- Queue/
|   |   `-- QueueMonitoringAuthorizationTest.php
|   |-- RateLimiting/
|   |   |-- AdvancedRateLimitMiddlewareTest.php
|   |   |-- AdvancedRateLimitServiceTest.php
|   |   |-- BookingRateLimitTest.php
|   |   `-- LoginRateLimitTest.php
|   |-- Room/
|   |   |-- RoomAuthorizationTest.php
|   |   |-- RoomConcurrencyTest.php
|   |   |-- RoomCrudTest.php
|   |   `-- RoomValidationTest.php
|   |-- Security/
|   |   |-- CsrfProtectionTest.php
|   |   |-- HtmlPurifierXssTest.php
|   |   `-- SecurityHeadersTest.php
|   |-- User/
|   |   `-- ProfileTest.php
|   |-- Validation/
|   |   `-- ApiValidationTest.php
|   |-- BookingCancellationTest.php
|   |-- CreateBookingConcurrencyTest.php
|   |-- ExampleTest.php
|   |-- HttpOnlyCookieAuthenticationTest.php
|   |-- LocationApiTest.php
|   |-- LocationTest.php
|   |-- MonitoringLoggingTest.php
|   |-- NPlusOneQueriesTest.php
|   |-- RoomOptimisticLockingTest.php
|   `-- TokenExpirationTest.php
|-- stubs/
|   `-- concurrent_booking_test.php
|-- Traits/
|   |-- RefreshDatabaseWithoutPrompts.php
|   `-- RoomTestAssertions.php
|-- Unit/
|   |-- Database/
|   |   |-- IdempotencyGuardTest.php
|   |   `-- TransactionIsolationTest.php
|   |-- Enums/
|   |   `-- UserRoleTest.php
|   |-- Mail/
|   |   `-- EmailTemplateRenderingTest.php
|   |-- Models/
|   |   |-- RoomTest.php
|   |   `-- UserRoleHelpersTest.php
|   |-- Notifications/
|   |   `-- BookingNotificationTest.php
|   |-- Policies/
|   |   `-- ReviewPolicyTest.php
|   |-- RateLimiting/
|   |   `-- AdvancedRateLimitServiceTest.php
|   |-- Repositories/
|   |   |-- EloquentBookingRepositoryTest.php
|   |   `-- EloquentRoomRepositoryTest.php
|   |-- CacheTest.php
|   |-- CacheUnitTest.php
|   |-- CreateBookingServiceTest.php
|   |-- ExampleTest.php
|   `-- UnitTestCase.php
`-- TestCase.php
```

## Test Base and Helpers

### `tests/TestCase.php`

- Extends Laravel `BaseTestCase`.
- Uses `RefreshDatabase`.
- Fakes notifications in `setUp()` via `Notification::fake()`.
- Sets default middleware bypass for `VerifyCsrfToken`.
- Overrides `actingAs()`:
  - supports Sanctum flow by creating a token and attaching `Authorization: Bearer ...`
  - handles `actingAs(null)` by clearing headers/auth state
- Overrides `artisan()` to enforce `--no-interaction` and `--force` for `migrate:fresh`.
- Provides `withHttpOnlyCookie()` helper for cookie-auth test flows.

### `tests/Unit/UnitTestCase.php`

- Unit-specific base class.
- Does not use `RefreshDatabase` (intentional isolation for unit tests).

### `tests/Traits/RefreshDatabaseWithoutPrompts.php`

- Wraps `RefreshDatabase` migration behavior.
- Forces `migrate:fresh` with `--no-interaction`.

### `tests/Traits/RoomTestAssertions.php`

- Shared room-domain assertions:
  - optimistic lock exception checks
  - conflict/validation/authorization response helpers
  - room JSON structure assertions

### `tests/stubs/concurrent_booking_test.php`

- Standalone stress script for concurrent booking behavior.
- Boots Laravel manually and sends concurrent HTTP requests.
- Not part of PHPUnit suites.

## Run Tests by Scope

### All tests

```bash
php artisan test
```

### By suite

```bash
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit
```

### By folder or domain

```bash
# Auth and token flows
php artisan test tests/Feature/Auth/
php artisan test tests/Feature/HttpOnlyCookieAuthenticationTest.php
php artisan test tests/Feature/TokenExpirationTest.php

# Booking and room
php artisan test tests/Feature/Booking/
php artisan test tests/Feature/Room/
php artisan test tests/Feature/RoomOptimisticLockingTest.php

# Security and rate limiting
php artisan test tests/Feature/Security/
php artisan test tests/Feature/RateLimiting/

# Unit repositories and database helpers
php artisan test tests/Unit/Repositories/
php artisan test tests/Unit/Database/
```

### By file and method filter

```bash
php artisan test tests/Feature/Booking/ConcurrentBookingTest.php
php artisan test --filter=ConcurrentBookingTest
php artisan test --filter=test_it_prevents_double_booking
```

## Notes

- `tests/stubs/concurrent_booking_test.php` matches `*Test.php` filename pattern but is not discovered by PHPUnit suites because suites only include `tests/Feature` and `tests/Unit`.
- If you need assertion-level totals, run the suite in your environment and update the metrics in this document after execution.
