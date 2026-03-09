# Frontend RBAC — Soleil Hostel

> Role-Based Access Control as implemented in the React 19 frontend.
> Grounded in repo truth as of 2026-03-09. All claims verified against source unless marked otherwise.

---

## OBJECTIVE

Document the frontend's role-based access control model: which roles exist, what each role can see and do, how route guards and navigation interact with roles, and where the frontend's responsibility ends and backend enforcement begins.

This document serves frontend engineers, reviewers, UX contributors, and auditors.

---

## SOURCE-OF-TRUTH

| File / Area | Why It Matters | Confidence |
|---|---|---|
| `frontend/src/shared/types/api.ts:35` | Canonical `User` interface with `role` union type | VERIFIED |
| `frontend/src/features/auth/AuthContext.tsx` | Auth state, login/logout flow, role source | VERIFIED |
| `frontend/src/features/auth/ProtectedRoute.tsx` | Route guard implementation | VERIFIED |
| `frontend/src/pages/DashboardPage.tsx` | Role-based dashboard rendering | VERIFIED |
| `frontend/src/app/router.tsx` | Route tree, layout nesting, guard placement | VERIFIED |
| `frontend/src/features/admin/AdminDashboard.tsx` | Admin-only UI and actions | VERIFIED |
| `frontend/src/features/admin/admin.api.ts` | Admin API endpoints | VERIFIED |
| `frontend/src/features/bookings/GuestDashboard.tsx` | Guest/user dashboard and actions | VERIFIED |
| `frontend/src/shared/lib/api.ts` | Axios interceptors, 401 handling, CSRF | VERIFIED |
| `frontend/src/shared/components/layout/Header.tsx` | Navigation visibility | VERIFIED |
| `backend/routes/api/v1.php` | Backend middleware enforcement boundary | VERIFIED |
| `docs/backend/features/RBAC.md` | Backend role hierarchy (3-tier) | VERIFIED |

---

## OWNERSHIP MODEL

| Knowledge Type | Canonical Owner | Notes |
|---|---|---|
| Backend role definitions and gates | `docs/backend/features/RBAC.md` | Do not duplicate here |
| Frontend role type | `frontend/src/shared/types/api.ts` | String union on `User.role` |
| Frontend route access rules | This document + `router.tsx` | Route tree is implementation; this doc is the spec |
| Frontend UI visibility rules | This document | Role-to-screen/action mapping |
| Backend API authorization | `backend/routes/api/v1.php` + middleware | Frontend references but does not own |
| Auth session model | `docs/frontend/SERVICES_LAYER.md` + `AuthContext.tsx` | This doc covers RBAC-relevant subset only |

---

## RBAC DOCUMENT

### 1. Purpose and Scope

This document defines how the Soleil Hostel React frontend handles role-based access control. It covers:

- The role model as consumed by the frontend
- Route-level access (what the router allows)
- Screen-level rendering (what each role sees)
- Action-level permissions (what each role can do)
- Navigation visibility (what appears in menus)
- Error/redirect behavior when access is denied
- The boundary between frontend UX gating and backend authorization

**Out of scope**: Backend gate definitions, middleware implementation details, database role storage. See `docs/backend/features/RBAC.md` for those.

**Critical principle**: Hiding UI is not authorization. The frontend controls user experience; the backend enforces authorization. Every admin action protected in the frontend MUST also be protected by backend middleware. The frontend is a convenience layer, not a security boundary.

---

### 2. Canonical Ownership and Boundaries

The frontend does NOT define roles. It consumes them from the backend via `GET /auth/me-httponly`, which returns a `User` object containing a `role` field.

- **Role definition**: Backend (`App\Enums\UserRole`)
- **Role assignment**: Backend (default `user` on registration)
- **Role enforcement**: Backend (middleware `role:admin`, `role:moderator`)
- **Role consumption**: Frontend (`user.role` from AuthContext)
- **UX gating**: Frontend (conditional rendering based on `user.role`)

The frontend MUST NOT:
- Store or cache roles independently of the auth session
- Promote or demote users
- Assume a role grants permissions without backend confirmation on each request

---

### 3. Role Model Overview

The frontend `User` type defines three roles:

```typescript
// frontend/src/shared/types/api.ts
interface User {
  role: 'user' | 'moderator' | 'admin'
  // ...other fields
}
```

| Role | Frontend Scope | Backend Scope (reference only) |
|---|---|---|
| `user` | Own bookings, booking creation, GuestDashboard | Own bookings only |
| `moderator` | **Same as `user` in current frontend** | View all bookings, moderate content, approve reviews |
| `admin` | AdminDashboard, manage all bookings, view contacts | Full system access |

**Current reality**: The frontend implements a **binary model** — `admin` vs. `non-admin`. The `moderator` role exists in the type system but has zero dedicated frontend UI, routes, or logic. A moderator sees the same frontend as a `user`.

---

### 4. Frontend RBAC Principles and Invariants

**INV-1**: ProtectedRoute checks authentication only, never authorization.
- Source: `frontend/src/features/auth/ProtectedRoute.tsx`
- All role decisions happen inside page components, not at route level.

**INV-2**: Role is derived from `AuthContext.user.role`, never from localStorage/sessionStorage.
- The role travels inside the httpOnly cookie session — the frontend reads it only from the `/auth/me-httponly` response.

**INV-3**: Admin detection uses strict equality: `user?.role === 'admin'`.
- Source: `frontend/src/pages/DashboardPage.tsx`
- No hierarchy check. A moderator does NOT get admin UI.

**INV-4**: Frontend role gating is UX-only, not a security boundary.
- Admin API calls (`/v1/admin/*`) are protected by backend `role:admin` middleware.
- If a non-admin user somehow reaches AdminDashboard (e.g., via devtools), API calls return 403.

**INV-5**: Email verification is an independent guard, orthogonal to role.
- GuestDashboard blocks unverified users with a verification notice.
- Source: `frontend/src/features/bookings/GuestDashboard.tsx`

---

### 5. Auth / Session Model Relevant to Frontend RBAC

**Session lifecycle**:

1. **Login** — `POST /auth/login-httponly` returns `{ user, csrf_token }`
2. **Mount** — If `sessionStorage.csrf_token` exists, call `GET /auth/me-httponly` to restore session
3. **Role availability** — `user.role` is set in AuthContext state after step 1 or 2
4. **Token refresh** — 401 interceptor calls `POST /auth/refresh-httponly`; new csrf_token saved; original request retried
5. **Logout** — `POST /auth/logout-httponly`; sessionStorage cleared; user state nulled

**Role in session**:
- Role is a property of `AuthContext.user`, not stored separately
- Role cannot change during a session unless `me()` is called again
- There is no role-switch or impersonation flow

**Post-login redirect**:
- Always `/dashboard` (or the `?from=` return URL if present)
- No role-based redirect at login time
- DashboardPage handles role routing internally

---

### 6. Route Access Model

#### Route Table

| Path | Layout | Guard | Auth Required | Role Required | Component |
|---|---|---|---|---|---|
| `/` | PublicLayout | None | No | None | HomePage |
| `/login` | Layout | None | No | None | LoginPage |
| `/register` | Layout | None | No | None | RegisterPage |
| `/rooms` | Layout | None | No | None | RoomListPage |
| `/locations` | Layout | None | No | None | LocationListPage |
| `/locations/:slug` | Layout | None | No | None | LocationDetailPage |
| `/booking` | Layout | ProtectedRoute | Yes | None | BookingPage |
| `/dashboard` | Layout | ProtectedRoute | Yes | None (role checked inside) | DashboardPage |
| `*` | Layout | None | No | None | NotFoundPage |

Source: `frontend/src/app/router.tsx`

#### Guard behavior

**ProtectedRoute** (wraps `/booking` and `/dashboard`):
- Loading → spinner
- Not authenticated → `Navigate to /login?from={currentPath}`
- Authenticated → render children

**No RoleGuard component exists.** Role-based rendering is handled inside DashboardPage, not at the router level. This means:
- A `user` can navigate to `/dashboard` and will see GuestDashboard
- An `admin` can navigate to `/dashboard` and will see AdminDashboard
- There is no `/admin/*` route prefix — admin UI lives inside `/dashboard`

---

### 7. Navigation Visibility Model

#### Header (dark, standard layout)

Source: `frontend/src/shared/components/layout/Header.tsx`

| Nav Item | Visible When | Link |
|---|---|---|
| Trang chủ | Always | `/` |
| Phòng | Always | `/rooms` |
| Chi nhánh | Always | `/locations` |
| Đặt phòng | Authenticated | `/booking` |
| Trang quản lý | Authenticated | `/dashboard` |
| Đăng nhập | Not authenticated | `/login` |
| Đăng ký | Not authenticated | `/register` |
| Xin chào, {name} | Authenticated | (display only) |
| Đăng xuất | Authenticated | (button, calls logout) |

**No role-based navigation differentiation.** All authenticated users see the same nav items regardless of role. There is no "Admin" nav section or moderator-specific link.

#### HeaderMobile (public homepage only)

Source: `frontend/src/features/home/components/HeaderMobile.tsx`

Static links: "Xem phong" | "Dang nhap" | "Dang ky". No auth context awareness.

#### BottomNav (public homepage only)

Source: `frontend/src/features/home/components/BottomNav.tsx`

4 tabs: Trang chu | Phong | Dat phong | Tai khoan. No auth or role logic.

---

### 8. Screen Access Matrix

| Screen | Component | user | moderator | admin | Notes |
|---|---|---|---|---|---|
| Home | HomePage | Public | Public | Public | |
| Login | LoginPage | Public | Public | Public | Redirect to `/dashboard` if already authenticated (not implemented — see Gaps) |
| Register | RegisterPage | Public | Public | Public | |
| Room List | RoomListPage | Public | Public | Public | |
| Location List | LocationListPage | Public | Public | Public | |
| Location Detail | LocationDetailPage | Public | Public | Public | |
| Booking Form | BookingPage | Yes | Yes | Yes | Requires auth; email verification enforced by backend |
| Dashboard (guest view) | GuestDashboard | Yes | Yes | No | Rendered when `role !== 'admin'` |
| Dashboard (admin view) | AdminDashboard | No | No | Yes | Rendered when `role === 'admin'` |
| Not Found | NotFoundPage | Public | Public | Public | |

**Key observations**:
- A `moderator` sees GuestDashboard (same as `user`), not AdminDashboard
- An `admin` sees AdminDashboard only; they do NOT see GuestDashboard (their own bookings, if any, are not shown)
- There is no screen exclusively for moderators

---

### 9. Action Permission Matrix

#### GuestDashboard Actions

| Action | Endpoint | user | moderator | admin | UI When Denied | Backend Enforcement |
|---|---|---|---|---|---|---|
| View own bookings | `GET /v1/bookings` | Yes | Yes | N/A (different screen) | — | Auth middleware; returns user's bookings only |
| View booking detail | `GET /v1/bookings/{id}` | Yes | Yes | N/A | — | Ownership check |
| Cancel own booking | `POST /v1/bookings/{id}/cancel` | Yes | Yes | N/A | Confirm dialog | Ownership check; no role middleware |
| Filter by tab (All/Upcoming/Past) | Client-side | Yes | Yes | N/A | — | N/A |

#### AdminDashboard Actions

| Action | Endpoint | user | moderator | admin | UI When Denied | Backend Enforcement |
|---|---|---|---|---|---|---|
| View all bookings | `GET /v1/admin/bookings` | No | No | Yes | Never shown (screen-level gate) | `role:admin` middleware → 403 |
| View trashed bookings | `GET /v1/admin/bookings/trashed` | No | No | Yes | Never shown | `role:admin` middleware → 403 |
| Restore booking | `POST /v1/admin/bookings/{id}/restore` | No | No | Yes | Never shown | `role:admin` middleware → 403 |
| Force-delete booking | `DELETE /v1/admin/bookings/{id}/force` | No | No | Yes | Never shown | `role:admin` middleware → 403 |
| View contact messages | `GET /v1/admin/contact-messages` | No | No | Yes | Never shown | `role:admin` middleware → 403 |
| Confirm booking | `POST /v1/bookings/{id}/confirm` | No | No | Yes | No frontend UI exists | `role:admin` middleware → 403 |

#### Shared Actions (DashboardPage quick actions)

| Action | Link | user | moderator | admin | Notes |
|---|---|---|---|---|---|
| View Rooms | `/rooms` | Yes | Yes | Yes | Same for all roles |
| View Locations | `/locations` | Yes | Yes | Yes | Same for all roles |

#### Booking Form Actions

| Action | Endpoint | user | moderator | admin | Notes |
|---|---|---|---|---|---|
| Create booking | `POST /v1/bookings` | Yes | Yes | Yes | No role restriction; email verification required by backend |

---

### 10. Redirect / Forbidden / Empty / Loading / Error Behavior

| Scenario | Current Behavior | Source |
|---|---|---|
| Unauthenticated → protected route | Redirect to `/login?from={path}` | ProtectedRoute.tsx |
| Auth loading | Spinner (Suspense fallback or inline) | ProtectedRoute.tsx |
| Admin API call by non-admin | API returns 403; component shows generic error state | No explicit 403 handler |
| Direct URL `/dashboard` by user | GuestDashboard renders | DashboardPage.tsx (role check inside) |
| Direct URL `/dashboard` by admin | AdminDashboard renders | DashboardPage.tsx |
| Session expired (401) | Auto-refresh attempt; if fails, redirect to `/login` | api.ts interceptor |
| Email not verified (user) | Inline message: "Email chua duoc xac minh" | GuestDashboard.tsx |
| No bookings | Empty state message | GuestDashboard.tsx / AdminDashboard.tsx |
| Already authenticated → `/login` | **No redirect** (page renders normally) | LoginPage.tsx (UNVERIFIED gap) |
| Role changes server-side mid-session | Frontend stale until page reload or `me()` call | AuthContext.tsx |

**Missing behaviors** (no implementation found):

- No 403 error page or component
- No "access denied" UI for role mismatch
- No redirect from `/login` or `/register` when already authenticated
- No stale-role detection or periodic role refresh

---

### 11. UX/UI Guidance for Role Clarity

**Current patterns**:

1. **Dashboard title changes by role**:
   - Admin: "Bang dieu khien quan tri" (Admin control panel)
   - Non-admin: "Trang quan ly" (Management page)

2. **Dashboard subtitle changes by role**:
   - Admin: "Quan ly he thong tai day." (Manage the system here)
   - Non-admin: "Quan ly dat phong cua ban tai day." (Manage your bookings here)

3. **No role indicator in navigation**: The header greeting "Xin chao, {name}" does not show role. An admin and a user see identical navigation.

**Anti-patterns found**:

1. **Admin has no access to their own bookings**: An admin who books a room cannot view or cancel their own booking via GuestDashboard because DashboardPage renders AdminDashboard exclusively for `role === 'admin'`.

2. **Moderator is invisible**: A moderator has the same frontend experience as a regular user. If backend adds moderator-specific APIs, the frontend has no UI surface for them.

3. **No visual role badge**: There is no badge, icon, or label indicating the user's current role anywhere in the UI. This can cause confusion during testing or for users with multiple accounts.

**Recommendations** (not implemented, for future consideration):

- Show role badge next to username in Header
- Give admin a toggle or tab to view their own bookings
- Plan moderator UI surface if backend moderator features are activated

---

### 12. Frontend vs Backend Enforcement Boundary

```
                     FRONTEND                          BACKEND
                   (UX layer)                      (security layer)

  /login ──────► AuthContext.loginHttpOnly() ───► POST /auth/login-httponly
                     │                                   │
                     ▼                                   ▼
              user.role set                     httpOnly cookie issued
                     │                                   │
                     ▼                                   │
  /dashboard ──► ProtectedRoute (auth only)              │
                     │                                   │
                     ▼                                   │
              DashboardPage                              │
              role === 'admin'?                          │
                /         \                              │
               /           \                             │
    AdminDashboard    GuestDashboard                     │
         │                  │                            │
         ▼                  ▼                            ▼
    GET /v1/admin/*    GET /v1/bookings      role:admin middleware
         │                  │                 validates every request
         ▼                  ▼                            │
    403 if not admin   returns own bookings   ◄──────────┘
```

**Enforcement summary**:

| Check | Frontend | Backend |
|---|---|---|
| Is user authenticated? | ProtectedRoute redirects to `/login` | Auth middleware returns 401 |
| Is user admin? | DashboardPage conditional rendering | `role:admin` middleware returns 403 |
| Is user moderator? | Not checked | `role:moderator` middleware returns 403 |
| Is email verified? | GuestDashboard inline guard | `verified` middleware on booking endpoints |
| Owns this booking? | Not checked (API returns own only) | Scoped query + ownership check |

**The frontend is NOT a trust boundary.** Any client-side role check can be bypassed. Backend middleware is the only enforcement that matters.

---

### 13. Known Gaps / Unverified Areas

| ID | Gap | Severity | Details |
|---|---|---|---|
| G-01 | **No moderator frontend UI** | Medium | Backend defines moderator permissions (view all bookings, moderate content, approve reviews). Frontend has zero moderator-specific logic. Moderators see GuestDashboard. |
| G-02 | **No 403 handling** | Medium | When admin API returns 403 (e.g., non-admin crafts request), the frontend shows a generic error. No user-friendly "access denied" message or redirect. |
| G-03 | **No role-based route guard** | Low | Role enforcement is inside page components, not at router level. Not a security issue (backend enforces), but a UX gap — a user who manipulates client state could briefly see admin UI skeleton before API calls fail. |
| G-04 | **Admin cannot view own bookings** | Low | Admin role renders AdminDashboard exclusively. No path to GuestDashboard for self-service booking management. |
| G-05 | **No role indicator in UI** | Low | No visual cue showing current role. Can cause confusion during testing or multi-account usage. |
| G-06 | **Authenticated user can visit /login** | Low | No redirect from login/register pages when already authenticated. UNVERIFIED — needs LoginPage source confirmation. |
| G-07 | **Stale role after server-side change** | Low | If an admin demotes a user mid-session, the frontend continues showing the old role until page reload or session refresh. No periodic role sync. |
| G-08 | **Booking confirm action has no frontend UI** | Info | `POST /v1/bookings/{id}/confirm` has `role:admin` middleware but no button in AdminDashboard. Backend-only or planned feature. |
| G-09 | **AuthContext.test.tsx uses `role: 'guest'`** | Info | Test fixture uses non-standard role value `'guest'` instead of `'user'`. Not a runtime issue but inconsistent with type definition. |

---

### 14. Maintenance and Validation Notes

**How to validate this document**:

1. Run `npx tsc --noEmit` — confirms `User.role` type is still a 3-value union
2. Read `frontend/src/pages/DashboardPage.tsx` — confirm `user?.role === 'admin'` check
3. Read `frontend/src/features/auth/ProtectedRoute.tsx` — confirm auth-only guard
4. Read `frontend/src/app/router.tsx` — confirm route table matches Section 6
5. Read `frontend/src/shared/components/layout/Header.tsx` — confirm nav visibility matches Section 7
6. Grep `moderator` in `frontend/src/` — confirm zero moderator-specific logic

**When to update this document**:

- New role added to `User.role` type
- New protected route added to router
- Role-based guard added to ProtectedRoute
- Moderator-specific frontend UI implemented
- Admin self-service booking view added
- 403 error handling implemented
- Navigation made role-aware

**Automated checks** (suggested, not yet implemented):

```bash
# Verify no moderator UI drift
grep -r "moderator" frontend/src/ --include="*.tsx" --include="*.ts" | grep -v "types/api.ts" | grep -v ".test."
# Should return 0 results if moderator frontend remains unimplemented

# Verify admin check pattern
grep -rn "role.*admin" frontend/src/ --include="*.tsx" --include="*.ts"
# Should show only DashboardPage and test files
```

**Test coverage for RBAC**:

| Test File | What It Covers | Gaps |
|---|---|---|
| `DashboardPage.test.tsx` | Admin→AdminDashboard, non-admin→GuestDashboard | No moderator-specific test |
| `AdminDashboard.test.tsx` | Admin actions (restore, force-delete, contacts) | Assumes admin; no 403 test |
| `GuestDashboard.test.tsx` | Guest actions (view, cancel, filter), email guard | No role boundary test |
| `AuthContext.test.tsx` | Login/logout/token flow | Uses `role: 'guest'` (incorrect) |

---

## VALIDATION

| Check | Result | Evidence |
|---|---|---|
| Route table matches `router.tsx` | Pass | 9 routes verified against source |
| ProtectedRoute is auth-only | Pass | No role check in component |
| Admin detection is `=== 'admin'` | Pass | `DashboardPage.tsx` line ~12 |
| No moderator frontend logic | Pass | Grep returns 0 non-type results |
| Admin APIs use `role:admin` middleware | Pass | `backend/routes/api/v1.php` lines 57-70 |
| Navigation is not role-aware | Pass | Header.tsx uses `isAuthenticated` only |
| 403 handling exists | **Fail** | No 403 interceptor or error component found |
| Tests cover role routing | Partial | DashboardPage tested; no moderator edge case |

---

## RESIDUAL RISKS

| Risk | Why It Remains | Recommended Follow-Up |
|---|---|---|
| Moderator role is a no-op in frontend | Backend defines capabilities; frontend ignores them. If moderator users exist, they have no access to their intended features via UI. | Implement moderator dashboard or at minimum show "view all bookings" for moderators |
| No 403 error boundary | A malicious or misconfigured client hitting admin endpoints gets raw error instead of graceful UX | Add 403 interceptor in `api.ts` that shows a toast or redirects |
| Brief admin UI flash for non-admins | Client-side role mutation could show AdminDashboard skeleton before API calls fail with 403 | Add role guard at router level or in ProtectedRoute |
| Admin loses self-service booking view | Admin users who also book rooms cannot manage their own bookings | Add "My Bookings" tab to AdminDashboard or allow role-based dashboard toggle |
| Stale role on demotion | No mechanism to detect server-side role change mid-session | Add periodic `me()` refresh or check role on navigation |
