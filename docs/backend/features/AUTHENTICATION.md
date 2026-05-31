# 🔐 Authentication

> Dual-mode authentication system with Bearer Token and HttpOnly Cookie support
>
> **Last Updated:** May 8, 2026

## Overview

Soleil Hostel supports two authentication modes (dual Sanctum):

| Mode            | Use Case          | Security             | Storage         |
| --------------- | ----------------- | -------------------- | --------------- |
| Bearer Token    | Mobile apps, SPAs | Good                 | localStorage    |
| HttpOnly Cookie | Web apps          | Excellent (XSS-safe) | HttpOnly cookie |

Both modes share `routes/api/v1.php`. Mode is detected at request time by `UnifiedAuthController::detectAuthMode()`, which uses Sanctum's `PersonalAccessToken::findToken()` for Bearer lookup (F-32, commit `4ab9cfd`, 2026-04-27) — the prior bespoke decoder did not handle Sanctum-format tokens correctly, causing valid tokens to fall through to cookie path.

### Recent hardening (Apr–May 2026)

- **Batch-2 Sanctum hardening** (`5e258e7`, 2026-04-25): atomic refresh (`tokenable_id, type` lock window), device-fingerprint binding, fence-post unification across `expires_at`/`revoked_at`/`refresh_count`.
- **Refresh-rate semantics** (2026-05-23): `sanctum.max_token_refreshes_per_hour` is a cache-backed 60-minute limiter for token-session refresh attempts. `refresh_count` is lifetime telemetry only and is not used for hourly enforcement. Expiration and revocation checks remain separate.
- **F-32 unified Bearer detection** (`4ab9cfd`, 2026-04-27): `detectAuthMode()` uses `PersonalAccessToken::findToken()`.
- **AUTH-004 OTP race hardening** (`1079946`, 2026-05-02): `EmailVerificationCodeService::sendCode()` now serializes resend attempts via `Cache::lock("evc:send:{user_id}", 5)`, eliminating the concurrent-double-send race.
- **AI harness kill-switch contract finalized** (`6372d7f`, 2026-05-08): `FeatureFlag::forget()` no longer re-throws Redis exceptions; the local in-process cache is always evicted on Redis outage so the auth-adjacent feature-flag layer cannot serve stale `enabled` values during partial outages.

---

## Form Request Validation

Authentication endpoints use dedicated Form Request classes for validation:

### RegisterRequest

**Location**: `app/Http/Requests/Auth/RegisterRequest.php`

```php
public function rules(): array
{
    return [
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'password' => 'required|string|min:8|confirmed',
    ];
}
```

### LoginRequest

**Location**: `app/Http/Requests/Auth/LoginRequest.php`

```php
public function rules(): array
{
    return [
        'email' => 'required|email',
        'password' => 'required|string',
    ];
}
```

---

## Token Lifecycle

```bash
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
fetch("/api/v1/bookings", {
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
fetch("/api/v1/bookings", {
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

### Email Verification Endpoints (OTP, since 2026-04-03)

| Method | Endpoint                          | Description                                               |
| ------ | --------------------------------- | --------------------------------------------------------- |
| POST   | `/api/email/send-code`            | Send 6-digit OTP code (race-hardened — AUTH-004)          |
| POST   | `/api/email/verify-code`          | Verify OTP code; sets `email_verified_at`                 |
| GET    | `/api/email/verification-status`  | Check verification status + cooldown                      |

> **The legacy signed-URL `/api/email/verify/{id}/{hash}` flow has been removed.** Verification is now a 6-digit OTP. Migration: 2026-04-03 (`2026_04_03_084257_create_email_verification_codes_table`); race-hardened: 2026-05-02 (`1079946`).

### Unified mode-agnostic endpoints

For frontends that need to remain agnostic about Bearer vs Cookie mode (e.g. the React SPA after login-mode flip):

| Method | Endpoint                          | Description                                          |
| ------ | --------------------------------- | ---------------------------------------------------- |
| GET    | `/api/auth/unified/me`            | Identity (works with either Bearer header or cookie) |
| POST   | `/api/auth/unified/logout`        | Logout current device (mode-agnostic)                |
| POST   | `/api/auth/unified/logout-all`    | Logout all devices (mode-agnostic)                   |

These are served by `UnifiedAuthController` which dispatches via `detectAuthMode()`.

---

## Email Verification (OTP)

Email verification is **required** before users can access protected routes (bookings, AI proposal confirmation, reviews, etc.).

```bash
Registration → POST /api/email/send-code → User receives 6-digit code → POST /api/email/verify-code → Verified → Access Granted
```

### Schema

`email_verification_codes` table (migration `2026_04_03_084257`):

| Column          | Type           | Notes                                          |
| --------------- | -------------- | ---------------------------------------------- |
| `user_id`       | FK→users CASCADE | One row per outstanding code per user        |
| `code_hash`     | CHAR(64)       | SHA-256 hex digest — **raw code never stored** |
| `expires_at`    | TIMESTAMPTZ    | Default TTL 10 min                             |
| `attempts`      | SMALLINT       | Increments on each verify attempt              |
| `max_attempts`  | SMALLINT       | Default 5 — hard brute-force ceiling           |
| `last_sent_at`  | TIMESTAMPTZ    | Cooldown source                                |
| `consumed_at`   | TIMESTAMPTZ    | NULL = unused; non-NULL = already redeemed     |

### Service: `EmailVerificationCodeService`

```php
// AUTH-004 race-hardened: serialize concurrent send attempts per user.
public function sendCode(User $user): VerificationResult
{
    return Cache::lock("evc:send:{$user->id}", 5)->block(0, function () use ($user) {
        // Cooldown check (last_sent_at + RESEND_COOLDOWN_SECONDS)
        // Generate code; store SHA-256 hash; dispatch notification
    });
}

public function verifyCode(User $user, string $rawCode): VerificationResult
{
    return DB::transaction(function () use ($user, $rawCode) {
        // SELECT FOR UPDATE on outstanding row
        // Increment attempts; check expiry, max_attempts, hash match
        // On success: set consumed_at, set users.email_verified_at, dispatch Verified event
    });
}
```

### Route Protection

```php
// Routes requiring verified email
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::get('/v1/bookings', [BookingController::class, 'index']);
    // ... booking, AI, and review routes require verified email
});
```

### Frontend Flow

```typescript
// 1. Send the 6-digit code
await api.post("/email/send-code");
// → "Verification code sent. Check your email."

// 2. User enters the code in the OTP form
const { verified } = await api.post("/email/verify-code", { code: "123456" });

// 3. Check status
const status = await api.get("/email/verification-status");
// → { verified, email, email_verified_at, cooldown_remaining_seconds }
```

### Response Examples

**Verification Status (Unverified)**

```json
{
  "success": true,
  "verified": false,
  "email": "user@example.com",
  "email_verified_at": null,
  "cooldown_remaining_seconds": 0
}
```

**Verification Status (Verified)**

```json
{
  "success": true,
  "verified": true,
  "email": "user@example.com",
  "email_verified_at": "2026-05-08T10:00:00.000000Z"
}
```

**Unverified User Accessing Protected Route**

```json
// HTTP 403 Forbidden
{
  "success": false,
  "message": "Your email address is not verified."
}
```

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
    'refresh_count',  // Lifetime refresh telemetry
];

// Check methods
$token->isExpired();   // expires_at < now
$token->isRevoked();   // revoked_at !== null
$token->isValid();     // !expired && !revoked
```

### Refresh Rate Limiting

```php
// Per token-session 60-minute limiter. Exceeding the limit returns 429.
// refresh_count is lifetime telemetry and is not used for enforcement.
config('sanctum.max_token_refreshes_per_hour');
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

> Per-suite test counts moved to [PROJECT_STATUS.md](../../../PROJECT_STATUS.md). Historical Mar-baseline categories (Login/Register, Token Expiration, Token Refresh, Multi-device Logout, HttpOnly Cookie) have since been joined by `tests/Feature/Auth/EmailVerificationTest`, `tests/Feature/Auth/OtpResendRaceTest` (AUTH-004), `tests/Feature/Auth/UnifiedAuthDetectModeTest` (F-32), and Sanctum-hardening tests (`5e258e7` batch-2 atomic refresh + fingerprint binding).
