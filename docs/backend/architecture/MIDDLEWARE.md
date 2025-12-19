# 🔒 Middleware

> Request pipeline middleware for authentication, authorization, and security

## Overview

Soleil Hostel uses custom middleware for:

1. **Authentication** - Token validation and user binding
2. **Authorization** - Role-based access control
3. **Security** - Headers, rate limiting, CORS
4. **Request Processing** - Ownership verification

---

## Middleware Stack

| Middleware                        | Purpose                      | Alias                  |
| --------------------------------- | ---------------------------- | ---------------------- |
| CheckTokenNotRevokedAndNotExpired | Validate Bearer tokens       | `check_token_valid`    |
| CheckHttpOnlyTokenValid           | Validate HttpOnly cookies    | `check_httponly_token` |
| EnsureUserHasRole                 | RBAC role checking           | `role`                 |
| SecurityHeaders                   | Security response headers    | (global)               |
| AdvancedRateLimitMiddleware       | Dual-algorithm rate limiting | (configurable)         |
| ThrottleApiRequests               | Simple API throttling        | `throttle`             |
| Cors                              | CORS handling                | (global)               |
| VerifyBookingOwnership            | Booking owner check          | (route-specific)       |

---

## CheckTokenNotRevokedAndNotExpired

Validates Bearer tokens on every protected request.

```php
// App\Http\Middleware\CheckTokenNotRevokedAndNotExpired

class CheckTokenNotRevokedAndNotExpired
{
    public function handle(Request $request, Closure $next)
    {
        $bearerToken = $request->bearerToken();

        // Allow session auth or pre-authenticated requests
        if (!$bearerToken) {
            if ($request->user('web') || $request->user('sanctum')) {
                return $next($request);
            }
            throw new AuthenticationException('Token không được cấp.');
        }

        // Find token in database
        $tokenHash = hash('sha256', $bearerToken);
        $token = PersonalAccessToken::where('token', $tokenHash)->first();

        if (!$token) {
            throw new AuthenticationException('Token không hợp lệ.');
        }

        // Bind user to request
        $user = $token->tokenable;
        $request->setUserResolver(fn () => $user);
        $user->accessToken = $token;

        // Check expiration
        if ($token->isExpired()) {
            return response()->json([
                'message' => 'Token đã hết hạn.',
                'code' => 'TOKEN_EXPIRED',
            ], 401);
        }

        // Check revocation
        if ($token->isRevoked()) {
            return response()->json([
                'message' => 'Token đã bị revoke.',
                'code' => 'TOKEN_REVOKED',
            ], 401);
        }

        // Check suspicious activity
        if ($token->refresh_count > config('sanctum.max_refresh_count_per_hour')) {
            $token->revoke();
            return response()->json([
                'message' => 'Suspicious activity detected.',
                'code' => 'SUSPICIOUS_ACTIVITY',
            ], 401);
        }

        // Update last_used_at
        $token->touchLastUsed();

        return $next($request);
    }
}
```

### Response Codes

| Code                  | HTTP | Action Required       |
| --------------------- | ---- | --------------------- |
| `TOKEN_EXPIRED`       | 401  | Call refresh endpoint |
| `TOKEN_REVOKED`       | 401  | Login again           |
| `SUSPICIOUS_ACTIVITY` | 401  | Login again           |

---

## CheckHttpOnlyTokenValid

Validates tokens stored in HttpOnly cookies.

```php
// App\Http\Middleware\CheckHttpOnlyTokenValid

class CheckHttpOnlyTokenValid
{
    public function handle(Request $request, Closure $next)
    {
        // Get token from httpOnly cookie
        $tokenIdentifier = $request->cookie('auth_token');

        if (!$tokenIdentifier) {
            throw new AuthenticationException('Cookie auth_token không tồn tại.');
        }

        // Find by token_identifier (not hashed)
        $tokenHash = hash('sha256', $tokenIdentifier);
        $token = PersonalAccessToken::where('token_hash', $tokenHash)->first();

        if (!$token || $token->isExpired() || $token->isRevoked()) {
            throw new AuthenticationException('Token không hợp lệ.');
        }

        // Optional: Verify device fingerprint
        if ($token->device_fingerprint) {
            $currentFingerprint = $this->generateDeviceFingerprint($request);
            if ($token->device_fingerprint !== $currentFingerprint) {
                $token->revoke();
                throw new AuthenticationException('Device mismatch detected.');
            }
        }

        // Bind user
        $user = $token->tokenable;
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
```

### Cookie Security

| Attribute  | Value  | Purpose            |
| ---------- | ------ | ------------------ |
| `HttpOnly` | true   | Prevent XSS access |
| `Secure`   | true   | HTTPS only         |
| `SameSite` | Strict | CSRF protection    |
| `Path`     | /api   | API routes only    |

---

## EnsureUserHasRole

RBAC middleware for role-based route protection.

```php
// App\Http\Middleware\EnsureUserHasRole

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();

        if (!$user) {
            throw new AuthenticationException();
        }

        // Check if user has any of the required roles
        if (!$user->hasAnyRole($roles)) {
            abort(403, 'Insufficient permissions.');
        }

        return $next($request);
    }
}
```

### Usage

```php
// routes/api.php

// Single role
Route::middleware('role:admin')->group(function () {
    Route::post('/rooms', [RoomController::class, 'store']);
});

// Multiple roles (OR)
Route::middleware('role:admin,moderator')->group(function () {
    Route::get('/admin/bookings', [AdminBookingController::class, 'index']);
});
```

---

## SecurityHeaders

Adds security headers to all responses.

```php
// App\Http\Middleware\SecurityHeaders

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Content-Security-Policy' => $this->buildCsp(),
            'Permissions-Policy' => $this->buildPermissionsPolicy(),
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ];

        foreach ($headers as $key => $value) {
            $response->headers->set($key, $value);
        }

        return $response;
    }
}
```

---

## VerifyBookingOwnership

Simple ownership verification for booking routes.

```php
// App\Http\Middleware\VerifyBookingOwnership

class VerifyBookingOwnership
{
    public function handle(Request $request, Closure $next)
    {
        $booking = $request->route('booking');

        if ($booking && $booking->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Not authorized to access this booking.',
            ], 403);
        }

        return $next($request);
    }
}
```

---

## Middleware Registration

```php
// bootstrap/app.php or App\Http\Kernel.php

// Route middleware aliases
'check_token_valid' => CheckTokenNotRevokedAndNotExpired::class,
'check_httponly_token' => CheckHttpOnlyTokenValid::class,
'role' => EnsureUserHasRole::class,

// Global middleware
SecurityHeaders::class,
Cors::class,
```

---

## Request Flow

```
HTTP Request
    ↓
Global Middleware (CORS, SecurityHeaders)
    ↓
Route Middleware (check_token_valid OR check_httponly_token)
    ↓
Role Middleware (role:admin) [if configured]
    ↓
Controller
    ↓
Response with Security Headers
```
