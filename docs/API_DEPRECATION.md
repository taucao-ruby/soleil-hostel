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

```php
// app/Http/Middleware/DeprecatedEndpoint.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class DeprecatedEndpoint
{
    public function handle(Request $request, Closure $next, string $sunset, string $successor = null)
    {
        $response = $next($request);

        // Add deprecation headers
        $response->headers->set('Deprecation', now()->toRfc7231String());
        $response->headers->set('Sunset', $sunset);
        $response->headers->set('X-Deprecation-Notice',
            "This endpoint is deprecated and will be removed on {$sunset}."
        );

        if ($successor) {
            $response->headers->set('Link', "<{$successor}>; rel=\"successor-version\"");
        }

        // Log usage for monitoring migration progress
        \Log::channel('deprecation')->info('Deprecated endpoint accessed', [
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'user_id' => $request->user()?->id,
            'client' => $request->header('User-Agent'),
        ]);

        return $response;
    }
}
```

### Route Registration

```php
// routes/api.php

// Active endpoint
Route::post('/auth/login-v2', [AuthController::class, 'loginV2']);

// Deprecated endpoint (sunset in 6 months)
Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('deprecated:2026-07-01,/api/auth/login-v2');

// Sunset endpoint (returns 410 for writes)
Route::post('/auth/old-login', function () {
    return response()->json([
        'error' => 'Gone',
        'message' => 'This endpoint has been removed. Use /api/auth/login-v2 instead.',
        'successor' => '/api/auth/login-v2',
    ], 410);
});
```

---

## Current Deprecation Schedule

### Deprecated Endpoints

| Endpoint                | Deprecated | Sunset   | Successor             |
| ----------------------- | ---------- | -------- | --------------------- |
| `POST /api/auth/login`  | Jan 2026   | Jul 2026 | `/api/auth/login-v2`  |
| `POST /api/auth/logout` | Jan 2026   | Jul 2026 | `/api/auth/logout-v2` |
| `GET /api/auth/me`      | Jan 2026   | Jul 2026 | `/api/auth/me-v2`     |

### Active Endpoints (Current)

| Endpoint                          | Version | Status |
| --------------------------------- | ------- | ------ |
| `POST /api/auth/login-v2`         | v2      | Active |
| `POST /api/auth/login-httponly`   | v2      | Active |
| `POST /api/auth/logout-v2`        | v2      | Active |
| `POST /api/auth/logout-all-v2`    | v2      | Active |
| `GET /api/auth/me-v2`             | v2      | Active |
| `POST /api/auth/refresh-httponly` | v2      | Active |
| `POST /api/bookings`              | v1      | Active |
| `GET /api/rooms`                  | v1      | Active |
| `GET /api/health/*`               | v1      | Active |

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
