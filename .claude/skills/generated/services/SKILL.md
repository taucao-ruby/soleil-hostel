---
name: services
description: "Skill for the Services area of soleil-hostel. 117 symbols across 38 files."
---

# Services

117 symbols | 38 files | Cohesion: 76%

## When to Use

- Working with code in `backend/`
- Understanding how RoomResource, RateLimiterDegraded, RateLimitService work
- Modifying services-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/tests/Unit/Services/HealthServiceTest.php` | test_check_cache_returns_healthy, test_check_queue_returns_healthy_for_sync_driver, test_check_component_delegates_correctly, test_check_component_returns_error_for_unknown, test_readiness_check_returns_expected_structure (+9) |
| `backend/app/Services/HealthService.php` | checkComponent, checkCache, checkQueue, readinessCheck, checkDatabase (+7) |
| `backend/app/Services/RateLimitService.php` | check, checkWithMemory, getStatus, redisAvailable, checkWithRedis (+4) |
| `backend/app/Services/CreateBookingService.php` | create, parseDate, createWithDeadlockRetry, classifyDatabaseError, calculateRetryDelay (+4) |
| `backend/app/Services/BookingService.php` | invalidateAllBookings, restore, forceDelete, invalidateTrashedBookings, bookingSelectColumns (+3) |
| `backend/app/Http/Controllers/HealthController.php` | database, cache, queue, readiness, detailed (+1) |
| `backend/app/Services/RoomService.php` | getRoomById, createRoom, invalidateAllRooms, findById, fetchRoomsFromDB |
| `backend/app/Repositories/Contracts/RoomRepositoryInterface.php` | create, findByIdWithBookings, getAllOrderedByName, hasOverlappingConfirmedBookings |
| `backend/app/Services/CustomerService.php` | getCustomers, getCustomerProfile, getCustomerBookings, getAggregateStats |
| `backend/app/Http/Controllers/Admin/CustomerController.php` | index, show, bookings, stats |

## Entry Points

Start here when exploring this area:

- **`RoomResource`** (Class) — `backend/app/Http/Resources/RoomResource.php:13`
- **`RateLimiterDegraded`** (Class) — `backend/app/Events/RateLimiterDegraded.php:13`
- **`RateLimitService`** (Class) — `backend/app/Services/RateLimitService.php:28`
- **`test_create_room_sets_lock_version_to_1_automatically`** (Method) — `backend/tests/Feature/RoomOptimisticLockingTest.php:252`
- **`getRoomById`** (Method) — `backend/app/Services/RoomService.php:34`

## Key Symbols

| Symbol | Type | File | Line |
|--------|------|------|------|
| `RoomResource` | Class | `backend/app/Http/Resources/RoomResource.php` | 13 |
| `RateLimiterDegraded` | Class | `backend/app/Events/RateLimiterDegraded.php` | 13 |
| `RateLimitService` | Class | `backend/app/Services/RateLimitService.php` | 28 |
| `test_create_room_sets_lock_version_to_1_automatically` | Method | `backend/tests/Feature/RoomOptimisticLockingTest.php` | 252 |
| `getRoomById` | Method | `backend/app/Services/RoomService.php` | 34 |
| `createRoom` | Method | `backend/app/Services/RoomService.php` | 182 |
| `getRoomAvailability` | Method | `backend/app/Services/RoomAvailabilityService.php` | 76 |
| `create` | Method | `backend/app/Repositories/Contracts/RoomRepositoryInterface.php` | 60 |
| `getLockVersion` | Method | `backend/app/Http/Requests/RoomRequest.php` | 51 |
| `show` | Method | `backend/app/Http/Controllers/RoomController.php` | 68 |
| `store` | Method | `backend/app/Http/Controllers/RoomController.php` | 86 |
| `update` | Method | `backend/app/Http/Controllers/RoomController.php` | 127 |
| `checkComponent` | Method | `backend/app/Services/HealthService.php` | 180 |
| `checkCache` | Method | `backend/app/Services/HealthService.php` | 223 |
| `checkQueue` | Method | `backend/app/Services/HealthService.php` | 307 |
| `test_check_cache_returns_healthy` | Method | `backend/tests/Unit/Services/HealthServiceTest.php` | 105 |
| `test_check_queue_returns_healthy_for_sync_driver` | Method | `backend/tests/Unit/Services/HealthServiceTest.php` | 121 |
| `test_check_component_delegates_correctly` | Method | `backend/tests/Unit/Services/HealthServiceTest.php` | 130 |
| `test_check_component_returns_error_for_unknown` | Method | `backend/tests/Unit/Services/HealthServiceTest.php` | 138 |
| `database` | Method | `backend/app/Http/Controllers/HealthController.php` | 87 |

## Execution Flows

| Flow | Type | Steps |
|------|------|-------|
| `Update → SupportsTags` | cross_community | 6 |
| `Update → Flush` | cross_community | 6 |
| `Handle → Flush` | cross_community | 6 |
| `Destroy → Flush` | cross_community | 6 |
| `HandlePaymentIntentSucceeded → Flush` | cross_community | 5 |
| `Store → ClassifyDatabaseError` | cross_community | 5 |
| `Handle → ClassifyDatabaseError` | cross_community | 5 |
| `Store → SupportsTags` | cross_community | 5 |
| `Store → Flush` | cross_community | 5 |
| `Destroy → Flush` | cross_community | 5 |

## Connected Areas

| Area | Connections |
|------|-------------|
| Cache | 8 calls |
| Feature | 5 calls |
| Enums | 4 calls |
| Booking | 3 calls |
| Database | 3 calls |
| Listeners | 3 calls |
| Policies | 2 calls |
| RateLimiting | 2 calls |

## How to Explore

1. `gitnexus_context({name: "RoomResource"})` — see callers and callees
2. `gitnexus_query({query: "services"})` — find related execution flows
3. Read key files listed above for implementation details
