---
name: bookings
description: "Skill for the Bookings area of soleil-hostel. 41 symbols across 12 files."
---

# Bookings

41 symbols | 12 files | Cohesion: 94%

## When to Use

- Working with code in `frontend/`
- Understanding how getStatusConfig, formatDateVN, formatDateRangeVN work
- Modifying bookings-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `deploy.php` | run, preflight, runGateChecks, runDeployOperations, runVerification (+15) |
| `frontend/src/features/admin/bookings/TodayOperations.tsx` | TodayOperations, loadData, handleCheckIn, handleCheckOut |
| `frontend/src/features/admin/bookings/AdminBookingTable.tsx` | AdminBookingTable, handleConfirm, handleCancel |
| `frontend/src/shared/lib/booking.utils.ts` | getStatusConfig, formatDateVN, formatDateRangeVN |
| `frontend/src/features/admin/bookings/BookingCalendar.tsx` | BookingCalendar, getBookingForDay, getStatusColor |
| `frontend/src/features/bookings/GuestDashboard.tsx` | BookingCard, GuestDashboard |
| `frontend/src/features/bookings/BookingDetailPanel.tsx` | DetailContent |
| `frontend/src/features/bookings/useMyBookings.ts` | useCancelBookingMutation |
| `frontend/src/features/booking/booking.api.ts` | cancelBooking |
| `frontend/src/features/booking/BookingCancelDialog.tsx` | handleCancel |

## Entry Points

Start here when exploring this area:

- **`getStatusConfig`** (Function) — `frontend/src/shared/lib/booking.utils.ts:25`
- **`formatDateVN`** (Function) — `frontend/src/shared/lib/booking.utils.ts:38`
- **`formatDateRangeVN`** (Function) — `frontend/src/shared/lib/booking.utils.ts:45`
- **`useCancelBookingMutation`** (Function) — `frontend/src/features/bookings/useMyBookings.ts:62`
- **`cancelBooking`** (Function) — `frontend/src/features/booking/booking.api.ts:47`

## Key Symbols

| Symbol | Type | File | Line |
|--------|------|------|------|
| `getStatusConfig` | Function | `frontend/src/shared/lib/booking.utils.ts` | 25 |
| `formatDateVN` | Function | `frontend/src/shared/lib/booking.utils.ts` | 38 |
| `formatDateRangeVN` | Function | `frontend/src/shared/lib/booking.utils.ts` | 45 |
| `useCancelBookingMutation` | Function | `frontend/src/features/bookings/useMyBookings.ts` | 62 |
| `cancelBooking` | Function | `frontend/src/features/booking/booking.api.ts` | 47 |
| `toBookingViewModel` | Function | `frontend/src/features/bookings/bookingViewModel.ts` | 24 |
| `run` | Method | `deploy.php` | 47 |
| `preflight` | Method | `deploy.php` | 70 |
| `runGateChecks` | Method | `deploy.php` | 106 |
| `runDeployOperations` | Method | `deploy.php` | 158 |
| `runVerification` | Method | `deploy.php` | 204 |
| `runCommand` | Method | `deploy.php` | 250 |
| `resolveHealthBaseUrl` | Method | `deploy.php` | 298 |
| `httpGet` | Method | `deploy.php` | 321 |
| `parseDotEnv` | Method | `deploy.php` | 357 |
| `findExecutable` | Method | `deploy.php` | 390 |
| `finish` | Method | `deploy.php` | 431 |
| `banner` | Method | `deploy.php` | 450 |
| `phase` | Method | `deploy.php` | 459 |
| `printList` | Method | `deploy.php` | 468 |

## Execution Flows

| Flow | Type | Steps |
|------|------|-------|
| `Run → Success` | intra_community | 4 |
| `Run → Line` | intra_community | 4 |
| `Run → PrintBlock` | intra_community | 4 |
| `Run → Phase` | intra_community | 3 |
| `Run → RecordError` | intra_community | 3 |
| `Run → FindExecutable` | intra_community | 3 |
| `RunVerification → PrintBlock` | intra_community | 3 |
| `RunVerification → Success` | intra_community | 3 |
| `RunGateChecks → Success` | intra_community | 3 |
| `RunDeployOperations → PrintBlock` | intra_community | 3 |

## Connected Areas

| Area | Connections |
|------|-------------|
| Services | 1 calls |
| Booking | 1 calls |

## How to Explore

1. `gitnexus_context({name: "getStatusConfig"})` — see callers and callees
2. `gitnexus_query({query: "bookings"})` — find related execution flows
3. Read key files listed above for implementation details
