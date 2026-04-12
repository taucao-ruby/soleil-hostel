# Permission Matrix — Soleil Hostel

> **Single source of truth for actor-to-permission mapping.**
> All other RBAC documentation references this file — do not redefine permissions elsewhere.
>
> Last verified: OPUS-01, OPUS-02R, OPUS-03R, OPUS-HARDEN-01, OPUS-VERIFY-01
> Verification status: **PASS WITH FOLLOW-UPS**
> Open follow-ups: **5** — see [Open Follow-Ups](#open-follow-ups)
>
> **UI DESIGN CONTEXT (Google Stitch):**
> This is the role-to-screen/action mapping used to drive every conditional UI element.
> Three roles: `user` (Guest), `moderator` (Staff/read-only admin), `admin` (Full control).
> Use this table to decide which buttons appear, which tabs exist, which routes are accessible.
> **Critical**: Moderator has read-only access to `/admin/*` (bookings, customers, rooms view).
> Room CUD and booking write operations (restore, force-delete) are **admin-only**.

---

## Table F — UI Role Summary (Stitch Reference)

Quick-reference for UI conditional rendering. Derived from Tables A–E below.

| Screen / Action | Guest (user) | Staff (moderator) | Admin |
|---|---|---|---|
| Homepage, Rooms, Locations | ✅ | ✅ | ✅ |
| Booking Form (`/booking`) | ✅ (auth required) | ✅ | ✅ |
| My Bookings (`/my-bookings`) | ✅ | ✅ | ✅ |
| Cancel own booking | ✅ | ✅ | ✅ |
| Guest Dashboard (`/dashboard`) | ✅ | ✅ | ❌ (sees Admin Dashboard) |
| Admin Dashboard (`/dashboard` for admin) | ❌ | ❌ | ✅ |
| Admin Bookings (`/admin/bookings`) | ❌ | ✅ read-only | ✅ full |
| Admin Trashed Bookings (`/admin/bookings/trashed`) | ❌ | ❌ | ✅ |
| Restore Booking | ❌ | ❌ | ✅ |
| Force-Delete Booking | ❌ | ❌ | ✅ |
| Admin Customers (`/admin/customers`) | ❌ | ✅ read-only | ✅ |
| Admin Rooms (`/admin/rooms`) | ❌ | ✅ view-only | ✅ |
| Room Create/Edit/Delete | ❌ | ❌ | ✅ |
| Contact Messages | ❌ | ❌ | ✅ |
| ReviewForm (for own past confirmed booking) | ✅ | ✅ | ✅ |
| FAQ Assistant Widget | ✅ (auth required) | ✅ | ✅ |
| Room Discovery Widget | ✅ (auth required) | ✅ | ✅ |
| Admin Draft Assistant (`/admin/draft-assistant`) | ❌ | ❌ | ✅ |
| Proposal Confirmation Modal | ✅ (auth required) | ✅ | ✅ |

> **Status badge color guide**: `pending` → yellow/amber · `confirmed` → green · `cancelled` → red/muted · `refund_failed` → orange + escalation · `refund_pending` → blue/info

---

## Table A — Backend Route Enforcement (Tier 1)

What the system actually does. Tier 1 evidence only.

| # | Operation | Guest | User | Moderator | Admin | Enforcement Layer | Enforcement Type | Defense-in-Depth | Test Coverage | Evidence |
|---|-----------|-------|------|-----------|-------|-------------------|------------------|------------------|---------------|----------|
| A1 | `POST /api/v1/rooms` | DENIED-EXPLICIT (401) | DENIED-SIDE-EFFECT (403) | DENIED-SIDE-EFFECT (403) | ALLOWED | Route `role:admin` (v1.php:33) + Policy `RoomPolicy::create` | HIERARCHY-DEPENDENT + EXACT-MATCH | YES | TEST-SURFACE DRIFT (legacy path) | OPUS-02R + OPUS-VERIFY-01 |
| A2 | `PUT /api/v1/rooms/{room}` | DENIED-EXPLICIT (401) | DENIED-SIDE-EFFECT (403) | DENIED-SIDE-EFFECT (403) | ALLOWED | Route `role:admin` (v1.php:33) + Policy `RoomPolicy::update` | HIERARCHY-DEPENDENT + EXACT-MATCH | YES | TEST-SURFACE DRIFT (legacy path) | OPUS-02R + OPUS-VERIFY-01 |
| A3 | `PATCH /api/v1/rooms/{room}` | DENIED-EXPLICIT (401) | DENIED-SIDE-EFFECT (403) | DENIED-SIDE-EFFECT (403) | ALLOWED | Route `role:admin` (v1.php:33) + Policy `RoomPolicy::update` | HIERARCHY-DEPENDENT + EXACT-MATCH | YES | CONFIRMED-V1 | OPUS-VERIFY-01 |
| A4 | `DELETE /api/v1/rooms/{room}` | DENIED-EXPLICIT (401) | DENIED-SIDE-EFFECT (403) | DENIED-SIDE-EFFECT (403) | ALLOWED | Route `role:admin` (v1.php:33) + Policy `RoomPolicy::delete` | HIERARCHY-DEPENDENT + EXACT-MATCH | YES | TEST-SURFACE DRIFT (legacy path) | OPUS-02R + OPUS-VERIFY-01 |
| A5 | `POST /api/v1/bookings/{b}/cancel` (own) | DENIED-EXPLICIT (401) | ALLOWED-OWN-ONLY | ALLOWED-OWN-ONLY | ALLOWED | Policy `BookingPolicy::cancel` + Service `CancellationService` | ROLE-OR-OWNERSHIP | NO (policy only) | CONFIRMED-V1 | OPUS-03R + OPUS-VERIFY-01 |
| A6 | `POST /api/v1/bookings/{b}/cancel` (others') | DENIED-EXPLICIT (401) | DENIED-SIDE-EFFECT (403) | DENIED-SIDE-EFFECT (403) | ALLOWED | Policy `BookingPolicy::cancel` | ROLE-OR-OWNERSHIP | NO (policy only) | CONFIRMED-V1 | OPUS-03R + OPUS-VERIFY-01 |
| A7 | `GET /api/v1/admin/bookings` | DENIED-EXPLICIT (401) | DENIED-SIDE-EFFECT (403) | ALLOWED | ALLOWED | Route `role:moderator` (v1.php:57) + Gate `view-all-bookings` (AdminBookingController) | HIERARCHY-DEPENDENT | YES | CONFIRMED-V1 | OPUS-VERIFY-01 |
| A8 | `GET /api/v1/admin/bookings/trashed` | DENIED-EXPLICIT (401) | DENIED-SIDE-EFFECT (403) | ALLOWED | ALLOWED | Route `role:moderator` (v1.php:57) + Gate `view-all-bookings` (AdminBookingController) | HIERARCHY-DEPENDENT | YES | CONFIRMED-V1 | OPUS-VERIFY-01 |
| A9 | `GET /api/v1/admin/bookings/trashed/{id}` | DENIED-EXPLICIT (401) | DENIED-SIDE-EFFECT (403) | ALLOWED | ALLOWED | Route `role:moderator` (v1.php:57) + Gate `view-all-bookings` (AdminBookingController) | HIERARCHY-DEPENDENT | YES | FOLLOW-UP REQUIRED (no v1 pin test) | OPUS-VERIFY-01 |
| A10 | `POST /api/v1/admin/bookings/{b}/restore` | DENIED-EXPLICIT (401) | DENIED-SIDE-EFFECT (403) | DENIED-SIDE-EFFECT (403) | ALLOWED | Route `role:admin` (v1.php:57) + Gate `admin` (AdminBookingController:109) | HIERARCHY-DEPENDENT + EXACT-MATCH | YES | CONFIRMED-V1 | OPUS-VERIFY-01 |
| A11 | `DELETE /api/v1/admin/bookings/{b}/force` | DENIED-EXPLICIT (401) | DENIED-SIDE-EFFECT (403) | DENIED-SIDE-EFFECT (403) | ALLOWED | Route `role:admin` (v1.php:57) + Gate `admin` (AdminBookingController:152) | HIERARCHY-DEPENDENT + EXACT-MATCH | YES | CONFIRMED-V1 | OPUS-VERIFY-01 |
| A12 | `POST /api/v1/admin/bookings/restore-bulk` | DENIED-EXPLICIT (401) | DENIED-SIDE-EFFECT (403) | DENIED-SIDE-EFFECT (403) | ALLOWED | Route `role:admin` (v1.php:57) + Gate `admin` (AdminBookingController:178) | HIERARCHY-DEPENDENT + EXACT-MATCH | YES | FOLLOW-UP REQUIRED (no moderator-denial test) | OPUS-VERIFY-01 |
| A13 | `POST /api/v1/ai/{task_type}` | DENIED-EXPLICIT (401) | ALLOWED (verified) | ALLOWED (verified) | ALLOWED | Route `check_token_valid` + `verified` + `throttle:10,1` + `ai_harness_enabled` + `ai_canary_router` + `ai_request_normalizer` (v1_ai.php:24-30) | AUTH-REQUIRED + FEATURE-GATED | NO (kill switch + rate limit only) | CONFIRMED-V1 | AI-HARNESS-01 |
| A14 | `POST /api/v1/ai/proposals/{hash}/decide` | DENIED-EXPLICIT (401) | ALLOWED (verified) | ALLOWED (verified) | ALLOWED | Route `check_token_valid` + `verified` + `throttle:10,1` + `ai_harness_enabled` (v1_ai.php:51-55) | AUTH-REQUIRED + FEATURE-GATED | NO (kill switch + rate limit only) | CONFIRMED-V1 | AI-HARNESS-01 |
| A15 | `GET /api/v1/ai/health` | ALLOWED | ALLOWED | ALLOWED | ALLOWED | Route `ai_harness_enabled` only (v1_ai.php:68-70) | FEATURE-GATED | NO | CONFIRMED-V1 | AI-HARNESS-01 |

**Resources not investigated** (out of scope for current batch):
- Booking create/update/delete (user endpoints)
- Booking confirm
- Contact messages (`/api/v1/admin/contact-messages/*`)
- Customers (`/api/v1/admin/customers/*`) — gated by `role:moderator`; `CustomerController`
- Reviews (`/api/v1/reviews/*`)
- Locations (`/api/v1/locations/*`)
- Auth endpoints (`/api/v1/auth/*`)

**AI Harness route notes** (rows A13–A15, added 2026-04-12):
- AI endpoints use `check_token_valid` + `verified` — any verified user can access. No role-based gate.
- `ai_harness_enabled` middleware acts as a kill switch (returns 404 when `AI_HARNESS_ENABLED=false`).
- `ai_canary_router` middleware performs percentage-based traffic routing; not a security gate.
- Proposal confirmation (`A14`) delegates to `CreateBookingService` / `BookingService::cancelBooking()` — existing booking invariants apply.
- Health endpoint (`A15`) is public but feature-gated — returns 404 when harness disabled.

**HIERARCHY-DEPENDENT NOTICE**: `role:admin` middleware is enforced via `EnsureUserHasRole` using role hierarchy comparison (`isAtLeast()`), not exact match. Current hierarchy places admin above moderator. If role hierarchy is modified, rows marked HIERARCHY-DEPENDENT may change without code review.

### Role Hierarchy Stability Warning

The `isAtLeast()` method (`User.php:134-146`) uses a static level mapping: `USER=1, MODERATOR=2, ADMIN=3`. This means:

- **Adding a role between existing roles** (e.g., `MANAGER` at level 3, pushing `ADMIN` to 4) silently shifts all `isAtLeast(ADMIN)` checks to include the new role.
- **Reordering role levels** changes every HIERARCHY-DEPENDENT permission row in Table A without any code change in controllers, policies, or routes.
- **All rows marked HIERARCHY-DEPENDENT** (A1-A4, A7-A12) are affected by hierarchy changes.

**Required procedure before any role hierarchy change:**
1. Full permission re-audit of all rows in Table A marked HIERARCHY-DEPENDENT
2. Review all `isAtLeast()` call sites: `EnsureUserHasRole.php`, `BookingPolicy.php`, `User.php`
3. Update this matrix with new enforcement evidence before deploy
4. Run the full test suite with explicit role-boundary assertions

Owner: Security Lead (see [CONTROL_PLANE_OWNERSHIP.md](./agents/CONTROL_PLANE_OWNERSHIP.md))

---

## Table B — Data Operation Permissions by Actor

| Operation | Guest | User | Moderator | Admin | Business Rules | Evidence |
|-----------|-------|------|-----------|-------|----------------|----------|
| room:create | DENIED-EXPLICIT | DENIED-SIDE-EFFECT | DENIED-SIDE-EFFECT | CURRENT | — | OPUS-02R |
| room:update | DENIED-EXPLICIT | DENIED-SIDE-EFFECT | DENIED-SIDE-EFFECT | CURRENT | — | OPUS-02R |
| room:delete | DENIED-EXPLICIT | DENIED-SIDE-EFFECT | DENIED-SIDE-EFFECT | CURRENT | — | OPUS-02R |
| booking:cancel-own | DENIED-EXPLICIT | CURRENT | CURRENT (UNRESOLVED INTENT) | CURRENT | BR-1, BR-2, BR-3 | OPUS-03R |
| booking:cancel-any | DENIED-EXPLICIT | DENIED-SIDE-EFFECT | DENIED-SIDE-EFFECT | CURRENT | BR-1 | OPUS-03R |
| booking:view-all | DENIED-EXPLICIT | DENIED-SIDE-EFFECT | ROUTE-ACCESSIBLE | CURRENT | — | OPUS-VERIFY-01 |
| booking:view-trashed | DENIED-EXPLICIT | DENIED-SIDE-EFFECT | DENIED-SIDE-EFFECT | CURRENT | — | OPUS-VERIFY-01 |
| booking:restore | DENIED-EXPLICIT | DENIED-SIDE-EFFECT | DENIED-SIDE-EFFECT | CURRENT | — | OPUS-VERIFY-01 |
| booking:force-delete | DENIED-EXPLICIT | DENIED-SIDE-EFFECT | DENIED-SIDE-EFFECT | CURRENT | — | OPUS-VERIFY-01 |
| gate:view-all-bookings | N/A | DENIED | ALLOWED | ALLOWED | — | AdminBookingController (index, trashed, showTrashed) |
| gate:manage-rooms | N/A | DENIED | DENIED | WOULD-ALLOW | — | LATENT-UNUSED |

---

## Table C — Latent Capability Registry

Capabilities that exist in code but are NOT currently enforced in any active execution path.

| # | Capability | Status | Location | Shadowed By | Activation Condition | Evidence |
|---|-----------|--------|----------|-------------|---------------------|----------|
| C1 | `BookingPolicy::viewAny()` moderator grant | LATENT | BookingPolicy.php:50 | N/A — not invoked | Route `role:moderator` and gate `view-all-bookings` both independently allow moderator; policy grant is consistent with but not in the active call path for admin booking reads. | OPUS-01 C1 |
| C2 | Gate `view-all-bookings` | CURRENT | AuthServiceProvider.php:73 | N/A — invoked by AdminBookingController | `AdminBookingController::index()`, `trashed()`, `showTrashed()` | OPUS-01 C3 |
| C3 | Gate `manage-rooms` | LATENT-UNUSED | AuthServiceProvider.php:81 | N/A — never invoked | Any controller calls `Gate::authorize('manage-rooms')` | OPUS-01 C3 |
| C4 | Gate `moderate-content` | LATENT-UNUSED | AuthServiceProvider.php:65 | N/A — never invoked | Any controller calls `Gate::authorize('moderate-content')` | OPUS-01 C3 |
| C5 | Moderator cancel-any | NO-PATH-FOUND | — | N/A | No route, controller branch, or policy path grants moderator cancel on others' bookings | OPUS-03R |
| C6 | Moderator admin booking read access | ROUTE-ACCESSIBLE | v1.php:57, AdminBookingController | N/A — active path | Route `role:moderator` + gate `view-all-bookings` both grant moderator read access to A7/A8/A9; no longer latent or shadowed. | OPUS-VERIFY-01 CLAIM-L1 |
| C7 | Moderator frontend admin access | CURRENT | router.tsx + AdminRoute.tsx | N/A — active path | AdminRoute default minRole='moderator' gates all /admin/* routes; room CUD uses minRole='admin'. Both align with backend enforcement. | 2026-03-29 Wave 2 |

---

## Table D — Business Rule Overlays

These restrict **when** an action is allowed, not **who** can perform it. They are not role permissions.

### BR-1: Cancellable Status Restriction

- **Rule**: Cancellation blocked when `booking.status` NOT IN `[pending, confirmed, refund_failed]`
- **Applies to**: All roles equally
- **Source**: `BookingPolicy.php:116` + `BookingStatus.php:28-35`
- **Dual-layer**: Also enforced in `CancellationService.php:96`
- **Note**: Policy and service must be updated together or behavior diverges

### BR-2: Timing Restriction (Started Booking)

- **Rule**: Non-admin cannot cancel after `booking.isStarted()` returns true
- **Applies to**: User, Moderator (not admin)
- **Admin exempt**: YES — admin bypasses at both policy and service layers
- **Source**: `BookingPolicy.php:121-123` + `CancellationService.php:101`

### BR-3: Config-Variable Timing Override

- **Rule**: `config('booking.cancellation.allow_after_checkin')` can disable BR-2 for non-admin actors
- **Default**: `false` (BR-2 is active)
- **If true**: Non-admin timing restriction is disabled
- **Source**: `BookingPolicy.php:122`
- **Config source**: NOT VERIFIED — **CONFIG-VARIABLE [SOURCE-UNVERIFIED]**
- **Risk**: Permission changes without code review if config is set to `true` in any environment
- **FOLLOW-UP REQUIRED**: Verify config source file and whether any environment sets this to `true` (OPUS-VERIFY-01 follow-up 3)

---

## Table E — Frontend Visibility (Tier 5 — NOT Enforcement)

> **This section describes UX gating only, not backend authorization.**
> For backend enforcement truth, see Tables A and B above.
> All claims below are Tier 5 — UX gating only, not backend enforcement.

| Screen / Action | user | moderator | admin | Source |
|-----------------|------|-----------|-------|--------|
| GuestDashboard at `/dashboard` | Rendered | Rendered (same as user) | Not rendered | `DashboardPage.tsx` |
| AdminDashboard at `/dashboard` | Not rendered | Not rendered | Rendered | `DashboardPage.tsx` |
| `/admin/*` route tree | Redirected (AdminRoute) | **Allowed** (AdminRoute minRole='moderator') | Allowed | `AdminRoute.tsx` + `router.tsx` |
| `/admin/bookings` (view all, filtered) | Blocked | **Allowed** | Allowed | AdminRoute + `role:moderator` backend |
| `/admin/bookings/trashed` (view) | Blocked | **Allowed** | Allowed | AdminRoute + `role:moderator` backend |
| `/admin/bookings/{id}/restore` | Blocked | Blocked | Allowed | AdminRoute routes to backend; `role:admin` blocks |
| `/admin/rooms` (view) | Blocked | **Allowed** (read-only) | Allowed | AdminRoute minRole='moderator' |
| `/admin/rooms/new` | Blocked | **Blocked** (AdminRoute minRole='admin') | Allowed | `AdminRoute.tsx` minRole="admin" |
| `/admin/rooms/:id/edit` | Blocked | **Blocked** (AdminRoute minRole='admin') | Allowed | `AdminRoute.tsx` minRole="admin" |
| `/admin/customers` | Blocked | **Allowed** | Allowed | AdminRoute minRole='moderator' |
| Admin API calls (GET /api/v1/admin/bookings*) | 403 from backend | Allowed (backend) | Allowed | `v1.php` (role:moderator) |
| Admin API calls (restore, force-delete) | 403 from backend | 403 from backend | Allowed | `v1.php` (role:admin) |
| FAQ Assistant Widget | Rendered (auth required) | Rendered | Rendered | `FaqAssistantWidget.tsx` |
| Room Discovery Widget | Rendered (auth required) | Rendered | Rendered | `RoomDiscoveryWidget.tsx` |
| `/admin/draft-assistant` | Blocked | Blocked | Allowed | `DraftAssistantPanel.tsx` (admin-only) |
| AI API calls (`POST /api/v1/ai/*`) | 401 (auth required) | Allowed (verified) | Allowed | `v1_ai.php` (check_token_valid + verified) |
| AI Proposal confirm/decline | 401 (auth required) | Allowed (verified) | Allowed | `v1_ai.php` (check_token_valid + verified) |

**Moderator UI status**: OPERATIONAL (admin booking/customer read) + RESTRICTED (room CUD, restore, force-delete)

Moderator now has dedicated SPA access via `/admin/*` route tree (AdminRoute minRole='moderator'). Booking views, customer views, and room read are accessible. Room CUD and booking write operations remain admin-only at both frontend (AdminRoute minRole='admin') and backend (role:admin middleware) layers. At `/dashboard`, moderator still sees GuestDashboard (no change).

For full frontend RBAC documentation, see `docs/frontend/RBAC.md` (Tier 5 source).

---

## Moderator Status Assessment

**Status: OPERATIONAL (admin booking/customer read) + RESTRICTED (room CUD, restore, force-delete)**

From Tier 1-3 evidence only:

1. Moderator role exists in `UserRole` enum (Tier 3 — definition)
2. `BookingPolicy::viewAny()` grants moderator (Tier 1d — policy); policy is LATENT (not invoked), but consistent with route and gate enforcement, which both independently allow moderator
3. Moderator cancel-own is CURRENT but UNRESOLVED INTENT — no documentation specifies whether this is intended behavior or a side effect of the ownership-based policy
4. Moderator read access to `GET /api/v1/admin/bookings*` (A7/A8/A9) is ROUTE-ACCESSIBLE via `role:moderator` middleware + `view-all-bookings` gate — not shared with the `user` role
5. Moderator now has dedicated SPA access via `/admin/*` route tree (AdminRoute minRole='moderator'). Booking views, customer views, and room read are accessible. Room CUD and booking write operations remain admin-only at both frontend (AdminRoute minRole='admin') and backend (role:admin middleware) layers.

---

## Open Follow-Ups

These are NOT resolved by the current batch. Do not close without explicit investigation.

### FU-1: Legacy Cancellation Test Migration

- **Source**: OPUS-VERIFY-01 follow-up 1
- **What**: `BookingCancellationTest` legacy `/api/bookings/` tests still exist alongside new v1 tests
- **Action**: Migrate remaining legacy tests to `/api/v1/bookings/{id}/cancel`
- **Impact**: TEST-SURFACE DRIFT persists for pre-existing cancellation tests
- **Closes**: CONFLICT-NEW-1

### FU-2: Admin Booking Endpoint Coverage Gaps

- **Source**: OPUS-VERIFY-01 follow-up 2
- **What**: `restore-bulk` has no moderator-denial test; `trashed/{id}` has no v1 pin test
- **Action**: Add `test_moderator_cannot_bulk_restore()` for `POST /api/v1/admin/bookings/restore-bulk`; add v1 pin test for `GET /api/v1/admin/bookings/trashed/{id}`
- **Impact**: Rows A9 and A12 remain with coverage gaps

### FU-3: Config Source Verification

- **Source**: OPUS-VERIFY-01 follow-up 3
- **What**: `config('booking.cancellation.allow_after_checkin')` — source file and production value unknown
- **Action**: Verify config file; confirm whether any environment sets this to `true`
- **Impact**: BR-3 severity cannot be confirmed; CONFIG-VARIABLE [SOURCE-UNVERIFIED] until closed

### FU-4: Room CUD Policy Re-verification

- **Source**: OPUS-VERIFY-01 scope gap
- **What**: `RoomController` `$this->authorize()` call-sites not re-verified after hardening batch
- **Action**: Re-inspect `RoomController` post-hardening to confirm policy layer is intact
- **Impact**: Room CUD policy layer carries VERIFICATION-INCOMPLETE for post-hardening batch

### FU-5: Room CREATE/PUT/DELETE Test Migration

- **Source**: OPUS-VERIFY-01 follow-up
- **What**: Room create, PUT, delete auth tests still target legacy `/api/rooms/*` paths
- **Action**: Migrate to `/api/v1/rooms/*` paths
- **Impact**: TEST-SURFACE DRIFT for these operations (rows A1, A2, A4)

---

## Gate Invocation Status

Actual gates defined in `AuthServiceProvider.php` and their invocation status:

| Gate | Definition | Invoked By | Status |
|------|-----------|------------|--------|
| `admin` | `$user->isAdmin()` (EXACT-MATCH) | `AdminBookingController` (all 6 methods) | CURRENT |
| `moderator` | `$user->isModerator()` (HIERARCHY) | Not invoked by any controller | LATENT-UNUSED |
| `manage-users` | `$user->isAdmin()` (EXACT-MATCH) | Not investigated | EVIDENCE-INCOMPLETE |
| `moderate-content` | `$user->isModerator()` (HIERARCHY) | Not invoked by any investigated controller | LATENT-UNUSED |
| `view-all-bookings` | `$user->isModerator()` (HIERARCHY) | `AdminBookingController` (`index`, `trashed`, `showTrashed`) | CURRENT |
| `manage-rooms` | `$user->isAdmin()` (EXACT-MATCH) | Not invoked by any investigated controller | LATENT-UNUSED |
| `view-queue-monitoring` | `$user->isAdmin()` (EXACT-MATCH) | Horizon authorization (not a controller) | EVIDENCE-INCOMPLETE |

---

## Contradiction Resolution Log

| ID | Contradiction | Resolution | Status |
|----|--------------|------------|--------|
| C1 | `BookingPolicy::viewAny()` grants moderator vs route blocks moderator | Route changed to `role:moderator` (v1.php:57); gate changed to `view-all-bookings` (allows `isModerator()`). Both now allow moderator. Policy grant is consistent with route+gate enforcement. | RESOLVED |
| C2 | `RBAC.md` route examples show `role:moderator` on admin booking routes | DOC DRIFT — `v1.php` is canonical Tier 1. RBAC.md updated. | RESOLVED |
| C3 | `POLICIES.md` gate list vs actual AuthServiceProvider | DOC DRIFT — AuthServiceProvider is canonical. POLICIES.md updated. | RESOLVED |
| C4 | Room CUD route comment vs actual enforcement | Moot — route hardening makes comment accurate. | RESOLVED |
| C5 | Moderator role declared but not operationalized | DEFINED-BUT-LATENT — unchanged. No moderator capabilities made CURRENT. | STATUS QUO |
| C7 | Moderator frontend admin access | CURRENT | router.tsx + AdminRoute.tsx | N/A — active path | AdminRoute default minRole='moderator' gates all /admin/* routes; room CUD uses minRole='admin'. Both align with backend enforcement. | 2026-03-29 Wave 2 |
