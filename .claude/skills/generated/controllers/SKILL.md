---
name: controllers
description: "Skill for the Controllers area of soleil-hostel. 29 symbols across 22 files."
---

# Controllers

29 symbols | 22 files | Cohesion: 90%

## When to Use

- Working with code in `backend/`
- Understanding how getErrorMessage, RoomController, ReviewController work
- Modifying controllers-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/app/Http/Controllers/AdminBookingController.php` | AdminBookingController, showTrashed, restore, forceDelete, restoreBulk |
| `backend/app/Http/Controllers/BookingController.php` | BookingController, confirm, cancel, buildCancellationMessage |
| `backend/app/Http/Controllers/RoomController.php` | RoomController |
| `backend/app/Http/Controllers/ReviewController.php` | ReviewController |
| `backend/app/Http/Controllers/LocationController.php` | LocationController |
| `backend/app/Http/Controllers/HealthController.php` | HealthController |
| `backend/app/Http/Controllers/CspViolationReportController.php` | CspViolationReportController |
| `backend/app/Http/Controllers/Controller.php` | Controller |
| `backend/app/Http/Controllers/ContactController.php` | ContactController |
| `backend/app/Http/Controllers/AuthController.php` | AuthController |

## Entry Points

Start here when exploring this area:

- **`getErrorMessage`** (Function) — `frontend/src/shared/utils/toast.ts:115`
- **`RoomController`** (Class) — `backend/app/Http/Controllers/RoomController.php:24`
- **`ReviewController`** (Class) — `backend/app/Http/Controllers/ReviewController.php:20`
- **`LocationController`** (Class) — `backend/app/Http/Controllers/LocationController.php:18`
- **`HealthController`** (Class) — `backend/app/Http/Controllers/HealthController.php:18`

## Key Symbols

| Symbol | Type | File | Line |
|--------|------|------|------|
| `RoomController` | Class | `backend/app/Http/Controllers/RoomController.php` | 24 |
| `ReviewController` | Class | `backend/app/Http/Controllers/ReviewController.php` | 20 |
| `LocationController` | Class | `backend/app/Http/Controllers/LocationController.php` | 18 |
| `HealthController` | Class | `backend/app/Http/Controllers/HealthController.php` | 18 |
| `CspViolationReportController` | Class | `backend/app/Http/Controllers/CspViolationReportController.php` | 8 |
| `Controller` | Class | `backend/app/Http/Controllers/Controller.php` | 6 |
| `ContactController` | Class | `backend/app/Http/Controllers/ContactController.php` | 11 |
| `BookingController` | Class | `backend/app/Http/Controllers/BookingController.php` | 23 |
| `AuthController` | Class | `backend/app/Http/Controllers/AuthController.php` | 23 |
| `AdminBookingController` | Class | `backend/app/Http/Controllers/AdminBookingController.php` | 20 |
| `UnifiedAuthController` | Class | `backend/app/Http/Controllers/Auth/UnifiedAuthController.php` | 28 |
| `HttpOnlyTokenController` | Class | `backend/app/Http/Controllers/Auth/HttpOnlyTokenController.php` | 22 |
| `EmailVerificationController` | Class | `backend/app/Http/Controllers/Auth/EmailVerificationController.php` | 26 |
| `AuthController` | Class | `backend/app/Http/Controllers/Auth/AuthController.php` | 34 |
| `CustomerController` | Class | `backend/app/Http/Controllers/Admin/CustomerController.php` | 8 |
| `BookingResource` | Class | `backend/app/Http/Resources/BookingResource.php` | 8 |
| `getErrorMessage` | Function | `frontend/src/shared/utils/toast.ts` | 115 |
| `getTrashedBookingById` | Method | `backend/app/Services/BookingService.php` | 406 |
| `hasOverlappingBookings` | Method | `backend/app/Repositories/Contracts/BookingRepositoryInterface.php` | 156 |
| `confirm` | Method | `backend/app/Http/Controllers/BookingController.php` | 212 |

## Execution Flows

| Flow | Type | Steps |
|------|------|-------|
| `Handle → SupportsTags` | cross_community | 6 |
| `Handle → Flush` | cross_community | 6 |
| `Handle → BookingConfirmed` | cross_community | 4 |
| `Handle → BookingResource` | intra_community | 3 |
| `RestoreBulk → GetTrashedBookingById` | intra_community | 3 |
| `RestoreBulk → HasOverlappingBookings` | intra_community | 3 |
| `RestoreBulk → BookingResource` | intra_community | 3 |
| `HandleCancelConfirm → User` | cross_community | 3 |
| `HandleCancelConfirm → BuildCancellationMessage` | intra_community | 3 |
| `HandleCancelConfirm → BookingResource` | cross_community | 3 |

## Connected Areas

| Area | Connections |
|------|-------------|
| Bookings | 1 calls |
| Notifications | 1 calls |
| Services | 1 calls |

## How to Explore

1. `gitnexus_context({name: "getErrorMessage"})` — see callers and callees
2. `gitnexus_query({query: "controllers"})` — find related execution flows
3. Read key files listed above for implementation details
