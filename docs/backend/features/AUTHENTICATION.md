# 🔐 Authentication

> Dual-mode authentication system with Bearer Token and HttpOnly Cookie support

## Overview

Soleil Hostel supports two authentication modes:

| Mode            | Use Case          | Security             | Storage         |
| --------------- | ----------------- | -------------------- | --------------- |
| Bearer Token    | Mobile apps, SPAs | Good                 | localStorage    |
| HttpOnly Cookie | Web apps          | Excellent (XSS-safe) | HttpOnly cookie |

---

## Token Lifecycle

```
1. Login     → Create token (expires_at = now + 1h hoặc 30 ngày)
2. Use       → Update last_used_at mỗi request
3. Refresh   → Create new token + revoke old token
4. Logout    → Revoke token (set revoked_at)
5. Expired   → Return 401 Unauthorized
```

### Token Types

| Type          | Duration | Use Case            | Config Key                                     |
| ------------- | -------- | ------------------- | ---------------------------------------------- |
| `short_lived` | 60 min   | Normal login        | `sanctum.short_lived_token_expiration_minutes` |
| `long_lived`  | 30 days  | "Remember me" login | `sanctum.long_lived_token_expiration_days`     |

---

## Quick Start

### Bearer Token Flow

```typescript
// 1. Login
const response = await fetch("/api/auth/login-v2", {
  method: "POST",
  headers: { "Content-Type": "application/json" },
  body: JSON.stringify({
    email,
    password,
    remember_me: false, // true = 30 days, false = 1 hour
    device_name: "iPhone 15",
  }),
});
const { token, expires_at, type } = await response.json();

// 2. Store token
localStorage.setItem("token", token);

// 3. Use in requests
fetch("/api/bookings", {
  headers: { Authorization: `Bearer ${token}` },
});

// 4. Refresh token (before expiry)
const refreshResponse = await fetch("/api/auth/refresh", {
  method: "POST",
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
  headers: { "Content-Type": "application/json" },
  body: JSON.stringify({ email, password }),
});

// 2. Save CSRF token (returned in response)
sessionStorage.setItem("csrf_token", response.csrf_token);

// 3. Requests (cookie sent automatically, add CSRF header)
fetch("/api/bookings", {
  method: "POST",
  credentials: "include",
  headers: { "X-XSRF-TOKEN": sessionStorage.getItem("csrf_token") },
});

// 4. Refresh (cookie rotated automatically)
await fetch("/api/auth/refresh-httponly", {
  method: "POST",
  credentials: "include",
});
```

---

## API Endpoints

### Bearer Token Endpoints

| Method | Endpoint                  | Description                          |
| ------ | ------------------------- | ------------------------------------ |
| POST   | `/api/auth/register`      | Register new user                    |
| POST   | `/api/auth/login-v2`      | Login, returns token + expires_at    |
| POST   | `/api/auth/refresh`       | Refresh token (revokes old)          |
| POST   | `/api/auth/logout-v2`     | Revoke current token                 |
| POST   | `/api/auth/logout-all-v2` | Revoke ALL user tokens (all devices) |
| GET    | `/api/auth/me-v2`         | Get current user + token info        |

### HttpOnly Cookie Endpoints

| Method | Endpoint                     | Description                   |
| ------ | ---------------------------- | ----------------------------- |
| POST   | `/api/auth/login-httponly`   | Login, sets HttpOnly cookie   |
| POST   | `/api/auth/refresh-httponly` | Rotate cookie + refresh_count |
| POST   | `/api/auth/logout-httponly`  | Clear HttpOnly cookie         |

---

## Request/Response Examples

### Login Request

```http
POST /api/auth/login-v2
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "password123",
  "remember_me": false,
  "device_name": "Web Browser"
}
```

### Login Response (201 Created)

```json
{
  "message": "Đăng nhập thành công.",
  "token": "abc123xyz789...",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com"
  },
  "expires_at": "2025-12-19T12:00:00+00:00",
  "expires_in_minutes": 60,
  "expires_in_seconds": 3600,
  "type": "short_lived",
  "device_id": "550e8400-e29b-41d4-a716-446655440000"
}
```

### Refresh Response (200 OK)

```json
{
  "message": "Token refreshed thành công.",
  "token": "new_token_xyz...",
  "user": { "id": 1, "name": "John Doe", "email": "john@example.com" },
  "expires_at": "2025-12-19T13:00:00+00:00",
  "expires_in_minutes": 60,
  "type": "short_lived",
  "old_token_status": "revoked"
}
```

---

## Security Features

### Token Model (PersonalAccessToken)

```php
// Key columns
$fillable = [
    'name',           // Device name
    'token',          // SHA-256 hashed token
    'abilities',      // Permissions array
    'expires_at',     // Expiration timestamp
    'revoked_at',     // Revocation timestamp (logout)
    'type',           // 'short_lived' or 'long_lived'
    'device_id',      // UUID per device
    'refresh_count',  // Suspicious activity tracking
];

// Check methods
$token->isExpired();   // expires_at < now
$token->isRevoked();   // revoked_at !== null
$token->isValid();     // !expired && !revoked
```

### Suspicious Activity Detection

```php
// If refresh_count exceeds limit → revoke token
if ($oldToken->refresh_count > config('sanctum.max_refresh_count_per_hour')) {
    $oldToken->revoke();
    return response()->json([
        'message' => 'Phát hiện hoạt động bất thường. Vui lòng login lại.',
        'code' => 'SUSPICIOUS_ACTIVITY',
    ], 401);
}
```

### Single Device Login (Optional)

```php
// config/sanctum.php
'single_device_login' => env('SANCTUM_SINGLE_DEVICE', false),

// When enabled: new login revokes ALL other tokens
PersonalAccessToken::where('tokenable_id', $user->id)
    ->notExpired()->notRevoked()
    ->each(fn($token) => $token->revoke());
```

### Token Scopes

```php
// Query scopes
PersonalAccessToken::notExpired();     // expires_at > now
PersonalAccessToken::notRevoked();     // revoked_at IS NULL
PersonalAccessToken::valid();          // notExpired + notRevoked
PersonalAccessToken::expired();        // expires_at < now (for cleanup)
PersonalAccessToken::ofType('long_lived');
PersonalAccessToken::otherDevices($currentDeviceId);
```

---

## Rate Limiting

| Endpoint | Limit         | Algorithm      | Key     |
| -------- | ------------- | -------------- | ------- |
| Login    | 5 per minute  | Sliding Window | IP      |
| Login    | 20 per hour   | Sliding Window | Email   |
| Refresh  | 10 per minute | Sliding Window | User ID |

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
php artisan test --filter=test_suspicious_activity_revokes_token
```

| Test Category       | Count  |
| ------------------- | ------ |
| Login/Register      | 6      |
| Token Expiration    | 10     |
| Token Refresh       | 4      |
| Multi-device Logout | 3      |
| HttpOnly Cookie     | 3      |
| **Total**           | **26** |
