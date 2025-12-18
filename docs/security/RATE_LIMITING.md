# 🚦 Rate Limiting

> Multi-tier rate limiting with Redis backend

## Overview

Rate limiting protects against brute-force attacks, spam, and API abuse using **sliding window** and **token bucket** algorithms.

---

## Limits

| Endpoint       | Limit   | Algorithm      | Key     |
| -------------- | ------- | -------------- | ------- |
| Login          | 5/min   | Sliding Window | IP      |
| Login          | 20/hour | Sliding Window | Email   |
| Booking Create | 3/min   | Token Bucket   | User ID |
| API (general)  | 60/min  | Sliding Window | User ID |

---

## Algorithms

### Sliding Window

Best for **strict time-window limits** (login attempts):

```
Window: 60 seconds
Request at T+0:  [1] → Allowed
Request at T+30: [2] → Allowed
Request at T+59: [5] → Allowed
Request at T+60: [1] → Allowed (window slides)
```

### Token Bucket

Best for **burst control** (bookings):

```
Bucket: 3 tokens, refill 1/min
Request 1: [3→2] → Allowed (burst)
Request 2: [2→1] → Allowed (burst)
Request 3: [1→0] → Allowed (burst)
Request 4: [0]   → Rejected (wait for refill)
```

---

## Implementation

### Service

```php
class AdvancedRateLimitService
{
    public function check(string $key, array $limits): bool
    {
        foreach ($limits as $limit) {
            if (!$this->checkLimit($key, $limit)) {
                return false;
            }
        }
        return true;
    }

    public function slidingWindow(string $key, int $max, int $window): bool
    {
        $count = Redis::zcount($key, now()->subSeconds($window)->timestamp, now()->timestamp);
        if ($count >= $max) {
            return false;
        }
        Redis::zadd($key, now()->timestamp, Str::uuid());
        Redis::expire($key, $window);
        return true;
    }
}
```

### Middleware

```php
class RateLimitMiddleware
{
    public function handle(Request $request, Closure $next, string $limiter)
    {
        $key = $this->resolveKey($request, $limiter);
        $limits = config("rate-limiting.{$limiter}");

        if (!$this->rateLimitService->check($key, $limits)) {
            return response()->json([
                'message' => 'Too many requests',
                'retry_after' => $this->getRetryAfter($key),
            ], 429);
        }

        return $next($request);
    }
}
```

---

## Response Headers

```http
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 42
X-RateLimit-Reset: 1702900800
Retry-After: 45
```

---

## 429 Response

```json
{
  "message": "Too many requests. Please try again later.",
  "retry_after": 45
}
```

---

## Configuration

```php
// config/rate-limiting.php
return [
    'login' => [
        ['max' => 5, 'window' => 60, 'key' => 'ip'],
        ['max' => 20, 'window' => 3600, 'key' => 'email'],
    ],
    'booking' => [
        ['max' => 3, 'window' => 60, 'key' => 'user', 'algorithm' => 'token_bucket'],
    ],
    'api' => [
        ['max' => 60, 'window' => 60, 'key' => 'user'],
    ],
];
```

---

## Fallback

When Redis is unavailable, the system falls back to in-memory limiting:

```php
if (!$this->redis->isConnected()) {
    return $this->memoryFallback($key, $limits);
}
```

---

## Tests

```bash
php artisan test tests/Feature/RateLimiting/
```

| Test Suite         | Count  |
| ------------------ | ------ |
| Login Rate Limit   | 4      |
| Booking Rate Limit | 3      |
| Middleware Tests   | 8      |
| **Total**          | **15** |

---

## Benchmarks

| Scenario          | Overhead |
| ----------------- | -------- |
| Rate limit check  | <1ms     |
| With Redis        | ~0.5ms   |
| Fallback (memory) | ~0.1ms   |

---

## Debugging

### Check Current Limits

```bash
redis-cli
> ZCOUNT ratelimit:login:192.168.1.1 -inf +inf
> TTL ratelimit:login:192.168.1.1
```

### Reset Limits

```bash
redis-cli
> DEL ratelimit:login:192.168.1.1
```
