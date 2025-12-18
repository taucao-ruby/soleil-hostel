# 🔐 Authentication

> Dual-mode authentication system with Bearer Token and HttpOnly Cookie support

## Overview

Soleil Hostel supports two authentication modes:

| Mode            | Use Case          | Security             | Storage         |
| --------------- | ----------------- | -------------------- | --------------- |
| Bearer Token    | Mobile apps, SPAs | Good                 | localStorage    |
| HttpOnly Cookie | Web apps          | Excellent (XSS-safe) | HttpOnly cookie |

---

## Quick Start

### Bearer Token Flow

```typescript
// 1. Login
const response = await fetch("/api/auth/login", {
  method: "POST",
  body: JSON.stringify({ email, password }),
});
const { token } = await response.json();

// 2. Store token
localStorage.setItem("token", token);

// 3. Use in requests
fetch("/api/bookings", {
  headers: { Authorization: `Bearer ${token}` },
});

// 4. Refresh token (before expiry)
const refreshResponse = await fetch("/api/auth/refresh", {
  headers: { Authorization: `Bearer ${token}` },
});
const { token: newToken } = await refreshResponse.json();
```

### HttpOnly Cookie Flow

```typescript
// 1. Login (cookie set automatically)
await fetch("/api/auth/login-httponly", {
  method: "POST",
  credentials: "include",
  body: JSON.stringify({ email, password }),
});

// 2. Requests (cookie sent automatically)
fetch("/api/bookings", { credentials: "include" });

// 3. Refresh (cookie rotated automatically)
await fetch("/api/auth/refresh-httponly", {
  method: "POST",
  credentials: "include",
});
```

---

## API Endpoints

### Bearer Token

| Method | Endpoint               | Description          |
| ------ | ---------------------- | -------------------- |
| POST   | `/api/auth/register`   | Register new user    |
| POST   | `/api/auth/login`      | Login, returns token |
| POST   | `/api/auth/refresh`    | Refresh token        |
| POST   | `/api/auth/logout`     | Revoke current token |
| POST   | `/api/auth/logout-all` | Revoke all tokens    |
| GET    | `/api/auth/me`         | Get current user     |

### HttpOnly Cookie

| Method | Endpoint                     | Description        |
| ------ | ---------------------------- | ------------------ |
| POST   | `/api/auth/login-httponly`   | Login, sets cookie |
| POST   | `/api/auth/refresh-httponly` | Rotate cookie      |
| POST   | `/api/auth/logout-httponly`  | Clear cookie       |
| GET    | `/api/auth/csrf-token`       | Get CSRF token     |

---

## Token Expiration

| Token Type   | Default TTL | Configurable                  |
| ------------ | ----------- | ----------------------------- |
| Access Token | 60 minutes  | `SANCTUM_TOKEN_EXPIRATION`    |
| Remember Me  | 30 days     | `SANCTUM_REMEMBER_EXPIRATION` |

---

## Security Features

### Token Revocation

```php
// Logout current device
$request->user()->currentAccessToken()->delete();

// Logout all devices
$request->user()->tokens()->delete();
```

### Suspicious Activity Detection

- Excessive refresh attempts trigger token revocation
- Token bound to specific user (cannot be reused)

### CSRF Protection (HttpOnly mode)

```typescript
// Get CSRF token
const csrfResponse = await fetch("/api/auth/csrf-token");
const { csrf_token } = await csrfResponse.json();

// Include in mutations
fetch("/api/bookings", {
  method: "POST",
  headers: { "X-XSRF-TOKEN": csrf_token },
  credentials: "include",
  body: JSON.stringify(data),
});
```

---

## Response Format

### Login Success

```json
{
  "token": "1|abc123...",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "role": "user"
  },
  "expires_at": "2025-12-18T11:00:00Z"
}
```

### Login Failure

```json
{
  "message": "Invalid credentials"
}
```

---

## Rate Limiting

| Endpoint | Limit                 | Window  |
| -------- | --------------------- | ------- |
| Login    | 5 per minute per IP   | Sliding |
| Login    | 20 per hour per email | Sliding |
| Refresh  | 10 per minute         | Sliding |

---

## Tests

```bash
# Run all auth tests
php artisan test tests/Feature/Auth/
php artisan test tests/Feature/HttpOnlyCookieAuthenticationTest.php
php artisan test tests/Feature/TokenExpirationTest.php

# Specific tests
php artisan test --filter=test_login_success_with_valid_credentials
php artisan test --filter=test_expired_token_returns_401
```

| Test Category    | Count  |
| ---------------- | ------ |
| Login/Logout     | 8      |
| Token Expiration | 6      |
| Token Refresh    | 4      |
| Multi-device     | 3      |
| HttpOnly Cookie  | 5      |
| **Total**        | **26** |
