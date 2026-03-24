---
name: services
description: "Skill for the Services area of soleil-hostel. 148 symbols across 40 files."
---

# Services

148 symbols | 40 files | Cohesion: 72%

## When to Use

- Working with code in `backend/`
- Understanding how UserResource, RateLimiterDegraded, RateLimitService work
- Modifying services-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/app/Services/OperationalDashboardService.php` | depositCollected, openCompensationExposure, settledCompensation, unsettledExposure, voucherExposure (+11) |
| `backend/tests/Unit/Services/HealthServiceTest.php` | test_check_cache_returns_healthy, test_check_queue_returns_healthy_for_sync_driver, test_check_component_delegates_correctly, test_check_component_returns_error_for_unknown, test_readiness_check_returns_expected_structure (+9) |
| `backend/app/Services/HealthService.php` | checkComponent, checkCache, checkQueue, readinessCheck, checkDatabase (+7) |
| `backend/app/Services/RateLimitService.php` | check, checkWithRedis, checkSlidingWindow, checkTokenBucket, checkWithMemory (+5) |
| `backend/app/Services/BookingService.php` | invalidateBooking, invalidateAllBookings, softDelete, restore, forceDelete (+4) |
| `backend/app/Services/ArrivalResolutionService.php` | blockerFor, sourceRoomForStay, hasLateCheckoutConflict, applyAcceptedRecommendation, normalizeReadinessStatus (+2) |
| `backend/app/Services/RoomService.php` | invalidateRoom, invalidateAllRooms, createRoom, stampReadinessAudit, findById (+1) |
| `backend/app/Http/Controllers/HealthController.php` | database, cache, queue, readiness, detailed (+1) |
| `backend/app/Repositories/Contracts/RoomRepositoryInterface.php` | findByIdWithConfirmedBookings, create, findByIdWithBookings, getAllOrderedByName, hasOverlappingConfirmedBookings |
| `backend/tests/Feature/Stays/OperationalDashboardServiceTest.php` | financial_queries_return_expected_operational_totals, room_readiness_board_queries_return_expected_rooms, service_recovery_board_returns_open_cases_blocked_arrivals_and_manual_review_count, arrival_and_departure_board_queries_return_expected_stays, createStayForBoard |

## Entry Points

Start here when exploring this area:

- **`UserResource`** (Class) — `backend/app/Http/Resources/UserResource.php:17`
- **`RateLimiterDegraded`** (Class) — `backend/app/Events/RateLimiterDegraded.php:13`
- **`RateLimitService`** (Class) — `backend/app/Services/RateLimitService.php:28`
- **`warning`** (Method) — `deploy.php:522`
- **`setUp`** (Method) — `backend/tests/Feature/NPlusOneQueriesTest.php:12`

## Key Symbols

| Symbol | Type | File | Line |
|--------|------|------|------|
| `UserResource` | Class | `backend/app/Http/Resources/UserResource.php` | 17 |
| `RateLimiterDegraded` | Class | `backend/app/Events/RateLimiterDegraded.php` | 13 |
| `RateLimitService` | Class | `backend/app/Services/RateLimitService.php` | 28 |
| `warning` | Method | `deploy.php` | 522 |
| `setUp` | Method | `backend/tests/Feature/NPlusOneQueriesTest.php` | 12 |
| `supportsTags` | Method | `backend/app/Traits/HasCacheTagSupport.php` | 11 |
| `invalidateRoom` | Method | `backend/app/Services/RoomService.php` | 55 |
| `invalidateAllRooms` | Method | `backend/app/Services/RoomService.php` | 67 |
| `getRoomDetailWithBookings` | Method | `backend/app/Services/RoomAvailabilityService.php` | 112 |
| `invalidateAllCache` | Method | `backend/app/Services/RoomAvailabilityService.php` | 233 |
| `normalizeDate` | Method | `backend/app/Services/RoomAvailabilityService.php` | 253 |
| `flush` | Method | `backend/app/Services/HtmlPurifierService.php` | 190 |
| `invalidateBooking` | Method | `backend/app/Services/BookingService.php` | 251 |
| `invalidateAllBookings` | Method | `backend/app/Services/BookingService.php` | 262 |
| `softDelete` | Method | `backend/app/Services/BookingService.php` | 285 |
| `restore` | Method | `backend/app/Services/BookingService.php` | 310 |
| `forceDelete` | Method | `backend/app/Services/BookingService.php` | 337 |
| `invalidateTrashedBookings` | Method | `backend/app/Services/BookingService.php` | 416 |
| `restoreWithAudit` | Method | `backend/app/Models/Booking.php` | 404 |
| `setUp` | Method | `backend/tests/Unit/Database/IdempotencyGuardTest.php` | 23 |

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
| Room | 7 calls |
| Cache | 6 calls |
| Feature | 5 calls |
| RateLimiting | 4 calls |
| Database | 3 calls |
| Booking | 3 calls |
| Listeners | 2 calls |

## How to Explore

1. `gitnexus_context({name: "UserResource"})` — see callers and callees
2. `gitnexus_query({query: "services"})` — find related execution flows
3. Read key files listed above for implementation details
