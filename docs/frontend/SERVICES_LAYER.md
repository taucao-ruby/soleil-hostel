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
src/features/booking/booking.api.ts
src/features/rooms/room.api.ts
src/features/locations/location.api.ts
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

export const bookingApi = {
  create: (data) => api.post('/bookings', data),
  getAll: () => api.get('/bookings'),
  getById: (id) => api.get(`/bookings/${id}`),
  update: (id, data) => api.put(`/bookings/${id}`, data),
  cancel: (id) => api.delete(`/bookings/${id}`),
}
```

### Room API (`features/rooms/room.api.ts`)

```typescript
import api from '@/shared/lib/api'

export const roomApi = {
  getAll: () => api.get('/rooms'),
  getById: (id) => api.get(`/rooms/${id}`),
}
```

### Location API (`features/locations/location.api.ts`)

```typescript
import api from '@/shared/lib/api'

export const locationApi = {
  getAll: () => api.get('/locations'),
  getBySlug: (slug) => api.get(`/locations/${slug}`),
}
```

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
