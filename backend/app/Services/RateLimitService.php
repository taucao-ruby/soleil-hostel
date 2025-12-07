<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Events\RateLimiterDegraded;
use App\Exceptions\RateLimitExceededException;
use Carbon\Carbon;
use stdClass;

/**
 * Advanced Rate Limiting Service
 * 
 * Implements dual-layer rate limiting (sliding window + token bucket)
 * with Redis backend and in-memory fallback for production resilience.
 * 
 * Features:
 * - Atomic Redis operations (zero race conditions)
 * - Sliding window for time-strict limits
 * - Token bucket for burst-friendly limits
 * - Circuit breaker fallback to in-memory
 * - Per-user, per-IP, per-room, per-endpoint limiting
 * - Prometheus metrics + structured logging
 */
class RateLimitService
{
    private const REDIS_PREFIX = 'rate:';
    private const MEMORY_STORE_LIMIT = 10000;
    private const FALLBACK_TIMEOUT = 100;  // milliseconds
    
    private array $memoryStore = [];
    private bool $redisHealthy = true;
    private array $metrics = [
        'checks_total' => 0,
        'allowed_total' => 0,
        'throttled_total' => 0,
        'fallback_count' => 0,
    ];

    /**
     * Check if a request is allowed under rate limits
     * 
     * @param string $key Unique identifier (e.g., "user:123:booking")
     * @param array<string, mixed> $limits Array of limit configurations
     * @return array{allowed: bool, remaining: int, reset_after: int, retry_after: int}
     */
    public function check(string $key, array $limits): array
    {
        $this->metrics['checks_total']++;
        
        try {
            // Try Redis first
            if ($this->redisHealthy && $this->redisAvailable()) {
                return $this->checkWithRedis($key, $limits);
            }
        } catch (\Throwable $e) {
            Log::warning('Redis rate limiter failed, using fallback', [
                'error' => $e->getMessage(),
                'key' => $key,
            ]);
            
            $this->redisHealthy = false;
            event(new RateLimiterDegraded(['service' => 'redis', 'reason' => $e->getMessage()]));
            $this->metrics['fallback_count']++;
        }

        // Fallback to in-memory
        return $this->checkWithMemory($key, $limits);
    }

    /**
     * Check rate limits using Redis (atomic, distributed-safe)
     */
    private function checkWithRedis(string $key, array $limits): array
    {
        $now = Carbon::now()->getTimestamp();
        $allowed = true;
        $remainingMin = PHP_INT_MAX;
        $resetAfterMin = 0;

        foreach ($limits as $limit) {
            $limitKey = $this->buildRedisKey($key, $limit);

            if ($limit['type'] === 'sliding_window') {
                $result = $this->checkSlidingWindow($limitKey, $limit, $now);
            } elseif ($limit['type'] === 'token_bucket') {
                $result = $this->checkTokenBucket($limitKey, $limit, $now);
            } else {
                continue;
            }

            if (!$result['allowed']) {
                $allowed = false;
            }

            $remainingMin = min($remainingMin, $result['remaining']);
            $resetAfterMin = max($resetAfterMin, $result['reset_after']);
        }

        if ($allowed) {
            $this->metrics['allowed_total']++;
        } else {
            $this->metrics['throttled_total']++;
        }

        return [
            'allowed' => $allowed,
            'remaining' => max(0, $remainingMin),
            'reset_after' => $resetAfterMin,
            'retry_after' => $resetAfterMin,
        ];
    }

    /**
     * Check sliding window limit (strict time-window enforcement)
     * 
     * Uses Lua script for atomic operations:
     * 1. Remove entries outside window
     * 2. Count remaining entries
     * 3. If below limit, add new entry
     * 4. Return [allowed, remaining]
     */
    private function checkSlidingWindow(string $key, array $limit, int $now): array
    {
        $window = $limit['window'] ?? 60;  // seconds
        $maxAttempts = $limit['max'] ?? 5;
        $ttl = $window * 2;  // Expire keys after 2 windows for cleanup

        $script = <<<'LUA'
local key = KEYS[1]
local now = tonumber(ARGV[1])
local window = tonumber(ARGV[2])
local limit = tonumber(ARGV[3])
local ttl = tonumber(ARGV[4])

-- Remove old entries outside window
redis.call('ZREMRANGEBYSCORE', key, 0, now - window)

-- Get current count
local count = redis.call('ZCARD', key)

if count < limit then
  -- Add new entry with current timestamp as score
  redis.call('ZADD', key, now, now)
  redis.call('EXPIRE', key, ttl)
  local remaining = limit - count - 1
  return { 1, remaining, 0 }  -- [allowed, remaining, reset_after]
else
  -- Get oldest entry (to calculate reset time)
  local oldest = redis.call('ZRANGE', key, 0, 0, 'WITHSCORES')
  local reset_after = 0
  if oldest[2] then
    reset_after = math.ceil(tonumber(oldest[2]) + window - now)
  end
  return { 0, 0, reset_after }  -- [rejected, remaining, reset_after]
end
LUA;

        $result = Redis::eval($script, 1, $key, $now, $window, $maxAttempts, $ttl);

        return [
            'allowed' => (bool) $result[0],
            'remaining' => $result[1] ?? 0,
            'reset_after' => $result[2] ?? 0,
        ];
    }

    /**
     * Check token bucket limit (burst-friendly rate limiting)
     * 
     * Algorithm:
     * 1. Get current tokens + last refill time
     * 2. Calculate tokens to add based on elapsed time
     * 3. If tokens >= cost, deduct and allow
     * 4. Otherwise, reject
     */
    private function checkTokenBucket(string $key, array $limit, int $now): array
    {
        $capacity = $limit['capacity'] ?? 10;
        $refillRate = $limit['refill_rate'] ?? 1;  // tokens per second
        $cost = $limit['cost'] ?? 1;
        $ttl = 3600;  // 1 hour

        $script = <<<'LUA'
local key = KEYS[1]
local now = tonumber(ARGV[1])
local capacity = tonumber(ARGV[2])
local refill_rate = tonumber(ARGV[3])
local cost = tonumber(ARGV[4])
local ttl = tonumber(ARGV[5])

-- Get current state
local state = redis.call('HGETALL', key)
local tokens = tonumber(state[2]) or capacity
local last_refill = tonumber(state[4]) or now

-- Calculate tokens to add
local elapsed = math.max(0, now - last_refill)
local refilled = math.floor(elapsed * refill_rate)
tokens = math.min(tokens + refilled, capacity)

if tokens >= cost then
  -- Deduct cost and update state
  tokens = tokens - cost
  redis.call('HSET', key, 'tokens', tokens, 'last_refill', now)
  redis.call('EXPIRE', key, ttl)
  return { 1, tokens, 0 }  -- [allowed, remaining, reset_after]
else
  -- Calculate time until next token available
  local time_to_next = math.ceil((cost - tokens) / refill_rate)
  redis.call('HSET', key, 'tokens', tokens, 'last_refill', now)
  redis.call('EXPIRE', key, ttl)
  return { 0, 0, time_to_next }  -- [rejected, remaining, reset_after]
end
LUA;

        $result = Redis::eval($script, 1, $key, $now, $capacity, $refillRate, $cost, $ttl);

        return [
            'allowed' => (bool) $result[0],
            'remaining' => $result[1] ?? 0,
            'reset_after' => $result[2] ?? 0,
        ];
    }

    /**
     * Check rate limits using in-memory store (fallback)
     * 
     * Used when Redis is unavailable. Less accurate in multi-process
     * environments but provides graceful degradation.
     */
    private function checkWithMemory(string $key, array $limits): array
    {
        $allowed = true;
        $remainingMin = PHP_INT_MAX;
        $resetAfterMin = 0;

        foreach ($limits as $limit) {
            $storeKey = "{$key}:{$limit['type']}";
            $now = time();

            if ($limit['type'] === 'sliding_window') {
                $window = $limit['window'] ?? 60;
                $max = $limit['max'] ?? 5;
                
                // Initialize or get existing entry
                if (!isset($this->memoryStore[$storeKey])) {
                    $this->memoryStore[$storeKey] = [];
                }

                // Remove old entries
                $this->memoryStore[$storeKey] = array_filter(
                    $this->memoryStore[$storeKey],
                    fn($ts) => $ts > $now - $window
                );

                $count = count($this->memoryStore[$storeKey]);

                if ($count < $max) {
                    $this->memoryStore[$storeKey][] = $now;
                    $remaining = $max - $count - 1;
                } else {
                    $allowed = false;
                    $oldest = min($this->memoryStore[$storeKey]);
                    $resetAfterMin = max($resetAfterMin, $oldest + $window - $now);
                }
            } elseif ($limit['type'] === 'token_bucket') {
                $capacity = $limit['capacity'] ?? 10;
                $refillRate = $limit['refill_rate'] ?? 1;
                $cost = $limit['cost'] ?? 1;

                if (!isset($this->memoryStore[$storeKey])) {
                    $this->memoryStore[$storeKey] = [
                        'tokens' => $capacity,
                        'last_refill' => $now,
                    ];
                }

                $state = &$this->memoryStore[$storeKey];
                $elapsed = $now - $state['last_refill'];
                $state['tokens'] = min($state['tokens'] + ($elapsed * $refillRate), $capacity);
                $state['last_refill'] = $now;

                if ($state['tokens'] >= $cost) {
                    $state['tokens'] -= $cost;
                    $remaining = (int) $state['tokens'];
                } else {
                    $allowed = false;
                    $timeToNext = (int) ceil(($cost - $state['tokens']) / $refillRate);
                    $resetAfterMin = max($resetAfterMin, $timeToNext);
                }
            }

            $remainingMin = min($remainingMin, $remaining ?? 0);
        }

        // Prune memory store if it gets too large
        if (count($this->memoryStore) > self::MEMORY_STORE_LIMIT) {
            $this->memoryStore = array_slice($this->memoryStore, -5000, null, true);
        }

        if ($allowed) {
            $this->metrics['allowed_total']++;
        } else {
            $this->metrics['throttled_total']++;
        }

        return [
            'allowed' => $allowed,
            'remaining' => max(0, $remainingMin),
            'reset_after' => $resetAfterMin,
            'retry_after' => $resetAfterMin,
        ];
    }

    /**
     * Reset rate limit for a user/IP (admin action)
     */
    public function reset(string $key): void
    {
        try {
            if ($this->redisAvailable()) {
                Redis::del($this->buildRedisKey($key, ['type' => 'sliding_window']));
                Redis::del($this->buildRedisKey($key, ['type' => 'token_bucket']));
            }
        } catch (\Throwable $e) {
            Log::error('Failed to reset rate limit', ['key' => $key, 'error' => $e->getMessage()]);
        }

        // Reset in-memory store
        unset($this->memoryStore[$key]);
    }

    /**
     * Get current rate limit status for a key
     */
    public function getStatus(string $key, array $limits): stdClass
    {
        $now = time();
        $status = new stdClass();
        $status->key = $key;
        $status->limits = [];

        foreach ($limits as $limit) {
            $limitKey = $this->buildRedisKey($key, $limit);
            $limitStatus = new stdClass();
            $limitStatus->type = $limit['type'];
            $limitStatus->max = $limit['max'] ?? $limit['capacity'] ?? 'N/A';

            if ($limit['type'] === 'sliding_window' && $this->redisAvailable()) {
                $count = Redis::zcard($limitKey);
                $limitStatus->current = $count;
                $limitStatus->remaining = max(0, $limit['max'] - $count);
            } elseif ($limit['type'] === 'token_bucket' && $this->redisAvailable()) {
                $state = Redis::hgetall($limitKey);
                $limitStatus->current_tokens = $state['tokens'] ?? $limit['capacity'];
                $limitStatus->remaining = $limitStatus->current_tokens;
            }

            $status->limits[] = $limitStatus;
        }

        return $status;
    }

    /**
     * Get service metrics
     */
    public function getMetrics(): array
    {
        $throttledRate = $this->metrics['checks_total'] > 0
            ? ($this->metrics['throttled_total'] / $this->metrics['checks_total']) * 100
            : 0;

        return [
            'total_checks' => $this->metrics['checks_total'],
            'allowed' => $this->metrics['allowed_total'],
            'throttled' => $this->metrics['throttled_total'],
            'throttled_percentage' => round($throttledRate, 2),
            'fallback_uses' => $this->metrics['fallback_count'],
            'redis_healthy' => $this->redisHealthy,
            'memory_store_size' => count($this->memoryStore),
        ];
    }

    /**
     * Check if Redis is available (with timeout)
     */
    private function redisAvailable(): bool
    {
        try {
            $start = microtime(true);
            Redis::ping();
            $elapsed = (microtime(true) - $start) * 1000;  // ms

            if ($elapsed > self::FALLBACK_TIMEOUT) {
                Log::warning("Redis response slow for rate limiting", ['latency_ms' => $elapsed]);
            }

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Build Redis key with type and identifier
     */
    private function buildRedisKey(string $key, array $limit): string
    {
        $type = $limit['type'] ?? 'unknown';
        return self::REDIS_PREFIX . "{$key}:{$type}";
    }
}
