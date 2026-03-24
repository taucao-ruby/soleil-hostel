---
name: controllers
description: "Skill for the Controllers area of soleil-hostel. 54 symbols across 29 files."
---

# Controllers

54 symbols | 29 files | Cohesion: 75%

## When to Use

- Working with code in `backend/`
- Understanding how getErrorMessage, RoomController, ReviewController work
- Modifying controllers-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/app/Http/Controllers/AdminBookingController.php` | index, trashed, AdminBookingController, showTrashed, restore (+2) |
| `backend/app/Http/Controllers/AuthController.php` | register, login, me, logout, refresh (+1) |
| `backend/app/Http/Controllers/BookingController.php` | BookingController, cancel, buildCancellationMessage, store, update |
| `backend/app/Http/Controllers/ContactController.php` | store, index, markAsRead, ContactController |
| `backend/app/Http/Controllers/RoomController.php` | RoomController, show, store, update |
| `backend/app/Http/Controllers/Auth/HttpOnlyTokenController.php` | login, generateDeviceFingerprint, HttpOnlyTokenController |
| `backend/app/Services/BookingService.php` | getTrashedBookings, getTrashedBookingById |
| `backend/app/Repositories/Contracts/BookingRepositoryInterface.php` | getAllWithTrashedPaginated, hasOverlappingBookings |
| `backend/app/Traits/ApiResponse.php` | success |
| `backend/app/Services/ContactMessageService.php` | getPaginated |

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
| `RoomResource` | Class | `backend/app/Http/Resources/RoomResource.php` | 14 |
| `BookingResource` | Class | `backend/app/Http/Resources/BookingResource.php` | 8 |
| `getErrorMessage` | Function | `frontend/src/shared/utils/toast.ts` | 115 |
| `success` | Method | `backend/app/Traits/ApiResponse.php` | 11 |
| `getPaginated` | Method | `backend/app/Services/ContactMessageService.php` | 47 |

## Execution Flows

| Flow | Type | Steps |
|------|------|-------|
| `Store → ClassifyDatabaseError` | cross_community | 5 |
| `Store → SupportsTags` | cross_community | 5 |
| `Store → Flush` | cross_community | 5 |
| `Store → GetInstance` | cross_community | 4 |
| `Store → DoPurify` | cross_community | 4 |
| `Store → CreateBookingWithLocking` | cross_community | 4 |
| `Store → RecordSuccess` | cross_community | 4 |
| `Store → GetInstance` | cross_community | 4 |
| `Store → DoPurify` | cross_community | 4 |
| `Update → GetInstance` | cross_community | 4 |

## Connected Areas

| Area | Connections |
|------|-------------|
| Services | 3 calls |
| Auth | 3 calls |
| Security | 2 calls |
| Listeners | 2 calls |
| Feature | 1 calls |
| Unit | 1 calls |
| Notifications | 1 calls |
| Bookings | 1 calls |

## How to Explore

1. `gitnexus_context({name: "getErrorMessage"})` — see callers and callees
2. `gitnexus_query({query: "controllers"})` — find related execution flows
3. Read key files listed above for implementation details
