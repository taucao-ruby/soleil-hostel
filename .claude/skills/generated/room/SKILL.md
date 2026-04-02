---
name: room
description: "Skill for the Room area of soleil-hostel. 107 symbols across 24 files."
---

# Room

107 symbols | 24 files | Cohesion: 85%

## When to Use

- Working with code in `backend/`
- Understanding how test_cancelled_by_admin_factory_sets_admin_id, test_multi_day_factory_creates_correct_duration, test_multi_day_defaults_to_three_days work
- Modifying room-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/tests/Feature/Room/RoomAuthorizationTest.php` | test_guest_can_access_rooms_index, test_user_can_access_rooms_index, test_moderator_can_access_rooms_index, test_admin_can_access_rooms_index, test_guest_can_access_rooms_show (+19) |
| `backend/tests/Feature/LocationTest.php` | it_casts_lock_version_as_integer, it_returns_null_coordinates_when_missing, location_has_many_rooms, location_has_many_active_rooms, room_belongs_to_location (+8) |
| `backend/tests/Unit/Models/RoomTest.php` | test_room_can_be_created_with_factory, test_lock_version_cannot_be_mass_assigned, test_lock_version_is_cast_to_integer, test_lock_version_accessor_returns_1_for_null, test_lock_version_accessor_returns_actual_value_when_set (+5) |
| `backend/tests/Feature/Room/LegacyRoomAuthorizationTest.php` | test_user_cannot_delete_room_via_legacy_endpoint, test_moderator_cannot_delete_room_via_legacy_endpoint, test_admin_can_delete_room_via_legacy_endpoint, getValidRoomData, test_user_cannot_create_room_via_legacy_endpoint (+5) |
| `backend/tests/Feature/Room/RoomCrudTest.php` | test_guests_can_list_all_rooms, test_rooms_index_includes_lock_version, test_show_room_includes_lock_version, test_regular_user_cannot_update_room, test_admin_can_delete_room (+2) |
| `backend/tests/Feature/LocationApiTest.php` | it_filters_rooms_by_location_id, it_returns_all_rooms_without_location_filter, rooms_endpoint_supports_date_availability_filter, room_show_endpoint_includes_active_booking_count |
| `backend/tests/Feature/Room/RoomValidationTest.php` | test_update_requires_all_fields, test_update_lock_version_must_be_integer, test_update_lock_version_must_be_at_least_1, test_update_accepts_null_lock_version |
| `backend/tests/Feature/Review/ReviewCrudTest.php` | test_non_owner_cannot_update_others_review, test_owner_can_delete_own_review, test_admin_can_delete_any_review, test_non_owner_cannot_delete_others_review |
| `backend/tests/Unit/BookingFactoryMethodsTest.php` | test_cancelled_by_admin_factory_sets_admin_id, test_multi_day_factory_creates_correct_duration, test_multi_day_defaults_to_three_days |
| `backend/tests/Feature/RoomOptimisticLockingTest.php` | test_new_room_starts_with_lock_version_1, test_get_room_returns_lock_version, test_lock_version_accessor_handles_null_as_version_1 |

## Entry Points

Start here when exploring this area:

- **`test_cancelled_by_admin_factory_sets_admin_id`** (Method) — `backend/tests/Unit/BookingFactoryMethodsTest.php:23`
- **`test_multi_day_factory_creates_correct_duration`** (Method) — `backend/tests/Unit/BookingFactoryMethodsTest.php:33`
- **`test_multi_day_defaults_to_three_days`** (Method) — `backend/tests/Unit/BookingFactoryMethodsTest.php:41`
- **`assertRoomUpdated`** (Method) — `backend/tests/Traits/RoomTestAssertions.php:88`
- **`assertRoomJsonStructure`** (Method) — `backend/tests/Traits/RoomTestAssertions.php:113`

## Key Symbols

| Symbol | Type | File | Line |
|--------|------|------|------|
| `test_cancelled_by_admin_factory_sets_admin_id` | Method | `backend/tests/Unit/BookingFactoryMethodsTest.php` | 23 |
| `test_multi_day_factory_creates_correct_duration` | Method | `backend/tests/Unit/BookingFactoryMethodsTest.php` | 33 |
| `test_multi_day_defaults_to_three_days` | Method | `backend/tests/Unit/BookingFactoryMethodsTest.php` | 41 |
| `assertRoomUpdated` | Method | `backend/tests/Traits/RoomTestAssertions.php` | 88 |
| `assertRoomJsonStructure` | Method | `backend/tests/Traits/RoomTestAssertions.php` | 113 |
| `test_new_room_starts_with_lock_version_1` | Method | `backend/tests/Feature/RoomOptimisticLockingTest.php` | 53 |
| `test_get_room_returns_lock_version` | Method | `backend/tests/Feature/RoomOptimisticLockingTest.php` | 306 |
| `test_lock_version_accessor_handles_null_as_version_1` | Method | `backend/tests/Feature/RoomOptimisticLockingTest.php` | 480 |
| `it_casts_lock_version_as_integer` | Method | `backend/tests/Feature/LocationTest.php` | 66 |
| `it_returns_null_coordinates_when_missing` | Method | `backend/tests/Feature/LocationTest.php` | 104 |
| `location_has_many_rooms` | Method | `backend/tests/Feature/LocationTest.php` | 114 |
| `location_has_many_active_rooms` | Method | `backend/tests/Feature/LocationTest.php` | 123 |
| `room_belongs_to_location` | Method | `backend/tests/Feature/LocationTest.php` | 133 |
| `with_room_counts_scope_loads_counts` | Method | `backend/tests/Feature/LocationTest.php` | 154 |
| `booking_auto_populates_location_id_on_create` | Method | `backend/tests/Feature/LocationTest.php` | 168 |
| `booking_updates_location_id_when_room_changes` | Method | `backend/tests/Feature/LocationTest.php` | 184 |
| `room_at_location_scope_filters_by_location` | Method | `backend/tests/Feature/LocationTest.php` | 209 |
| `room_available_between_scope_excludes_booked_rooms` | Method | `backend/tests/Feature/LocationTest.php` | 222 |
| `room_available_between_allows_same_day_checkout_checkin` | Method | `backend/tests/Feature/LocationTest.php` | 252 |
| `room_display_name_includes_room_number` | Method | `backend/tests/Feature/LocationTest.php` | 276 |

## Execution Flows

| Flow | Type | Steps |
|------|------|-------|
| `Run → Create` | cross_community | 3 |

## Connected Areas

| Area | Connections |
|------|-------------|
| Booking | 47 calls |

## How to Explore

1. `soleil-ai-review-engine_context({name: "test_cancelled_by_admin_factory_sets_admin_id"})` — see callers and callees
2. `soleil-ai-review-engine_query({query: "room"})` — find related execution flows
3. Read key files listed above for implementation details
