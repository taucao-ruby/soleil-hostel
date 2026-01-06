# 🧪 Testing Guide

> Complete guide to running and writing tests for Soleil Hostel

## Quick Commands

```bash
cd backend

# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/Auth/AuthenticationTest.php

# Run with coverage
php artisan test --coverage

# Run specific test method
php artisan test --filter=test_login_success_with_valid_credentials

# Run tests in parallel
php artisan test --parallel
```

---

## Test Summary

| Category                  | Tests   | Assertions |
| ------------------------- | ------- | ---------- |
| Authentication            | 26      | 78         |
| Booking                   | 60      | 180        |
| Room (Optimistic Locking) | 24      | 58         |
| RBAC                      | 47      | 141        |
| Security Headers          | 14      | 42         |
| XSS Protection            | 48      | 96         |
| Rate Limiting             | 15      | 45         |
| Caching                   | 6       | 18         |
| N+1 Prevention            | 7       | 21         |
| Health Check              | 7       | 21         |
| Repository Unit Tests     | 53      | 53         |
| **Total**                 | **349** | **943**    |

---

## Test Structure

```
tests/
├── Unit/                           # Unit tests
│   ├── CacheTest.php
│   ├── CreateBookingServiceTest.php
│   ├── Enums/
│   │   └── UserRoleTest.php
│   ├── Models/
│   │   └── UserRoleHelpersTest.php
│   ├── RateLimiting/
│   │   └── AdvancedRateLimitServiceTest.php
│   └── Repositories/
│       ├── EloquentBookingRepositoryTest.php
│       └── EloquentRoomRepositoryTest.php
│
├── Feature/                        # Feature/Integration tests
│   ├── Auth/
│   │   └── AuthenticationTest.php
│   ├── Authorization/
│   │   └── GateTest.php
│   ├── Booking/
│   │   ├── BookingPolicyTest.php
│   │   ├── BookingSoftDeleteTest.php
│   │   └── ConcurrentBookingTest.php
│   ├── Cache/
│   │   ├── CacheInvalidationOnBookingTest.php
│   │   └── RoomAvailabilityCacheTest.php
│   ├── HealthCheck/
│   │   └── HealthCheckControllerTest.php
│   ├── Middleware/
│   │   └── EnsureUserHasRoleTest.php
│   ├── RateLimiting/
│   │   ├── AdvancedRateLimitMiddlewareTest.php
│   │   ├── BookingRateLimitTest.php
│   │   └── LoginRateLimitTest.php
│   ├── Security/
│   │   ├── HtmlPurifierXssTest.php
│   │   └── SecurityHeadersTest.php
│   ├── RoomOptimisticLockingTest.php
│   ├── HttpOnlyCookieAuthenticationTest.php
│   ├── NPlusOneQueriesTest.php
│   └── TokenExpirationTest.php
```

---

## Running Specific Test Suites

### Authentication Tests

```bash
php artisan test tests/Feature/Auth/
php artisan test tests/Feature/HttpOnlyCookieAuthenticationTest.php
php artisan test tests/Feature/TokenExpirationTest.php
```

### Booking Tests

```bash
php artisan test tests/Feature/Booking/
php artisan test tests/Feature/CreateBookingConcurrencyTest.php
```

### Security Tests

```bash
php artisan test tests/Feature/Security/
```

### Room Optimistic Locking Tests

```bash
php artisan test --filter=RoomOptimisticLockingTest
```

### RBAC Tests

```bash
php artisan test tests/Feature/Authorization/
php artisan test tests/Feature/Middleware/EnsureUserHasRoleTest.php
```

### Repository Unit Tests

```bash
# Run all repository unit tests (no database required)
php vendor/bin/phpunit tests/Unit/Repositories --testdox --no-coverage

# Run specific repository test
php vendor/bin/phpunit tests/Unit/Repositories/EloquentBookingRepositoryTest.php
php vendor/bin/phpunit tests/Unit/Repositories/EloquentRoomRepositoryTest.php
```

> **Note:** Repository unit tests use Mockery with `@runInSeparateProcess` to mock Eloquent static methods. They run in complete isolation without database connections.

---

## Writing Tests

### Test Traits

```php
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class MyTest extends TestCase
{
    use RefreshDatabase; // Reset DB each test
    use WithFaker;       // Generate fake data
}
```

### Test Factories

```php
// Create user
$user = User::factory()->create();
$admin = User::factory()->admin()->create();
$moderator = User::factory()->moderator()->create();

// Create room
$room = Room::factory()->create();

// Create booking
$booking = Booking::factory()->create([
    'user_id' => $user->id,
    'room_id' => $room->id,
]);
```

### Authentication in Tests

```php
// Act as authenticated user
$this->actingAs($user);

// With Sanctum token
$this->actingAs($user, 'sanctum');

// Make authenticated request
$response = $this->actingAs($user)
    ->postJson('/api/bookings', $data);
```

### Assertions

```php
// HTTP status
$response->assertStatus(200);
$response->assertCreated();     // 201
$response->assertNoContent();   // 204
$response->assertNotFound();    // 404
$response->assertForbidden();   // 403
$response->assertUnauthorized(); // 401

// JSON structure
$response->assertJsonStructure([
    'data' => ['id', 'name', 'price']
]);

// JSON values
$response->assertJson([
    'data' => ['id' => 1]
]);

// Database
$this->assertDatabaseHas('bookings', ['guest_name' => 'John']);
$this->assertDatabaseMissing('bookings', ['id' => 999]);
$this->assertSoftDeleted('bookings', ['id' => 1]);
```

---

## Test Database

Tests use SQLite in-memory by default:

```xml
<!-- phpunit.xml -->
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

To use PostgreSQL for tests:

```bash
php artisan test --env=testing
```

---

## Parallel Testing

```bash
# Run tests in parallel (faster)
php artisan test --parallel

# With specific number of processes
php artisan test --parallel --processes=4
```

---

## Coverage Report

```bash
# HTML coverage report
php artisan test --coverage-html=coverage

# Text coverage in terminal
php artisan test --coverage
```

---

## CI/CD Integration

Tests run automatically on GitHub Actions:

```yaml
# .github/workflows/tests.yml
- name: Run Tests
  run: php artisan test --parallel
```

---

## Troubleshooting

### Tests Fail with Database Errors

```bash
# Clear test cache
php artisan config:clear
php artisan cache:clear

# Re-run migrations
php artisan migrate:fresh --env=testing
```

### Redis Tests Fail

Tests use database cache by default. If Redis is required:

```bash
docker-compose up -d redis
```

### Slow Tests

```bash
# Run in parallel
php artisan test --parallel

# Or filter specific tests
php artisan test --filter=MySpecificTest
```

---

## Next Steps

- [Environment Setup](./ENVIRONMENT_SETUP.md)
- [API Reference](../architecture/API.md)
- [Feature Documentation](../features/README.md)
