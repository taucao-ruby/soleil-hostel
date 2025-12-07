# Advanced Rate Limiting - Quick Reference

## 1. File Structure

```
backend/
├── app/
│   ├── Services/
│   │   └── RateLimitService.php          [NEW] Core rate limiting logic
│   ├── Http/
│   │   └── Middleware/
│   │       └── AdvancedRateLimitMiddleware.php  [NEW] Apply limits to routes
│   └── Events/
│       ├── RequestThrottled.php          [NEW] Fired when throttled
│       └── RateLimiterDegraded.php       [NEW] Fired on Redis failure
├── config/
│   └── rate-limits.php                   [NEW] Configuration
├── tests/
│   └── Feature/
│       └── RateLimiting/
│           ├── AdvancedRateLimitServiceTest.php      [NEW] Unit tests
│           └── AdvancedRateLimitMiddlewareTest.php   [NEW] Feature tests
└── routes/
    └── api.php                           [UPDATED] Add middleware to routes
```

---

## 2. Core Concepts

### Sliding Window (Strict Time-Based)

```
Limit: 5 per 60 seconds
Implementation: Sorted Set in Redis
Use case: Login attempts, spam prevention
```

### Token Bucket (Burst-Friendly)

```
Limit: 20 tokens, refill 1/sec
Implementation: Hash in Redis
Use case: API queries, high-throughput endpoints
```

---

## 3. Middleware Parameters

### Format

```php
->middleware('rate-limit:{type}:{value}:{config}')
```

### Sliding Window

```php
->middleware('rate-limit:sliding:5:60')
//                              ↓  ↓
//                         max window(sec)
```

### Token Bucket

```php
->middleware('rate-limit:token:20:1')
//                            ↓  ↓
//                       capacity refill_rate(/sec)
```

### Multiple Limits (AND logic)

```php
->middleware('rate-limit:sliding:5:60,token:100:10')
// First check: 5 per 60 seconds
// Then check: 100 tokens, refill 10/sec
// Both must pass
```

---

## 4. Common Patterns

### Pattern 1: Brute-Force Protection

```php
Route::post('/auth/login', [...])
    ->middleware('rate-limit:sliding:5:60');
    // 5 attempts per minute, fail fast
```

### Pattern 2: Spam Prevention

```php
Route::post('/contact', [...])
    ->middleware('rate-limit:sliding:3:60');
    // 3 per minute, very restrictive
```

### Pattern 3: High-Throughput API

```php
Route::get('/rooms', [...])
    ->middleware('rate-limit:token:100:10');
    // 100 burst, then 10/sec = high throughput
```

### Pattern 4: Multi-Level Protection

```php
Route::post('/bookings', [...])
    ->middleware('auth:sanctum')
    ->middleware('rate-limit:sliding:3:60,token:20:1');
    // Layer 1: Strict per-minute limit
    // Layer 2: Allow bursts between refills
```

---

## 5. Response Format

### HTTP 200 (Allowed)

```json
{
  "success": true,
  "data": {...}
}

Headers:
X-RateLimit-Limit: "5 per 60s"
X-RateLimit-Remaining: 2
X-RateLimit-Reset: 1733600060
```

### HTTP 429 (Throttled)

```json
{
  "success": false,
  "message": "Too many requests. Please try again later.",
  "retry_after": 45
}

Headers:
Retry-After: 45
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1733600045
```

---

## 6. Configuration

### config/rate-limits.php

```php
'user_tiers' => [
    'free' => ['multiplier' => 1.0],
    'premium' => ['multiplier' => 3.0],
    'enterprise' => ['multiplier' => 10.0],
],

'whitelist' => [
    'ips' => ['127.0.0.1'],
    'user_ids' => [1, 2, 3],  // Admins
],

'responses' => [
    'http_status' => 429,
    'include_retry_after' => true,
],
```

---

## 7. Monitoring

### Check Metrics

```bash
php artisan rate-limit:metrics

# Output:
Rate Limiting Metrics:
┌──────────────────────┬────────┐
│ Metric               │ Value  │
├──────────────────────┼────────┤
│ Total Checks         │ 50000  │
│ Allowed              │ 49500  │
│ Throttled            │ 500    │
│ Throttled %          │ 1.00%  │
│ Fallback Uses        │ 0      │
│ Redis Healthy        │ Yes    │
│ Memory Store Size    │ 2384   │
└──────────────────────┴────────┘
```

### Run Benchmark

```bash
php artisan rate-limit:benchmark --requests=10000
# Expected: < 1ms average latency
```

### View Logs

```bash
tail -f storage/logs/rate-limiting.log
```

---

## 8. Testing

### Run Unit Tests

```bash
php artisan test --filter AdvancedRateLimitServiceTest
```

### Run Feature Tests

```bash
php artisan test --filter AdvancedRateLimitMiddlewareTest
```

### Manual Testing

```bash
# 6 login attempts (limit 5/min)
for i in {1..6}; do
  curl -X POST http://localhost:8000/api/auth/login \
    -H "Content-Type: application/json" \
    -d '{"email":"test@example.com","password":"password"}'
  echo "---"
done

# Expected: First 5 succeed, 6th returns 429
```

---

## 9. Troubleshooting

| Problem            | Cause                  | Solution                            |
| ------------------ | ---------------------- | ----------------------------------- |
| Always 429         | Limit too strict       | Increase `max` value                |
| Not throttling     | Middleware not applied | Check `routes/api.php`              |
| High latency       | Redis slow             | Check `redis-cli --latency`         |
| Memory growing     | No TTL on keys         | Verify `EXPIRE` in Lua script       |
| Different per user | Using IP instead       | Ensure `$request->user()` available |

---

## 10. Production Checklist

- [ ] Redis running with persistence
- [ ] All tests passing
- [ ] Limits reviewed by product team
- [ ] Monitoring alerts configured
- [ ] Documentation shared with frontend team
- [ ] Runbook created for on-call
- [ ] Rollback plan tested
- [ ] Performance benchmarks reviewed
- [ ] Load test completed
- [ ] Edge cases validated

---

## 11. Useful Commands

```bash
# Check Redis connection
redis-cli ping

# Monitor rate limiting keys
redis-cli MONITOR | grep "rate:"

# Flush all rate limiting keys
redis-cli DEL $(redis-cli KEYS "rate:*")

# Get rate limit status for user
php artisan tinker
$limiter = app(\App\Services\RateLimitService::class);
$limiter->getStatus('user:123', [...]);

# Reset user's rate limit
$limiter->reset('user:123');

# Get all metrics
$metrics = $limiter->getMetrics();
```

---

## 12. References

- Design Document: `RATE_LIMITING_ADVANCED_DESIGN.md`
- Integration Guide: `ADVANCED_RATE_LIMITING_INTEGRATION.md`
- Benchmark Guide: `RATE_LIMITING_BENCHMARK.md`
- Edge Cases: `RATE_LIMITING_EDGE_CASES.md`
- Code: `app/Services/RateLimitService.php`
- Config: `config/rate-limits.php`

---

**Last Updated:** 2025-12-07  
**Status:** Production Ready ✅
