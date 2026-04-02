---
name: admin
description: "Skill for the Admin area of soleil-hostel. 29 symbols across 12 files."
---

# Admin

29 symbols | 12 files | Cohesion: 79%

## When to Use

- Working with code in `frontend/`
- Understanding how getErrorMessage, cancelBooking, restoreBooking work
- Modifying admin-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `frontend/src/features/admin/AdminDashboard.tsx` | handleRestore, handleForceDelete, parseDisplayDate, formatDateTime, renderLoadingCards (+5) |
| `frontend/src/features/admin/admin.api.ts` | restoreBooking, forceDeleteBooking, fetchAdminBookings, fetchTrashedBookings, fetchContactMessages |
| `frontend/src/shared/lib/booking.utils.ts` | getStatusConfig, formatDateVN, formatDateRangeVN |
| `frontend/src/features/admin/AdminLayout.tsx` | getAdminBreadcrumb, getInitials, AdminLayout |
| `deploy.php` | success |
| `frontend/src/shared/utils/toast.ts` | getErrorMessage |
| `frontend/src/features/booking/booking.api.ts` | cancelBooking |
| `frontend/src/features/bookings/GuestDashboard.tsx` | handleResendVerification |
| `frontend/src/features/bookings/BookingDetailPage.tsx` | handleCancelConfirm |
| `frontend/src/features/booking/BookingCancelDialog.tsx` | handleCancel |

## Entry Points

Start here when exploring this area:

- **`getErrorMessage`** (Function) — `frontend/src/shared/utils/toast.ts:115`
- **`cancelBooking`** (Function) — `frontend/src/features/booking/booking.api.ts:49`
- **`restoreBooking`** (Function) — `frontend/src/features/admin/admin.api.ts:72`
- **`forceDeleteBooking`** (Function) — `frontend/src/features/admin/admin.api.ts:81`
- **`getStatusConfig`** (Function) — `frontend/src/shared/lib/booking.utils.ts:25`

## Key Symbols

| Symbol | Type | File | Line |
|--------|------|------|------|
| `getErrorMessage` | Function | `frontend/src/shared/utils/toast.ts` | 115 |
| `cancelBooking` | Function | `frontend/src/features/booking/booking.api.ts` | 49 |
| `restoreBooking` | Function | `frontend/src/features/admin/admin.api.ts` | 72 |
| `forceDeleteBooking` | Function | `frontend/src/features/admin/admin.api.ts` | 81 |
| `getStatusConfig` | Function | `frontend/src/shared/lib/booking.utils.ts` | 25 |
| `formatDateVN` | Function | `frontend/src/shared/lib/booking.utils.ts` | 38 |
| `formatDateRangeVN` | Function | `frontend/src/shared/lib/booking.utils.ts` | 45 |
| `fetchAdminBookings` | Function | `frontend/src/features/admin/admin.api.ts` | 22 |
| `fetchTrashedBookings` | Function | `frontend/src/features/admin/admin.api.ts` | 41 |
| `fetchContactMessages` | Function | `frontend/src/features/admin/admin.api.ts` | 62 |
| `success` | Method | `deploy.php` | 517 |
| `handleResendVerification` | Function | `frontend/src/features/bookings/GuestDashboard.tsx` | 171 |
| `handleCancelConfirm` | Function | `frontend/src/features/bookings/BookingDetailPage.tsx` | 182 |
| `handleCancel` | Function | `frontend/src/features/booking/BookingCancelDialog.tsx` | 23 |
| `handleRestore` | Function | `frontend/src/features/admin/AdminDashboard.tsx` | 223 |
| `handleForceDelete` | Function | `frontend/src/features/admin/AdminDashboard.tsx` | 247 |
| `handleSubmit` | Function | `frontend/src/features/admin/rooms/RoomForm.tsx` | 79 |
| `DetailContent` | Function | `frontend/src/features/bookings/BookingDetailPanel.tsx` | 21 |
| `parseDisplayDate` | Function | `frontend/src/features/admin/AdminDashboard.tsx` | 71 |
| `formatDateTime` | Function | `frontend/src/features/admin/AdminDashboard.tsx` | 75 |

## Execution Flows

| Flow | Type | Steps |
|------|------|-------|
| `HandleForceDelete → SupportsTags` | cross_community | 6 |
| `HandleForceDelete → Flush` | cross_community | 6 |
| `Run → Success` | cross_community | 4 |
| `BookingCard → FormatDateVN` | cross_community | 4 |
| `HandleForceDelete → BookingConfirmed` | cross_community | 4 |
| `RunVerification → Success` | cross_community | 3 |
| `RunGateChecks → Success` | cross_community | 3 |
| `RunDeployOperations → Success` | cross_community | 3 |
| `GuestDashboard → CancelBooking` | cross_community | 3 |
| `HandleForceDelete → BookingResource` | cross_community | 3 |

## Connected Areas

| Area | Connections |
|------|-------------|
| Controllers | 1 calls |
| Bookings | 1 calls |

## How to Explore

1. `soleil-ai-review-engine_context({name: "getErrorMessage"})` — see callers and callees
2. `soleil-ai-review-engine_query({query: "admin"})` — find related execution flows
3. Read key files listed above for implementation details
