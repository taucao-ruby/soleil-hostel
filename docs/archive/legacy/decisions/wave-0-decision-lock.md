# Wave 0 Decision Lock

**Status**: OPEN — requires owner sign-off before Wave 1 implementation begins  
**Date**: 2026-03-29  
**Reviewers required**: Product Owner, Backend Lead, Frontend Lead  
**Scope**: Semantic contract lock for moderator access, TodayOperations, and password reset priority

> **GATE**: Implementation of Wave 1 or any later wave that touches these three areas must not start until the decision fields marked `REQUIRED OWNER SIGN-OFF` are signed off and this document is updated to `LOCKED`.

---

## Decision 1 — Moderator Scope

### Current code reality

**Backend (confirmed from code):**

| Route group | Middleware | Moderator access |
|---|---|---|
| `GET /api/v1/admin/bookings` (index) | `role:moderator` + gate `view-all-bookings` | **ALLOWED** |
| `GET /api/v1/admin/bookings/trashed` | `role:moderator` + gate `view-all-bookings` | **ALLOWED** |
| `GET /api/v1/admin/bookings/trashed/{id}` | `role:moderator` + gate `view-all-bookings` | **ALLOWED** |
| `POST /api/v1/admin/bookings/{id}/restore` | `role:admin` (strict) | **DENIED** |
| `POST /api/v1/admin/bookings/restore-bulk` | `role:admin` (strict) | **DENIED** |
| `DELETE /api/v1/admin/bookings/{id}/force` | `role:admin` (strict) | **DENIED** |
| `GET /api/v1/admin/contact-messages` | `role:moderator` | **ALLOWED** |
| `PATCH /api/v1/admin/contact-messages/{id}/read` | `role:moderator` | **ALLOWED** |
| `GET /api/v1/admin/customers` (index, stats, show, bookings) | `role:moderator` | **ALLOWED** |

Evidence: `backend/routes/api/v1.php:57–87`, `AdminBookingController.php`, `docs/PERMISSION_MATRIX.md` Table A.

**Latent backend capabilities (defined but never invoked):**  
- Gate `moderate-content` — registered in `AuthServiceProvider`, no controller invokes it.  
- Moderator cancel-own — reachable via ownership policy; **intent unresolved** (PERMISSION_MATRIX Table D, BR-2).

**Frontend (confirmed from code):**  
- `AdminRoute.tsx:25`: `if (user?.role !== 'admin') return <Navigate to="/dashboard" replace />`  
- A logged-in moderator is redirected to `/dashboard` and sees `GuestDashboard`, not the admin UI.  
- No dedicated moderator UI exists anywhere in the frontend (`router.tsx`, `DashboardPage.tsx`).  
- The frontend is a strict binary: `admin` or `non-admin`. Moderator is treated as `non-admin`.

Evidence: `frontend/src/features/auth/AdminRoute.tsx:25`, `frontend/src/pages/DashboardPage.tsx:10,38`.

### Gap

The backend grants moderators read access to bookings, contact messages, and customers. The frontend blocks all of it. Moderator role is operationally dead from the user's perspective.

### Decision fields — REQUIRED OWNER SIGN-OFF

| # | Question | Options | Decision |
|---|---|---|---|
| M1 | Can a moderator view the admin booking list and trashed bookings? | YES (backend already allows) / NO (remove from backend) | **UNSIGNED** |
| M2 | Can a moderator view customer profiles and contact messages? | YES (backend already allows) / NO (remove from backend) | **UNSIGNED** |
| M3 | Is a dedicated moderator-only UI required, or is the existing admin UI sufficient with destructive actions hidden? | Shared admin UI (simplest) / Separate moderator UI | **UNSIGNED** |
| M4 | What is the intended behaviour of moderator cancel-own? | Intended (document in PERMISSION_MATRIX) / Remove from policy | **UNSIGNED** |

### Recommended default

If the moderator role is staff-facing (e.g., front-desk staff who monitor bookings but cannot delete data):  
- **M1 → YES** — no backend change needed; add frontend access under `role >= moderator`.  
- **M2 → YES** — same logic; no backend change needed.  
- **M3 → Shared admin UI with destructive actions hidden** — lowest implementation cost.  
- **M4 → Intended** — document as a permitted self-service cancel, block cancel-any.

### Consequences

| Choice | YES for M1/M2 | NO for M1/M2 |
|---|---|---|
| Backend work | None (already correct) | Remove `role:moderator` groups, add `role:admin` |
| Frontend work | Update `AdminRoute` to allow `moderator`; hide restore/force-delete buttons | No change needed |
| Operational risk | Moderator can read sensitive booking data | Moderator role has no operational purpose |

If M3 → **Separate moderator UI**: significant frontend scope increase; excluded from Wave 1 unless explicitly scoped.

---

## Decision 2 — TodayOperations Semantic Contract

### Current code reality

**Frontend sends** (confirmed from `adminBooking.api.ts:61–84`):

```
GET /api/v1/admin/bookings
  ?check_in_start=YYYY-MM-DD
  &check_in_end=YYYY-MM-DD
  &status=confirmed
  &location_id=N
```

"today" is computed as `new Date().toISOString().split('T')[0]` — UTC date from the browser.

**Backend processes** (confirmed from `AdminBookingController::index()` + `EloquentBookingRepository::getAllWithTrashedPaginated()`):  
- `getAllWithTrashedPaginated()` accepts only `$relations` and `$perPage`. No filter parameters are read from the request.  
- `check_in_start`, `check_in_end`, `check_out_start`, `check_out_end`, `status`, and `location_id` are all **silently ignored**.  
- The endpoint returns all bookings (active + trashed), paginated, ordered by `created_at` desc.

**TodayOperations is non-functional**: the UI renders arrivals and departures from what is actually an unfiltered paginated dump. Additionally, `response.data.data` is returned, but the response shape is `{ bookings: [...], meta: {...} }`, so the return value is the `meta` object, not the booking array (F-53 in `docs/FINDINGS_BACKLOG.md`). Arrivals and departures both silently render as empty or undefined at runtime.

**Check-in/check-out action** (confirmed from `TodayOperations.tsx:46–75`):  
- `handleCheckIn` calls `PATCH /v1/rooms/{roomId}/status` with `status: 'occupied'`.  
- `handleCheckOut` calls `PATCH /v1/rooms/{roomId}/status` with `status: 'maintenance'`.  
- No booking status transition occurs (e.g., `confirmed → checked_in`).  
- `lock_version: 1` is hardcoded (F-61 in `docs/FINDINGS_BACKLOG.md`).

### Semantic fields to lock — REQUIRED OWNER SIGN-OFF

| # | Field | Options | Decision |
|---|---|---|---|
| T1 | Timezone for "today" | Server timezone (hostel-local) / UTC / configurable per location | **UNSIGNED** |
| T2 | Arrivals filter: which bookings appear? | `check_in = today` exactly / `check_in >= today AND check_in < tomorrow` (half-open) | **UNSIGNED** |
| T3 | Departures filter: which bookings appear? | `check_out = today` exactly / `check_out >= today AND check_out < tomorrow` (half-open) | **UNSIGNED** |
| T4 | Status filter for TodayOperations | `confirmed` only / `confirmed + pending` | **UNSIGNED** |
| T5 | Should check-in action transition booking status? | Room status only (current) / Room status + booking status transition | **UNSIGNED** |
| T6 | Should TodayOperations use a dedicated backend endpoint or extend the existing admin bookings query? | Dedicated `GET /api/v1/admin/bookings/today` or `GET /api/v1/admin/today-operations` / Extend existing `index()` with filter support | **UNSIGNED** |

### Recommended defaults with rationale

| Field | Recommended default | Rationale |
|---|---|---|
| T1 | Server timezone (hostel-local) | The hostel has a fixed physical location; "today" must match checkout desk clock, not browser UTC |
| T2 | Half-open: `check_in >= today AND check_in < tomorrow` | Consistent with the repo-wide half-open `[check_in, check_out)` interval contract |
| T3 | Half-open: `check_out >= today AND check_out < tomorrow` | Same rationale |
| T4 | `confirmed` only | Pending bookings have not been accepted; showing them would create false operational pressure |
| T5 | Room status only for now; defer booking status transition to a later wave | Booking status transitions require a defined state machine (not implemented); the room status change is sufficient for basic operations |
| T6 | Extend existing `index()` with request filter support | Avoids a new endpoint contract; filter support is reusable for other admin views |

### Concrete example (recommended defaults applied)

```
Request date: 2026-03-29 (UTC: 2026-03-28T17:00Z, hostel TZ: Asia/Ho_Chi_Minh = UTC+7)
Server "today": 2026-03-29

Arrivals query:
  check_in >= '2026-03-29 00:00:00' AND check_in < '2026-03-30 00:00:00'  [in hostel TZ]
  AND status = 'confirmed'
  AND deleted_at IS NULL

Departures query:
  check_out >= '2026-03-29 00:00:00' AND check_out < '2026-03-30 00:00:00'  [in hostel TZ]
  AND status = 'confirmed'
  AND deleted_at IS NULL
```

If T1 is resolved as UTC instead, a guest with `check_in = 2026-03-29` who checks in at 08:00 hostel time (01:00 UTC) would NOT appear in the arrivals list if "today" is resolved at 02:00 UTC as 2026-03-29, but AT 00:00 UTC the previous day's date would still apply. This is an operationally unsafe default for a Vietnamese hostel.

---

## Decision 3 — Launch Mode and Password Reset Priority

### Current repository reality

- `POST /api/forgot-password` → **404** (not routed)
- `POST /api/reset-password` → **404** (not routed)
- `password_reset_tokens` table **exists** (created in initial migration `0001_01_01_000000_create_users_table.php:25`).
- `PasswordResetTest.php` documents expected behaviour and has two active tests confirming both endpoints return 404. Implementation tests are commented out as specification.
- No frontend route or page for password reset exists (`router.tsx` confirmed).
- Admin can manually reset passwords via admin tooling (Horizon/Artisan access) — confirmed from `docs/OPERATIONAL_PLAYBOOK.md:667`.

Evidence: `backend/tests/Feature/Auth/PasswordResetTest.php:13–20`, `backend/routes/api/v1.php` (no password reset routes), `backend/database/migrations/0001_01_01_000000_create_users_table.php:25`.

### Launch mode definitions — REQUIRED OWNER SIGN-OFF

| Launch mode | Description |
|---|---|
| **Internal / staff-managed** | Users are created and managed by staff (admin creates accounts, resets passwords manually). No self-service account creation or recovery for end-users. |
| **Public B2C / self-service** | Users self-register and must be able to recover their own account without staff intervention. Password reset is a user-recoverable path. |

### Priority by launch mode

| | Internal / staff-managed | Public B2C / self-service |
|---|---|---|
| Is password reset a blocker for launch? | **NO** | **YES** |
| Wave assignment | Wave 3 or deferred | Wave 1 (pull forward as blocker) |
| Risk if absent | Staff handles recovery via admin tooling; accepted operational cost | Users locked out of their own accounts with no recovery path; public-facing blocking defect |
| Operational cost | Admin must reset passwords manually per-request | None beyond implementation cost |
| User risk | Low (internal audience, staff-mediated) | High (public users, no self-service recovery) |
| Email delivery dependency | Not needed for launch | Required: password reset emails must be reliably delivered before launch |

### Recommendation block

> **If launch mode is Internal/staff-managed**, password reset belongs in **Wave 3** (or later). The `password_reset_tokens` table exists; the implementation is ready to be activated but not blocking.
>
> **If launch mode is Public B2C/self-service**, password reset belongs in **Wave 1** and is a **launch blocker**. Both the backend routes and the frontend flow must be implemented and tested before any public traffic is accepted.

### Decision field — REQUIRED OWNER SIGN-OFF

| # | Question | Decision |
|---|---|---|
| P1 | What is the launch mode for Soleil Hostel? | Internal/staff-managed OR Public B2C/self-service — **UNSIGNED** |

---

## Route Matrix — Confirmed from code (as of 2026-03-29)

### Admin-only routes (strictly `role:admin`, moderator DENIED)

| Route | Method | Controller |
|---|---|---|
| `/api/v1/admin/bookings/{id}/restore` | POST | `AdminBookingController::restore` |
| `/api/v1/admin/bookings/restore-bulk` | POST | `AdminBookingController::restoreBulk` |
| `/api/v1/admin/bookings/{id}/force` | DELETE | `AdminBookingController::forceDelete` |
| `/api/v1/rooms` (CUD) | POST/PUT/PATCH/DELETE | `RoomController` |

### Moderator+ routes (`role:moderator` hierarchy, moderator AND admin ALLOWED)

| Route | Method | Controller |
|---|---|---|
| `/api/v1/admin/bookings` | GET | `AdminBookingController::index` |
| `/api/v1/admin/bookings/trashed` | GET | `AdminBookingController::trashed` |
| `/api/v1/admin/bookings/trashed/{id}` | GET | `AdminBookingController::showTrashed` |
| `/api/v1/admin/contact-messages` | GET | `ContactController::index` |
| `/api/v1/admin/contact-messages/{id}/read` | PATCH | `ContactController::markAsRead` |
| `/api/v1/admin/customers` | GET | `CustomerController::index` |
| `/api/v1/admin/customers/stats` | GET | `CustomerController::stats` |
| `/api/v1/admin/customers/{email}` | GET | `CustomerController::show` |
| `/api/v1/admin/customers/{email}/bookings` | GET | `CustomerController::bookings` |

### User-only routes (authenticated user, no elevated role required)

| Route | Method | Notes |
|---|---|---|
| `/api/v1/bookings/{b}/cancel` (own) | POST | Ownership check in policy |
| `/api/v1/reviews` (own) | POST/PUT/PATCH/DELETE | Ownership check in policy |
| `/api/v1/booking` (create) | POST | Authenticated user |
| `/api/v1/my-bookings` | GET | User-scoped |

> **Frontend caveat**: `AdminRoute.tsx` currently redirects any user with `role !== 'admin'` to `/dashboard`. The moderator+ backend routes above are unreachable from the frontend until `AdminRoute.tsx` is updated to allow `role >= moderator`. This is the primary gap driving Decision 1.

---

## Sign-off block

This document becomes actionable when the following fields are resolved and initialled:

| Field | Question | Decision | Initials | Date |
|---|---|---|---|---|
| M1 | Moderator can view admin booking list? | | | |
| M2 | Moderator can view customers and contact messages? | | | |
| M3 | Shared admin UI or separate moderator UI? | | | |
| M4 | Moderator cancel-own: intended behaviour? | | | |
| T1 | Timezone for "today" in TodayOperations | | | |
| T2 | Arrivals filter semantics | | | |
| T3 | Departures filter semantics | | | |
| T4 | Status filter: confirmed-only or confirmed+pending? | | | |
| T5 | Check-in action: room status only or + booking status transition? | | | |
| T6 | TodayOperations endpoint strategy: filter existing or new endpoint? | | | |
| P1 | Launch mode: Internal or Public B2C? | | | |

**All fields above must be signed before Wave 1 implementation of these areas begins.**

---

## Cross-references

- `docs/PERMISSION_MATRIX.md` — authoritative RBAC truth (Tables A–E, Open Follow-Ups)
- `docs/FINDINGS_BACKLOG.md` — F-53 (TodayOperations return type bug), F-61 (hardcoded lock_version)
- `backend/tests/Feature/Auth/PasswordResetTest.php` — specification tests (commented), active 404 assertions
- `docs/ADR.md` — ADR-005 (Enum-based RBAC), ADR-006 (Dual Auth), ADR-009 (Soft Deletes)
- `frontend/src/features/auth/AdminRoute.tsx` — frontend role gate (admin-only, binary)
- `frontend/src/features/admin/bookings/adminBooking.api.ts:61–84` — getTodayArrivals/getTodayDepartures
- `backend/app/Http/Controllers/AdminBookingController.php:37–55` — index() (no filter support)
