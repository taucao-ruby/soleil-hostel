# API Migration Guide: v1 → v2

> **Last Updated:** February 9, 2026 | **Target Completion:** July 1, 2026

## Overview

Version 2.0 introduces:

- **Unified authentication endpoints** (mode-agnostic, auto-detect Bearer/Cookie)
- **Optimistic locking** for room updates (prevents lost updates)
- **Email verification** required for booking creation
- **Deprecation of legacy auth endpoints** (sunset July 2026)
- **URL-prefixed API versioning** (`/api/v1/...`)

---

## Timeline

| Date                 | Milestone                                     |
| -------------------- | --------------------------------------------- |
| **January 15, 2026** | v2.0 released (backward compatible)           |
| **February 9, 2026** | Documentation & migration guide published     |
| **April 2026**       | Deprecation reminders to active API consumers |
| **July 1, 2026**     | Legacy endpoints removed → return `410 Gone`  |
| **October 2026**     | Legacy code removed from codebase             |

---

## Breaking Changes

### 1. Room Updates Require `lock_version`

Room update requests **must** include `lock_version` to prevent lost updates in concurrent environments.

**Before (v1 — no longer accepted):**

```http
PUT /api/rooms/42
Content-Type: application/json
Authorization: Bearer {token}

{
  "price": 120.00
}
```

**After (v2 — required):**

```http
# Step 1: Fetch current room (note lock_version)
GET /api/v1/rooms/42

# Response:
{
  "success": true,
  "data": {
    "id": 42,
    "name": "Ocean View Double",
    "price": 100.00,
    "lock_version": 5
  }
}

# Step 2: Update with lock_version
PUT /api/v1/rooms/42
Content-Type: application/json
Authorization: Bearer {token}

{
  "price": 120.00,
  "lock_version": 5
}

# Success (200):
{
  "success": true,
  "message": "Room updated successfully",
  "data": {
    "id": 42,
    "price": 120.00,
    "lock_version": 6
  }
}

# Conflict (409) - another user updated first:
{
  "message": "Room data has been modified by another user. Please refresh and try again.",
  "current_version": 7,
  "provided_version": 5
}
```

**Client Implementation with Retry Logic:**

```javascript
/**
 * Update a room with optimistic locking and automatic retry.
 *
 * @param {number} roomId - Room ID to update
 * @param {object} updates - Fields to update (e.g., { price: 120 })
 * @param {number} maxRetries - Max retry attempts on version conflict
 * @returns {Promise<object>} Updated room data
 */
async function updateRoom(roomId, updates, maxRetries = 3) {
  for (let attempt = 0; attempt < maxRetries; attempt++) {
    // Always fetch fresh data to get current lock_version
    const roomRes = await fetch(`/api/v1/rooms/${roomId}`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    const { data: room } = await roomRes.json();

    const response = await fetch(`/api/v1/rooms/${roomId}`, {
      method: "PUT",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${token}`,
      },
      body: JSON.stringify({
        ...updates,
        lock_version: room.lock_version,
      }),
    });

    if (response.status === 409) {
      console.warn(
        `Version conflict on attempt ${attempt + 1}/${maxRetries}. Retrying...`,
      );
      await new Promise((r) => setTimeout(r, 100 * (attempt + 1))); // Backoff
      continue;
    }

    if (!response.ok) {
      throw new Error(`Update failed: ${response.status}`);
    }

    return await response.json();
  }

  throw new Error(
    "Max retries exceeded for room update. Please refresh and try again.",
  );
}

// Usage:
try {
  const result = await updateRoom(42, { price: 120.0 });
  console.log("Updated:", result.data);
} catch (error) {
  alert(error.message);
}
```

### 2. Deprecated Auth Endpoints

| Old Endpoint             | Status        | Replacement                                                          | Sunset Date  |
| ------------------------ | ------------- | -------------------------------------------------------------------- | ------------ |
| `POST /api/auth/login`   | ⚠️ Deprecated | `/api/auth/login-v2` (Bearer) or `/api/auth/login-httponly` (Cookie) | July 1, 2026 |
| `POST /api/auth/logout`  | ⚠️ Deprecated | `/api/auth/logout-v2` (Bearer) or `/api/auth/unified/logout` (auto)  | July 1, 2026 |
| `POST /api/auth/refresh` | ⚠️ Deprecated | `/api/auth/refresh-v2`                                               | July 1, 2026 |
| `GET /api/auth/me`       | ⚠️ Deprecated | `/api/auth/me-v2` (Bearer) or `/api/auth/unified/me` (auto)          | July 1, 2026 |

**Detecting Deprecation:** Deprecated endpoints return RFC 8594 headers:

```http
HTTP/1.1 200 OK
Deprecation: Mon, 15 Jan 2026 00:00:00 GMT
Sunset: Thu, 01 Jul 2026 00:00:00 GMT
Link: </api/auth/login-v2>; rel="successor-version"
X-Deprecation-Notice: This endpoint is deprecated and will be removed on 2026-07-01. Use /api/auth/login-v2 instead.
```

### 3. URL-Prefixed Routes

Legacy root-level routes (`/api/rooms`, `/api/bookings`) now have versioned equivalents:

| Legacy Route                | Versioned Route                | Status            |
| --------------------------- | ------------------------------ | ----------------- |
| `GET /api/rooms`            | `GET /api/v1/rooms`            | Legacy deprecated |
| `POST /api/rooms`           | `POST /api/v1/rooms`           | Legacy deprecated |
| `PUT /api/rooms/{id}`       | `PUT /api/v1/rooms/{id}`       | Legacy deprecated |
| `GET /api/bookings`         | `GET /api/v1/bookings`         | Legacy deprecated |
| `POST /api/bookings`        | `POST /api/v1/bookings`        | Legacy deprecated |
| `GET /api/admin/bookings/*` | `GET /api/v1/admin/bookings/*` | Legacy deprecated |

Legacy routes still work but return deprecation headers. Switch to `/api/v1/...` paths.

---

## Migration Steps

### Step 1: Update Auth Flow

**For Bearer Token clients (mobile apps, SPAs):**

```javascript
// ❌ OLD CODE (deprecated):
const loginRes = await fetch("/api/auth/login", {
  method: "POST",
  headers: { "Content-Type": "application/json" },
  body: JSON.stringify({ email, password }),
});
const {
  data: { access_token },
} = await loginRes.json();
headers["Authorization"] = `Bearer ${access_token}`;

// ✅ NEW CODE:
const loginRes = await fetch("/api/auth/login-v2", {
  method: "POST",
  headers: { "Content-Type": "application/json" },
  body: JSON.stringify({ email, password, remember_me: false }),
});
const { token, expires_at, type } = await loginRes.json();
headers["Authorization"] = `Bearer ${token}`;
```

**For Web Browser clients (HttpOnly cookies):**

```javascript
// ✅ NEW CODE (HttpOnly cookie — recommended for web):
const loginRes = await fetch("/api/auth/login-httponly", {
  method: "POST",
  credentials: "include", // IMPORTANT: sends/receives cookies
  headers: { "Content-Type": "application/json" },
  body: JSON.stringify({ email, password }),
});
// Token is stored in HttpOnly cookie automatically
// No need to manage tokens in JavaScript!

// Subsequent requests — just include credentials:
const rooms = await fetch("/api/v1/rooms", {
  credentials: "include",
});
```

### Step 2: Use Unified Endpoints

Unified endpoints work with **both** Bearer token and HttpOnly cookie — auto-detected:

```javascript
// ✅ Works with Bearer token OR HttpOnly cookie:
const userRes = await fetch("/api/auth/unified/me", {
  headers: token ? { Authorization: `Bearer ${token}` } : {},
  credentials: "include", // For cookie mode
});

// Logout (auto-detects auth mode):
await fetch("/api/auth/unified/logout", {
  method: "POST",
  headers: token ? { Authorization: `Bearer ${token}` } : {},
  credentials: "include",
});

// Logout all devices:
await fetch("/api/auth/unified/logout-all", {
  method: "POST",
  headers: token ? { Authorization: `Bearer ${token}` } : {},
  credentials: "include",
});
```

### Step 3: Handle Optimistic Locking

For any code that updates rooms, add `lock_version`:

```javascript
// ❌ OLD CODE:
await fetch(`/api/rooms/${roomId}`, {
  method: "PUT",
  body: JSON.stringify({ price: 150 }),
});

// ✅ NEW CODE:
// 1. Fetch room to get current lock_version
const room = await fetch(`/api/v1/rooms/${roomId}`).then((r) => r.json());

// 2. Include lock_version in update
const res = await fetch(`/api/v1/rooms/${roomId}`, {
  method: "PUT",
  headers: {
    "Content-Type": "application/json",
    Authorization: `Bearer ${token}`,
  },
  body: JSON.stringify({
    price: 150,
    lock_version: room.data.lock_version,
  }),
});

// 3. Handle 409 Conflict
if (res.status === 409) {
  const error = await res.json();
  alert(`Conflict: ${error.message}. Please refresh.`);
}
```

### Step 4: Implement Email Verification Flow

Booking creation now requires verified email:

```javascript
// 1. Register
await fetch("/api/auth/register", {
  method: "POST",
  body: JSON.stringify({
    name,
    email,
    password,
    password_confirmation: password,
  }),
});

// 2. Check verification status
const status = await fetch("/api/email/verification-status", {
  headers: { Authorization: `Bearer ${token}` },
}).then((r) => r.json());

if (!status.verified) {
  // Show "Please check your email" banner in UI

  // 3. Optionally resend verification
  await fetch("/api/email/verification-notification", {
    method: "POST",
    headers: { Authorization: `Bearer ${token}` },
  });
}

// 4. After verification, bookings work:
const booking = await fetch("/api/v1/bookings", {
  method: "POST",
  headers: {
    "Content-Type": "application/json",
    Authorization: `Bearer ${token}`,
  },
  body: JSON.stringify({
    room_id: 42,
    check_in: "2026-06-01",
    check_out: "2026-06-05",
    guest_name: "Jane",
    guest_email: "jane@example.com",
  }),
});
// Returns 403 if email not verified
```

### Step 5: Update API Base URLs

```javascript
// ❌ OLD:
const API_BASE = "/api";
fetch(`${API_BASE}/rooms`);
fetch(`${API_BASE}/bookings`);

// ✅ NEW:
const API_BASE = "/api/v1";
fetch(`${API_BASE}/rooms`);
fetch(`${API_BASE}/bookings`);
```

---

## Testing Your Migration

### 1. Optimistic Locking (Version Conflict)

```bash
# Terminal 1: Get room, note lock_version
curl -s http://localhost:8000/api/v1/rooms/1 | jq '.data.lock_version'
# Output: 5

# Terminal 1: Update with correct version
curl -X PUT http://localhost:8000/api/v1/rooms/1 \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{"price": 120, "lock_version": 5}'
# Expected: 200 OK, lock_version: 6

# Terminal 2: Update with STALE version
curl -X PUT http://localhost:8000/api/v1/rooms/1 \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{"price": 130, "lock_version": 5}'
# Expected: 409 Conflict
```

### 2. Deprecated Endpoint Headers

```bash
curl -i -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "admin@soleilhostel.com", "password": "password"}'

# Check response headers:
# Deprecation: Mon, 09 Feb 2026 00:00:00 GMT
# Sunset: Thu, 01 Jul 2026 00:00:00 GMT
# Link: </api/auth/login-v2>; rel="successor-version"
# X-Deprecation-Notice: This endpoint is deprecated...
```

### 3. Bearer vs HttpOnly Login

```bash
# Bearer Token:
curl -X POST http://localhost:8000/api/auth/login-v2 \
  -H "Content-Type: application/json" \
  -d '{"email": "admin@soleilhostel.com", "password": "password"}'
# Returns: { "token": "abc123...", "expires_at": "..." }

# HttpOnly Cookie:
curl -X POST http://localhost:8000/api/auth/login-httponly \
  -H "Content-Type: application/json" \
  -c cookies.txt \
  -d '{"email": "admin@soleilhostel.com", "password": "password"}'
# Sets cookie: soleil_token=uuid-here

# Use cookie for subsequent requests:
curl http://localhost:8000/api/auth/unified/me -b cookies.txt
```

### 4. Email Verification Required

```bash
# Create booking without verified email:
curl -X POST http://localhost:8000/api/v1/bookings \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer UNVERIFIED_USER_TOKEN" \
  -d '{"room_id": 1, "check_in": "2026-06-01", "check_out": "2026-06-05", "guest_name": "Jane", "guest_email": "jane@test.com"}'
# Expected: 403 Forbidden
```

---

## Non-Breaking Changes

These changes are additive and require no client modifications:

| Change                  | Description                                                            |
| ----------------------- | ---------------------------------------------------------------------- |
| New unified endpoints   | `/auth/unified/me`, `/auth/unified/logout`, `/auth/unified/logout-all` |
| Token metadata in `/me` | Response now includes `expires_at`, `type`, `device_id`                |
| Booking status enum     | Added `refund_pending`, `refund_failed` states                         |
| Health probe endpoints  | New `/health/live`, `/health/ready`, `/health/full`                    |
| Correlation ID          | Pass `X-Correlation-ID` header for request tracing                     |
| Contact form            | New `POST /contact` endpoint                                           |

---

## Checklist

```markdown
- [ ] Updated auth login to use /auth/login-v2 or /auth/login-httponly
- [ ] Updated /auth/me to use /auth/me-v2 or /auth/unified/me
- [ ] Updated /auth/logout to use /auth/logout-v2 or /auth/unified/logout
- [ ] Added lock_version to all room update requests
- [ ] Added 409 Conflict handling for room updates
- [ ] Switched API paths from /api/rooms to /api/v1/rooms
- [ ] Implemented email verification flow before booking
- [ ] Tested deprecated endpoint headers
- [ ] Removed any hardcoded references to deprecated endpoints
```

---

## Support

- **Questions:** Open issue on GitHub
- **Migration Help:** support@soleilhostel.com
- **OpenAPI Spec:** [docs/api/openapi.yaml](../api/openapi.yaml)
- **Postman Collection:** [backend/postman/Soleil_Hostel_v2.postman_collection.json](../../backend/postman/Soleil_Hostel_v2.postman_collection.json)
- **Changelog:** See [PROJECT_STATUS.md](../../PROJECT_STATUS.md)
