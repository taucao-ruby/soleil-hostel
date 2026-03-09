# RBAC UX/UI AUDIT — Soleil Hostel Frontend

> Audit date: 2026-03-09 | Branch: `dev` | Commit: `99cb0a3`
> Auditor: Claude Opus 4.6 (source inspection)

---

## OBJECTIVE

This audit examines the frontend role-based access control (RBAC) implementation of the Soleil Hostel booking system across all three declared roles (user, moderator, admin). The audit is conducted in **SOURCE_ONLY** mode — all findings are grounded in source file inspection; no live browser or API client was used. Runtime-dependent claims are explicitly marked UNVERIFIED or BLOCKED. The audit covers: route access, role-branching logic, navigation visibility, action permissions, session lifecycle, moderator gap analysis, and accessibility on role-gated surfaces. It explicitly excludes backend business logic beyond the route middleware boundary.

---

## EXECUTION CONTEXT DECLARATION

- **mode**: SOURCE_ONLY
- **credentials available**: none
- **devtools available**: N/A
- **runtime notes**: No live frontend available. All runtime flow claims (redirect behavior, API response handling, visual rendering) are INFERRED from source. Devtools mutation tests are BLOCKED.

---

## SOURCE-OF-TRUTH

| # | File | Canonical Concern | Hypothesis Impact | Key Finding | Confidence |
|---|------|-------------------|-------------------|-------------|------------|
| 1 | `AuthContext.tsx` → `loginHttpOnly()`, `logoutHttpOnly()`, `useEffect` mount | auth, session | CONFIRMS: HttpOnly cookie auth; CONFIRMS: logout clears both cookie + sessionStorage | Login: POST `/auth/login-httponly` → sets user + csrf_token. Logout: POST `/auth/logout-httponly` → `setUser(null)` + `clearCsrfToken()`. Mount: checks `sessionStorage.csrf_token` → GET `/auth/me-httponly`. | VERIFIED |
| 2 | `ProtectedRoute.tsx` → `ProtectedRoute` | routing | CONFIRMS: auth-only, no role check | Destructures `{ isAuthenticated, loading }` only. Returns `<Navigate to="/login">` if not authenticated. No `user.role` reference. | VERIFIED |
| 3 | `api.ts` → request/response interceptors | session, auth | CONFIRMS: no 403 handler; CONFIRMS: `withCredentials: true` | Request interceptor: adds `X-XSRF-TOKEN` for non-GET. Response interceptor: handles 401 → refresh → retry → redirect-to-login. Line 178: all non-401 errors fall through to `Promise.reject(error)`. No 403 branch exists. | VERIFIED |
| 4 | `router.tsx` → `createBrowserRouter` | routing | CONFIRMS: full route tree matches hypothesis; EXPANDS: PublicLayout wraps only `/` with HeaderMobile + BottomNav | `/booking` and `/dashboard` wrapped in `ProtectedRoute` + `Suspense`. No role-based route guards. `PublicLayout` renders `HeaderMobile` + `BottomNav` for `/` only. | VERIFIED |
| 5 | `api.ts` (shared types) → `User` interface | type-model | CONFIRMS: `role: 'user' \| 'moderator' \| 'admin'` | Line 35: strict 3-value union type. TypeScript strict mode enforced (tsconfig.app.json line 19). | VERIFIED |
| 6 | `DashboardPage.tsx` → `isAdmin` | role-branching | CONFIRMS: binary `user?.role === 'admin'` check | Line 10: `const isAdmin = user?.role === 'admin'`. Line 38: ternary renders `AdminDashboard` or `GuestDashboard`. No Suspense/useTransition in role-branching path (Suspense is in router.tsx around the entire DashboardPage). | VERIFIED |
| 7 | `LoginPage.tsx` → `handleSubmit` | auth | CONFIRMS: always redirects to `/dashboard`; EXPANDS: no `location.state.from` handling | Line 70: `navigate('/dashboard')` — hardcoded. Does not read `location.state` for return URL. | VERIFIED |
| 8 | `Header.tsx` → `navLinks` | navigation | CONFIRMS: auth-gated only, not role-conditional | Lines 34-39: `/booking` and `/dashboard` appear for all authenticated users. No `user.role` reference in Header. | VERIFIED |
| 9 | `AdminDashboard.tsx` → component root | admin-api | CONFIRMS: 3 tabs, no role check inside component | Calls `fetchAdminBookings`, `fetchTrashedBookings`, `fetchContactMessages`. No `useAuth()` or role check. Assumes caller (DashboardPage) already filtered by role. | VERIFIED |
| 10 | `v1.php` → middleware groups | backend-boundary | CONFIRMS: `role:admin` on admin/* routes; REFUTES: moderator has NO backend route access to admin endpoints | Lines 57-64: `middleware('role:admin')` on all admin/bookings routes. Lines 67-70: `middleware('role:admin')` on admin/contact-messages. No `role:moderator` middleware defined on any route. `isAtLeast()` hierarchy means moderator does NOT pass `role:admin`. | VERIFIED |
| 11 | `tsconfig.app.json` → compilerOptions | build-config | CONFIRMS: `strict: true` | Line 19. No `noUncheckedIndexedAccess` (absent = false). `strictNullChecks` implied by `strict: true`. | VERIFIED |
| 12 | `.env` + `.env.example` | env-config | CONFIRMS: no role/auth VITE_* variables | Only `VITE_API_URL` and `VITE_APP_TITLE`. No `VITE_ADMIN_*`, `VITE_ROLE_*`, or similar. | VERIFIED |
| 13 | `GuestDashboard.tsx` → email verification guard | role-branching | CONFIRMS: inline email verification check | Lines 125-140: if `!user.email_verified_at`, renders amber banner with Vietnamese message. Does not redirect — renders inline. | VERIFIED |
| 14 | `BookingDetailPanel.tsx` → component | role-branching | CONFIRMS: read-only detail panel, no role check, no action buttons | Fetches via `getBookingById()`. Renders detail `<dl>` + close button. No restore/force-delete/cancel actions. No role awareness. | VERIFIED |
| 15 | `ConfirmDialog.tsx` → component | UX | N/A | Reusable dialog with `role="dialog"`, `aria-modal`, `aria-labelledby`, `aria-describedby`, Escape key handler, `autoFocus` on cancel button. | VERIFIED |
| 16 | `HeaderMobile.tsx` | navigation | CONFIRMS: public homepage only, no auth context | Rendered by `PublicLayout` which wraps only `/`. Static links: /rooms, /login, /register. No `useAuth()`. | VERIFIED |
| 17 | `BottomNav.tsx` | navigation | CONFIRMS: public homepage only, local state, no routing | Rendered by `PublicLayout`. Uses `useState` for tab selection. No `useNavigate()`, no auth context. | VERIFIED |
| 18 | `BookingForm.tsx` | role-branching | CONFIRMS: no role check; URL param initialization | Behind `ProtectedRoute` in router. No `useAuth()` for role. Reads `room_id`, `check_in`, `check_out`, `guests` from URL params. | INFERRED |

---

## INVARIANTS

| # | Invariant | Type | Source | Confidence | Status |
|---|-----------|------|--------|------------|--------|
| INV-1 | ProtectedRoute checks only `isAuthenticated`, never `user.role` | RBAC | `ProtectedRoute.tsx` → line 24: `{ isAuthenticated, loading }` | VERIFIED | HOLDS |
| INV-2 | DashboardPage uses strict equality `user?.role === 'admin'` — binary, not hierarchical | RBAC | `DashboardPage.tsx` → line 10 | VERIFIED | HOLDS |
| INV-3 | All admin API endpoints require `role:admin` middleware (moderator cannot pass) | RBAC | `v1.php` → lines 57, 67: `middleware('role:admin')` + `EnsureUserHasRole.php` → `isAtLeast()` | VERIFIED | HOLDS |
| INV-4 | Frontend Axios has no 403 response handler — all non-401 errors reject generically | auth | `api.ts` → line 178: `return Promise.reject(error)` | VERIFIED | HOLDS |
| INV-5 | Logout clears both HttpOnly cookie (server-side POST) and sessionStorage CSRF token | session | `AuthContext.tsx` → `logoutHttpOnly()`: POST `/auth/logout-httponly` + `clearCsrfToken()` | VERIFIED | HOLDS |
| INV-6 | `withCredentials: true` is set on the shared Axios instance | auth | `api.ts` → line 45 | VERIFIED | HOLDS |
| INV-7 | Navigation links are auth-gated but never role-gated | UX | `Header.tsx` → lines 38-39: spread conditional on `isAuthenticated` only | VERIFIED | HOLDS |
| INV-8 | TypeScript strict mode is enabled — role type narrowing is compile-time safe | build | `tsconfig.app.json` → line 19: `"strict": true` | VERIFIED | HOLDS |
| INV-9 | PublicLayout (HeaderMobile + BottomNav) renders only on `/` route | routing | `router.tsx` → PublicLayout children: `[{ path: '/' }]` | VERIFIED | HOLDS |
| INV-10 | `POST /v1/bookings` requires `check_token_valid` + `verified` — email verification enforced at backend | RBAC | `v1.php` → line 42-43 | VERIFIED | HOLDS |

---

## ROLE MATRIX

### User (guest traveler)

| Aspect | Detail |
|--------|--------|
| **Intended capability scope** | Own bookings only; create, view, cancel own bookings |
| **Implemented frontend scope** | GuestDashboard (own bookings list, filter tabs, cancel with confirm dialog, booking detail panel); BookingForm (create booking); Header shows /booking + /dashboard when authenticated |
| **Observed runtime scope** | BLOCKED — no runtime |
| **Intent → implementation delta** | None detected — frontend correctly scopes user to own bookings |
| **Intent → runtime delta** | BLOCKED |
| **First destination after login** | `/dashboard` (hardcoded in LoginPage line 70) |
| **Main navigation items visible** | Trang chủ, Phòng, Chi nhánh, Đặt phòng, Bảng điều khiển |
| **Mobile experience** | Header.tsx has mobile hamburger menu; dashboard uses `p-8` padding which may be generous on small screens but not layout-breaking |
| **Confidence** | VERIFIED (source) |

### Moderator (hostel staff)

| Aspect | Detail |
|--------|--------|
| **Intended capability scope** | View all bookings, moderate content, approve reviews (per backend RBAC docs) |
| **Implemented frontend scope** | IDENTICAL to user — GuestDashboard renders (DashboardPage line 10: `'moderator' !== 'admin'` → else branch) |
| **Observed runtime scope** | BLOCKED — no runtime |
| **Intent → implementation delta** | **SIGNIFICANT**: Backend defines moderator as level 2 with expanded capabilities, but frontend provides zero moderator-specific UI. Moderator sees GuestDashboard with "Quản lý đặt phòng của bạn" ("manage your bookings") — a guest-context message for a staff member. |
| **Intent → runtime delta** | BLOCKED |
| **First destination after login** | `/dashboard` → GuestDashboard |
| **Main navigation items visible** | Same as user — no staff/moderator indicators |
| **Mobile experience** | Same guest dashboard — inappropriate context for staff operational tasks |
| **Confidence** | VERIFIED (source) |

### Admin (system manager)

| Aspect | Detail |
|--------|--------|
| **Intended capability scope** | Manage all bookings, restore soft-deleted, force-delete, view contact messages, manage users/rooms |
| **Implemented frontend scope** | AdminDashboard (3 tabs: Bookings/Trashed/Contacts); restore + force-delete actions on trashed bookings; pagination on active bookings; contact message list. No user management UI. No room management UI. |
| **Observed runtime scope** | BLOCKED — no runtime |
| **Intent → implementation delta** | Partial: admin has booking management + contacts UI but no user management or room management frontend surfaces (backend endpoints exist for rooms at `/v1/rooms` POST/PUT/DELETE) |
| **Intent → runtime delta** | BLOCKED |
| **First destination after login** | `/dashboard` → AdminDashboard |
| **Main navigation items visible** | Same as user — no admin indicators in nav |
| **Mobile experience** | AdminDashboard tab buttons are `px-4 py-1.5` rounded-full — reasonable touch targets; card layout is single-column |
| **Confidence** | VERIFIED (source) |

---

## SCREEN INVENTORY

| # | Screen / Route | Classification | Nav Visible | User | Moderator | Admin | Notes |
|---|----------------|----------------|-------------|------|-----------|-------|-------|
| 1 | `/` (HomePage) | PUBLIC | Yes (always) | Yes | Yes | Yes | PublicLayout with HeaderMobile + BottomNav |
| 2 | `/login` (LoginPage) | PUBLIC | No (not in navLinks) | Yes | Yes | Yes | No redirect-away for authenticated users — see G-06 |
| 3 | `/register` (RegisterPage) | PUBLIC | No (not in navLinks) | Yes | Yes | Yes | Same as login — no authenticated redirect |
| 4 | `/rooms` (RoomListPage) | PUBLIC | Yes (always) | Yes | Yes | Yes | |
| 5 | `/locations` (LocationListPage) | PUBLIC | Yes (always) | Yes | Yes | Yes | |
| 6 | `/locations/:slug` (LocationDetailPage) | PUBLIC | No | Yes | Yes | Yes | |
| 7 | `/booking` (BookingForm) | SHARED_AUTH | Yes (auth) | Yes | Yes | Yes | ProtectedRoute; no role check |
| 8 | `/dashboard` (DashboardPage) | SHARED_AUTH | Yes (auth) | GuestDashboard | GuestDashboard | AdminDashboard | Role-branching inside component |
| 9 | `*` (NotFoundPage) | PUBLIC | N/A | Yes | Yes | Yes | |

---

## SUBSURFACE INVENTORY

### AdminDashboard tabs

| Parent | Subsurface | Actions | User | Moderator | Admin | Role Check | Backend Enforcement | A11y | Evidence |
|--------|-----------|---------|------|-----------|-------|------------|---------------------|------|----------|
| `/dashboard` | Bookings tab | View list, paginate | No (DashboardPage gates) | No (DashboardPage gates) | Yes | DashboardPage line 10 (UI-only) | `role:admin` on GET `/v1/admin/bookings` | `role="tabpanel"`, `aria-label="Đặt phòng"` | AdminDashboard.tsx:403-425 |
| `/dashboard` | Trashed tab | View list, restore, force-delete | No | No | Yes | DashboardPage line 10 (UI-only) | `role:admin` on all trashed endpoints | `role="tabpanel"`, `aria-label="Đã xóa"` | AdminDashboard.tsx:428-451 |
| `/dashboard` | Contacts tab | View list | No | No | Yes | DashboardPage line 10 (UI-only) | `role:admin` on GET `/v1/admin/contact-messages` | `role="tabpanel"`, `aria-label="Liên hệ"` | AdminDashboard.tsx:454-471 |

### AdminDashboard destructive actions

| Parent | Subsurface | Actions | User | Moderator | Admin | Role Check | Backend | A11y | Evidence |
|--------|-----------|---------|------|-----------|-------|------------|---------|------|----------|
| Trashed tab | Restore button | POST `/v1/admin/bookings/{id}/restore` | No | No | Yes | None in component — parent gates | `role:admin` | `variant="outline"`, no explicit `aria-label` | AdminBookingCard:272-279 |
| Trashed tab | Force-delete button | Opens ConfirmDialog → DELETE `/v1/admin/bookings/{id}/force` | No | No | Yes | None in component — parent gates | `role:admin` | `variant="danger"`, no explicit `aria-label` | AdminBookingCard:281-289 |
| Trashed tab | Force-delete dialog | Confirm/cancel | No | No | Yes | None — follows button visibility | `role:admin` | `role="dialog"`, `aria-modal`, `aria-labelledby`, `aria-describedby`, Escape key, `autoFocus` on cancel | ConfirmDialog.tsx:38-66 |

### GuestDashboard surfaces

| Parent | Subsurface | Actions | User | Moderator | Admin | Role Check | Backend | A11y | Evidence |
|--------|-----------|---------|------|-----------|-------|------------|---------|------|----------|
| `/dashboard` | Email verification guard | Blocks unverified users inline | Yes | Yes | No (admin gets AdminDashboard) | Checks `user.email_verified_at` | `verified` middleware on booking endpoints | Semantic amber banner | GuestDashboard.tsx:125-140 |
| `/dashboard` | Filter tabs (All/Upcoming/Past) | Filter booking list | Yes | Yes | No | None needed — own bookings only | Auth-only | `role="tablist"`, `role="tab"`, `aria-selected` | GuestDashboard.tsx:149-165 |
| `/dashboard` | Cancel button | Opens ConfirmDialog → POST `/v1/bookings/{id}/cancel` | Yes (if `canCancel`) | Yes (if `canCancel`) | No | `booking.canCancel` conditional render | `check_token_valid` + `verified` (no role) | `aria-label="Hủy đặt phòng #${id}"` | GuestDashboard.tsx:53-62 |
| `/dashboard` | Cancel dialog | Confirm/cancel | Yes | Yes | No | Follows button visibility | Same as cancel endpoint | Full dialog a11y (reuses ConfirmDialog) | GuestDashboard.tsx:223-232 |
| `/dashboard` | BookingDetailPanel | View booking detail, close | Yes | Yes | No | None — read-only | Auth-only (own booking) | `role="dialog"`, `aria-modal`, `aria-labelledby`, Escape key | BookingDetailPanel.tsx:179-223 |

### Header nav items

| Parent | Subsurface | Actions | User | Moderator | Admin | Role Check | Evidence |
|--------|-----------|---------|------|-----------|-------|------------|----------|
| All routes (Layout) | "Đặt phòng" link | Navigate to /booking | Yes | Yes | Yes | `isAuthenticated` only | Header.tsx:38 |
| All routes (Layout) | "Bảng điều khiển" link | Navigate to /dashboard | Yes | Yes | Yes | `isAuthenticated` only | Header.tsx:39 |
| All routes (Layout) | "Đăng xuất" button | Logout | Yes | Yes | Yes | `isAuthenticated` only | Header.tsx:90-92 |
| All routes (Layout) | Mobile menu button | Toggle nav | All | All | All | None | `aria-label="Mở menu"` — Header.tsx:110 |

### BookingForm accessibility

| Parent | Subsurface | A11y Feature | Evidence |
|--------|-----------|--------------|----------|
| `/booking` | Room select | `aria-required`, `aria-invalid`, `aria-describedby` linked to error `<p>` | BookingForm.tsx (agent report) |
| `/booking` | Date inputs | `aria-required`, `aria-invalid`, `aria-describedby` | BookingForm.tsx (agent report) |
| `/booking` | Submit button | `aria-busy={loading}`, spinner `aria-hidden="true"` | BookingForm.tsx (agent report) |
| `/booking` | Form-level | `noValidate` on `<form>`, custom validation, `<label htmlFor>` on all fields | BookingForm.tsx (agent report) |

---

## KNOWN GAP VERIFICATION

### G-01: No moderator frontend UI

- **Prior statement**: Moderator has zero dedicated frontend UI — treated identically to user
- **Current status**: VERIFIED
- **Evidence**: `DashboardPage.tsx` line 10: `user?.role === 'admin'` — moderator falls to else branch (GuestDashboard). No component, route, or conditional in the entire frontend references `'moderator'` for UI branching.
- **Updated severity**: MEDIUM (same)
- **New information**: Backend also has NO `role:moderator` routes — all admin/* routes use `role:admin`. Moderator cannot even access the data via API that the missing UI would display. The gap is symmetric: neither frontend nor backend exposes moderator-specific surfaces.

### G-02: No 403 handling

- **Prior statement**: No 403 handling — generic error shown
- **Current status**: VERIFIED
- **Evidence**: `api.ts` line 178: `return Promise.reject(error)` for all non-401 errors. AdminDashboard catches errors generically (line 54-58: sets `isError: true`). GuestDashboard similarly catches generically. No toast or redirect on 403.
- **Updated severity**: MEDIUM (same) — mitigated by backend enforcement, but UX is poor if a non-admin somehow reaches AdminDashboard
- **New information**: The 403 would only surface if role state is manipulated client-side or desynchronized — DashboardPage gates UI correctly, so 403 is an edge case under normal operation.

### G-03: No role guard at router level

- **Prior statement**: No role-based route guard
- **Current status**: VERIFIED
- **Evidence**: `ProtectedRoute.tsx` lines 23-47: only checks `isAuthenticated`. `router.tsx`: no `AdminRoute` or `RoleRoute` component exists.
- **Updated severity**: LOW (same) — DashboardPage handles role branching internally; backend enforces
- **New information**: None

### G-04: Admin cannot view own bookings

- **Prior statement**: Admin has no path to view or cancel own bookings
- **Current status**: VERIFIED
- **Evidence**: `DashboardPage.tsx` line 38: `isAdmin ? <AdminDashboard /> : <GuestDashboard />` — admin always gets AdminDashboard. AdminDashboard shows all-bookings admin view, not own-bookings. No "my bookings" tab or link exists for admin. Admin CAN navigate to `/booking` to create a booking (BookingForm has no role check).
- **Updated severity**: LOW (same)
- **New information**: Admin can CREATE bookings (BookingForm accessible), but cannot view or cancel their own bookings through any frontend surface. Backend GET `/v1/bookings` (own bookings) and POST `/v1/bookings/{id}/cancel` are accessible to admin (auth-only + verified), but no UI calls them for admin role.

### G-05: No role indicator in UI

- **Prior statement**: No role badge/indicator in UI
- **Current status**: VERIFIED
- **Evidence**: `Header.tsx` line 89: displays `Xin chào, {user?.name}` — name only, no role. `DashboardPage.tsx` lines 29-30: heading text differs ("Bảng điều khiển quản trị" vs "Trang quản lý") but no explicit role badge.
- **Updated severity**: LOW (same)
- **New information**: DashboardPage heading copy implicitly indicates role context, which partially mitigates this. But Header shows no role indicator on any page.

### G-06: Authenticated user can visit /login

- **Prior statement**: Authenticated user can visit /login (was UNVERIFIED)
- **Current status**: VERIFIED
- **Evidence**: `LoginPage.tsx`: no `useAuth()` check for redirect-away. Component renders form regardless of auth state. `router.tsx`: `/login` has no guard preventing authenticated access.
- **Updated severity**: LOW (same)
- **New information**: LoginPage also does NOT honor `location.state.from` for return URL — see NEW-01. Successful login always goes to `/dashboard` regardless of where the user came from.

### G-07: Stale role after server-side change

- **Prior statement**: Stale role after server-side role change
- **Current status**: VERIFIED
- **Evidence**: `AuthContext.tsx`: role is set once at login (line 164) or mount (line 91). `me()` function (line 274) can re-fetch, but nothing calls it periodically or on route changes. No WebSocket or polling for role changes.
- **Updated severity**: LOW (same)
- **New information**: The only way to refresh role is: (a) page reload triggers mount effect, (b) 401 → refresh cycle re-fetches. Neither is triggered by a backend role change event.

### G-08: No frontend UI for POST /v1/bookings/{id}/confirm

- **Prior statement**: Booking confirm action has no frontend UI
- **Current status**: VERIFIED
- **Evidence**: No component in `frontend/src/` calls a confirm endpoint. AdminDashboard renders booking list cards but no "confirm" button. `admin.api.ts` does not export a `confirmBooking` function. Backend: `POST /v1/bookings/{booking}/confirm` exists with `role:admin` middleware (v1.php line 51-52).
- **Updated severity**: INFO (same)
- **New information**: None

### G-09: AuthContext.test.tsx uses role: 'guest'

- **Prior statement**: AuthContext.test.tsx uses `role: 'guest'` (invalid)
- **Current status**: VERIFIED — and expanded
- **Evidence**: `AuthContext.test.tsx` lines 78, 123, 182: `role: 'guest'`. `DashboardPage.test.tsx` line 53: `role: 'guest'`, line 79: `role: 'staff'`. Neither `'guest'` nor `'staff'` exist in `User.role` type (`'user' | 'moderator' | 'admin'`).
- **Updated severity**: Escalated to LOW — two test files use invalid roles, increasing risk of false-passing tests
- **New information**: DashboardPage.test.tsx also uses `'staff'` role (line 79). TypeScript strict mode should catch this at `tsc --noEmit` — UNVERIFIED whether tests use a looser type assertion that bypasses the check.

---

## ROLE-PLAY FINDINGS

### ROLE 1 — USER (guest traveler)

| # | Screen / Action | Intended | Implemented | Runtime | Result | Severity | Gap Ref |
|---|-----------------|----------|-------------|---------|--------|----------|---------|
| U-1 | `/login` form | Clear login form with Vietnamese labels | Form with email/password, Vietnamese labels ("Địa chỉ email", "Mật khẩu"), "Ghi nhớ đăng nhập trong 30 ngày", error/success states, link to register | BLOCKED | PASS | — | — |
| U-2 | Login success redirect | Redirect to dashboard | `navigate('/dashboard')` after 500ms | BLOCKED | PASS (functional but see NEW-01) | LOW | NEW-01 |
| U-3 | Invalid credentials | Clear error without credential leakage | `authError` displayed in red banner. Error message comes from backend `response.data.message` — content depends on backend (no frontend credential leakage) | BLOCKED | PASS | — | — |
| U-4 | `/dashboard` landing | Guest-appropriate booking management context | Heading: "Trang quản lý", subtitle: "Quản lý đặt phòng của bạn tại đây." GuestDashboard with filter tabs. Quick action links below. | BLOCKED | PASS | — | — |
| U-5 | Booking list + filters | All/Upcoming/Past tabs with booking cards | Three tabs with `role="tablist"`, `aria-selected`. Filter via `useMemo` on date. | BLOCKED | PASS | — | — |
| U-6 | Cancel booking | Confirm dialog, success toast | ConfirmDialog with "Hủy đặt phòng" title, irreversibility warning, Vietnamese labels. Success: `showToast.success('Đã hủy đặt phòng thành công.')`. Error: `showToast.error(getErrorMessage(...))`. | BLOCKED | PASS | — | — |
| U-7 | `/booking` form | Pre-fills from URL params, Vietnamese form | URL params: `room_id`, `check_in`, `check_out`, `guests`. All form fields with proper `<label>`, `aria-required`, `aria-invalid`. Price summary in VND. | BLOCKED | PASS | — | — |
| U-8 | User → admin endpoint | 403 from backend | No frontend guard; backend returns 403. Frontend has no 403 handler — generic error state. | BLOCKED | INFERRED PASS (backend enforces) | MEDIUM | cross-ref: G-02 |
| U-9 | Devtools: set role='admin' | Should not grant real access | BLOCKED — devtools unavailable | BLOCKED | — | — | — |
| U-10 | Logout | Clear both auth paths, redirect to `/` | `logoutHttpOnly()`: POST `/auth/logout-httponly` (server revokes + clears cookie) → `setUser(null)` + `clearCsrfToken()`. Header `handleLogout`: `navigate('/')`. | BLOCKED | PASS | — | — |
| U-11 | Session expired mid-flow | 401 → refresh → retry → login redirect | `api.ts` interceptor: 401 → POST `/auth/refresh-httponly` → update csrf → retry. If refresh fails: clear storage + `appNavigate('/login')`. | BLOCKED | PASS (source logic sound) | — | — |

### ROLE 2 — MODERATOR (hostel staff)

**Three-layer analysis (mandatory):**

| # | Screen / Action | Intended | Implemented | Runtime | Result | Severity | Gap Ref |
|---|-----------------|----------|-------------|---------|--------|----------|---------|
| M-1 | Login + redirect | Staff-appropriate landing | Same as user — `/dashboard` → GuestDashboard. `'moderator' !== 'admin'` → else branch. | BLOCKED | FAIL | MEDIUM | cross-ref: G-01 |
| M-2 | Dashboard copy | Staff-appropriate context | "Trang quản lý" + "Quản lý đặt phòng của bạn tại đây." — guest wording ("your bookings") for a staff member | BLOCKED | FAIL | MEDIUM | cross-ref: G-01 |
| M-3 | View all bookings | Should see all bookings per backend RBAC docs | No frontend surface. GuestDashboard calls `/v1/bookings` (own bookings only). | BLOCKED | FAIL | MEDIUM | cross-ref: G-01 |
| M-4 | Moderator → admin endpoints | Backend should block destructive actions | Backend: `role:admin` on admin/* routes. `isAtLeast(ADMIN)` = level 3 required. Moderator = level 2. Result: 403. | BLOCKED | PASS (backend enforces correctly) | — | — |
| M-5 | Moderator → admin read endpoints | Backend hypothesis: moderator can read admin bookings | REFUTED. `v1.php` line 57: `middleware('role:admin')` on all admin/bookings routes including GET. Moderator gets 403 even for read. | BLOCKED | N/A — hypothesis refuted | — | — |
| M-6 | Devtools: set role='admin' | Should not grant API access | BLOCKED | BLOCKED | — | — | — |

### ROLE 3 — ADMIN (system manager)

| # | Screen / Action | Intended | Implemented | Runtime | Result | Severity | Gap Ref |
|---|-----------------|----------|-------------|---------|--------|----------|---------|
| A-1 | Login + redirect | Admin management landing | `/dashboard` → `isAdmin` → AdminDashboard. Heading: "Bảng điều khiển quản trị". | BLOCKED | PASS | — | — |
| A-2 | Bookings tab | All bookings with pagination | `fetchAdminBookings(page)` with Previous/Next pagination. Vietnamese labels ("Trang X / Y", "Trước", "Sau"). | BLOCKED | PASS | — | — |
| A-3 | Trashed tab | Restore + force-delete | Restore: outline button → `restoreBooking(id)` → toast. Force-delete: danger button → ConfirmDialog ("Xóa vĩnh viễn đặt phòng?") with irreversibility warning → `forceDeleteBooking(id)` → toast. | BLOCKED | PASS | — | — |
| A-4 | Contacts tab | View contact messages | `fetchContactMessages()`. Contact cards with unread badge ("Mới"), subject, message preview (`line-clamp-2`). | BLOCKED | PASS | — | — |
| A-5 | Admin own bookings | Admin should be able to view/cancel own bookings | No path. AdminDashboard shows system bookings, no "my bookings" view. | BLOCKED | FAIL | LOW | cross-ref: G-04 |
| A-6 | Admin → /booking | Admin can create bookings | BookingForm has no role check, accessible to all authenticated users. Backend POST `/v1/bookings` has no role restriction. | BLOCKED | PASS | — | — |
| A-7 | Tab pagination isolation | Per-tab page state | Bookings and Trashed use separate `useAdminPaginatedFetch` instances with independent `page` state. Switching tabs does not cross-contaminate. | N/A | PASS | — | — |

---

## LOGIN FINDINGS

### All Roles (shared login flow)

| Aspect | Finding | Evidence | Severity |
|--------|---------|----------|----------|
| **Login entry** | `/login` → `LoginPage` | router.tsx |  |
| **Form quality** | Vietnamese labels: "Địa chỉ email", "Mật khẩu". CTA: "Đăng nhập". Remember me: "Ghi nhớ đăng nhập trong 30 ngày". Error state: red banner with backend message. Placeholder: "you@example.com" (English). | LoginPage.tsx:99-235 | — |
| **Success path** | `loginHttpOnly()` → `setUser(response.data.data.user)` + `setCsrfToken()` → 500ms delay → `navigate('/dashboard')` | LoginPage.tsx:63-71 | — |
| **Failure path** | `authError` from AuthContext displayed in red banner. Message from backend (`error?.response?.data?.message \|\| 'Đăng nhập thất bại'`). No credential leakage. | AuthContext.tsx:174, LoginPage.tsx:116-120 | — |
| **Post-login destination** | Always `/dashboard` — appropriate for user and admin (DashboardPage branches). Inappropriate context for moderator (gets GuestDashboard). | LoginPage.tsx:70 | MEDIUM (moderator) |
| **Logout behavior** | POST `/auth/logout-httponly` (server revokes token + clears cookie) → `setUser(null)` + `clearCsrfToken()` + `navigate('/')`. Even if API fails, local state cleared. | AuthContext.tsx:246-264, Header.tsx:22-29 | — |
| **Session restore** | Mount effect: checks `sessionStorage.csrf_token` → GET `/auth/me-httponly` → sets user or clears on 401 | AuthContext.tsx:70-130 | — |
| **Session expiry** | 401 → refresh → retry → redirect-to-login if refresh fails. Clears both sessionStorage and localStorage auth keys. | api.ts:92-174 | — |
| **Return URL** | NOT HONORED. LoginPage does not read `location.state.from`. ProtectedRoute passes `state={{ from: location.pathname }}` on redirect (line 42), but LoginPage ignores it. | LoginPage.tsx (no `state.from` reference), ProtectedRoute.tsx:42 | LOW (NEW-01) |
| **Dual-auth path** | Login uses HttpOnly cookie path only (`/auth/login-httponly`). No Bearer token created at login. Session restore uses HttpOnly cookie (browser sends automatically with `withCredentials: true`). CSRF token in sessionStorage is supplementary, not an auth token. | AuthContext.tsx:147-182 | — |
| **International guest copy** | Login heading and labels in Vietnamese. Placeholder "you@example.com" is English — mixed language. Error message from backend may be in English or Vietnamese depending on backend locale config. | LoginPage.tsx:99-100, 139 | INFO |

---

## UX/UI FINDINGS

| ID | Role | Screen / Flow | Category | Issue | User Impact | Recommendation | Evidence | Severity |
|----|------|---------------|----------|-------|-------------|----------------|----------|----------|
| UX-01 | all | `/login` | consistency | Placeholder "you@example.com" is English while all other copy is Vietnamese | Minor inconsistency; does not impair comprehension | Change placeholder to Vietnamese example email or remove | LoginPage.tsx:139 | INFO |
| UX-02 | all | `/login` → post-login | navigation | Return URL from ProtectedRoute `state.from` is ignored — user always lands on `/dashboard` regardless of where they were redirected from | User redirected from `/booking` to `/login` loses context; must re-navigate to `/booking` after login | Read `location.state?.from` in LoginPage and use it as redirect target | LoginPage.tsx:70, ProtectedRoute.tsx:42 | LOW |
| UX-03 | moderator | `/dashboard` | hierarchy | Dashboard heading "Trang quản lý" + "Quản lý đặt phòng của bạn tại đây" communicates a guest context to a staff member | Staff member sees personal booking manager instead of operational view; confusion about what they can do | cross-ref: G-01 — see MODERATOR GAP ANALYSIS | DashboardPage.tsx:30-34 | MEDIUM |
| UX-04 | admin | `/dashboard` | discoverability | No "Xác nhận đặt phòng" (confirm booking) action in AdminDashboard despite backend endpoint existing | Admin must use external tool (Postman, CLI) to confirm bookings | cross-ref: G-08 | AdminDashboard.tsx (absent), v1.php:51-52 | INFO |
| UX-05 | all | `/dashboard` quick actions | CTA | Quick action links ("Xem phòng", "Xem chi nhánh") are identical for all roles — no "Đặt phòng" shortcut despite being the primary user task | User must use header nav to find booking CTA; not immediately discoverable from dashboard | Add "Đặt phòng" to quick actions for non-admin roles | DashboardPage.tsx:44-55 | LOW |
| UX-06 | admin | Trashed tab | feedback | Restore and force-delete buttons on AdminBookingCard lack `aria-label` — screen readers announce "Khôi phục" / "Xóa vĩnh viễn" from button text, which is adequate but lacks booking context | Screen reader user cannot distinguish which booking the action applies to without reading surrounding card content | Add `aria-label={`Khôi phục đặt phòng #${booking.id}`}` pattern (GuestDashboard already does this for cancel) | AdminBookingCard:272-289 | LOW |
| UX-07 | all | ProtectedRoute | loading-state | Loading text "Checking authentication..." is English while all other UI copy is Vietnamese | Inconsistent language during auth check | Change to Vietnamese: "Đang kiểm tra xác thực..." | ProtectedRoute.tsx:34 | LOW |
| UX-08 | all | Header mobile menu | focus-management | Mobile menu close does not return focus to hamburger button | Keyboard/screen reader users lose focus position after closing menu | Set focus back to menu button on close | Header.tsx:108, 140 | LOW |

---

## RBAC FINDINGS

| ID | Role | Screen / Action / Endpoint | Issue Type | Intended | Actual | Attack/Confusion Vector | Frontend Enforces | Backend Enforces | Evidence | Severity |
|----|------|---------------------------|-----------|----------|--------|------------------------|-------------------|------------------|----------|----------|
| RBAC-01 | moderator | `/dashboard` | missing-ui-for-capability | Backend RBAC docs describe moderator capabilities (view all bookings, moderate content) | Frontend renders GuestDashboard; backend also blocks moderator from admin endpoints (`role:admin`) | No attack vector — moderator has neither UI nor API access. Confusion: product docs promise capabilities that neither layer delivers. | N/A | Yes (blocks correctly) | DashboardPage.tsx:10, v1.php:57 | MEDIUM |
| RBAC-02 | all | `api.ts` interceptor | hidden-only-not-secure | 403 should be handled gracefully for unauthorized API calls | No 403 handler — error falls through to generic catch. If a role-desynchronized user reaches AdminDashboard, they see "Không thể tải danh sách đặt phòng." without explanation | Devtools role manipulation → AdminDashboard renders → API returns 403 → generic error state (no "insufficient permissions" message) | No | Yes (403 returned) | api.ts:178, AdminDashboard.tsx:54-58 | MEDIUM |
| RBAC-03 | all | `/login` when authenticated | inconsistent-redirect | Authenticated users should not see login form | LoginPage renders regardless of auth state; user could re-login or get confused | No security impact — re-login just refreshes session. UX confusion only. | No | N/A | LoginPage.tsx (no auth check) | LOW |
| RBAC-04 | admin | `/dashboard` → own bookings | underexposure | Admin should be able to manage own bookings | Admin only sees AdminDashboard (system view). No "my bookings" surface. Backend allows GET `/v1/bookings` for admin. | Admin must use separate tool to view own bookings | UI-only gap | Yes (API accessible) | DashboardPage.tsx:38 | LOW |
| RBAC-05 | all | Test fixtures | role-confusion | Tests should use valid role values matching `User.role` type | `DashboardPage.test.tsx` uses `'guest'` (line 53) and `'staff'` (line 79). `AuthContext.test.tsx` uses `'guest'` (lines 78, 123, 182). | Tests pass for wrong reason — any non-`'admin'` string triggers GuestDashboard. If type checking is bypassed in test mocks, invalid roles are never caught. | N/A | N/A | DashboardPage.test.tsx:53,79; AuthContext.test.tsx:78,123,182 | LOW |
| RBAC-06 | all | Room management endpoints | hidden-only-not-secure | Room CRUD should require admin role | Backend `v1.php` lines 33-38: room POST/PUT/PATCH/DELETE use only `check_token_valid` — **no `role:admin` middleware**. Any authenticated user could create/modify/delete rooms via direct API call. | Direct API call from any authenticated user to POST/PUT/DELETE `/v1/rooms` may succeed without admin role check at route level. (May have controller/policy-level check — UNVERIFIED) | No frontend room management UI exists | **Partial — missing `role:admin` on routes; controller-level check UNVERIFIED** | v1.php:33-38 | HIGH |

---

## MODERATOR GAP ANALYSIS

**Three-layer analysis:**

- **Intended product behavior**: Backend RBAC docs (RBAC.md) define moderator at level 2 with capabilities: "View all bookings, moderate content, approve reviews." PRODUCT_GOAL.md lists "RBAC (3 roles: user / moderator / admin)" as complete with 47 tests.

- **Implemented frontend behavior**: `DashboardPage.tsx` line 10: `const isAdmin = user?.role === 'admin'`. Moderator (`'moderator'`) evaluates to `false` → GuestDashboard renders. No component anywhere in `frontend/src/` checks for `'moderator'`. Zero moderator-specific UI surfaces exist.

- **Observed runtime behavior**: BLOCKED — no runtime, no credentials.

- **Intent → implementation gap**: The product claims 3-role RBAC is "complete," but the frontend implements binary admin/non-admin. The moderator role is defined in the type system (`User.role: 'user' | 'moderator' | 'admin'`) and enforced at backend level, but has ZERO frontend differentiation from user.

**Endpoints moderator can call at API level** (VERIFIED against v1.php):

| Endpoint | Middleware | Moderator Access |
|----------|-----------|------------------|
| GET `/v1/admin/bookings` | `role:admin` | **DENIED** (level 2 < level 3) |
| GET `/v1/admin/bookings/trashed` | `role:admin` | **DENIED** |
| POST `/v1/admin/bookings/{id}/restore` | `role:admin` | **DENIED** |
| DELETE `/v1/admin/bookings/{id}/force` | `role:admin` | **DENIED** |
| GET `/v1/admin/contact-messages` | `role:admin` | **DENIED** |
| POST `/v1/bookings/{id}/confirm` | `role:admin` | **DENIED** |
| GET `/v1/bookings` | `check_token_valid` + `verified` | Allowed (own bookings) |
| POST `/v1/bookings/{id}/cancel` | `check_token_valid` + `verified` | Allowed (own bookings, policy check) |

**Endpoints moderator has frontend UI for**: Own bookings via GuestDashboard (identical to user). No moderator-specific UI.

**Security risk — moderator with API client access**: NONE. All admin endpoints use `role:admin`, which rejects moderator (level 2). There is no data accessible to moderator that is hidden only by frontend. The gap is symmetric: neither layer provides moderator-specific access.

**UX risk — wrong dashboard context**: Staff member sees "Quản lý đặt phòng của bạn tại đây" ("Manage your bookings here") — a guest-oriented message. If moderator has no bookings, they see an empty state with no indication of what they should be doing. This creates confusion about their operational role.

**Mobile risk**: GuestDashboard on mobile is already a compact view (filter tabs + booking cards). For a staff member, the guest dashboard provides no operational value — there's nothing useful for them to do on mobile.

**Is this gap intentional deferral or a defect?**: Evidence points to **intentional deferral**. The backend defines the role hierarchy and the frontend type system includes `'moderator'`, but no backend routes actually use `role:moderator`. The backend RBAC middleware supports hierarchy-inclusive checks (`isAtLeast`), but no route leverages it for moderator. This suggests the moderator role is defined as infrastructure for future use, not currently active.

### Resolution Path Options

**Option A — BUILD NOW: Dedicated moderator surface (current sprint)**
- Scope: Create `ModeratorDashboard` component with read-only "All Bookings" view
- Backend prerequisite: Add `role:moderator` middleware to GET `/v1/admin/bookings` and GET `/v1/admin/bookings/trashed` (separate from destructive endpoints which remain `role:admin`)
- Frontend: Add moderator branch in `DashboardPage.tsx`: `isAdmin ? AdminDashboard : isModerator ? ModeratorDashboard : GuestDashboard`
- Constraint: ModeratorDashboard must NOT reuse AdminDashboard — no restore/force-delete actions
- Rationale: Closes the gap completely but requires backend route changes

**Option B — DEFER WITH GUARDRAILS: Document and protect current state (next sprint)**
- Scope: No new UI. Add comment in `DashboardPage.tsx` documenting the intentional moderator→GuestDashboard fallback. Update RBAC.md to explicitly state moderator has no dedicated frontend.
- Optional: Render a "Tính năng quản lý dành cho nhân viên sắp ra mắt" ("Staff management features coming soon") banner when `user.role === 'moderator'` in DashboardPage — prevents silent misdirection.
- Add backend test confirming moderator cannot reach admin-only destructive endpoints (may already exist in 47 RBAC tests).
- Rationale: Acceptable if moderator role is not operationally active. Low cost, preserves status quo safely.

**Option C — STAGED ROLLOUT: Read-only first, moderation tools second**
- Phase 1: Expose read-only "All Bookings" view for moderator using existing backend read endpoints (requires backend `role:moderator` on GET admin/bookings routes).
- Phase 2: Add content moderation tools (approve reviews, moderate content) when backend supports them.
- Constraint: Phase 1 must NOT scaffold admin action buttons (restore, force-delete).
- Rationale: Reduces time-to-useful while avoiding premature surface design.

**Recommended path**: **Option B** (defer with guardrails). Evidence indicates the moderator role is infrastructure-only — no backend routes use `role:moderator`, so building a frontend surface for non-existent API access is premature. Add the moderator-specific banner and documentation to make the gap visible and intentional rather than silent.

---

## NO-ACTION AREAS

| Area | Reason No Change Justified | Confidence |
|------|---------------------------|------------|
| ProtectedRoute auth-only guard | Correctly prevents unauthenticated access. Role branching in DashboardPage is the intended pattern — adding role guards at route level would be redundant given backend enforcement. | VERIFIED |
| Logout dual-path clearing | `logoutHttpOnly()` correctly POSTs to server (revokes token + clears cookie) AND clears sessionStorage. Even on API failure, local state is cleared. No desync risk. | VERIFIED |
| CSRF token injection | Request interceptor correctly adds `X-XSRF-TOKEN` for POST/PUT/PATCH/DELETE. Token source: `sessionStorage.csrf_token`. | VERIFIED |
| 401 refresh chain | Robust: mutex prevents concurrent refresh, failed queue processing, retry on success, storage clear + login redirect on failure. | VERIFIED |
| AdminDashboard tab accessibility | Correct use of `role="tablist"`, `role="tab"`, `aria-selected`, `role="tabpanel"`, `aria-label` | VERIFIED |
| GuestDashboard cancel flow | ConfirmDialog with proper a11y, Escape key support, `autoFocus` on cancel button, Vietnamese copy, pending state handling | VERIFIED |
| BookingDetailPanel | Read-only detail view with proper dialog a11y, Escape key, backdrop click, AbortController cleanup | VERIFIED |
| ConfirmDialog component | Reusable with `role="dialog"`, `aria-modal`, `aria-labelledby`, `aria-describedby`, `autoFocus` on cancel, pending-aware Escape handler | VERIFIED |
| PublicLayout scoping | HeaderMobile + BottomNav correctly scoped to `/` only via PublicLayout in router — no leakage to authenticated routes | VERIFIED |
| Vite env var exposure | Only `VITE_API_URL` and `VITE_APP_TITLE` exist — no role, auth, or admin configuration leaked to client bundle | VERIFIED |
| TypeScript strict mode | `strict: true` in tsconfig.app.json ensures compile-time type safety for role narrowing (`user?.role === 'admin'`) | VERIFIED |

---

## REMEDIATION BATCHES

### BATCH-01: Room endpoint authorization gap
- **Priority**: P0
- **Focus**: Backend room CRUD endpoints lack role middleware — any authenticated user may be able to modify rooms
- **Finding IDs**: RBAC-06
- **Files likely involved**: `backend/routes/api/v1.php`; possibly `backend/app/Http/Controllers/RoomController.php`, `backend/app/Policies/RoomPolicy.php`
- **Specific changes needed**: (1) Verify whether `RoomController` or a `RoomPolicy` enforces admin-only at controller level. (2) If not, add `role:admin` middleware to room management routes (lines 33-38 in v1.php). (3) Add tests confirming non-admin cannot create/update/delete rooms.
- **Prerequisite batch**: none
- **Estimated risk**: MEDIUM (if controller already authorizes, route-level is defense-in-depth; if not, this is a real vulnerability)
- **Suggested verification**: `php artisan test --filter=Room` + manual review of RoomController authorize calls

### BATCH-02: 403 error handling + moderator UX guardrail
- **Priority**: P1
- **Focus**: No 403 handling in Axios + moderator silent misdirection
- **Finding IDs**: RBAC-02, G-02, G-01, UX-03
- **Files likely involved**: `frontend/src/shared/lib/api.ts`, `frontend/src/pages/DashboardPage.tsx`
- **Specific changes needed**: (1) Add 403 branch in response interceptor — show toast "Bạn không có quyền truy cập" and optionally redirect to `/dashboard`. (2) Add moderator-specific banner in DashboardPage when `user?.role === 'moderator'` — e.g., "Tính năng quản lý dành cho nhân viên đang được phát triển."
- **Prerequisite batch**: none
- **Estimated risk**: LOW
- **Suggested verification**: `npx tsc --noEmit && npx vitest run`

### BATCH-03: Test fixture role values
- **Priority**: P2
- **Focus**: Test files use invalid role values ('guest', 'staff') that don't match the User type
- **Finding IDs**: RBAC-05, G-09
- **Files likely involved**: `frontend/src/pages/DashboardPage.test.tsx`, `frontend/src/features/auth/AuthContext.test.tsx`
- **Specific changes needed**: Replace `role: 'guest'` with `role: 'user'` and `role: 'staff'` with `role: 'moderator'` in all test fixtures
- **Prerequisite batch**: none
- **Estimated risk**: LOW
- **Suggested verification**: `npx vitest run --reporter=verbose`

### BATCH-04: Login return URL + authenticated redirect
- **Priority**: P2
- **Focus**: Login ignores return URL from ProtectedRoute; authenticated users can access /login
- **Finding IDs**: NEW-01 (UX-02), RBAC-03, G-06
- **Files likely involved**: `frontend/src/features/auth/LoginPage.tsx`
- **Specific changes needed**: (1) Read `location.state?.from` and use as redirect target after successful login (fallback: `/dashboard`). (2) Add early return: if `isAuthenticated`, redirect to `/dashboard`.
- **Prerequisite batch**: none
- **Estimated risk**: LOW
- **Suggested verification**: `npx vitest run` + manual test of login redirect flow

### BATCH-05: Minor UX polish
- **Priority**: P3
- **Focus**: English copy in Vietnamese UI, missing aria-labels, focus management
- **Finding IDs**: UX-01, UX-05, UX-06, UX-07, UX-08
- **Files likely involved**: `frontend/src/features/auth/LoginPage.tsx`, `frontend/src/features/auth/ProtectedRoute.tsx`, `frontend/src/pages/DashboardPage.tsx`, `frontend/src/features/admin/AdminDashboard.tsx`, `frontend/src/shared/components/layout/Header.tsx`
- **Specific changes needed**: (1) ProtectedRoute loading text → Vietnamese. (2) LoginPage placeholder → Vietnamese. (3) AdminBookingCard restore/force-delete buttons → add `aria-label` with booking ID. (4) Quick actions: add "Đặt phòng" link for non-admin. (5) Header: return focus to menu button on mobile menu close.
- **Prerequisite batch**: none
- **Estimated risk**: LOW
- **Suggested verification**: `npx tsc --noEmit && npx vitest run`

---

## VALIDATION

| # | Check | Coverage Type | Method | Result | Blocked By | Evidence |
|---|-------|---------------|--------|--------|------------|----------|
| 1 | ProtectedRoute is auth-only, no role check | invariant | source-inspection | PASS | N/A | ProtectedRoute.tsx:24 |
| 2 | DashboardPage uses `user?.role === 'admin'` binary check | invariant | source-inspection | PASS | N/A | DashboardPage.tsx:10 |
| 3 | All admin routes use `role:admin` middleware | boundary-check | source-inspection | PASS | N/A | v1.php:57,67 |
| 4 | Moderator CAN access admin read endpoints | hypothesis | source-inspection | FAIL (refuted) | N/A | v1.php:57 — `role:admin` blocks moderator |
| 5 | No 403 handling in Axios | invariant | source-inspection | PASS (confirmed absent) | N/A | api.ts:178 |
| 6 | Logout clears both auth paths | invariant | source-inspection | PASS | N/A | AuthContext.tsx:246-264 |
| 7 | LoginPage honors return URL | hypothesis | source-inspection | FAIL (does not) | N/A | LoginPage.tsx — no `state.from` reference |
| 8 | Authenticated user redirected away from /login | hypothesis | source-inspection | FAIL (no redirect) | N/A | LoginPage.tsx — no auth check |
| 9 | HeaderMobile scoped to homepage only | invariant | source-inspection | PASS | N/A | router.tsx — PublicLayout children |
| 10 | BottomNav scoped to homepage only | invariant | source-inspection | PASS | N/A | router.tsx — PublicLayout children |
| 11 | TypeScript strict mode enabled | build-config | source-inspection | PASS | N/A | tsconfig.app.json:19 |
| 12 | No role/auth VITE_* env vars | env-config | source-inspection | PASS | N/A | .env, .env.example |
| 13 | Test fixtures use valid role values | invariant | source-inspection | FAIL | N/A | DashboardPage.test.tsx:53,79; AuthContext.test.tsx:78,123,182 |
| 14 | Devtools role mutation → AdminDashboard renders but API returns 403 | runtime-flow | N/A | BLOCKED | No runtime | — |
| 15 | Email verification enforced on booking creation at backend | boundary-check | source-inspection | PASS | N/A | v1.php:42 — `verified` middleware |
| 16 | POST /v1/bookings/{id}/cancel has no role restriction | boundary-check | source-inspection | PASS (confirmed) | N/A | v1.php:53-54 — no role middleware |
| 17 | Room CRUD endpoints require admin role | boundary-check | source-inspection | FAIL | N/A | v1.php:33-38 — only `check_token_valid`, no `role:admin` |
| 18 | Dual-auth desync: logout clears both Bearer + cookie | invariant | source-inspection | PASS (no Bearer used) | N/A | AuthContext.tsx — HttpOnly-only auth; no Bearer token in frontend |
| 19 | React 19 Suspense race in role-branching | runtime-flow | source-inspection | PASS (no race) | N/A | Suspense wraps entire DashboardPage in router.tsx; role check is synchronous inside DashboardPage after auth loading completes |

---

## RESIDUAL RISKS

| # | Risk | Why Unresolvable This Run | Resolution Action | Recommended Owner | Severity If Unresolved |
|---|------|--------------------------|-------------------|-------------------|----------------------|
| R-1 | Room CRUD endpoints may lack authorization entirely (no `role:admin` at route level; controller/policy-level check UNVERIFIED) | Cannot inspect RoomController authorization logic without deeper backend audit | Inspect `RoomController` methods for `$this->authorize()` or `Gate::authorize()` calls; add `role:admin` middleware to routes if absent | backend | HIGH |
| R-2 | Test fixtures with invalid role values may mask type-safety issues if mock setup bypasses TypeScript checking | Would need to run `tsc --noEmit` against test files to confirm whether TS catches the invalid literals | Run `npx tsc --noEmit` and check for errors in test files; fix invalid role values | frontend | LOW |
| R-3 | Devtools role mutation behavior cannot be verified in source-only mode | No runtime browser available | Test in browser: set `user.role = 'admin'` in React DevTools while logged in as user; verify AdminDashboard renders but all API calls return 403 | frontend | MEDIUM |
| R-4 | CSRF token staleness after long idle session — unclear if refresh cycle updates CSRF | Would need runtime test: idle for token TTL, then submit form | Test: login, wait for token expiry, attempt booking form submission; verify refresh chain fires and updates CSRF | frontend | LOW |
| R-5 | Admin cancel-any-booking scope — `POST /v1/bookings/{id}/cancel` has no role middleware; unclear if policy restricts to own bookings | Would need to inspect `BookingPolicy` or `CancellationService` | Inspect backend cancellation authorization: does it check `$user->id === $booking->user_id`? If admin can cancel any booking, is that intentional? | backend | MEDIUM |

---

## AUDIT CONSTRAINTS CHECKLIST

- [x] Execution context declared explicitly at top of output
- [x] Evidence rule applied throughout — no invented line numbers; file + symbol fallback used
- [x] Devtools mutation tests marked BLOCKED (SOURCE_ONLY context)
- [x] Every finding cites specific file + symbol or runtime observation
- [x] No finding claims backend security from frontend hiding alone
- [x] All preloaded SYSTEM CONTEXT claims marked VERIFIED / REFUTED / UNVERIFIED
- [x] SCREEN INVENTORY covers route-level access only — no action/button detail
- [x] SUBSURFACE INVENTORY covers component/action level — no route-level duplication
- [x] Known gaps G-01 through G-09 each have a row in KNOWN GAP VERIFICATION
- [x] None of G-01 through G-09 re-appear as new findings in other sections (cross-ref used)
- [x] Moderator gap has its own dedicated section with three-layer analysis
- [x] Moderator Gap options A/B/C are distinct by timing/scope strategy
- [x] Three-layer distinction applied to all MEDIUM+ severity findings (moderator role-play)
- [x] Vietnamese copy findings raised only where material to task completion or trust
- [x] International guest copy findings raised only where load-bearing (login placeholder — INFO)
- [x] Mobile/touch-target findings raised only where primary task completion is impaired
- [x] Severity assigned per rubric — criteria matched explicitly
- [x] SUBSURFACE INVENTORY covers: BookingDetailPanel, AdminDashboard tabs, Header nav items, BookingForm accessibility surface, cancel dialog, force-delete dialog
- [x] VALIDATION section includes coverage-type and blocked-by for each row
- [x] Remediation batches ordered P0 → P3; each references canonical finding IDs; no circular prerequisites
- [x] BLOCKED used wherever runtime, credentials, or API access was unavailable
- [x] OUTPUT COMPRESSION RULE applied — signal-first ordering; no MEDIUM+ finding omitted
- [x] FINDING DEDUPLICATION RULE applied — canonical IDs assigned once, cross-referenced elsewhere
- [x] Dual-auth desync scenario inspected — HttpOnly-only auth path, no Bearer used in frontend
- [x] TypeScript strict mode status verified — `strict: true` confirmed
- [x] Vite env var exposure checked — no role/auth VITE_* variables found
- [x] No code changes made during this audit run
