---
name: room
description: "Skill for the Room area of soleil-hostel. 217 symbols across 38 files."
---

# Room

217 symbols | 38 files | Cohesion: 73%

## When to Use

- Working with code in `backend/`
- Understanding how actingAs, assertConflictResponse, actingAsAdmin work
- Modifying room-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/tests/Feature/Room/RoomAuthorizationTest.php` | test_admin_can_delete_room, test_guest_can_access_rooms_index, test_user_can_access_rooms_index, test_moderator_can_access_rooms_index, test_admin_can_access_rooms_index (+19) |
| `backend/tests/Feature/Room/RoomValidationTest.php` | test_store_requires_name, test_store_requires_description, test_store_requires_price, test_store_requires_max_guests, test_store_requires_status (+16) |
| `backend/tests/Feature/Booking/BookingPolicyTest.php` | test_owner_can_view_own_booking, test_non_owner_cannot_view_other_booking, test_owner_can_update_own_booking, test_non_owner_cannot_update_other_booking, test_owner_can_delete_own_booking (+8) |
| `backend/tests/Feature/Room/RoomCrudTest.php` | test_admin_can_create_room, test_regular_user_cannot_create_room, test_admin_can_update_room, test_update_returns_404_for_nonexistent_room, test_delete_returns_404_for_nonexistent_room (+7) |
| `backend/tests/Feature/LocationTest.php` | it_returns_null_coordinates_when_missing, location_has_many_rooms, location_has_many_active_rooms, room_belongs_to_location, with_room_counts_scope_loads_counts (+7) |
| `backend/tests/Feature/Audit/AdminAuditLogTest.php` | test_force_delete_creates_audit_log, test_force_delete_preserves_audit_after_record_destroyed, test_restore_creates_audit_log, test_bulk_restore_creates_audit_logs_per_booking, test_room_create_creates_audit_log (+6) |
| `backend/tests/Feature/CreateBookingConcurrencyTest.php` | test_normal_booking_creation_succeeds, test_fully_overlapping_booking_is_rejected, test_same_day_checkin_checkout_boundary_is_allowed, test_partial_overlap_at_start_is_rejected, test_partial_overlap_at_end_is_rejected (+5) |
| `backend/tests/Feature/Room/LegacyRoomAuthorizationTest.php` | test_moderator_cannot_delete_room_via_legacy_endpoint, test_user_cannot_delete_room_via_legacy_endpoint, test_admin_can_delete_room_via_legacy_endpoint, getValidRoomData, test_user_cannot_create_room_via_legacy_endpoint (+5) |
| `backend/tests/Feature/Booking/BookingSoftDeleteTest.php` | test_delete_uses_soft_delete_and_preserves_data, test_soft_deleted_bookings_excluded_from_index, test_soft_delete_records_audit_trail, test_admin_delete_records_admin_as_deleter, test_regular_user_cannot_view_trashed (+5) |
| `backend/tests/Unit/Models/RoomTest.php` | test_room_can_be_created_with_factory, test_lock_version_cannot_be_mass_assigned, test_lock_version_is_cast_to_integer, test_lock_version_accessor_returns_1_for_null, test_lock_version_accessor_returns_actual_value_when_set (+5) |

## Entry Points

Start here when exploring this area:

- **`actingAs`** (Method) — `backend/tests/TestCase.php:36`
- **`assertConflictResponse`** (Method) — `backend/tests/Traits/RoomTestAssertions.php:60`
- **`actingAsAdmin`** (Method) — `backend/tests/Feature/MonitoringLoggingTest.php:16`
- **`test_detailed_health_endpoint_returns_correct_structure`** (Method) — `backend/tests/Feature/MonitoringLoggingTest.php:66`
- **`test_normal_booking_creation_succeeds`** (Method) — `backend/tests/Feature/CreateBookingConcurrencyTest.php:56`

## Key Symbols

| Symbol | Type | File | Line |
|--------|------|------|------|
| `actingAs` | Method | `backend/tests/TestCase.php` | 36 |
| `assertConflictResponse` | Method | `backend/tests/Traits/RoomTestAssertions.php` | 60 |
| `actingAsAdmin` | Method | `backend/tests/Feature/MonitoringLoggingTest.php` | 16 |
| `test_detailed_health_endpoint_returns_correct_structure` | Method | `backend/tests/Feature/MonitoringLoggingTest.php` | 66 |
| `test_normal_booking_creation_succeeds` | Method | `backend/tests/Feature/CreateBookingConcurrencyTest.php` | 56 |
| `test_fully_overlapping_booking_is_rejected` | Method | `backend/tests/Feature/CreateBookingConcurrencyTest.php` | 89 |
| `test_same_day_checkin_checkout_boundary_is_allowed` | Method | `backend/tests/Feature/CreateBookingConcurrencyTest.php` | 136 |
| `test_partial_overlap_at_start_is_rejected` | Method | `backend/tests/Feature/CreateBookingConcurrencyTest.php` | 179 |
| `test_partial_overlap_at_end_is_rejected` | Method | `backend/tests/Feature/CreateBookingConcurrencyTest.php` | 219 |
| `test_cancelled_booking_does_not_block_new_booking` | Method | `backend/tests/Feature/CreateBookingConcurrencyTest.php` | 259 |
| `test_booking_update_with_overlap_is_rejected` | Method | `backend/tests/Feature/CreateBookingConcurrencyTest.php` | 299 |
| `test_different_rooms_can_have_same_dates` | Method | `backend/tests/Feature/CreateBookingConcurrencyTest.php` | 348 |
| `test_invalid_date_range_is_rejected` | Method | `backend/tests/Feature/CreateBookingConcurrencyTest.php` | 389 |
| `test_past_checkin_date_is_rejected` | Method | `backend/tests/Feature/CreateBookingConcurrencyTest.php` | 410 |
| `test_validation_error_includes_per_field_errors` | Method | `backend/tests/Feature/ApiErrorFormatTest.php` | 61 |
| `test_403_returns_standardized_json` | Method | `backend/tests/Feature/ApiErrorFormatTest.php` | 133 |
| `test_model_not_found_returns_standardized_404` | Method | `backend/tests/Feature/ApiErrorFormatTest.php` | 154 |
| `test_bulk_restore_requires_ids` | Method | `backend/tests/Feature/Validation/FormRequestValidationTest.php` | 159 |
| `test_bulk_restore_requires_ids_to_be_array` | Method | `backend/tests/Feature/Validation/FormRequestValidationTest.php` | 170 |
| `test_bulk_restore_requires_non_empty_ids` | Method | `backend/tests/Feature/Validation/FormRequestValidationTest.php` | 181 |

## Execution Flows

| Flow | Type | Steps |
|------|------|-------|
| `Run → Create` | cross_community | 3 |

## How to Explore

1. `soleil-ai-review-engine_context({name: "actingAs"})` — see callers and callees
2. `soleil-ai-review-engine_query({query: "room"})` — find related execution flows
3. Read key files listed above for implementation details
