---
name: booking
description: "Skill for the Booking area of soleil-hostel. 164 symbols across 34 files."
---

# Booking

164 symbols | 34 files | Cohesion: 66%

## When to Use

- Working with code in `backend/`
- Understanding how calculateNights, formatDateForInput, getMinCheckInDate work
- Modifying booking-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/tests/Feature/Booking/BookingSoftDeleteTest.php` | test_delete_uses_soft_delete_and_preserves_data, test_soft_deleted_bookings_excluded_from_index, test_soft_delete_records_audit_trail, test_admin_delete_records_admin_as_deleter, test_regular_user_cannot_view_trashed (+21) |
| `backend/tests/Feature/Room/RoomValidationTest.php` | test_store_requires_name, test_store_requires_description, test_store_requires_price, test_store_requires_max_guests, test_store_requires_status (+12) |
| `backend/tests/Feature/Booking/AdminBookingCoverageTest.php` | test_moderator_can_access_admin_booking_index, test_user_cannot_access_admin_booking_index, test_moderator_can_access_admin_trashed_index, test_user_cannot_access_admin_trashed_index, createTrashedBooking (+9) |
| `backend/tests/Feature/Booking/BookingPolicyTest.php` | test_owner_can_view_own_booking, test_non_owner_cannot_view_other_booking, test_owner_can_update_own_booking, test_non_owner_cannot_update_other_booking, test_owner_can_delete_own_booking (+8) |
| `backend/tests/Feature/Audit/AdminAuditLogTest.php` | test_force_delete_creates_audit_log, test_force_delete_preserves_audit_after_record_destroyed, test_restore_creates_audit_log, test_bulk_restore_creates_audit_logs_per_booking, test_room_create_creates_audit_log (+6) |
| `backend/tests/Feature/CreateBookingConcurrencyTest.php` | test_normal_booking_creation_succeeds, test_fully_overlapping_booking_is_rejected, test_same_day_checkin_checkout_boundary_is_allowed, test_partial_overlap_at_start_is_rejected, test_partial_overlap_at_end_is_rejected (+5) |
| `backend/tests/Feature/Health/HealthEndpointTest.php` | test_detailed_requires_admin_role, test_detailed_returns_full_health_for_admin, test_db_endpoint_returns_healthy_for_admin, test_cache_endpoint_returns_healthy_for_admin, test_queue_endpoint_returns_healthy_for_admin (+1) |
| `backend/tests/Feature/Contact/ContactAuthorizationTest.php` | test_moderator_can_access_contact_index, test_user_cannot_access_contact_index, test_admin_can_access_contact_index, test_moderator_can_mark_contact_as_read, test_user_cannot_mark_contact_as_read (+1) |
| `backend/tests/Feature/User/ProfileTest.php` | test_user_can_view_own_profile, test_legacy_me_endpoint_returns_user_data, test_unified_me_endpoint_has_transient_token_bug, test_profile_update_endpoint_not_implemented, test_password_change_endpoint_not_implemented |
| `backend/tests/Feature/Room/RoomCrudTest.php` | test_admin_can_create_room, test_regular_user_cannot_create_room, test_admin_can_update_room, test_update_returns_404_for_nonexistent_room, test_delete_returns_404_for_nonexistent_room |

## Entry Points

Start here when exploring this area:

- **`calculateNights`** (Function) — `frontend/src/features/booking/booking.validation.ts:97`
- **`formatDateForInput`** (Function) — `frontend/src/features/booking/booking.validation.ts:109`
- **`getMinCheckInDate`** (Function) — `frontend/src/features/booking/booking.validation.ts:119`
- **`getMinCheckOutDate`** (Function) — `frontend/src/features/booking/booking.validation.ts:126`
- **`isValidEmail`** (Function) — `frontend/src/shared/utils/security.ts:39`

## Key Symbols

| Symbol | Type | File | Line |
|--------|------|------|------|
| `calculateNights` | Function | `frontend/src/features/booking/booking.validation.ts` | 97 |
| `formatDateForInput` | Function | `frontend/src/features/booking/booking.validation.ts` | 109 |
| `getMinCheckInDate` | Function | `frontend/src/features/booking/booking.validation.ts` | 119 |
| `getMinCheckOutDate` | Function | `frontend/src/features/booking/booking.validation.ts` | 126 |
| `isValidEmail` | Function | `frontend/src/shared/utils/security.ts` | 39 |
| `validateBookingForm` | Function | `frontend/src/features/booking/booking.validation.ts` | 20 |
| `createBooking` | Function | `frontend/src/features/booking/booking.api.ts` | 24 |
| `getBookingById` | Function | `frontend/src/features/booking/booking.api.ts` | 58 |
| `actingAs` | Method | `backend/tests/TestCase.php` | 36 |
| `assertConflictResponse` | Method | `backend/tests/Traits/RoomTestAssertions.php` | 60 |
| `assertRoomUpdated` | Method | `backend/tests/Traits/RoomTestAssertions.php` | 88 |
| `assertRoomJsonStructure` | Method | `backend/tests/Traits/RoomTestAssertions.php` | 113 |
| `actingAsAdmin` | Method | `backend/tests/Feature/MonitoringLoggingTest.php` | 16 |
| `test_detailed_health_endpoint_returns_correct_structure` | Method | `backend/tests/Feature/MonitoringLoggingTest.php` | 66 |
| `test_normal_booking_creation_succeeds` | Method | `backend/tests/Feature/CreateBookingConcurrencyTest.php` | 56 |
| `test_fully_overlapping_booking_is_rejected` | Method | `backend/tests/Feature/CreateBookingConcurrencyTest.php` | 89 |
| `test_same_day_checkin_checkout_boundary_is_allowed` | Method | `backend/tests/Feature/CreateBookingConcurrencyTest.php` | 136 |
| `test_partial_overlap_at_start_is_rejected` | Method | `backend/tests/Feature/CreateBookingConcurrencyTest.php` | 179 |
| `test_partial_overlap_at_end_is_rejected` | Method | `backend/tests/Feature/CreateBookingConcurrencyTest.php` | 219 |
| `test_cancelled_booking_does_not_block_new_booking` | Method | `backend/tests/Feature/CreateBookingConcurrencyTest.php` | 259 |

## Execution Flows

| Flow | Type | Steps |
|------|------|-------|
| `Destroy → SoftDeleteWithAudit` | cross_community | 3 |
| `BookingForm → GetRooms` | cross_community | 3 |
| `BookingForm → FormatDateForInput` | intra_community | 3 |
| `HandleSubmit → IsValidEmail` | intra_community | 3 |

## Connected Areas

| Area | Connections |
|------|-------------|
| Room | 7 calls |
| Feature | 3 calls |
| Rooms | 2 calls |

## How to Explore

1. `soleil-ai-review-engine_context({name: "calculateNights"})` — see callers and callees
2. `soleil-ai-review-engine_query({query: "booking"})` — find related execution flows
3. Read key files listed above for implementation details
