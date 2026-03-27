---
name: services
description: "Skill for the Services area of soleil-hostel. 158 symbols across 49 files."
---

# Services

158 symbols | 49 files | Cohesion: 73%

## When to Use

- Working with code in `backend/`
- Understanding how ArrivalResolutionResult, RateLimiterDegraded, RateLimitService work
- Modifying services-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/app/Services/OperationalDashboardService.php` | depositCollected, openCompensationExposure, settledCompensation, unsettledExposure, voucherExposure (+12) |
| `backend/tests/Unit/Services/HealthServiceTest.php` | test_check_cache_returns_healthy, test_check_queue_returns_healthy_for_sync_driver, test_check_component_delegates_correctly, test_check_component_returns_error_for_unknown, test_readiness_check_returns_expected_structure (+9) |
| `backend/app/Services/HealthService.php` | checkComponent, checkCache, checkQueue, readinessCheck, checkDatabase (+7) |
| `backend/app/Services/ArrivalResolutionService.php` | resolve, alternateLocationsFor, buildResult, sourceRoomForStay, blockerFor (+5) |
| `backend/app/Services/RateLimitService.php` | check, checkWithMemory, getStatus, redisAvailable, checkWithRedis (+4) |
| `backend/app/Services/CreateBookingService.php` | createWithDeadlockRetry, classifyDatabaseError, calculateRetryDelay, isRetryableException, createBookingWithLocking (+4) |
| `backend/app/Services/BookingService.php` | invalidateAllBookings, restore, forceDelete, invalidateTrashedBookings, bookingSelectColumns (+3) |
| `backend/app/Http/Controllers/HealthController.php` | database, cache, queue, readiness, detailed (+1) |
| `backend/tests/Feature/Stays/OperationalDashboardServiceTest.php` | financial_queries_return_expected_operational_totals, arrival_and_departure_board_queries_return_expected_stays, room_readiness_board_queries_return_expected_rooms, service_recovery_board_returns_open_cases_blocked_arrivals_and_manual_review_count, createStayForBoard |
| `backend/app/Services/RoomService.php` | invalidateAllRooms, createRoom, stampReadinessAudit, findById, fetchRoomsFromDB |

## Entry Points

Start here when exploring this area:

- **`ArrivalResolutionResult`** (Class) — `backend/app/Services/ArrivalResolutionResult.php:11`
- **`RateLimiterDegraded`** (Class) — `backend/app/Events/RateLimiterDegraded.php:13`
- **`RateLimitService`** (Class) — `backend/app/Services/RateLimitService.php:28`
- **`withDeposit`** (Method) — `backend/database/factories/BookingFactory.php:129`
- **`depositApplied`** (Method) — `backend/database/factories/BookingFactory.php:141`

## Key Symbols

| Symbol | Type | File | Line |
|--------|------|------|------|
| `ArrivalResolutionResult` | Class | `backend/app/Services/ArrivalResolutionResult.php` | 11 |
| `RateLimiterDegraded` | Class | `backend/app/Events/RateLimiterDegraded.php` | 13 |
| `RateLimitService` | Class | `backend/app/Services/RateLimitService.php` | 28 |
| `withDeposit` | Method | `backend/database/factories/BookingFactory.php` | 129 |
| `depositApplied` | Method | `backend/database/factories/BookingFactory.php` | 141 |
| `depositCollected` | Method | `backend/app/Services/OperationalDashboardService.php` | 139 |
| `openCompensationExposure` | Method | `backend/app/Services/OperationalDashboardService.php` | 148 |
| `settledCompensation` | Method | `backend/app/Services/OperationalDashboardService.php` | 156 |
| `unsettledExposure` | Method | `backend/app/Services/OperationalDashboardService.php` | 166 |
| `voucherExposure` | Method | `backend/app/Services/OperationalDashboardService.php` | 173 |
| `relocationDeltaAbsorbed` | Method | `backend/app/Services/OperationalDashboardService.php` | 185 |
| `sumExposureForSettlementStatuses` | Method | `backend/app/Services/OperationalDashboardService.php` | 212 |
| `financial_queries_return_expected_operational_totals` | Method | `backend/tests/Feature/Stays/OperationalDashboardServiceTest.php` | 118 |
| `booking_deposit_scopes_filter_collected_and_applied_deposits` | Method | `backend/tests/Feature/Stays/FinancialLifecycleTest.php` | 17 |
| `checkComponent` | Method | `backend/app/Services/HealthService.php` | 180 |
| `checkCache` | Method | `backend/app/Services/HealthService.php` | 223 |
| `checkQueue` | Method | `backend/app/Services/HealthService.php` | 307 |
| `test_check_cache_returns_healthy` | Method | `backend/tests/Unit/Services/HealthServiceTest.php` | 105 |
| `test_check_queue_returns_healthy_for_sync_driver` | Method | `backend/tests/Unit/Services/HealthServiceTest.php` | 121 |
| `test_check_component_delegates_correctly` | Method | `backend/tests/Unit/Services/HealthServiceTest.php` | 130 |

## Execution Flows

| Flow | Type | Steps |
|------|------|-------|
| `Handle → Flush` | cross_community | 6 |
| `Destroy → Flush` | cross_community | 6 |
| `HandlePaymentIntentSucceeded → Flush` | cross_community | 5 |
| `Store → ClassifyDatabaseError` | cross_community | 5 |
| `Handle → ClassifyDatabaseError` | cross_community | 5 |
| `Store → SupportsTags` | cross_community | 5 |
| `Store → Flush` | cross_community | 5 |
| `Destroy → Flush` | cross_community | 5 |
| `ManualReviewRequired → Active` | cross_community | 5 |
| `Resolve → Active` | cross_community | 4 |

## Connected Areas

| Area | Connections |
|------|-------------|
| Cache | 15 calls |
| Room | 9 calls |
| Stays | 8 calls |
| Feature | 7 calls |
| Models | 7 calls |
| Enums | 4 calls |
| Booking | 3 calls |
| Listeners | 3 calls |

## How to Explore

1. `soleil-ai-review-engine_context({name: "ArrivalResolutionResult"})` — see callers and callees
2. `soleil-ai-review-engine_query({query: "services"})` — find related execution flows
3. Read key files listed above for implementation details
