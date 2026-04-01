---
name: booking
description: "Skill for the Booking area of soleil-hostel. 205 symbols across 38 files."
---

# Booking

205 symbols | 38 files | Cohesion: 64%

## When to Use

- Working with code in `backend/`
- Understanding how isValidEmail, validateBookingForm, calculateNights work
- Modifying booking-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/tests/Feature/Booking/BookingSoftDeleteTest.php` | test_delete_uses_soft_delete_and_preserves_data, test_soft_deleted_bookings_excluded_from_index, test_soft_delete_records_audit_trail, test_admin_delete_records_admin_as_deleter, test_regular_user_cannot_view_trashed (+21) |
| `backend/tests/Feature/Room/RoomValidationTest.php` | test_store_requires_name, test_store_requires_description, test_store_requires_price, test_store_requires_max_guests, test_store_requires_status (+12) |
| `backend/tests/Feature/Booking/RestoreIntegrityTest.php` | make23P01QueryException, test_restore_db_exclusion_constraint_returns_409, test_restore_non_conflict_query_exception_rethrows, test_restore_clean_succeeds, test_restore_fires_booking_restored_event (+11) |
| `backend/tests/Feature/Booking/AdminBookingFilterTest.php` | booking, asAdmin, asModerator, test_index_returns_all_bookings_when_no_filters, test_check_in_start_filters_out_earlier_bookings (+10) |
| `backend/tests/Feature/Booking/AdminBookingCoverageTest.php` | test_moderator_can_access_admin_booking_index, test_user_cannot_access_admin_booking_index, test_moderator_can_access_admin_trashed_index, test_user_cannot_access_admin_trashed_index, createTrashedBooking (+9) |
| `backend/tests/Feature/Booking/BookingPolicyTest.php` | test_owner_can_view_own_booking, test_non_owner_cannot_view_other_booking, test_owner_can_update_own_booking, test_non_owner_cannot_update_other_booking, test_owner_can_delete_own_booking (+8) |
| `backend/tests/Feature/Audit/AdminAuditLogTest.php` | test_force_delete_creates_audit_log, test_force_delete_preserves_audit_after_record_destroyed, test_restore_creates_audit_log, test_bulk_restore_creates_audit_logs_per_booking, test_room_create_creates_audit_log (+6) |
| `backend/tests/Feature/CreateBookingConcurrencyTest.php` | test_normal_booking_creation_succeeds, test_fully_overlapping_booking_is_rejected, test_same_day_checkin_checkout_boundary_is_allowed, test_partial_overlap_at_start_is_rejected, test_partial_overlap_at_end_is_rejected (+5) |
| `frontend/src/features/booking/BookingForm.tsx` | buildBookingReference, handleSubmit, formatRoomLabel, formatDateDisplay, parseRoomId (+5) |
| `frontend/src/features/booking/booking.validation.ts` | parseDateInput, validateBookingForm, calculateNights, addDays, getMinCheckOutDate (+3) |

## Entry Points

Start here when exploring this area:

- **`isValidEmail`** (Function) — `frontend/src/shared/utils/security.ts:39`
- **`validateBookingForm`** (Function) — `frontend/src/features/booking/booking.validation.ts:52`
- **`calculateNights`** (Function) — `frontend/src/features/booking/booking.validation.ts:133`
- **`createBooking`** (Function) — `frontend/src/features/booking/booking.api.ts:26`
- **`getMinCheckOutDate`** (Function) — `frontend/src/features/booking/booking.validation.ts:163`

## Key Symbols

| Symbol | Type | File | Line |
|--------|------|------|------|
| `isValidEmail` | Function | `frontend/src/shared/utils/security.ts` | 39 |
| `validateBookingForm` | Function | `frontend/src/features/booking/booking.validation.ts` | 52 |
| `calculateNights` | Function | `frontend/src/features/booking/booking.validation.ts` | 133 |
| `createBooking` | Function | `frontend/src/features/booking/booking.api.ts` | 26 |
| `getMinCheckOutDate` | Function | `frontend/src/features/booking/booking.validation.ts` | 163 |
| `getMaxCheckOutDate` | Function | `frontend/src/features/booking/booking.validation.ts` | 175 |
| `formatDateForInput` | Function | `frontend/src/features/booking/booking.validation.ts` | 146 |
| `getMinCheckInDate` | Function | `frontend/src/features/booking/booking.validation.ts` | 156 |
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

## Execution Flows

| Flow | Type | Steps |
|------|------|-------|
| `ExecuteWarmup → Today` | cross_community | 5 |
| `HandleChange → ParseDateInput` | cross_community | 4 |
| `HandleChange → FormatDateForInput` | cross_community | 4 |
| `BookingForm → GetRooms` | cross_community | 3 |
| `BookingForm → ParseDateInput` | cross_community | 3 |
| `Destroy → SoftDeleteWithAudit` | cross_community | 3 |
| `HandleSubmit → IsValidEmail` | intra_community | 3 |
| `HandleSubmit → ParseDateInput` | intra_community | 3 |

## Connected Areas

| Area | Connections |
|------|-------------|
| Feature | 10 calls |
| Room | 4 calls |
| Rooms | 1 calls |
| Bookings | 1 calls |

## How to Explore

1. `soleil-ai-review-engine_context({name: "isValidEmail"})` — see callers and callees
2. `soleil-ai-review-engine_query({query: "booking"})` — find related execution flows
3. Read key files listed above for implementation details
