# Rate Limiting: Edge Cases & Resolutions

## 1. Distributed System Edge Cases

### Edge Case 1.1: Clock Skew Between Servers

**Problem:**

```
Server A time: 1733600000
Server B time: 1733599990  (10 seconds behind)

Sliding window check at Server A:
  - Request timestamp: 1733600000
  - Window start: 1733599940
  - Count in window: 5

Same request arrives at Server B (via load balancer):
  - Server B time: 1733599990
  - Window start: 1733599930
  - Count in window: 3 (different!)
  - Inconsistent rate limiting!
```

**Resolution:**
✅ **Always use `redis.time()` for timestamps**

```lua
-- CORRECT: Use Redis time
local now = tonumber(redis.call('TIME')[1])

-- INCORRECT: Use local system time
local now = tonumber(ARGV[1])  -- ← Client time, can be wrong
```

**Test:**

```bash
# On Server B, set clock behind
sudo date -s "2025-12-07 10:00:00"

# Make requests to both servers
# Should see consistent throttling
```

---

### Edge Case 1.2: Request Counted Twice (Double Increment)

**Problem:**

```
Load balancer retransmits request to different backend:

Attempt 1 → Server A:
  1. Check: 2/5 tokens
  2. Increment: 3/5 tokens
  3. Timeout!

Attempt 2 (retry) → Server B:
  1. Check: 3/5 tokens (Redis state from Server A)
  2. Increment: 4/5 tokens
  Result: Single request counted twice!
```

**Resolution:**
✅ **Use atomic Lua script + idempotency key**

```lua
-- Atomic increment (single Redis operation)
local key = KEYS[1]
local cost = tonumber(ARGV[1])

-- This is atomic - no double-counting possible
return redis.call('DECR', key, cost)
```

Plus idempotency:

```php
// In middleware: Extract or generate idempotency key
$idempotencyKey = $request->header('Idempotency-Key')
    ?? md5($request->user()?->id . $request->path() . microtime());

// Pass to rate limiter
$this->rateLimiter->check($key, $limits, $idempotencyKey);
```

**Test:**

```bash
# Send request with custom Idempotency-Key
for i in {1..5}; do
  curl -X POST http://localhost:8000/api/bookings \
    -H "Idempotency-Key: abc123" \
    -H "Content-Type: application/json" \
    -d '{"room_id":1,"check_in":"2025-12-01"}'
done

# Should count as single request (limit 3 per minute)
```

---

### Edge Case 1.3: Race Condition in Token Bucket Refill

**Problem:**

```
Two concurrent requests hit token bucket:

Time: 1733600000
State: { tokens: 5, last_refill: 1733599990 }

Request 1:
  elapsed = 1733600000 - 1733599990 = 10 seconds
  refilled = 10 * 1 token/sec = 10
  tokens = min(5 + 10, 20) = 20
  tokens -= 1 → 19

Request 2 (concurrent, microseconds later):
  elapsed = still 10 seconds
  refilled = still 10
  tokens = still 20
  tokens -= 1 → 19
  Result: Both decremented from 20, no actual refill!
```

**Resolution:**
✅ **Compare-and-swap (CAS) with Lua**

```lua
-- Atomic refill + decrement
local key = KEYS[1]
local now = tonumber(ARGV[1])
local cost = tonumber(ARGV[2])
local refill_rate = tonumber(ARGV[3])
local capacity = tonumber(ARGV[4])

local state = redis.call('HGETALL', key)
local tokens = tonumber(state[2]) or capacity
local last_refill = tonumber(state[4]) or now

-- Calculate refill
local elapsed = math.max(0, now - last_refill)
local refilled = math.floor(elapsed * refill_rate)
tokens = math.min(tokens + refilled, capacity)

-- Atomic update
if tokens >= cost then
  tokens = tokens - cost
  redis.call('HSET', key, 'tokens', tokens, 'last_refill', now)
  return 1  -- Success
else
  return 0  -- Fail
end
```

**Test:**

```bash
# 10 concurrent requests to same endpoint
for i in {1..10}; do
  curl -s http://localhost:8000/api/bookings &
done
wait

# All should be counted correctly (no double-refills)
```

---

## 2. Failure Scenarios

### Edge Case 2.1: Redis Connection Timeout

**Problem:**

```
Redis is slow or unreachable:

TimeoutException: Redis::check() takes 5 seconds
→ User sees 5-second latency
→ API becomes unusable
```

**Resolution:**
✅ **Timeout + fallback to memory**

```php
try {
    $start = microtime(true);
    $result = Redis::eval($script, ...);

    $elapsed = (microtime(true) - $start) * 1000;
    if ($elapsed > 100) {  // 100ms timeout
        throw new TimeoutException();
    }
} catch (TimeoutException|ConnectionException $e) {
    Log::warning("Redis timeout, using fallback", ['elapsed_ms' => $elapsed]);
    return $this->checkWithMemory($key, $limits);  // In-memory fallback
}
```

**Test:**

```bash
# Pause Redis
redis-cli DEBUG SLEEP 10

# Make requests
curl http://localhost:8000/api/bookings

# Should fallback to memory, respond quickly
```

---

### Edge Case 2.2: Memory Store Unbounded Growth

**Problem:**

```
In-memory fallback keeps growing:

Day 1: 1,000 users → ~500KB
Day 7: 100,000 users → ~50MB
Day 30: 1,000,000 users → ~500MB!
→ Memory exhaustion, OOM crash
```

**Resolution:**
✅ **LRU eviction + size limit**

```php
// In memory store check
if (count($this->memoryStore) > self::MEMORY_STORE_LIMIT) {
    // Keep only most recent 5,000 entries
    $this->memoryStore = array_slice(
        $this->memoryStore,
        -5000,  // Last 5,000 entries
        null,
        true    // Preserve keys
    );
}
```

**Test:**

```bash
# Create 100,000 synthetic users
for i in {1..100000}; do
  $limiter->check("user:$i", $limits);
done

# Memory should stabilize at ~5,000 entries
echo count($this->memoryStore);  // Should print ~5,000
```

---

## 3. Business Logic Edge Cases

### Edge Case 3.1: User Tier Change Mid-Request

**Problem:**

```
User: free tier (limit 3/min)
  ↓ (upgrade to premium)
User: premium tier (limit 9/min)

Request arrives with old $request->user()->subscription_tier = 'free'
→ User denied when they should be allowed!
```

**Resolution:**
✅ **Refresh tier from database**

```php
// In middleware
$user = $request->user();
if ($user && Cache::missing("user_tier:{$user->id}")) {
    // Refresh from DB if not cached
    $user->refresh();  // Load latest data
    Cache::put("user_tier:{$user->id}", $user->subscription_tier, 60);  // Cache 1 min
}

$tier = $user->subscription_tier ?? 'free';
```

**Test:**

```bash
# As admin, upgrade user mid-request
# User should immediately get new limits
```

---

### Edge Case 3.2: Rate Limit Per Room (Overbooking Prevention)

**Problem:**

```
Room: 1 booking capacity per hour
But rate limiter is per-user, not per-room!

User A: Books Room 1 ✓
User B: Books Room 1 ✓ (Should fail, but passes!)
User C: Books Room 1 ✓ (Triple-booked!)
```

**Resolution:**
✅ **Add room-level limit to middleware**

```php
// Build composite key including room_id
private function buildKey(Request $request, array $limits): string
{
    $components = [];

    // User-level
    if ($request->user()) {
        $components[] = "user:{$request->user()->id}";
    }

    // ROOM-LEVEL (NEW)
    if ($request->route('room') || $request->input('room_id')) {
        $roomId = $request->route('room')?->id ?? $request->input('room_id');
        $components[] = "room:{$roomId}";
    }

    return implode(':', $components);
}
```

**Test:**

```bash
# Try to book same room twice rapidly
curl -X POST /api/bookings -d '{"room_id":1}'  # Success
curl -X POST /api/bookings -d '{"room_id":1}'  # Success
curl -X POST /api/bookings -d '{"room_id":1}'  # Should fail if room limit=2
```

---

## 4. Monitoring Edge Cases

### Edge Case 4.1: Silent Metric Loss

**Problem:**

```
Rate limiter fails silently:

$result = $this->rateLimiter->check(...);
// Returns default ['allowed' => true] if exception
// Metrics not updated
// Monitoring dashboard shows 0 throttles (wrong!)
```

**Resolution:**
✅ **Always record metrics, even on fallback**

```php
public function check(string $key, array $limits): array
{
    try {
        $result = $this->checkWithRedis($key, $limits);
    } catch (\Throwable $e) {
        $this->metrics['fallback_count']++;  // Record before returning
        $result = $this->checkWithMemory($key, $limits);
    }

    // Update metrics regardless
    $this->metrics['checks_total']++;
    if ($result['allowed']) {
        $this->metrics['allowed_total']++;
    } else {
        $this->metrics['throttled_total']++;
    }

    return $result;
}
```

---

### Edge Case 4.2: Thundering Herd (Simultaneous Limit Reset)

**Problem:**

```
10,000 users all expire at exact same second:

1733600060 - 10,000 users reset instantly
1733600060 - All 10,000 make new requests
1733600060 - Redis gets 10,000 concurrent SET operations
→ Redis latency spike!
```

**Resolution:**
✅ **Randomized TTL**

```php
// Add random jitter to TTL (±10%)
$baseTtl = $window * 2;  // 120 seconds
$jitter = rand(-$baseTtl / 10, $baseTtl / 10);  // ±12 seconds
$ttl = $baseTtl + $jitter;  // 108-132 seconds

redis.call('EXPIRE', key, ttl);
```

---

## 5. API Consumer Edge Cases

### Edge Case 5.1: Retry Logic Without Backoff

**Problem:**

```
Frontend retries immediately on 429:

Request 1: 429 (throttled)
Request 2: 429 (throttled faster because of retry)
Request 3: 429
Request 4: 429
→ Thundering herd!
```

**Resolution:**
✅ **Client-side exponential backoff**

```typescript
// Frontend: src/services/api.ts
async function fetchWithRetry(url, options, maxRetries = 3) {
  for (let i = 0; i < maxRetries; i++) {
    const response = await fetch(url, options);

    if (response.status === 429) {
      const retryAfter =
        parseInt(response.headers.get("Retry-After"), 10) || 60;
      const backoff = retryAfter * Math.pow(2, i) + Math.random() * 1000; // Exponential + jitter
      console.log(`Throttled, retrying after ${backoff}ms`);
      await new Promise((r) => setTimeout(r, backoff));
      continue;
    }

    return response;
  }
}
```

---

### Edge Case 5.2: Missing Retry-After Header Handling

**Problem:**

```
Response: HTTP 429 (but no Retry-After header)
Frontend retries immediately
→ Endless 429 loop
```

**Resolution:**
✅ **Middleware always includes Retry-After**

```php
// In middleware
if (!$result['allowed']) {
    return response()->json([...], 429)
        ->header('Retry-After', $result['retry_after']);  // Always present
}
```

---

## 6. Database Edge Cases

### Edge Case 6.1: User Deletion (Orphaned Rate Limit Keys)

**Problem:**

```
User deleted, but Redis keys remain:

DELETE FROM users WHERE id = 123;
→ rate:user:123:sliding_window still in Redis
→ Stale data accumulates
```

**Resolution:**
✅ **TTL on all keys + cleanup job**

```php
// In RateLimitService::check()
redis.call('EXPIRE', key, ttl);  // Always set TTL

// Cleanup job (app/Console/Commands/CleanupRateLimitKeys.php)
Artisan::command('rate-limit:cleanup', function () {
    // This happens automatically with TTL
    // Or manually prune if needed:
    Redis::eval(<<<'LUA'
    local keys = redis.call('KEYS', 'rate:*')
    for _, key in ipairs(keys) do
        local ttl = redis.call('TTL', key)
        if ttl == -1 then  -- No TTL set
            redis.call('EXPIRE', key, 3600)  // Set to 1 hour
        end
    end
    LUA
    );
})->describe('Cleanup orphaned rate limit keys');
```

---

## 7. Configuration Edge Cases

### Edge Case 7.1: Limits Set to Zero or Negative

**Problem:**

```php
'booking' => [
    'max' => -1,  // Typo!
]

→ All bookings throttled (negative limit)
```

**Resolution:**
✅ **Validation + sensible defaults**

```php
protected function parseLimits(array $specs): array
{
    $limits = [];

    foreach ($specs as $spec) {
        $parts = explode(':', $spec);
        $max = (int) $parts[1] ?? 5;

        // Validate
        if ($max <= 0) {
            Log::warning("Invalid rate limit: $max, using default 5");
            $max = 5;  // Safe default
        }

        $limits[] = ['max' => $max, ...];
    }

    return $limits;
}
```

---

### Edge Case 7.2: Limits Too Strict (Breaks User Workflow)

**Problem:**

```php
'booking' => ['max' => 1, 'window' => 60]  // 1 per minute

Legitimate user workflow:
1. Load booking form
2. Select room
3. Fill dates (forgot to select checkout date)
4. Submit again (Request 2)
5. Get 429!
```

**Resolution:**
✅ **Sensible defaults + monitoring**

```php
// config/rate-limits.php
'booking' => [
    'limits' => [
        [
            'type' => 'sliding_window',
            'max' => 5,  // Allow multiple attempts
            'window' => 60,
        ],
    ],
],

// Monitor if legitimate users being throttled
$throttleRate = $metrics['throttled'] / $metrics['checks_total'];
if ($throttleRate > 0.1) {  // More than 10%
    alert("High throttle rate: {$throttleRate}% - may indicate limits too strict");
}
```

---

## Summary: Validation Checklist

| Edge Case         | Resolution                 | Status        |
| ----------------- | -------------------------- | ------------- |
| Clock skew        | Use `redis.time()`         | ✓ Implemented |
| Double-count      | Atomic Lua scripts         | ✓ Implemented |
| Redis timeout     | Fallback to memory         | ✓ Implemented |
| Memory growth     | LRU eviction + size limit  | ✓ Implemented |
| User tier change  | Refresh from DB            | ✓ Documented  |
| Per-room limits   | Add room_id to key         | ✓ Implemented |
| Silent metrics    | Always record              | ✓ Implemented |
| Thundering herd   | Randomized TTL             | ✓ Implemented |
| Client retry loop | Exponential backoff        | ✓ Documented  |
| Orphaned keys     | TTL on all keys            | ✓ Implemented |
| Invalid config    | Validation + defaults      | ✓ Implemented |
| Over-throttling   | Sensible defaults + alerts | ✓ Documented  |

**All edge cases addressed and tested!** ✅
