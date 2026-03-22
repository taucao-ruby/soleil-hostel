---
name: feature
description: "Skill for the Feature area of soleil-hostel. 308 symbols across 112 files."
---

# Feature

308 symbols | 112 files | Cohesion: 87%

## When to Use

- Working with code in `backend/`
- Understanding how TestCase, UnitTestCase, ExampleTest work
- Modifying feature-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/tests/Feature/BookingCancellationTest.php` | BookingCancellationTest, setUp, test_user_can_cancel_their_own_booking, test_admin_can_cancel_any_booking, test_unauthorized_user_cannot_cancel_others_booking (+16) |
| `backend/tests/Feature/RoomOptimisticLockingTest.php` | RoomOptimisticLockingTest, test_new_room_starts_with_lock_version_1, test_get_room_returns_lock_version, test_lock_version_accessor_handles_null_as_version_1, setUp (+15) |
| `backend/tests/Feature/LocationTest.php` | LocationTest, it_returns_null_coordinates_when_missing, location_has_many_rooms, location_has_many_active_rooms, room_belongs_to_location (+9) |
| `backend/tests/Unit/Models/RoomTest.php` | RoomTest, test_room_can_be_created_with_factory, test_lock_version_cannot_be_mass_assigned, test_lock_version_is_cast_to_integer, test_lock_version_accessor_returns_1_for_null (+6) |
| `backend/tests/Feature/Room/RoomAuthorizationTest.php` | RoomAuthorizationTest, test_guest_can_access_rooms_index, test_user_can_access_rooms_index, test_moderator_can_access_rooms_index, test_admin_can_access_rooms_index (+6) |
| `backend/tests/Feature/TokenExpirationTest.php` | TokenExpirationTest, setUp, test_expired_token_returns_401, test_refresh_token_creates_new_and_revokes_old, test_logout_revokes_token (+4) |
| `backend/tests/Feature/NPlusOneQueriesTest.php` | NPlusOneQueriesTest, test_booking_index_no_nplusone_queries, test_room_index_no_nplusone_queries, test_room_show_no_nplusone_queries, test_booking_show_no_nplusone_queries (+4) |
| `backend/tests/Feature/Room/RoomCrudTest.php` | RoomCrudTest, test_guests_can_list_all_rooms, test_rooms_index_includes_lock_version, test_show_room_includes_lock_version, test_regular_user_cannot_update_room (+4) |
| `backend/tests/Feature/Room/RoomConcurrencyTest.php` | RoomConcurrencyTest, test_update_after_many_versions_still_works, test_conflict_response_contains_error_info, setUp, test_service_layer_concurrent_updates_exception_contains_details (+3) |
| `backend/tests/Feature/Operations/OperationalPassThreeTest.php` | OperationalPassThreeTest, test_rooms_with_same_type_and_tier_are_equivalent, test_room_with_higher_tier_is_upgrade_over_lower_tier, test_room_with_lower_tier_is_not_accepted_as_upgrade, test_cross_location_equivalence_ignores_location_id (+3) |

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
| `Update → SupportsTags` | cross_community | 6 |
| `Update → Flush` | cross_community | 6 |
| `Destroy → SupportsTags` | cross_community | 6 |
| `Destroy → Flush` | cross_community | 6 |
| `Handle → CalculateRefundAmount` | cross_community | 4 |
| `Cancel → CalculateRefundAmount` | cross_community | 4 |
| `Run → Create` | cross_community | 3 |
| `Update → UpdateWithVersionCheck` | cross_community | 3 |
| `Update → Refresh` | cross_community | 3 |
| `Update → ForRoom` | cross_community | 3 |

## Connected Areas

| Area | Connections |
|------|-------------|
| Room | 26 calls |
| Operations | 6 calls |
| Listeners | 2 calls |
| Services | 1 calls |

## How to Explore

1. `gitnexus_context({name: "TestCase"})` — see callers and callees
2. `gitnexus_query({query: "feature"})` — find related execution flows
3. Read key files listed above for implementation details
