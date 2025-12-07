# Advanced Rate Limiting System - Design Document

## Soleil Hostel Backend (Laravel 12 + Redis)

---

## 1. Executive Summary

This document outlines a **production-grade, distributed Rate Limiting system** that protects Soleil Hostel's API from brute-force attacks, spam, and DDoS-like overload while maintaining <1ms overhead per request.

**Key Improvements Over Baseline:**

- ✅ Multi-level limiting (User + IP + Room + Endpoint)
- ✅ Token bucket + sliding window algorithms with burst control
- ✅ Atomic Redis operations (zero race conditions)
- ✅ Distributed-safe (Redlock-like locking mechanism)
- ✅ Circuit breaker fallback (Redis failure gracefully handled)
- ✅ Prometheus metrics + structured logging
- ✅ Dynamic quota management by user tier
- ✅ Comprehensive monitoring dashboard

---

## 2. Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                    HTTP Request                             │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
        ┌──────────────────────────────┐
        │  RateLimitMiddleware         │
        │  (Applied per-route)         │
        └──────────┬───────────────────┘
                   │
                   ▼
        ┌──────────────────────────────┐
        │  RateLimitService::check()   │
        │  (Core rate limiting logic)  │
        └──────────┬───────────────────┘
                   │
        ┌──────────┴──────────┐
        │                     │
        ▼                     ▼
    ┌────────────┐      ┌──────────────┐
    │   Redis   │      │ In-Memory    │
    │ (Primary) │      │ (Fallback)   │
    └────────────┘      └──────────────┘
        │                     │
        └──────────┬──────────┘
                   │
                   ▼
    ┌──────────────────────────────┐
    │  Decision: Allow/Throttle    │
    │  (429 with Retry-After)      │
    └──────────────────────────────┘
        │                     │
        ▼                     ▼
    ┌──────────┐         ┌──────────┐
    │ Continue │         │ Reject   │
    │ Request  │         │ Request  │
    └──────────┘         └──────────┘
```

---

## 3. Rate Limiting Algorithms

### 3.1 Token Bucket Algorithm

**When to use:** Burst control (bookings, API calls)

```
Bucket Capacity:    N tokens
Refill Rate:        M tokens/second
Current Tokens:     T

Request arrives:
  if T >= cost:
    T -= cost
    Allow request
  else:
    Reject (429)

Every second:
  T = min(T + M, N)
```

**Advantages:**

- Handles burst traffic naturally
- Fair to all clients
- Easy to implement with Redis

**Example:** 10 tokens initially, refill 1/sec, burst allows 2 bookings instantly then 1 per second.

### 3.2 Sliding Window Algorithm

**When to use:** Strict time-window limits (login attempts, contact form)

```
Window Duration:    W seconds
Max Requests:       L

Request arrives:
  current_time = now()
  requests_in_window = COUNT(requests where timestamp > current_time - W)

  if requests_in_window < L:
    Allow request
    Store timestamp
  else:
    Reject (429)
```

**Advantages:**

- Precise time-window enforcement
- No burst spike behavior
- Memory-efficient

**Example:** Login: 5 requests per 60 seconds, strictly enforced.

### 3.3 Dual-Layer Strategy (Recommended)

Combine both for optimal protection:

```
Layer 1: Sliding Window (strict)
├─ Login: 5 per minute (brute-force prevention)
├─ Contact: 3 per minute (spam prevention)
└─ Booking: 3 per minute (abuse prevention)

Layer 2: Token Bucket (burst-friendly)
├─ Booking: 20 tokens, refill 1/sec (allows burst of 20 bookings)
├─ Room queries: 100 tokens, refill 10/sec (high throughput)
└─ API: 1000 tokens, refill 100/sec (global rate)
```

**Flow:**

```
Request arrives
  │
  ├─ Check Layer 1 (Sliding Window) → Pass/Fail
  │
  ├─ If Pass, check Layer 2 (Token Bucket) → Pass/Fail
  │
  └─ Return Allow/Throttle (429)
```

---

## 4. Key Patterns & Redis Schema

### 4.1 Rate Limit Keys

```php
// Per-user booking limit (sliding window)
"rate:booking:user:{user_id}:window"
  Type: Sorted Set (timestamp => weight)
  TTL: 1 minute
  Value: [ts1, ts2, ts3, ...]

// Per-user token bucket
"rate:booking:user:{user_id}:bucket"
  Type: Hash
  TTL: 60 seconds (auto-refill)
  Fields: { current_tokens: 20, last_refill: 1733600000 }

// Per-IP login attempt tracking
"rate:login:ip:{ip}:window"
  Type: Sorted Set
  TTL: 1 hour
  Value: [ts1, ts2, ts3, ts4, ts5]

// Per-email hourly login limit
"rate:login:email:{email}:hourly"
  Type: String (counter)
  TTL: 1 hour
  Value: 15 (attempts)

// Per-room booking spam check
"rate:booking:room:{room_id}:day"
  Type: String (counter)
  TTL: 24 hours
  Value: 45 (bookings today)

// Global endpoint meter
"rate:metrics:endpoint:{endpoint}:minute"
  Type: String (counter)
  TTL: 1 minute
  Value: 523 (requests this minute)
```

### 4.2 Atomic Operations

```lua
-- SCRIPT: Check & Increment Sliding Window (Lua)
local key = KEYS[1]
local now = tonumber(ARGV[1])
local window = tonumber(ARGV[2])  -- seconds
local limit = tonumber(ARGV[3])
local ttl = tonumber(ARGV[4])

-- Remove old entries outside window
redis.call('ZREMRANGEBYSCORE', key, 0, now - window)

-- Get current count
local count = redis.call('ZCARD', key)

if count < limit then
  -- Add new entry
  redis.call('ZADD', key, now, now)
  redis.call('EXPIRE', key, ttl)
  return { 1, limit - count - 1 }  -- [allowed, remaining]
else
  return { 0, 0 }  -- [rejected, remaining]
end
```

---

## 5. Integration with Existing Codebase

### 5.1 Middleware Stack Order

```php
// In Kernel.php
protected $middleware = [
    // ... existing middleware ...
    \Illuminate\Middleware\HandleCors::class,
    \Illuminate\Middleware\TrustProxies::class,
    // NEW: Global rate limiting (must be early)
    \App\Http\Middleware\GlobalRateLimitMiddleware::class,
    // ... other middleware ...
];

protected $routeMiddleware = [
    // Existing
    'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
    'auth:sanctum' => \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
    // NEW
    'rate-limit' => \App\Http\Middleware\AdvancedRateLimitMiddleware::class,
    'rate-limit.booking' => \App\Http\Middleware\BookingRateLimitMiddleware::class,
    'rate-limit.login' => \App\Http\Middleware\LoginRateLimitMiddleware::class,
];
```

### 5.2 Route Integration

```php
// In routes/api.php
// Login endpoints
Route::post('/auth/login', [...])
    ->middleware('rate-limit.login:5,60');  // 5 per 60s

// Booking endpoints
Route::post('/bookings', [...])
    ->middleware('auth:sanctum')
    ->middleware('rate-limit.booking:3,60,20');  // 3/min, 20 burst

// Room availability (high-traffic)
Route::get('/rooms/{id}/availability', [...])
    ->middleware('rate-limit:100,60');  // 100/min
```

---

## 6. Fallback & Circuit Breaker

### 6.1 Redis Failure Handling

```
Redis Available?
  │
  ├─ YES → Use Redis (atomic, accurate)
  │
  └─ NO → Fallback to In-Memory Rate Limiter
         (warn to logs, degrade gracefully)

In-Memory Strategy:
  ├─ Store in-process LRU cache
  ├─ Lose data on process restart (acceptable)
  ├─ Each process has independent limit
  ├─ Less accurate in multi-process, but safe
  └─ Log every fallback event
```

### 6.2 Implementation Pattern

```php
try {
    $allowed = $this->redisLimiter->check($key);
} catch (RedisConnectionException $e) {
    Log::warning("Redis rate limiter failed, using fallback", [
        'error' => $e->getMessage(),
        'key' => $key,
    ]);

    $allowed = $this->memoryLimiter->check($key);  // Degraded

    // Alert ops
    event(new RateLimiterDegraded(['service' => 'redis']));
}
```

---

## 7. Monitoring & Observability

### 7.1 Prometheus Metrics

```php
rate_limit_checks_total{endpoint="POST /api/bookings", result="allowed"}
rate_limit_checks_total{endpoint="POST /api/bookings", result="throttled"}
rate_limit_remaining_tokens{user_id="123", endpoint="booking"}
rate_limit_reset_after_seconds{user_id="123", endpoint="booking"}
redis_limiter_latency_ms{operation="check"}
redis_limiter_latency_ms{operation="increment"}
redis_limiter_fallback_count{reason="connection_timeout"}
```

### 7.2 Structured Logging

```json
{
  "timestamp": "2025-12-07T10:30:45Z",
  "event": "rate_limit_exceeded",
  "user_id": 123,
  "ip": "192.168.1.100",
  "endpoint": "POST /api/bookings",
  "limit_type": "sliding_window",
  "window_seconds": 60,
  "limit": 3,
  "current_attempts": 4,
  "reset_after_seconds": 45,
  "response_code": 429
}
```

---

## 8. Configuration Design

### 8.1 config/rate-limits.php

```php
return [
    'default' => 'redis',  // redis|memory
    'fallback_to_memory' => true,
    'memory_store_limit' => 10000,  // Max entries in memory
    'redis_key_prefix' => 'rate:',

    'endpoints' => [
        'login' => [
            'limits' => [
                ['type' => 'sliding', 'window' => 60, 'max' => 5, 'by' => 'ip'],
                ['type' => 'sliding', 'window' => 3600, 'max' => 20, 'by' => 'email'],
            ],
        ],
        'booking.create' => [
            'limits' => [
                ['type' => 'sliding', 'window' => 60, 'max' => 3, 'by' => 'user'],
                ['type' => 'bucket', 'tokens' => 20, 'refill_rate' => 1, 'by' => 'user'],
                ['type' => 'sliding', 'window' => 86400, 'max' => 100, 'by' => 'room'],
            ],
        ],
    ],

    'user_tiers' => [
        'free' => [
            'booking_per_minute' => 2,
            'booking_per_hour' => 30,
            'booking_per_day' => 100,
        ],
        'premium' => [
            'booking_per_minute' => 10,
            'booking_per_hour' => 200,
            'booking_per_day' => 1000,
        ],
    ],
];
```

---

## 9. Pros & Cons Analysis

| Feature            | Pros                      | Cons                         | Mitigation                    |
| ------------------ | ------------------------- | ---------------------------- | ----------------------------- |
| Redis-based        | Atomic, distributed, fast | External dependency          | Fallback to memory            |
| Token Bucket       | Burst-friendly, fair      | Requires refill logic        | Cron job or lazy eval         |
| Sliding Window     | Precise, simple           | High memory for active users | Cleanup job, TTL              |
| Dual-layer         | Comprehensive             | More complex, higher latency | Cache frequently-checked keys |
| In-Memory Fallback | Fast, resilient           | Less accurate multi-process  | Acceptable for degraded mode  |
| Per-User + Per-IP  | Fine-grained control      | More Redis keys/memory       | Prune inactive keys daily     |

---

## 10. Performance Targets

```
Latency per check:      < 1ms (p99)
Latency with fallback:  < 0.5ms (in-memory)
Redis throughput:       > 10,000 ops/sec
Memory per user:        ~500 bytes
Concurrent users:       50,000+ (with 25GB Redis)
```

**Benchmark Setup:**

- 100 concurrent clients
- 1,000 requests per second
- Measure: latency, throughput, error rate
- Target: p99 latency < 1ms, zero dropped requests

---

## 11. Potential Edge Cases

| Edge Case                  | Problem                          | Solution                              |
| -------------------------- | -------------------------------- | ------------------------------------- |
| Clock skew                 | Two servers have different time  | Use `redis.time()` for all timestamps |
| Thundering herd            | All users hit limit at same time | Use randomized backoff in response    |
| Double-count in burst      | Request counted twice            | Atomic INCR with Lua script           |
| Memory leak on Redis       | Keys never expire                | Set TTL on every key                  |
| Integer overflow           | Counter exceeds 64-bit           | Use BITCOUNT with rolling window      |
| Distributed race condition | Lock acquired during check       | Use Redlock algorithm                 |

---

## Summary

This design provides a **scalable, safe, production-ready rate limiting system** that:

- Prevents brute-force, spam, and DDoS attacks
- Maintains <1ms overhead per request
- Gracefully degrades if Redis fails
- Supports multi-level limiting (user, IP, room, endpoint)
- Provides comprehensive observability via logs and metrics
- Integrates seamlessly with Laravel 12 + existing Redis setup
