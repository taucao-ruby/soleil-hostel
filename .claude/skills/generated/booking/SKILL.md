---
name: booking
description: "Skill for the Booking area of soleil-hostel. 47 symbols across 14 files."
---

# Booking

47 symbols | 14 files | Cohesion: 71%

## When to Use

- Working with code in `backend/`
- Understanding how calculateNights, formatDateForInput, getMinCheckInDate work
- Modifying booking-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/tests/Feature/Booking/BookingSoftDeleteTest.php` | test_admin_can_view_trashed_bookings, test_admin_can_restore_trashed_booking, test_regular_user_cannot_restore, test_admin_can_force_delete_trashed_booking, test_regular_user_cannot_force_delete (+11) |
| `backend/tests/Feature/Booking/AdminBookingCoverageTest.php` | createTrashedBooking, test_admin_can_view_trashed_booking_via_v1, test_moderator_can_view_trashed_booking_via_v1, test_user_cannot_view_trashed_booking_via_v1, test_user_cannot_restore_booking (+5) |
| `frontend/src/features/booking/booking.validation.ts` | calculateNights, formatDateForInput, getMinCheckInDate, getMinCheckOutDate, validateBookingForm |
| `frontend/src/features/booking/BookingList.tsx` | BookingList, loadBookings, getStatusBadge |
| `frontend/src/features/booking/booking.validation.test.ts` | getFutureDateStr, getPastDateStr |
| `frontend/src/features/booking/BookingForm.tsx` | BookingForm, handleSubmit |
| `frontend/src/features/booking/booking.api.ts` | createBooking, getBookingById |
| `backend/app/Models/Booking.php` | softDeleteWithAudit |
| `backend/database/factories/BookingFactory.php` | forUser |
| `backend/app/Providers/HorizonServiceProvider.php` | gate |

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
| `softDeleteWithAudit` | Method | `backend/app/Models/Booking.php` | 393 |
| `test_admin_can_view_trashed_bookings` | Method | `backend/tests/Feature/Booking/BookingSoftDeleteTest.php` | 162 |
| `test_admin_can_restore_trashed_booking` | Method | `backend/tests/Feature/Booking/BookingSoftDeleteTest.php` | 220 |
| `test_regular_user_cannot_restore` | Method | `backend/tests/Feature/Booking/BookingSoftDeleteTest.php` | 241 |
| `test_admin_can_force_delete_trashed_booking` | Method | `backend/tests/Feature/Booking/BookingSoftDeleteTest.php` | 283 |
| `test_regular_user_cannot_force_delete` | Method | `backend/tests/Feature/Booking/BookingSoftDeleteTest.php` | 316 |
| `test_soft_deleted_bookings_dont_block_new_bookings` | Method | `backend/tests/Feature/Booking/BookingSoftDeleteTest.php` | 331 |
| `test_admin_can_view_specific_trashed_booking` | Method | `backend/tests/Feature/Booking/BookingSoftDeleteTest.php` | 400 |
| `test_deleted_by_relationship` | Method | `backend/tests/Feature/Booking/BookingSoftDeleteTest.php` | 441 |
| `test_moderator_can_view_trashed` | Method | `backend/tests/Feature/Booking/BookingSoftDeleteTest.php` | 466 |
| `test_moderator_cannot_restore` | Method | `backend/tests/Feature/Booking/BookingSoftDeleteTest.php` | 476 |
| `test_moderator_cannot_force_delete` | Method | `backend/tests/Feature/Booking/BookingSoftDeleteTest.php` | 486 |

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
| Room | 27 calls |
| Feature | 3 calls |
| Rooms | 2 calls |

## How to Explore

1. `soleil-ai-review-engine_context({name: "calculateNights"})` — see callers and callees
2. `soleil-ai-review-engine_query({query: "booking"})` — find related execution flows
3. Read key files listed above for implementation details
