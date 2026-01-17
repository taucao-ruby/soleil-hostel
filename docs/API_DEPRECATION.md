# API Deprecation Strategy

> Guidelines for evolving the API while maintaining backward compatibility

## Overview

This document defines how we deprecate and sunset API endpoints, ensuring:

- ✅ Clients have time to migrate
- ✅ Clear communication of changes
- ✅ Graceful degradation
- ✅ No surprise breaking changes

---

## Deprecation Lifecycle

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│   Active    │ ──▶ │ Deprecated  │ ──▶ │   Sunset    │ ──▶ │   Removed   │
│             │     │ (warnings)  │     │ (read-only) │     │   (410 Gone)│
└─────────────┘     └─────────────┘     └─────────────┘     └─────────────┘
      │                   │                   │                   │
   Normal              6 months            3 months           Permanent
   operation          minimum              notice
```

### Phase Definitions

| Phase          | Duration   | Behavior              | Response Headers           |
| -------------- | ---------- | --------------------- | -------------------------- |
| **Active**     | Indefinite | Normal operation      | None                       |
| **Deprecated** | 6+ months  | Normal + warnings     | `Deprecation`, `Sunset`    |
| **Sunset**     | 3+ months  | Read-only or degraded | `Sunset`, `410` for writes |
| **Removed**    | Permanent  | Returns 410 Gone      | `410 Gone`                 |

---

## Deprecation Headers

### Standard Headers

When an endpoint is deprecated, include these headers:

```http
HTTP/1.1 200 OK
Deprecation: Sun, 01 Jul 2026 00:00:00 GMT
Sunset: Sun, 01 Jan 2027 00:00:00 GMT
Link: </api/v2/bookings>; rel="successor-version"
X-Deprecation-Notice: This endpoint is deprecated. Use /api/v2/bookings instead.
```

| Header                 | Purpose                                     |
| ---------------------- | ------------------------------------------- |
| `Deprecation`          | When the endpoint was deprecated (RFC 8594) |
| `Sunset`               | When the endpoint will be removed           |
| `Link`                 | Reference to the replacement endpoint       |
| `X-Deprecation-Notice` | Human-readable message                      |

---

## Implementation

### Middleware for Deprecated Endpoints

The `DeprecatedEndpoint` middleware is implemented at `app/Http/Middleware/DeprecatedEndpoint.php`:

```php
// app/Http/Middleware/DeprecatedEndpoint.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DeprecatedEndpoint
{
    public function handle(Request $request, Closure $next, string $sunset, ?string $successor = null)
    {
        $response = $next($request);

        // Parse sunset date
        $sunsetDate = \Carbon\Carbon::parse($sunset);

        // Set RFC 8594 deprecation headers
        $response->headers->set('Deprecation', now()->toRfc7231String());
        $response->headers->set('Sunset', $sunsetDate->toRfc7231String());

        $notice = "This endpoint is deprecated and will be removed on {$sunsetDate->format('Y-m-d')}.";
        if ($successor) {
            $notice .= " Use {$successor} instead.";
            $response->headers->set('Link', "<{$successor}>; rel=\"successor-version\"");
        }
        $response->headers->set('X-Deprecation-Notice', $notice);

        // Log usage for monitoring migration progress
        Log::channel('single')->info('Deprecated endpoint accessed', [
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'user_id' => $request->user()?->id,
            'client' => $request->header('User-Agent'),
            'sunset' => $sunset,
            'successor' => $successor,
        ]);

        return $response;
    }
}
```

Middleware is registered in `bootstrap/app.php`:

```php
$middleware->alias([
    // ...
    'deprecated' => \App\Http\Middleware\DeprecatedEndpoint::class,
]);
```

````

### Route Registration

```php
// routes/api.php

// ========== LEGACY ENDPOINTS (Deprecated - Sunset July 2026) ==========
Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware(['throttle:5,1', 'deprecated:2026-07-01,/api/auth/login-v2']);

Route::middleware(['check_token_valid'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout'])
        ->middleware('deprecated:2026-07-01,/api/auth/logout-v2');
    Route::post('/auth/refresh', [AuthController::class, 'refresh'])
        ->middleware('deprecated:2026-07-01,/api/auth/refresh-v2');
    Route::get('/auth/me', [AuthController::class, 'me'])
        ->middleware('deprecated:2026-07-01,/api/auth/me-v2');
});

// ========== UNIFIED ENDPOINTS (NEW - Mode-agnostic) ==========
Route::prefix('auth/unified')->group(function () {
    Route::get('/me', [UnifiedAuthController::class, 'me']);
    Route::post('/logout', [UnifiedAuthController::class, 'logout']);
    Route::post('/logout-all', [UnifiedAuthController::class, 'logoutAll']);
});
````

---

## Current Deprecation Schedule

### Deprecated Endpoints

| Endpoint                    | Deprecated | Sunset   | Successor                  | Headers Active |
| --------------------------- | ---------- | -------- | -------------------------- | -------------- |
| `POST /api/auth/login`      | Jan 2026   | Jul 2026 | `/api/auth/login-v2`       | ✅ Yes         |
| `POST /api/auth/logout`     | Jan 2026   | Jul 2026 | `/api/auth/logout-v2`      | ✅ Yes         |
| `POST /api/auth/refresh`    | Jan 2026   | Jul 2026 | `/api/auth/refresh-v2`     | ✅ Yes         |
| `GET /api/auth/me`          | Jan 2026   | Jul 2026 | `/api/auth/me-v2`          | ✅ Yes         |
| `GET /api/rooms`            | Jan 2026   | Jul 2026 | `/api/v1/rooms`            | ✅ Yes         |
| `GET /api/rooms/{id}`       | Jan 2026   | Jul 2026 | `/api/v1/rooms/{id}`       | ✅ Yes         |
| `POST /api/rooms`           | Jan 2026   | Jul 2026 | `/api/v1/rooms`            | ✅ Yes         |
| `PUT /api/rooms/{id}`       | Jan 2026   | Jul 2026 | `/api/v1/rooms/{id}`       | ✅ Yes         |
| `DELETE /api/rooms/{id}`    | Jan 2026   | Jul 2026 | `/api/v1/rooms/{id}`       | ✅ Yes         |
| `GET /api/bookings`         | Jan 2026   | Jul 2026 | `/api/v1/bookings`         | ✅ Yes         |
| `POST /api/bookings`        | Jan 2026   | Jul 2026 | `/api/v1/bookings`         | ✅ Yes         |
| `GET /api/bookings/{id}`    | Jan 2026   | Jul 2026 | `/api/v1/bookings/{id}`    | ✅ Yes         |
| `PUT /api/bookings/{id}`    | Jan 2026   | Jul 2026 | `/api/v1/bookings/{id}`    | ✅ Yes         |
| `DELETE /api/bookings/{id}` | Jan 2026   | Jul 2026 | `/api/v1/bookings/{id}`    | ✅ Yes         |
| `GET /api/admin/bookings/*` | Jan 2026   | Jul 2026 | `/api/v1/admin/bookings/*` | ✅ Yes         |

### Active Endpoints (Current - v1)

| Endpoint                          | Version | Mode   | Status |
| --------------------------------- | ------- | ------ | ------ |
| `POST /api/auth/login-v2`         | v2      | Bearer | Active |
| `POST /api/auth/login-httponly`   | v2      | Cookie | Active |
| `POST /api/auth/logout-v2`        | v2      | Bearer | Active |
| `POST /api/auth/logout-httponly`  | v2      | Cookie | Active |
| `POST /api/auth/logout-all-v2`    | v2      | Bearer | Active |
| `GET /api/auth/me-v2`             | v2      | Bearer | Active |
| `GET /api/auth/me-httponly`       | v2      | Cookie | Active |
| `POST /api/auth/refresh-v2`       | v2      | Bearer | Active |
| `POST /api/auth/refresh-httponly` | v2      | Cookie | Active |
| `GET /api/v1/bookings`            | v1      | Any    | Active |
| `POST /api/v1/bookings`           | v1      | Any    | Active |
| `GET /api/v1/rooms`               | v1      | Any    | Active |
| `POST /api/v1/rooms`              | v1      | Any    | Active |
| `GET /api/v1/admin/bookings/*`    | v1      | Admin  | Active |
| `GET /api/health/*`               | v1      | Any    | Active |

### API v2 (Under Development)

All v2 endpoints currently return `501 Not Implemented`:

```json
{
  "error": "NOT_IMPLEMENTED",
  "message": "API v2 under development",
  "useInstead": "/api/v1/..."
}
```

### Unified Endpoints (NEW - January 2026)

Mode-agnostic endpoints that auto-detect authentication mode (Bearer or Cookie):

| Endpoint                            | Version | Mode | Status |
| ----------------------------------- | ------- | ---- | ------ |
| `GET /api/auth/unified/me`          | v2      | Both | Active |
| `POST /api/auth/unified/logout`     | v2      | Both | Active |
| `POST /api/auth/unified/logout-all` | v2      | Both | Active |

---

## Migration Guide Template

When deprecating an endpoint, create a migration guide:

````markdown
## Migrating from /api/auth/login to /api/auth/login-v2

### Why?

The v2 endpoint provides improved security with token rotation and
better error messages.

### Timeline

- **Deprecated**: January 1, 2026
- **Sunset**: July 1, 2026
- **Removed**: October 1, 2026

### Changes

| Aspect       | Old (v1)                 | New (v2)                                      |
| ------------ | ------------------------ | --------------------------------------------- |
| Endpoint     | `/api/auth/login`        | `/api/auth/login-v2`                          |
| Response     | `{token: "..."}`         | `{access_token: "...", token_type: "Bearer"}` |
| Token header | `Authorization: {token}` | `Authorization: Bearer {token}`               |

### Migration Steps

1. Update endpoint URL
2. Update response parsing to use `access_token`
3. Add `Bearer ` prefix to Authorization header
4. Test authentication flow

### Example

```javascript
// Before
const response = await fetch("/api/auth/login", {
  method: "POST",
  body: JSON.stringify({ email, password }),
});
const { token } = await response.json();
headers["Authorization"] = token;

// After
const response = await fetch("/api/auth/login-v2", {
  method: "POST",
  body: JSON.stringify({ email, password }),
});
const { access_token } = await response.json();
headers["Authorization"] = `Bearer ${access_token}`;
```
````

```

---

## Versioning Strategy

### URL Path Versioning (Adopted)

We use URL path with suffixes for versioning:

```

/api/auth/login → v1 (implicit, deprecated)
/api/auth/login-v2 → v2 (current)
/api/auth/login-v3 → v3 (future)

```

### Rationale

| Strategy | Pros | Cons |
|----------|------|------|
| **URL suffix (chosen)** | Clear, cacheable, easy routing | URL "pollution" |
| URL prefix (`/v2/auth/login`) | Clean grouping | Harder to deprecate individual endpoints |
| Header (`Accept-Version: 2`) | Clean URLs | Hidden, harder to test |
| Query param (`?version=2`) | Flexible | Not RESTful, caching issues |

### Future: Prefix Versioning

For major API overhauls (v3+), we may switch to prefix:

```

/api/v3/auth/login
/api/v3/bookings

```

---

## Breaking vs Non-Breaking Changes

### Non-Breaking (Safe)

These changes can be made without deprecation:

- ✅ Adding new optional fields to responses
- ✅ Adding new optional query parameters
- ✅ Adding new endpoints
- ✅ Adding new enum values (if clients ignore unknown)
- ✅ Relaxing validation rules
- ✅ Improving error messages

### Breaking (Requires Deprecation)

These changes require the full deprecation cycle:

- ❌ Removing fields from responses
- ❌ Renaming fields
- ❌ Changing field types
- ❌ Removing endpoints
- ❌ Adding required parameters
- ❌ Changing authentication mechanism
- ❌ Changing error response format
- ❌ Removing enum values

---

## Communication Plan

### When Deprecating

1. **Update OpenAPI/Swagger** with `deprecated: true`
2. **Add to this document** (Deprecation Schedule)
3. **Create migration guide** in `/docs/migrations/`
4. **Announce in changelog** (`CHANGELOG.md`)
5. **Email API consumers** (if applicable)
6. **Add deprecation headers** to responses

### Timeline Communication

```

6 months before sunset:
├── Announce deprecation
├── Publish migration guide
└── Start returning deprecation headers

3 months before sunset:
├── Reminder announcement
└── Check migration metrics

1 month before sunset:
├── Final warning
└── Contact stragglers directly

At sunset:
├── Make endpoint read-only or return 410
└── Monitor for issues

3 months after sunset:
└── Remove endpoint code

````

---

## Monitoring Deprecated Endpoints

### Metrics to Track

```php
// Log channel: deprecation
[
    'endpoint' => '/api/auth/login',
    'method' => 'POST',
    'user_id' => 123,
    'client' => 'MyApp/1.0',
    'timestamp' => '2026-01-15T10:30:00Z',
]
````

### Queries

```bash
# Count usage by endpoint
grep "/api/auth/login" storage/logs/deprecation.log | wc -l

# Identify unmigrated clients
grep "/api/auth/login" storage/logs/deprecation.log | jq '.client' | sort | uniq -c
```

### Grafana Dashboard

Track:

- Deprecated endpoint call volume (should trend to zero)
- Unique clients still using deprecated endpoints
- Error rate on deprecated vs new endpoints

---

## Exception Handling

### Emergency Deprecation

For security vulnerabilities:

1. **Immediate sunset** (skip deprecation phase)
2. **Force migration** with 410 response
3. **Emergency notification** to all consumers
4. **Post-mortem** documentation

### Extension Requests

If major consumers need more time:

1. Evaluate impact of delay
2. Maximum 3-month extension
3. Document exception and reason
4. Set hard final deadline

---

## Examples

### Full Deprecation Lifecycle Example

**Scenario**: Deprecating `/api/auth/login` in favor of `/api/auth/login-v2`

```
January 2026: DEPRECATION
├── Add middleware to /api/auth/login
├── Headers: Deprecation, Sunset: July 2026, Link: /api/auth/login-v2
├── Log all requests
└── Publish migration guide

April 2026: CHECK-IN
├── Review usage metrics
├── Contact active users of deprecated endpoint
└── Update documentation

July 2026: SUNSET
├── Return 410 Gone for /api/auth/login
├── Keep logging for monitoring
└── Response: {"error": "Gone", "successor": "/api/auth/login-v2"}

October 2026: REMOVAL
├── Remove route from routes/api.php
├── Remove controller method
└── Clean up middleware
```

---

## Changelog Template

```markdown
## [2026-01-15] API Deprecations

### Deprecated

- `POST /api/auth/login` - Use `/api/auth/login-v2` instead (sunset: July 2026)
- `GET /api/auth/me` - Use `/api/auth/me-v2` instead (sunset: July 2026)

### Migration Guides

- [Auth Login Migration](./docs/migrations/AUTH_LOGIN_V2.md)
```

---

## Related Documentation

- [API.md](./backend/architecture/API.md) - Complete API reference
- [AUTHENTICATION.md](./backend/features/AUTHENTICATION.md) - Auth documentation
- [ADR-012](./ADR.md#adr-012-api-versioning-strategy) - API versioning decision
