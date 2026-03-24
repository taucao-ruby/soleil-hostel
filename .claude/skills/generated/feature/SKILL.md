---
name: feature
description: "Skill for the Feature area of soleil-hostel. 211 symbols across 111 files."
---

# Feature

211 symbols | 111 files | Cohesion: 83%

## When to Use

- Working with code in `backend/`
- Understanding how TestCase, UnitTestCase, ExampleTest work
- Modifying feature-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/tests/Feature/BookingCancellationTest.php` | BookingCancellationTest, setUp, test_user_can_cancel_their_own_booking, test_admin_can_cancel_any_booking, test_unauthorized_user_cannot_cancel_others_booking (+15) |
| `backend/tests/Feature/RoomOptimisticLockingTest.php` | RoomOptimisticLockingTest, setUp, test_successful_update_with_matching_version_increments_lock_version, test_update_with_stale_version_throws_optimistic_lock_exception, test_concurrent_update_scenario_second_update_fails (+12) |
| `backend/tests/Feature/NPlusOneQueriesTest.php` | NPlusOneQueriesTest, test_booking_index_no_nplusone_queries, test_room_index_no_nplusone_queries, test_room_show_no_nplusone_queries, test_booking_show_no_nplusone_queries (+4) |
| `backend/tests/Feature/Stays/ServiceRecoveryCaseTest.php` | ServiceRecoveryCaseTest, test_case_with_null_stay_id_is_valid, test_compensation_amounts_stored_and_retrieved_in_cents, test_compensation_amounts_can_be_null, test_scope_by_severity_filters_correctly (+1) |
| `backend/tests/Feature/Stays/StayInvariantTest.php` | StayInvariantTest, test_one_stay_per_booking_unique_constraint, test_different_bookings_can_each_have_a_stay, test_booking_overlap_exclusion_remains_intact_after_stay_migration |
| `backend/tests/Feature/Room/RoomConcurrencyTest.php` | RoomConcurrencyTest, setUp, test_transaction_rollback_preserves_original_version, test_concurrent_delete_attempts_only_first_succeeds |
| `backend/app/Repositories/Contracts/RoomRepositoryInterface.php` | updateWithVersionCheck, deleteWithVersionCheck, refresh |
| `backend/app/Exceptions/OptimisticLockException.php` | getDetailedMessage, forRoom, OptimisticLockException |
| `backend/tests/TestCase.php` | TestCase, setUp |
| `backend/tests/Unit/CreateBookingServiceTest.php` | CreateBookingServiceTest, setUp |

## Entry Points

Start here when exploring this area:

- **`TestCase`** (Class) — `backend/tests/TestCase.php:8`
- **`UnitTestCase`** (Class) — `backend/tests/Unit/UnitTestCase.php:10`
- **`ExampleTest`** (Class) — `backend/tests/Unit/ExampleTest.php:6`
- **`CreateBookingServiceTest`** (Class) — `backend/tests/Unit/CreateBookingServiceTest.php:19`
- **`CacheUnitTest`** (Class) — `backend/tests/Unit/CacheUnitTest.php:9`

## Key Symbols

| Symbol | Type | File | Line |
|--------|------|------|------|
| `TestCase` | Class | `backend/tests/TestCase.php` | 8 |
| `UnitTestCase` | Class | `backend/tests/Unit/UnitTestCase.php` | 10 |
| `ExampleTest` | Class | `backend/tests/Unit/ExampleTest.php` | 6 |
| `CreateBookingServiceTest` | Class | `backend/tests/Unit/CreateBookingServiceTest.php` | 19 |
| `CacheUnitTest` | Class | `backend/tests/Unit/CacheUnitTest.php` | 9 |
| `CacheTest` | Class | `backend/tests/Unit/CacheTest.php` | 7 |
| `BookingFactoryMethodsTest` | Class | `backend/tests/Unit/BookingFactoryMethodsTest.php` | 10 |
| `TokenExpirationTest` | Class | `backend/tests/Feature/TokenExpirationTest.php` | 24 |
| `RoomOptimisticLockingTest` | Class | `backend/tests/Feature/RoomOptimisticLockingTest.php` | 31 |
| `NPlusOneQueriesTest` | Class | `backend/tests/Feature/NPlusOneQueriesTest.php` | 10 |
| `MonitoringLoggingTest` | Class | `backend/tests/Feature/MonitoringLoggingTest.php` | 12 |
| `LocationTest` | Class | `backend/tests/Feature/LocationTest.php` | 16 |
| `LocationApiTest` | Class | `backend/tests/Feature/LocationApiTest.php` | 19 |
| `HttpOnlyCookieAuthenticationTest` | Class | `backend/tests/Feature/HttpOnlyCookieAuthenticationTest.php` | 20 |
| `ExampleTest` | Class | `backend/tests/Feature/ExampleTest.php` | 7 |
| `CreateBookingConcurrencyTest` | Class | `backend/tests/Feature/CreateBookingConcurrencyTest.php` | 24 |
| `BookingCancellationTest` | Class | `backend/tests/Feature/BookingCancellationTest.php` | 16 |
| `ApiErrorFormatTest` | Class | `backend/tests/Feature/ApiErrorFormatTest.php` | 27 |
| `HealthServiceTest` | Class | `backend/tests/Unit/Services/HealthServiceTest.php` | 8 |
| `UpdateBookingRequestValidationTest` | Class | `backend/tests/Unit/Requests/UpdateBookingRequestValidationTest.php` | 13 |

## Execution Flows

| Flow | Type | Steps |
|------|------|-------|
| `Destroy → SupportsTags` | cross_community | 6 |
| `Destroy → Flush` | cross_community | 6 |
| `Handle → CalculateRefundAmount` | cross_community | 4 |
| `Cancel → CalculateRefundAmount` | cross_community | 4 |
| `Update → StampReadinessAudit` | cross_community | 3 |
| `Update → UpdateWithVersionCheck` | cross_community | 3 |
| `Update → Refresh` | cross_community | 3 |
| `Update → ForRoom` | cross_community | 3 |
| `Destroy → DeleteWithVersionCheck` | intra_community | 3 |
| `Destroy → Refresh` | intra_community | 3 |

## Connected Areas

| Area | Connections |
|------|-------------|
| Room | 42 calls |
| Booking | 18 calls |
| Services | 4 calls |

## How to Explore

1. `gitnexus_context({name: "TestCase"})` — see callers and callees
2. `gitnexus_query({query: "feature"})` — find related execution flows
3. Read key files listed above for implementation details
