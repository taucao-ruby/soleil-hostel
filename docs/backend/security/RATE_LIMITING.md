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

## RateLimitService Implementation

```php
class RateLimitService
{
    private const REDIS_PREFIX = 'rate:';
    private const MEMORY_STORE_LIMIT = 10000;

    private array $memoryStore = [];
    private bool $redisHealthy = true;

    public function check(string $key, array $limits): array
    {
        try {
            if ($this->redisHealthy && $this->redisAvailable()) {
                return $this->checkWithRedis($key, $limits);
            }
        } catch (\Throwable $e) {
            Log::warning('Redis rate limiter failed, using fallback', [
                'error' => $e->getMessage()
            ]);
            $this->redisHealthy = false;
            event(new RateLimiterDegraded(['service' => 'redis']));
        }

        // Automatic fallback to in-memory
        return $this->checkWithMemory($key, $limits);
    }

    // Returns: [allowed, remaining, reset_after, retry_after]
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
