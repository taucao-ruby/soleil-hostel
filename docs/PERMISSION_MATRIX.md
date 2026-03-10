# Permission Matrix — Soleil Hostel

> **Single source of truth for actor-to-permission mapping.**
> All other RBAC documentation references this file — do not redefine permissions elsewhere.
>
> Last verified: OPUS-01, OPUS-02R, OPUS-03R, OPUS-HARDEN-01, OPUS-VERIFY-01
> Verification status: **PASS WITH FOLLOW-UPS**
> Open follow-ups: **5** — see [Open Follow-Ups](#open-follow-ups)

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
| A7 | `GET /api/v1/admin/bookings` | DENIED-EXPLICIT (401) | DENIED-SIDE-EFFECT (403) | DENIED-SIDE-EFFECT (403) | ALLOWED | Route `role:admin` (v1.php:57) + Gate `admin` (AdminBookingController:41) | HIERARCHY-DEPENDENT + EXACT-MATCH | YES | CONFIRMED-V1 | OPUS-VERIFY-01 |
| A8 | `GET /api/v1/admin/bookings/trashed` | DENIED-EXPLICIT (401) | DENIED-SIDE-EFFECT (403) | DENIED-SIDE-EFFECT (403) | ALLOWED | Route `role:admin` (v1.php:57) + Gate `admin` (AdminBookingController:69) | HIERARCHY-DEPENDENT + EXACT-MATCH | YES | CONFIRMED-V1 | OPUS-VERIFY-01 |
| A9 | `GET /api/v1/admin/bookings/trashed/{id}` | DENIED-EXPLICIT (401) | DENIED-SIDE-EFFECT (403) | DENIED-SIDE-EFFECT (403) | ALLOWED | Route `role:admin` (v1.php:57) + Gate `admin` (AdminBookingController:88) | HIERARCHY-DEPENDENT + EXACT-MATCH | YES | FOLLOW-UP REQUIRED (no v1 pin test) | OPUS-VERIFY-01 |
| A10 | `POST /api/v1/admin/bookings/{b}/restore` | DENIED-EXPLICIT (401) | DENIED-SIDE-EFFECT (403) | DENIED-SIDE-EFFECT (403) | ALLOWED | Route `role:admin` (v1.php:57) + Gate `admin` (AdminBookingController:109) | HIERARCHY-DEPENDENT + EXACT-MATCH | YES | CONFIRMED-V1 | OPUS-VERIFY-01 |
| A11 | `DELETE /api/v1/admin/bookings/{b}/force` | DENIED-EXPLICIT (401) | DENIED-SIDE-EFFECT (403) | DENIED-SIDE-EFFECT (403) | ALLOWED | Route `role:admin` (v1.php:57) + Gate `admin` (AdminBookingController:152) | HIERARCHY-DEPENDENT + EXACT-MATCH | YES | CONFIRMED-V1 | OPUS-VERIFY-01 |
| A12 | `POST /api/v1/admin/bookings/restore-bulk` | DENIED-EXPLICIT (401) | DENIED-SIDE-EFFECT (403) | DENIED-SIDE-EFFECT (403) | ALLOWED | Route `role:admin` (v1.php:57) + Gate `admin` (AdminBookingController:178) | HIERARCHY-DEPENDENT + EXACT-MATCH | YES | FOLLOW-UP REQUIRED (no moderator-denial test) | OPUS-VERIFY-01 |

**Resources not investigated** (out of scope for current batch):
- Booking create/update/delete (user endpoints)
- Booking confirm
- Contact messages (`/api/v1/admin/contact-messages/*`)
- Reviews (`/api/v1/reviews/*`)
- Locations (`/api/v1/locations/*`)
- Auth endpoints (`/api/v1/auth/*`)

**HIERARCHY-DEPENDENT NOTICE**: `role:admin` middleware is enforced via `EnsureUserHasRole` using role hierarchy comparison (`isAtLeast()`), not exact match. Current hierarchy places admin above moderator. If role hierarchy is modified, rows marked HIERARCHY-DEPENDENT may change without code review.

---

## Table B — Data Operation Permissions by Actor

| Operation | Guest | User | Moderator | Admin | Business Rules | Evidence |
|-----------|-------|------|-----------|-------|----------------|----------|
| room:create | DENIED-EXPLICIT | DENIED-SIDE-EFFECT | DENIED-SIDE-EFFECT | CURRENT | — | OPUS-02R |
| room:update | DENIED-EXPLICIT | DENIED-SIDE-EFFECT | DENIED-SIDE-EFFECT | CURRENT | — | OPUS-02R |
| room:delete | DENIED-EXPLICIT | DENIED-SIDE-EFFECT | DENIED-SIDE-EFFECT | CURRENT | — | OPUS-02R |
| booking:cancel-own | DENIED-EXPLICIT | CURRENT | CURRENT (UNRESOLVED INTENT) | CURRENT | BR-1, BR-2, BR-3 | OPUS-03R |
| booking:cancel-any | DENIED-EXPLICIT | DENIED-SIDE-EFFECT | DENIED-SIDE-EFFECT | CURRENT | BR-1 | OPUS-03R |
| booking:view-all (admin) | DENIED-EXPLICIT | DENIED-SIDE-EFFECT | DENIED-SIDE-EFFECT | CURRENT | — | OPUS-VERIFY-01 |
| booking:view-trashed | DENIED-EXPLICIT | DENIED-SIDE-EFFECT | DENIED-SIDE-EFFECT | CURRENT | — | OPUS-VERIFY-01 |
| booking:restore | DENIED-EXPLICIT | DENIED-SIDE-EFFECT | DENIED-SIDE-EFFECT | CURRENT | — | OPUS-VERIFY-01 |
| booking:force-delete | DENIED-EXPLICIT | DENIED-SIDE-EFFECT | DENIED-SIDE-EFFECT | CURRENT | — | OPUS-VERIFY-01 |
| gate:view-all-bookings | N/A | DENIED | WOULD-ALLOW | WOULD-ALLOW | — | LATENT-UNUSED |
| gate:manage-rooms | N/A | DENIED | DENIED | WOULD-ALLOW | — | LATENT-UNUSED |

---

## Table C — Latent Capability Registry

Capabilities that exist in code but are NOT currently enforced in any active execution path.

| # | Capability | Status | Location | Shadowed By | Activation Condition | Evidence |
|---|-----------|--------|----------|-------------|---------------------|----------|
| C1 | `BookingPolicy::viewAny()` moderator grant | LATENT-SHADOWED | BookingPolicy.php:50 | Route `role:admin` (v1.php:57) + `Gate::authorize('admin')` (AdminBookingController) | **Two-failure condition**: both route middleware AND gate authorization must be removed simultaneously. Neither alone is sufficient. | OPUS-01 C1 + OPUS-VERIFY-01 CLAIM-L1 |
| C2 | Gate `view-all-bookings` | LATENT-UNUSED | AuthServiceProvider.php:73 | N/A — never invoked | Any controller calls `Gate::authorize('view-all-bookings')` | OPUS-01 C3 |
| C3 | Gate `manage-rooms` | LATENT-UNUSED | AuthServiceProvider.php:81 | N/A — never invoked | Any controller calls `Gate::authorize('manage-rooms')` | OPUS-01 C3 |
| C4 | Gate `moderate-content` | LATENT-UNUSED | AuthServiceProvider.php:65 | N/A — never invoked | Any controller calls `Gate::authorize('moderate-content')` | OPUS-01 C3 |
| C5 | Moderator cancel-any | NO-PATH-FOUND | — | N/A | No route, controller branch, or policy path grants moderator cancel on others' bookings | OPUS-03R |
| C6 | Moderator admin booking access | DENIED (two layers) + LATENT-SHADOWED (via BookingPolicy::viewAny) | BookingPolicy.php:50, v1.php:57, AdminBookingController | Route `role:admin` + `Gate::authorize('admin')` | Same two-failure condition as C1 | OPUS-VERIFY-01 CLAIM-L1 |

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

| Screen | User | Moderator | Admin | Source |
|--------|------|-----------|-------|--------|
| GuestDashboard | Rendered | Rendered (same as user) | Not rendered | DashboardPage.tsx |
| AdminDashboard | Not rendered | Not rendered | Rendered | DashboardPage.tsx |
| Admin API calls | 403 from backend | 403 from backend | Allowed | v1.php middleware |

**Moderator UI status**: DEFINED-BUT-LATENT. No Tier 1 moderator-specific capability is CURRENT. The frontend implements a binary model (`admin` vs `non-admin`). See Table C for latent capabilities.

For full frontend RBAC documentation, see `docs/frontend/RBAC.md` (Tier 5 source).

---

## Moderator Status Assessment

**Status: DEFINED-BUT-LATENT**

From Tier 1-3 evidence only:

1. Moderator role exists in `UserRole` enum (Tier 3 — definition)
2. `BookingPolicy::viewAny()` grants moderator (Tier 1d — policy) but is LATENT-SHADOWED by two earlier Tier 1 mechanisms (route middleware + gate authorization)
3. Moderator cancel-own is CURRENT but UNRESOLVED INTENT — no documentation specifies whether this is intended behavior or a side effect of the ownership-based policy
4. No distinct moderator capability is CURRENT via a full Tier 1 chain that is (a) not shared with the `user` role and (b) not shadowed by an earlier mechanism

**To move toward PARTIALLY-OPERATIONAL**: A documented, tested, intentional moderator capability must be made CURRENT on a distinct route or policy branch — without relying on DENIED-SIDE-EFFECT or LATENT-SHADOWED mechanisms. This requires a human product decision.

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
| `view-all-bookings` | `$user->isModerator()` (HIERARCHY) | Not invoked by any controller | LATENT-UNUSED |
| `manage-rooms` | `$user->isAdmin()` (EXACT-MATCH) | Not invoked by any investigated controller | LATENT-UNUSED |
| `view-queue-monitoring` | `$user->isAdmin()` (EXACT-MATCH) | Horizon authorization (not a controller) | EVIDENCE-INCOMPLETE |

---

## Contradiction Resolution Log

| ID | Contradiction | Resolution | Status |
|----|--------------|------------|--------|
| C1 | `BookingPolicy::viewAny()` grants moderator vs route blocks moderator | Middleware EFFECTIVE, policy LATENT-SHADOWED. Post-hardening: two-layer shadow (route + gate). | RESOLVED |
| C2 | `RBAC.md` route examples show `role:moderator` on admin booking routes | DOC DRIFT — `v1.php` is canonical Tier 1. RBAC.md updated. | RESOLVED |
| C3 | `POLICIES.md` gate list vs actual AuthServiceProvider | DOC DRIFT — AuthServiceProvider is canonical. POLICIES.md updated. | RESOLVED |
| C4 | Room CUD route comment vs actual enforcement | Moot — route hardening makes comment accurate. | RESOLVED |
| C5 | Moderator role declared but not operationalized | DEFINED-BUT-LATENT — unchanged. No moderator capabilities made CURRENT. | STATUS QUO |
