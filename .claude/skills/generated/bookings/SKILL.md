---
name: bookings
description: "Skill for the Bookings area of soleil-hostel. 26 symbols across 14 files."
---

# Bookings

26 symbols | 14 files | Cohesion: 85%

## When to Use

- Working with code in `frontend/`
- Understanding how useMyBookingsQuery, useCancelBookingMutation, fetchMyBookings work
- Modifying bookings-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `frontend/src/features/admin/bookings/TodayOperations.tsx` | TodayOperations, loadData, handleCheckIn, handleCheckOut |
| `frontend/src/features/admin/bookings/AdminBookingTable.tsx` | AdminBookingTable, handleConfirm, handleCancel |
| `frontend/src/shared/lib/booking.utils.ts` | getStatusConfig, formatDateVN, formatDateRangeVN |
| `frontend/src/features/admin/bookings/BookingCalendar.tsx` | BookingCalendar, getBookingForDay, getStatusColor |
| `frontend/src/features/bookings/useMyBookings.ts` | useMyBookingsQuery, useCancelBookingMutation |
| `frontend/src/features/booking/booking.api.ts` | fetchMyBookings, cancelBooking |
| `frontend/src/features/bookings/GuestDashboard.tsx` | GuestDashboard, BookingCard |
| `frontend/src/features/booking/BookingCancelDialog.tsx` | handleCancel |
| `frontend/src/features/admin/bookings/AdminBookingDashboard.tsx` | fetchBookings |
| `deploy.php` | success |

## Entry Points

Start here when exploring this area:

- **`useMyBookingsQuery`** (Function) — `frontend/src/features/bookings/useMyBookings.ts:11`
- **`useCancelBookingMutation`** (Function) — `frontend/src/features/bookings/useMyBookings.ts:62`
- **`fetchMyBookings`** (Function) — `frontend/src/features/booking/booking.api.ts:36`
- **`cancelBooking`** (Function) — `frontend/src/features/booking/booking.api.ts:47`
- **`getStatusConfig`** (Function) — `frontend/src/shared/lib/booking.utils.ts:25`

## Key Symbols

| Symbol | Type | File | Line |
|--------|------|------|------|
| `useMyBookingsQuery` | Function | `frontend/src/features/bookings/useMyBookings.ts` | 11 |
| `useCancelBookingMutation` | Function | `frontend/src/features/bookings/useMyBookings.ts` | 62 |
| `fetchMyBookings` | Function | `frontend/src/features/booking/booking.api.ts` | 36 |
| `cancelBooking` | Function | `frontend/src/features/booking/booking.api.ts` | 47 |
| `getStatusConfig` | Function | `frontend/src/shared/lib/booking.utils.ts` | 25 |
| `formatDateVN` | Function | `frontend/src/shared/lib/booking.utils.ts` | 38 |
| `formatDateRangeVN` | Function | `frontend/src/shared/lib/booking.utils.ts` | 45 |
| `toBookingViewModel` | Function | `frontend/src/features/bookings/bookingViewModel.ts` | 24 |
| `success` | Method | `deploy.php` | 517 |
| `GuestDashboard` | Function | `frontend/src/features/bookings/GuestDashboard.tsx` | 77 |
| `handleCancel` | Function | `frontend/src/features/booking/BookingCancelDialog.tsx` | 23 |
| `fetchBookings` | Function | `frontend/src/features/admin/bookings/AdminBookingDashboard.tsx` | 36 |
| `handleSubmit` | Function | `frontend/src/features/admin/rooms/RoomForm.tsx` | 79 |
| `AdminBookingTable` | Function | `frontend/src/features/admin/bookings/AdminBookingTable.tsx` | 12 |
| `handleConfirm` | Function | `frontend/src/features/admin/bookings/AdminBookingTable.tsx` | 16 |
| `handleCancel` | Function | `frontend/src/features/admin/bookings/AdminBookingTable.tsx` | 29 |
| `BookingCard` | Function | `frontend/src/features/bookings/GuestDashboard.tsx` | 22 |
| `DetailContent` | Function | `frontend/src/features/bookings/BookingDetailPanel.tsx` | 20 |
| `TodayOperations` | Function | `frontend/src/features/admin/bookings/TodayOperations.tsx` | 8 |
| `loadData` | Function | `frontend/src/features/admin/bookings/TodayOperations.tsx` | 24 |

## Execution Flows

| Flow | Type | Steps |
|------|------|-------|
| `Run → Success` | cross_community | 4 |
| `RunVerification → Success` | cross_community | 3 |
| `RunGateChecks → Success` | cross_community | 3 |
| `RunDeployOperations → Success` | cross_community | 3 |
| `TodayOperations → Success` | cross_community | 3 |
| `TodayOperations → LoadData` | intra_community | 3 |
| `AdminBookingTable → Success` | intra_community | 3 |
| `BookingCard → FormatDateVN` | intra_community | 3 |
| `GuestDashboard → FetchMyBookings` | intra_community | 3 |
| `GuestDashboard → FetchBookings` | intra_community | 3 |

## How to Explore

1. `soleil-ai-review-engine_context({name: "useMyBookingsQuery"})` — see callers and callees
2. `soleil-ai-review-engine_query({query: "bookings"})` — find related execution flows
3. Read key files listed above for implementation details
