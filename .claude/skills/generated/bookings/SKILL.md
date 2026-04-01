---
name: bookings
description: "Skill for the Bookings area of soleil-hostel. 51 symbols across 16 files."
---

# Bookings

51 symbols | 16 files | Cohesion: 88%

## When to Use

- Working with code in `frontend/`
- Understanding how getBookingById, buildBookingReference, formatShortBookingDate work
- Modifying bookings-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `frontend/src/features/bookings/BookingDetailPage.tsx` | buildBookingReference, capitalizeFirstLetter, formatDetailDate, formatDetailDateTime, getRoomLabel (+5) |
| `frontend/src/features/admin/bookings/adminBooking.helpers.ts` | parseDisplayDate, buildBookingReference, formatShortBookingDate, formatAdminBookingAmount, getAdminBookingStatusConfig (+2) |
| `frontend/src/features/admin/bookings/AdminBookingDashboard.tsx` | hasActiveFilters, isAbortError, buildApiFilters, AdminBookingDashboard, updateFilter |
| `frontend/src/features/bookings/GuestDashboard.tsx` | GuestDashboard, getDashboardStatus, formatBookingReference, formatDashboardDateRange, BookingCard |
| `frontend/src/features/bookings/bookingViewModel.ts` | formatCompactVND, getRoomName, getAmountFormatted, toBookingViewModel |
| `frontend/src/features/admin/bookings/TodayOperations.tsx` | TodayOperations, loadData, handleCheckIn, handleCheckOut |
| `frontend/src/features/booking/booking.api.ts` | getBookingById, fetchMyBookings, submitReview |
| `frontend/src/features/admin/bookings/BookingCalendar.tsx` | BookingCalendar, getBookingForDay, getStatusColor |
| `frontend/src/features/bookings/useMyBookings.ts` | useMyBookingsQuery, useCancelBookingMutation |
| `frontend/src/features/bookings/ReviewForm.tsx` | getAutoTitle, handleSubmit |

## Entry Points

Start here when exploring this area:

- **`getBookingById`** (Function) — `frontend/src/features/booking/booking.api.ts:60`
- **`buildBookingReference`** (Function) — `frontend/src/features/admin/bookings/adminBooking.helpers.ts:45`
- **`formatShortBookingDate`** (Function) — `frontend/src/features/admin/bookings/adminBooking.helpers.ts:54`
- **`formatAdminBookingAmount`** (Function) — `frontend/src/features/admin/bookings/adminBooking.helpers.ts:58`
- **`getAdminBookingStatusConfig`** (Function) — `frontend/src/features/admin/bookings/adminBooking.helpers.ts:72`

## Key Symbols

| Symbol | Type | File | Line |
|--------|------|------|------|
| `getBookingById` | Function | `frontend/src/features/booking/booking.api.ts` | 60 |
| `buildBookingReference` | Function | `frontend/src/features/admin/bookings/adminBooking.helpers.ts` | 45 |
| `formatShortBookingDate` | Function | `frontend/src/features/admin/bookings/adminBooking.helpers.ts` | 54 |
| `formatAdminBookingAmount` | Function | `frontend/src/features/admin/bookings/adminBooking.helpers.ts` | 58 |
| `getAdminBookingStatusConfig` | Function | `frontend/src/features/admin/bookings/adminBooking.helpers.ts` | 72 |
| `getAdminBookingRoomLabel` | Function | `frontend/src/features/admin/bookings/adminBooking.helpers.ts` | 76 |
| `normalizeAdminBookingSearch` | Function | `frontend/src/features/admin/bookings/adminBooking.helpers.ts` | 81 |
| `toBookingViewModel` | Function | `frontend/src/features/bookings/bookingViewModel.ts` | 43 |
| `formatVND` | Function | `frontend/src/shared/lib/formatCurrency.ts` | 2 |
| `useMyBookingsQuery` | Function | `frontend/src/features/bookings/useMyBookings.ts` | 11 |
| `useCancelBookingMutation` | Function | `frontend/src/features/bookings/useMyBookings.ts` | 62 |
| `fetchMyBookings` | Function | `frontend/src/features/booking/booking.api.ts` | 38 |
| `submitReview` | Function | `frontend/src/features/booking/booking.api.ts` | 75 |
| `BookingDetailPanel` | Function | `frontend/src/features/bookings/BookingDetailPanel.tsx` | 146 |
| `buildBookingReference` | Function | `frontend/src/features/bookings/BookingDetailPage.tsx` | 22 |
| `capitalizeFirstLetter` | Function | `frontend/src/features/bookings/BookingDetailPage.tsx` | 28 |
| `formatDetailDate` | Function | `frontend/src/features/bookings/BookingDetailPage.tsx` | 33 |
| `formatDetailDateTime` | Function | `frontend/src/features/bookings/BookingDetailPage.tsx` | 44 |
| `getRoomLabel` | Function | `frontend/src/features/bookings/BookingDetailPage.tsx` | 63 |
| `canCancelBooking` | Function | `frontend/src/features/bookings/BookingDetailPage.tsx` | 72 |

## Execution Flows

| Flow | Type | Steps |
|------|------|-------|
| `BookingCard → FormatDateVN` | cross_community | 4 |
| `BookingDetailPage → GetBookingById` | intra_community | 3 |
| `BookingDetailPage → CapitalizeFirstLetter` | intra_community | 3 |
| `AdminBookingTable → ParseDisplayDate` | intra_community | 3 |
| `AdminBookingDashboard → NormalizeAdminBookingSearch` | intra_community | 3 |
| `GuestDashboard → FetchMyBookings` | intra_community | 3 |
| `GuestDashboard → CancelBooking` | cross_community | 3 |
| `RenderBookingsPanel → FormatVND` | cross_community | 3 |
| `TodayOperations → Success` | cross_community | 3 |
| `TodayOperations → LoadData` | intra_community | 3 |

## Connected Areas

| Area | Connections |
|------|-------------|
| Admin | 4 calls |

## How to Explore

1. `soleil-ai-review-engine_context({name: "getBookingById"})` — see callers and callees
2. `soleil-ai-review-engine_query({query: "bookings"})` — find related execution flows
3. Read key files listed above for implementation details
