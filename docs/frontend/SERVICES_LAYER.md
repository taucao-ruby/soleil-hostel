# API & Services

> HTTP client, authentication, and CSRF protection

## Overview

There is **no separate `src/services/` directory**. API communication is handled by:

```text
src/shared/lib/
├── api.ts             # Axios client with interceptors (CSRF, token refresh)
└── navigation.ts      # Programmatic navigation outside React tree

src/shared/utils/
├── csrf.ts            # CSRF token get/set/clear helpers
└── security.ts        # XSS sanitization, URL validation

src/features/auth/
└── AuthContext.tsx     # Authentication state (login, register, logout, me)
```

Each feature also has its own API module:

```text
src/features/booking/booking.api.ts    # createBooking, fetchMyBookings, cancelBooking, submitReview
src/features/rooms/room.api.ts         # getRooms
src/features/locations/location.api.ts # getLocations, getLocationBySlug
src/features/admin/admin.api.ts        # fetchAdminBookings, fetchTrashedBookings, fetchContactMessages
src/features/auth/email.api.ts         # sendVerificationCode, verifyCode, getVerificationStatus (OTP flow, since 2026-04-03)
src/features/assistant/assistant.api.ts # discoverRooms, markProposalShown, decideProposal (AI Harness, since 2026-04-09)
```

---

## 1. Shared API Client (`shared/lib/api.ts`)

### Axios Instance

```typescript
const api = axios.create({
  baseURL: BASE_URL,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
  withCredentials: true, // CRITICAL: Enable httpOnly cookie sending
})
```

- `withCredentials: true` enables browser to send httpOnly cookies automatically
- Base URL from `VITE_API_URL` env variable (required in production)

### Request Interceptor - CSRF Protection

```typescript
api.interceptors.request.use((config) => {
  // Only add CSRF token for state-changing requests
  if (['post', 'put', 'patch', 'delete'].includes(config.method)) {
    const csrfToken = getCsrfToken()
    if (csrfToken) {
      config.headers['X-XSRF-TOKEN'] = csrfToken
    }
  }
  return config
})
```

### Response Interceptor - Auto Token Refresh

```typescript
api.interceptors.response.use(
  response => response,
  async (error) => {
    // Handle 401 Unauthorized with automatic token refresh
    if (error.response?.status === 401 && !originalRequest._retry) {
      // Use mutex to prevent concurrent refresh requests
      if (isRefreshing) {
        return new Promise((resolve, reject) => {
          failedQueue.push({ resolve, reject })
        }).then(() => api(originalRequest))
      }

      // POST /auth/refresh-httponly
      // Browser sends httpOnly cookie automatically
      // Backend returns new csrf_token
      const refreshResponse = await api.post('/auth/refresh-httponly')
      setCsrfToken(refreshResponse.data.csrf_token)

      // Retry original request
      return api(originalRequest)
    }
  }
)
```

**Key features:**

- Refresh mutex prevents multiple concurrent refresh requests
- Failed queue retries all queued requests after successful refresh
- Only redirects to login on non-public routes
- Uses `appNavigate()` for SPA navigation (not `window.location.href`)

---

## 2. Navigation Service (`shared/lib/navigation.ts`)

Provides programmatic navigation outside the React component tree (e.g., from API interceptors).

```typescript
let _navigate: NavigateFunction | null = null

export function setNavigate(nav: NavigateFunction): void {
  _navigate = nav
}

export function appNavigate(to: string): void {
  if (_navigate) {
    _navigate(to) // SPA navigation, preserves state
  } else {
    window.location.href = to // Fallback before router init
  }
}
```

Registered by `NavigationSetter` component inside the router tree (see APP_LAYER.md).

---

## 3. CSRF Token Management (`shared/utils/csrf.ts`)

```typescript
export function getCsrfToken(): string | null {
  return sessionStorage.getItem('csrf_token')
}

export function setCsrfToken(token: string): void {
  sessionStorage.setItem('csrf_token', token)
}

export function clearCsrfToken(): void {
  sessionStorage.removeItem('csrf_token')
}
```

- Stored in `sessionStorage` (cleared on browser close)
- Set after login/refresh, cleared on logout
- Added to `X-XSRF-TOKEN` header by request interceptor

---

## 4. Authentication (`features/auth/AuthContext.tsx`)

Auth state is managed via React Context, not a standalone service.

```typescript
interface AuthContextType {
  user: User | null
  isAuthenticated: boolean
  loading: boolean
  error: string | null
  loginHttpOnly: (email: string, password: string, rememberMe?: boolean) => Promise<void>
  registerHttpOnly: (name: string, email: string, password: string, passwordConfirmation: string) => Promise<void>
  logoutHttpOnly: () => Promise<void>
  me: () => Promise<User | null>
  clearError: () => void
}
```

### Authentication Flow

```text
Login:
  POST /auth/login-httponly → Backend sets httpOnly cookie + returns csrf_token
  → setCsrfToken(csrf_token) → setUser(user)

Token Validation (on mount):
  Check sessionStorage for csrf_token → GET /auth/me-httponly
  → Browser sends httpOnly cookie → Backend returns user

Logout:
  POST /auth/logout-httponly → Backend clears cookie
  → clearCsrfToken() → setUser(null)

Auto Refresh (transparent):
  Protected request → 401 → POST /auth/refresh-httponly
  → New cookie + csrf_token → Retry original request
```

---

## 5. Feature API Modules

Each feature has its own API module that uses the shared `api` client:

### Booking API (`features/booking/booking.api.ts`)

```typescript
import api from '@/shared/lib/api'

// POST /v1/bookings — creates a new booking (10/min throttle); backend creates a Stripe payment-hold (Apr 22)
export async function createBooking(data: BookingFormData): Promise<Booking>

// GET /v1/bookings — returns the authenticated user's bookings
export async function fetchMyBookings(signal?: AbortSignal): Promise<BookingApiRaw[]>

// POST /v1/bookings/:id/cancel — cancels a booking (CSRF auto-attached);
// backend captures immutable cancellation actor snapshot + propagates to stays (OPS-004)
export async function cancelBooking(id: number): Promise<CancelBookingResponse>

// POST /v1/reviews — submits a star-rating review for a confirmed past booking
// Errors: 403 (already reviewed) — UI shows "Bạn đã đánh giá rồi"; 422 (validation) — inline errors
export async function submitReview(data: ReviewSubmitData): Promise<ReviewSubmitResponse>
```

### Room API (`features/rooms/room.api.ts`)

```typescript
import api from '@/shared/lib/api'

// GET /v1/rooms — returns all rooms
export async function getRooms(): Promise<Room[]>
```

### Location API (`features/locations/location.api.ts`)

```typescript
import api from '@/shared/lib/api'

// GET /v1/locations — returns all active locations
export async function getLocations(): Promise<Location[]>

// GET /v1/locations/:slug — returns a location with its rooms
// params: check_in, check_out, guests (optional; filters available rooms)
export async function getLocationBySlug(
  slug: string,
  params?: { check_in?: string; check_out?: string; guests?: number }
): Promise<LocationWithRooms>
```

### Admin API (`features/admin/admin.api.ts`)

```typescript
import api from '@/shared/lib/api'

// GET /v1/admin/bookings — paginated admin booking list with 7 filters (moderator+)
//   filters: check_in_start, check_in_end, check_out_start, check_out_end, status, location_id, search (ILIKE)
export async function fetchAdminBookings(signal?: AbortSignal): Promise<AdminBookingRaw[]>

// GET /v1/admin/bookings/trashed — soft-deleted bookings (moderator+)
export async function fetchTrashedBookings(signal?: AbortSignal): Promise<AdminBookingRaw[]>

// GET /v1/admin/contact-messages — contact form messages (ADMIN ONLY since RBAC-001, 2026-04-26)
export async function fetchContactMessages(signal?: AbortSignal): Promise<ContactMessageRaw[]>

// POST /v1/admin/bookings/:id/restore — admin-only; transactional + FOR UPDATE (TOCTOU-safe)
export async function restoreBooking(id: number): Promise<AdminBookingRaw>

// DELETE /v1/admin/bookings/:id/force — admin-only; permanent delete + admin_audit_logs row
export async function forceDeleteBooking(id: number): Promise<void>
```

### Email Verification API (`features/auth/email.api.ts`) — OTP flow since 2026-04-03

```typescript
import api from '@/shared/lib/api'

// POST /api/email/send-code — send 6-digit OTP (race-hardened AUTH-004; cooldown enforced server-side)
export async function sendVerificationCode(): Promise<{ cooldown_remaining_seconds: number }>

// POST /api/email/verify-code — verify the 6-digit code; sets users.email_verified_at on success
//   max_attempts (default 5) enforced; row consumed (consumed_at = now) on success
export async function verifyCode(code: string): Promise<{ verified: true }>

// GET /api/email/verification-status — verification status + remaining cooldown
export async function getVerificationStatus(): Promise<{
  verified: boolean
  email: string
  email_verified_at: string | null
  cooldown_remaining_seconds: number
}>
```

> The legacy `MustVerifyEmail` signed-URL flow (`/api/email/verify/{id}/{hash}`) has been removed. The frontend OTP page (`features/auth/EmailVerifyPage.tsx`) is the only verification surface.

### Assistant API (`features/assistant/assistant.api.ts`) — AI Harness since 2026-04-09

```typescript
import api from '@/shared/lib/api'

// POST /v1/ai/room_discovery — natural-language room discovery (10/min throttle)
//   Returns { content, proposals, citations }; proposals are persisted in ai_proposals
export async function discoverRooms(query: string): Promise<DiscoverResponse>

// POST /v1/ai/proposals/:hash/shown — mark proposal as displayed (idempotent)
//   Required before /decide; missing call → ProposalNotShownException
export async function markProposalShown(hash: string): Promise<void>

// POST /v1/ai/proposals/:hash/decide — confirm/decline proposal (5/min throttle)
//   Server-side: re-validates room availability, price drift (context_version), expiry, shown-before-confirm.
//   Proposer-binding (F-67): cache envelope proposer_user_id MUST equal current user; mismatch → 404
//   Lifecycle errors mapped on the client side:
//     - ProposalNotShownException        → "Đề xuất chưa được hiển thị, vui lòng thử lại"
//     - ProposalExpiredException         → "Đề xuất đã hết hạn"
//     - ProposalPriceChangedException    → "Giá phòng đã thay đổi" (context_version mismatch)
//     - ProposedRoomNoLongerAvailableException → "Phòng vừa được khách khác đặt"
export async function decideProposal(
  hash: string,
  decision: 'confirmed' | 'declined'
): Promise<DecideResponse>
```

> Only `RoomDiscoveryWidget` should call these endpoints. Proposer-binding requires the same authenticated user across discovery → shown → decide.

---

## 6. Security Architecture

### HttpOnly Cookie Authentication

- Server sets `Set-Cookie: token=...; HttpOnly; Secure; SameSite=Strict`
- JavaScript **cannot** read the cookie (XSS protection)
- Browser sends it automatically with every request (`withCredentials: true`)

### CSRF Protection (Double-Submit Pattern)

- `csrf_token` returned in login/refresh response body
- Stored in `sessionStorage`
- Added to `X-XSRF-TOKEN` header on non-GET requests
- Backend validates header matches session

### Token Refresh

- Transparent to the user
- Mutex prevents concurrent refresh attempts
- Failed requests queued and retried after successful refresh
