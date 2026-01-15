# Auth Endpoint Migration Guide

> Migration guide for consolidating to unified auth endpoints (January 2026)

## Overview

This guide helps clients migrate from legacy/mode-specific auth endpoints to the new unified endpoints.

---

## Timeline

| Phase           | Date                | Action                                      |
| --------------- | ------------------- | ------------------------------------------- |
| **Deprecation** | January 2026        | Legacy endpoints return deprecation headers |
| **Monitoring**  | January - June 2026 | Track migration progress via logs           |
| **Sunset**      | July 2026           | Legacy endpoints return 410 Gone            |
| **Removal**     | October 2026        | Legacy endpoints removed from codebase      |

---

## Endpoint Mapping

### Legacy → Current Mapping

| Legacy Endpoint          | Replacement                       | Notes                 |
| ------------------------ | --------------------------------- | --------------------- |
| `POST /api/auth/login`   | `POST /api/auth/login-v2`         | Bearer mode           |
| `POST /api/auth/login`   | `POST /api/auth/login-httponly`   | Cookie mode           |
| `POST /api/auth/logout`  | `POST /api/auth/logout-v2`        | Bearer mode           |
| `POST /api/auth/logout`  | `POST /api/auth/logout-httponly`  | Cookie mode           |
| `POST /api/auth/logout`  | `POST /api/auth/unified/logout`   | **NEW** Mode-agnostic |
| `POST /api/auth/refresh` | `POST /api/auth/refresh-v2`       | Bearer mode           |
| `POST /api/auth/refresh` | `POST /api/auth/refresh-httponly` | Cookie mode           |
| `GET /api/auth/me`       | `GET /api/auth/me-v2`             | Bearer mode           |
| `GET /api/auth/me`       | `GET /api/auth/me-httponly`       | Cookie mode           |
| `GET /api/auth/me`       | `GET /api/auth/unified/me`        | **NEW** Mode-agnostic |

### New Unified Endpoints (Recommended for New Clients)

| Endpoint                            | Description            | Detects Mode    |
| ----------------------------------- | ---------------------- | --------------- |
| `GET /api/auth/unified/me`          | Get current user       | Cookie > Bearer |
| `POST /api/auth/unified/logout`     | Logout current session | Cookie > Bearer |
| `POST /api/auth/unified/logout-all` | Logout all devices     | Cookie > Bearer |

---

## Response Shape Changes

### Login Response Comparison

**Legacy `/api/auth/login`:**

```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": { "id": 1, "name": "John", "email": "john@example.com" },
    "access_token": "1|abcdef...",
    "token_type": "Bearer"
  }
}
```

**Current `/api/auth/login-v2`:**

```json
{
  "message": "Đăng nhập thành công.",
  "token": "abcdef...",
  "user": { "id": 1, "name": "John", "email": "john@example.com" },
  "expires_at": "2026-01-15T13:00:00+00:00",
  "expires_in_minutes": 60,
  "expires_in_seconds": 3600,
  "type": "short_lived",
  "device_id": "550e8400-e29b-41d4-a716-446655440000"
}
```

**Key Changes:**

- Token now at root level as `token` (not `data.access_token`)
- Added `expires_at`, `expires_in_minutes`, `type` for proactive refresh
- Added `device_id` for multi-device tracking

---

## Code Migration Examples

### JavaScript/TypeScript (Bearer Mode)

```typescript
// BEFORE (Legacy)
const response = await fetch("/api/auth/login", {
  method: "POST",
  headers: { "Content-Type": "application/json" },
  body: JSON.stringify({ email, password }),
});
const { data } = await response.json();
const token = data.access_token;
localStorage.setItem("token", token);

// API calls
fetch("/api/bookings", {
  headers: { Authorization: token }, // Missing "Bearer " prefix
});

// AFTER (v2)
const response = await fetch("/api/auth/login-v2", {
  method: "POST",
  headers: { "Content-Type": "application/json" },
  body: JSON.stringify({
    email,
    password,
    remember_me: false, // NEW: explicit short/long lived
    device_name: "Web Browser", // NEW: device identification
  }),
});
const { token, expires_at, type } = await response.json();
localStorage.setItem("token", token);
localStorage.setItem("token_expires_at", expires_at);

// API calls - note the "Bearer " prefix
fetch("/api/bookings", {
  headers: { Authorization: `Bearer ${token}` },
});

// Proactive refresh before expiry
if (new Date(expires_at) < new Date(Date.now() + 5 * 60 * 1000)) {
  await refreshToken();
}
```

### JavaScript/TypeScript (HttpOnly Cookie Mode)

```typescript
// HttpOnly mode - token in cookie, not accessible to JS
const response = await fetch("/api/auth/login-httponly", {
  method: "POST",
  credentials: "include", // IMPORTANT: send/receive cookies
  headers: { "Content-Type": "application/json" },
  body: JSON.stringify({ email, password, remember_me: false }),
});
const { csrf_token, expires_at } = await response.json();

// Store CSRF token for subsequent requests
sessionStorage.setItem("csrf_token", csrf_token);

// API calls - cookie sent automatically, add CSRF header
fetch("/api/bookings", {
  method: "POST",
  credentials: "include",
  headers: {
    "Content-Type": "application/json",
    "X-XSRF-TOKEN": sessionStorage.getItem("csrf_token"),
  },
  body: JSON.stringify(bookingData),
});
```

### Using Unified Endpoints (Recommended for Multi-Mode Support)

```typescript
// Unified endpoints auto-detect auth mode
// Works with both Bearer header AND HttpOnly cookie

// Get current user - works regardless of auth mode
const meResponse = await fetch("/api/auth/unified/me", {
  credentials: "include", // Include cookies if present
  headers: authMode === "bearer" ? { Authorization: `Bearer ${token}` } : {},
});

// Logout - works regardless of auth mode
await fetch("/api/auth/unified/logout", {
  method: "POST",
  credentials: "include",
  headers: authMode === "bearer" ? { Authorization: `Bearer ${token}` } : {},
});

// Logout all devices - NEW unified endpoint
await fetch("/api/auth/unified/logout-all", {
  method: "POST",
  credentials: "include",
  headers: authMode === "bearer" ? { Authorization: `Bearer ${token}` } : {},
});
```

---

## cURL Examples

### Bearer Token Flow

```bash
# Login (v2)
curl -X POST http://localhost:8000/api/auth/login-v2 \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"password123","remember_me":false}'

# Response: {"message":"...","token":"abc123","expires_at":"...","type":"short_lived"}

# Get user info
curl http://localhost:8000/api/auth/me-v2 \
  -H "Authorization: Bearer abc123"

# Refresh token
curl -X POST http://localhost:8000/api/auth/refresh-v2 \
  -H "Authorization: Bearer abc123"

# Logout
curl -X POST http://localhost:8000/api/auth/logout-v2 \
  -H "Authorization: Bearer abc123"

# Logout all devices
curl -X POST http://localhost:8000/api/auth/logout-all-v2 \
  -H "Authorization: Bearer abc123"
```

### HttpOnly Cookie Flow

```bash
# Login (cookie set in response)
curl -X POST http://localhost:8000/api/auth/login-httponly \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"password123"}' \
  -c cookies.txt

# Get user info (cookie sent automatically)
curl http://localhost:8000/api/auth/me-httponly \
  -b cookies.txt

# Refresh token
curl -X POST http://localhost:8000/api/auth/refresh-httponly \
  -b cookies.txt \
  -c cookies.txt

# Logout
curl -X POST http://localhost:8000/api/auth/logout-httponly \
  -b cookies.txt
```

### Unified Endpoints

```bash
# Get user (auto-detects mode)
curl http://localhost:8000/api/auth/unified/me \
  -H "Authorization: Bearer abc123"
# OR
curl http://localhost:8000/api/auth/unified/me \
  -b cookies.txt

# Logout all devices (works for both modes)
curl -X POST http://localhost:8000/api/auth/unified/logout-all \
  -H "Authorization: Bearer abc123"
# OR
curl -X POST http://localhost:8000/api/auth/unified/logout-all \
  -b cookies.txt
```

---

## Deprecation Headers

When using legacy endpoints, responses include deprecation headers:

```http
HTTP/1.1 200 OK
Deprecation: Thu, 15 Jan 2026 12:00:00 GMT
Sunset: Wed, 01 Jul 2026 00:00:00 GMT
Link: </api/auth/login-v2>; rel="successor-version"
X-Deprecation-Notice: This endpoint is deprecated and will be removed on 2026-07-01. Use /api/auth/login-v2 instead.

{"success":true,"data":{...}}
```

**Client Action Required:**

1. Monitor logs/alerts for these headers
2. Update code to use successor endpoint
3. Test thoroughly before sunset date

---

## Checklist for Migration

- [ ] Identify which auth mode your application uses (Bearer/Cookie)
- [ ] Update login endpoint to v2 variant
- [ ] Update token storage to handle new response shape
- [ ] Add "Bearer " prefix to Authorization header if missing
- [ ] Implement proactive token refresh using `expires_at`
- [ ] Update logout endpoint to v2 variant
- [ ] Update me endpoint to v2 variant (or use unified)
- [ ] Consider migrating to unified endpoints for simpler code
- [ ] Test full auth flow in staging environment
- [ ] Remove deprecated endpoint usage before sunset date

---

## Support

For migration assistance:

- GitHub Issues: https://github.com/taucao-ruby/soleil-hostel/issues
- Documentation: [AUTHENTICATION.md](./backend/features/AUTHENTICATION.md)
- API Reference: [API_DEPRECATION.md](./API_DEPRECATION.md)
