---
name: services
description: "Skill for the Services area of soleil-hostel. 163 symbols across 46 files."
---

# Services

163 symbols | 46 files | Cohesion: 73%

## When to Use

- Working with code in `backend/`
- Understanding how RateLimiterDegraded, RateLimitService, getRoomDetailWithBookings work
- Modifying services-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/app/Services/OperationalDashboardService.php` | expectedArrivals, inHouseGuests, dueOuts, lateCheckouts, blockedArrivals (+12) |
| `backend/tests/Unit/Services/HealthServiceTest.php` | test_check_cache_returns_healthy, test_check_queue_returns_healthy_for_sync_driver, test_check_component_delegates_correctly, test_check_component_returns_error_for_unknown, test_readiness_check_returns_expected_structure (+9) |
| `backend/app/Services/HealthService.php` | checkComponent, checkCache, checkQueue, readinessCheck, checkDatabase (+7) |
| `backend/app/Services/BookingService.php` | invalidateUserBookings, invalidateBooking, getTrashedBookings, invalidateAllBookings, restore (+6) |
| `backend/app/Services/RateLimitService.php` | check, checkWithRedis, checkSlidingWindow, checkTokenBucket, checkWithMemory (+5) |
| `backend/app/Services/CreateBookingService.php` | create, parseDate, createWithDeadlockRetry, classifyDatabaseError, calculateRetryDelay (+4) |
| `backend/app/Services/ArrivalResolutionService.php` | blockerFor, sourceRoomForStay, hasLateCheckoutConflict, applyAcceptedRecommendation, normalizeReadinessStatus (+2) |
| `backend/app/Http/Controllers/HealthController.php` | database, cache, queue, readiness, detailed (+1) |
| `backend/app/Services/RoomAvailabilityService.php` | getRoomDetailWithBookings, invalidateAvailability, normalizeDate, invalidateAllCache, checkOverlappingBookings |
| `backend/app/Listeners/InvalidateCacheOnBookingChange.php` | handle, handleCreated, handleUpdated, handleDeleted, handleCancelled |

## Entry Points

Start here when exploring this area:

- **`RateLimiterDegraded`** (Class) — `backend/app/Events/RateLimiterDegraded.php:13`
- **`RateLimitService`** (Class) — `backend/app/Services/RateLimitService.php:28`
- **`getRoomDetailWithBookings`** (Method) — `backend/app/Services/RoomAvailabilityService.php:112`
- **`invalidateAvailability`** (Method) — `backend/app/Services/RoomAvailabilityService.php:214`
- **`normalizeDate`** (Method) — `backend/app/Services/RoomAvailabilityService.php:253`

## Key Symbols

| Symbol | Type | File | Line |
|--------|------|------|------|
| `RateLimiterDegraded` | Class | `backend/app/Events/RateLimiterDegraded.php` | 13 |
| `RateLimitService` | Class | `backend/app/Services/RateLimitService.php` | 28 |
| `getRoomDetailWithBookings` | Method | `backend/app/Services/RoomAvailabilityService.php` | 112 |
| `invalidateAvailability` | Method | `backend/app/Services/RoomAvailabilityService.php` | 214 |
| `normalizeDate` | Method | `backend/app/Services/RoomAvailabilityService.php` | 253 |
| `invalidateUserBookings` | Method | `backend/app/Services/BookingService.php` | 241 |
| `invalidateBooking` | Method | `backend/app/Services/BookingService.php` | 251 |
| `getTrashedBookings` | Method | `backend/app/Services/BookingService.php` | 369 |
| `supportsTags` | Method | `backend/app/Traits/HasCacheTagSupport.php` | 11 |
| `handle` | Method | `backend/app/Listeners/InvalidateCacheOnBookingChange.php` | 20 |
| `handleCreated` | Method | `backend/app/Listeners/InvalidateCacheOnBookingChange.php` | 37 |
| `handleUpdated` | Method | `backend/app/Listeners/InvalidateCacheOnBookingChange.php` | 45 |
| `handleDeleted` | Method | `backend/app/Listeners/InvalidateCacheOnBookingChange.php` | 59 |
| `handleCancelled` | Method | `backend/app/Listeners/InvalidateCacheOnBookingChange.php` | 67 |
| `findByIdWithConfirmedBookings` | Method | `backend/app/Repositories/Contracts/RoomRepositoryInterface.php` | 33 |
| `checkComponent` | Method | `backend/app/Services/HealthService.php` | 180 |
| `checkCache` | Method | `backend/app/Services/HealthService.php` | 223 |
| `checkQueue` | Method | `backend/app/Services/HealthService.php` | 307 |
| `test_check_cache_returns_healthy` | Method | `backend/tests/Unit/Services/HealthServiceTest.php` | 105 |
| `test_check_queue_returns_healthy_for_sync_driver` | Method | `backend/tests/Unit/Services/HealthServiceTest.php` | 121 |

## Execution Flows

| Flow | Type | Steps |
|------|------|-------|
| `Handle → SupportsTags` | cross_community | 6 |
| `Handle → Flush` | cross_community | 6 |
| `ExecuteWithProgress → SupportsTags` | cross_community | 6 |
| `Destroy → SupportsTags` | cross_community | 6 |
| `Destroy → Flush` | cross_community | 6 |
| `WarmAll → SupportsTags` | cross_community | 6 |
| `HandlePaymentIntentSucceeded → SupportsTags` | cross_community | 5 |
| `HandlePaymentIntentSucceeded → Flush` | cross_community | 5 |
| `Store → ClassifyDatabaseError` | cross_community | 5 |
| `Handle → ClassifyDatabaseError` | cross_community | 5 |

## Connected Areas

| Area | Connections |
|------|-------------|
| Stays | 10 calls |
| Cache | 8 calls |
| Room | 8 calls |
| Feature | 7 calls |
| Enums | 4 calls |
| RateLimiting | 4 calls |
| Booking | 3 calls |
| Database | 3 calls |

## How to Explore

1. `soleil-ai-review-engine_context({name: "RateLimiterDegraded"})` — see callers and callees
2. `soleil-ai-review-engine_query({query: "services"})` — find related execution flows
3. Read key files listed above for implementation details
