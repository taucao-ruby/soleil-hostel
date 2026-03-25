---
name: services
description: "Skill for the Services area of soleil-hostel. 153 symbols across 41 files."
---

# Services

153 symbols | 41 files | Cohesion: 73%

## When to Use

- Working with code in `backend/`
- Understanding how BookingDeleted, UserResource, RateLimiterDegraded work
- Modifying services-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/app/Services/OperationalDashboardService.php` | depositCollected, openCompensationExposure, settledCompensation, unsettledExposure, voucherExposure (+11) |
| `backend/tests/Unit/Services/HealthServiceTest.php` | test_check_cache_returns_healthy, test_check_queue_returns_healthy_for_sync_driver, test_check_component_delegates_correctly, test_check_component_returns_error_for_unknown, test_readiness_check_returns_expected_structure (+9) |
| `backend/app/Services/HealthService.php` | checkComponent, checkCache, checkQueue, readinessCheck, checkDatabase (+7) |
| `backend/app/Services/BookingService.php` | invalidateUserBookings, invalidateBooking, softDelete, restore, forceDelete (+5) |
| `backend/app/Services/RateLimitService.php` | check, checkWithRedis, checkSlidingWindow, checkTokenBucket, checkWithMemory (+5) |
| `backend/app/Services/ArrivalResolutionService.php` | blockerFor, sourceRoomForStay, hasLateCheckoutConflict, applyAcceptedRecommendation, normalizeReadinessStatus (+2) |
| `backend/app/Http/Controllers/HealthController.php` | database, cache, queue, readiness, detailed (+1) |
| `backend/app/Services/RoomAvailabilityService.php` | getRoomDetailWithBookings, invalidateAvailability, normalizeDate, invalidateAllCache, checkOverlappingBookings |
| `backend/app/Listeners/InvalidateCacheOnBookingChange.php` | handle, handleCreated, handleUpdated, handleDeleted, handleCancelled |
| `backend/app/Repositories/Contracts/RoomRepositoryInterface.php` | findByIdWithConfirmedBookings, create, findByIdWithBookings, getAllOrderedByName, hasOverlappingConfirmedBookings |

## Entry Points

Start here when exploring this area:

- **`BookingDeleted`** (Class) — `backend/app/Events/BookingDeleted.php:9`
- **`UserResource`** (Class) — `backend/app/Http/Resources/UserResource.php:17`
- **`RateLimiterDegraded`** (Class) — `backend/app/Events/RateLimiterDegraded.php:13`
- **`RateLimitService`** (Class) — `backend/app/Services/RateLimitService.php:28`
- **`supportsTags`** (Method) — `backend/app/Traits/HasCacheTagSupport.php:11`

## Key Symbols

| Symbol | Type | File | Line |
|--------|------|------|------|
| `BookingDeleted` | Class | `backend/app/Events/BookingDeleted.php` | 9 |
| `UserResource` | Class | `backend/app/Http/Resources/UserResource.php` | 17 |
| `RateLimiterDegraded` | Class | `backend/app/Events/RateLimiterDegraded.php` | 13 |
| `RateLimitService` | Class | `backend/app/Services/RateLimitService.php` | 28 |
| `supportsTags` | Method | `backend/app/Traits/HasCacheTagSupport.php` | 11 |
| `getRoomDetailWithBookings` | Method | `backend/app/Services/RoomAvailabilityService.php` | 112 |
| `invalidateAvailability` | Method | `backend/app/Services/RoomAvailabilityService.php` | 214 |
| `normalizeDate` | Method | `backend/app/Services/RoomAvailabilityService.php` | 253 |
| `invalidateUserBookings` | Method | `backend/app/Services/BookingService.php` | 241 |
| `invalidateBooking` | Method | `backend/app/Services/BookingService.php` | 251 |
| `softDelete` | Method | `backend/app/Services/BookingService.php` | 285 |
| `restore` | Method | `backend/app/Services/BookingService.php` | 310 |
| `forceDelete` | Method | `backend/app/Services/BookingService.php` | 337 |
| `invalidateTrashedBookings` | Method | `backend/app/Services/BookingService.php` | 416 |
| `restoreWithAudit` | Method | `backend/app/Models/Booking.php` | 404 |
| `handle` | Method | `backend/app/Listeners/InvalidateCacheOnBookingChange.php` | 20 |
| `handleCreated` | Method | `backend/app/Listeners/InvalidateCacheOnBookingChange.php` | 37 |
| `handleUpdated` | Method | `backend/app/Listeners/InvalidateCacheOnBookingChange.php` | 45 |
| `handleDeleted` | Method | `backend/app/Listeners/InvalidateCacheOnBookingChange.php` | 59 |
| `handleCancelled` | Method | `backend/app/Listeners/InvalidateCacheOnBookingChange.php` | 67 |

## Execution Flows

| Flow | Type | Steps |
|------|------|-------|
| `Handle → SupportsTags` | cross_community | 6 |
| `Handle → Flush` | cross_community | 6 |
| `ExecuteWithProgress → SupportsTags` | cross_community | 6 |
| `Destroy → SupportsTags` | cross_community | 6 |
| `Destroy → Flush` | cross_community | 6 |
| `HandlePaymentIntentSucceeded → SupportsTags` | cross_community | 5 |
| `HandlePaymentIntentSucceeded → Flush` | cross_community | 5 |
| `Store → ClassifyDatabaseError` | cross_community | 5 |
| `Handle → ClassifyDatabaseError` | cross_community | 5 |
| `ExecuteWithProgress → BookingSelectColumns` | cross_community | 5 |

## Connected Areas

| Area | Connections |
|------|-------------|
| Stays | 9 calls |
| Cache | 8 calls |
| Room | 7 calls |
| Feature | 5 calls |
| Database | 3 calls |
| Booking | 3 calls |
| Policies | 2 calls |
| RateLimiting | 2 calls |

## How to Explore

1. `gitnexus_context({name: "BookingDeleted"})` — see callers and callees
2. `gitnexus_query({query: "services"})` — find related execution flows
3. Read key files listed above for implementation details
