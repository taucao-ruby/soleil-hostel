# 🚦 Rate Limiting

> Advanced multi-tier rate limiting with dual algorithms and Redis + in-memory fallback

## Overview

Rate limiting protects against brute-force attacks, spam, and API abuse using **sliding window** (time-strict) and **token bucket** (burst-friendly) algorithms with **atomic Redis operations**.

---

## Features

| Feature            | Description                                 |
| ------------------ | ------------------------------------------- |
| Dual Algorithms    | Sliding window + Token bucket               |
| Atomic Operations  | Redis Lua scripts (zero race conditions)    |
| Circuit Breaker    | Auto-fallback to in-memory when Redis fails |
| Multi-key Limiting | Per-IP, per-user, per-email, per-endpoint   |
| Real-time Metrics  | Prometheus-compatible metrics               |
| Dynamic Quotas     | Configurable by user tier                   |

---

## Limits Configuration

| Endpoint       | Limit   | Algorithm      | Key     | Use Case               |
| -------------- | ------- | -------------- | ------- | ---------------------- |
| Login          | 5/min   | Sliding Window | IP      | Brute-force prevention |
| Login          | 20/hour | Sliding Window | Email   | Account enumeration    |
| Booking Create | 3/min   | Token Bucket   | User ID | Spam prevention        |
| API (general)  | 60/min  | Sliding Window | User ID | General rate limit     |

---

## Algorithms

### Sliding Window (Strict Time-Based)

Best for **strict time-window limits** như login attempts:

```
Window: 60 seconds
T+0:  Request 1 → Allowed [count: 1]
T+30: Request 2 → Allowed [count: 2]
T+59: Request 5 → Allowed [count: 5]
T+60: Request 6 → Allowed [count: 1, window slides]
```

**Redis Lua Script (Atomic):**

```lua
-- Remove entries outside window
redis.call('ZREMRANGEBYSCORE', key, 0, now - window)
-- Get current count
local count = redis.call('ZCARD', key)
-- If below limit, add new entry
if count < limit then
    redis.call('ZADD', key, now, now)
    return { 1, limit - count - 1, 0 }  -- [allowed, remaining, reset_after]
else
    -- Get oldest entry timestamp
    local oldest = redis.call('ZRANGE', key, 0, 0, 'WITHSCORES')
    return { 0, 0, oldest[2] + window - now }  -- [denied, 0, retry_after]
end
```

### Token Bucket (Burst-Friendly)

Best for **burst control** như booking creation:

```
Bucket: 3 tokens, refill 1 token/min
Request 1: [3→2] → Allowed (burst OK)
Request 2: [2→1] → Allowed (burst OK)
Request 3: [1→0] → Allowed (burst OK)
Request 4: [0]   → Rejected (wait 60s for refill)
After 60s: [0→1] → Allowed
```

---

## Production Implementation

Rate limiting in production is provided by Laravel's `RateLimiter` facade,
configured in [`app/Providers/RateLimiterServiceProvider.php`](../../../backend/app/Providers/RateLimiterServiceProvider.php).
That provider is the single source of truth for the limits below; the
table in this document is descriptive, not authoritative.

Each named limiter (`login`, `booking`, `api-public`, `refresh-token`,
`global-api`, `password-reset`, `email-verification`) is applied via
`->middleware('throttle:<name>')` on the corresponding routes in
`routes/api/v1.php`.

```php
// app/Providers/RateLimiterServiceProvider.php
RateLimiter::for('login', function (Request $request) {
    return [
        Limit::perMinute(5)->by($request->ip()),
        Limit::perHour(20)->by('login:' . $request->input('email')),
    ];
});
```

Storage uses the Laravel cache driver (Redis in production, array in tests).
There is no project-defined fallback or degradation event — Laravel handles
cache-driver failures via the configured cache store.

> **Historical note**: A more elaborate `App\Services\RateLimitService` with
> dual algorithms, atomic Lua scripts, and `RateLimiterDegraded` /
> `RequestThrottled` events was prototyped but never registered in the
> middleware stack. It was removed on 2026-05-09 (FINDINGS_BACKLOG F-69).
> If a future need for token-bucket semantics or in-process fallback
> arises, prefer extending the Laravel facade configuration over
> reintroducing a parallel limiter.

---

## Response Headers

```http
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 42
X-RateLimit-Reset: 1702900800
Retry-After: 45
```

These are emitted by Laravel's built-in `ThrottleRequests` middleware.

---

## 429 Response

The custom messages per limiter are defined inline in
`RateLimiterServiceProvider`. For example:

```json
{
  "message": "Too many login attempts. Please try again in 60 seconds.",
  "retry_after": 60
}
```

---

## Tests

```bash
php artisan test tests/Feature/RateLimiting/
```

Two suites cover the live limiters end-to-end:
`LoginRateLimitTest` and `BookingRateLimitTest`.

---

## Debugging

The Laravel rate limiter stores counters in the configured cache. With the
Redis cache driver, throttle keys are namespaced under the cache prefix
(e.g. `laravel_cache:...`). Use the Laravel cache facade rather than
`redis-cli` for portable inspection:

```bash
php artisan tinker
> Cache::get('login:192.168.1.1');
> RateLimiter::clear('login:192.168.1.1');
```
