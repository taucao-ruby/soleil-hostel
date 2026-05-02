<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Redis-backed runtime kill switches.
 *
 * Batch 4 / 3E. Replaces config-only flags (env + container restart) with a
 * Redis layer that lets operators flip features without redeploying. Flags
 * are stored at `feature:{$key}` as the literal strings `"on"` / `"off"`.
 *
 * Read path:
 *   1. 30-second in-process cache (driver-agnostic Cache facade) — keeps load
 *      off Redis under hot paths.
 *   2. Redis connection — lookup the canonical state.
 *   3. Fallback to the caller's $default if Redis is unreachable so a Redis
 *      outage cannot suddenly enable/disable behaviour. Callers must choose
 *      a conservative default (typically false / disabled).
 *
 * Writes go through {@see set()} (artisan `feature:toggle` is the operator
 * UX over this); they purge the local cache so the next read sees the new
 * value within the cache TTL window across instances (max 30s + clock skew).
 */
class FeatureFlag
{
    /** Local cache TTL — short enough to propagate quickly, long enough to dampen Redis load. */
    public const LOCAL_CACHE_TTL_SECONDS = 30;

    private const REDIS_PREFIX = 'feature:';

    private const LOCAL_CACHE_PREFIX = 'feature_flag:local:';

    /**
     * Soft toggle resolution.
     *
     * Behaviour: read Redis; if the key is absent OR Redis is unreachable,
     * fall back to the caller-supplied $default. Suitable for operational
     * toggles where a Redis outage should NOT silently flip behaviour
     * (e.g. `booking.expire_pending` defaults to ON; we want it to keep
     * running through a Redis blip rather than stalling work).
     *
     * For "emergency disable" flags use {@see killSwitch()} instead — that
     * variant is sticky-off and survives Redis loss without resurrecting
     * a config-default ON.
     *
     * @param  string  $key  Stable identifier, e.g. "booking.expire_pending".
     * @param  bool  $default  Returned when the key has never been set OR Redis is unreachable.
     */
    public static function get(string $key, bool $default = false): bool
    {
        $localKey = self::LOCAL_CACHE_PREFIX.$key;

        $cached = Cache::get($localKey);
        if ($cached !== null) {
            return $cached === 'on';
        }

        $redisValue = self::readFromRedis($key);

        // null = key absent or Redis down. We cannot distinguish those without
        // sacrificing the fail-open contract — both fall back to $default.
        if ($redisValue === null) {
            return $default;
        }

        Cache::put($localKey, $redisValue, self::LOCAL_CACHE_TTL_SECONDS);

        return $redisValue === 'on';
    }

    /**
     * Hard kill switch — sticky-off semantics.
     *
     * Returns true ONLY when the Redis key is explicitly set to a truthy
     * value ("on", "true", "1", "enabled"). Any other state — key absent,
     * key set to "off", malformed value, or Redis unreachable — returns
     * false. There is no $default fallback: the absence of a positive
     * affirmation is itself the safe answer.
     *
     * Use this for flags whose entire purpose is "stay off unless someone
     * explicitly lit them up", or where a config-default ON would defeat
     * an operator-set runtime disable when Redis goes dark. Prime example:
     * `ai_harness.enabled` (AUTH-004 / Batch 8 platform hardening) — if an
     * operator has flipped the harness off and Redis later fails, we MUST
     * stay off rather than silently re-enabling via the env default.
     *
     * Local cache uses a separate sentinel ('absent') so we can distinguish
     * "Redis confirmed off" from "Redis confirmed absent" without an extra
     * round trip; both collapse to false at the boundary, but the cache
     * layer still avoids hammering Redis under hot paths.
     *
     * @param  string  $key  Stable identifier, e.g. "ai_harness.enabled".
     */
    public static function killSwitch(string $key): bool
    {
        $localKey = self::LOCAL_CACHE_PREFIX.$key;

        $cached = Cache::get($localKey);
        if ($cached !== null) {
            return $cached === 'on';
        }

        $redisValue = self::readFromRedis($key);

        if ($redisValue === null) {
            // Sticky-off: do NOT cache the absent/down result. Caching it would
            // smear a transient Redis outage across LOCAL_CACHE_TTL_SECONDS even
            // after Redis recovers, which is the opposite of what an operator
            // wants when re-enabling the flag mid-incident.
            return false;
        }

        Cache::put($localKey, $redisValue, self::LOCAL_CACHE_TTL_SECONDS);

        return $redisValue === 'on';
    }

    /**
     * Set a flag's state. Used by the artisan command — not intended for hot paths.
     *
     * @param  int|null  $ttlSeconds  When set, the Redis key auto-expires (operator-driven temporary toggle).
     */
    public static function set(string $key, bool $on, ?int $ttlSeconds = null): void
    {
        $value = $on ? 'on' : 'off';

        try {
            $redisKey = self::REDIS_PREFIX.$key;
            if ($ttlSeconds !== null && $ttlSeconds > 0) {
                Redis::setex($redisKey, $ttlSeconds, $value);
            } else {
                Redis::set($redisKey, $value);
            }
        } catch (\Throwable $e) {
            Log::warning('FeatureFlag::set failed to write Redis', [
                'key' => $key,
                'on' => $on,
                'ttl' => $ttlSeconds,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        // Purge local cache so the next read in this process picks up the change
        // immediately. Other processes converge within LOCAL_CACHE_TTL_SECONDS.
        Cache::forget(self::LOCAL_CACHE_PREFIX.$key);
    }

    /**
     * Delete a flag — readers fall back to the default.
     */
    public static function forget(string $key): void
    {
        try {
            Redis::del(self::REDIS_PREFIX.$key);
        } catch (\Throwable $e) {
            Log::warning('FeatureFlag::forget failed to delete Redis key', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
        Cache::forget(self::LOCAL_CACHE_PREFIX.$key);
    }

    /**
     * Read raw Redis state. Returns 'on', 'off', or null (absent / unavailable).
     */
    private static function readFromRedis(string $key): ?string
    {
        try {
            /** @var string|null $value */
            $value = Redis::get(self::REDIS_PREFIX.$key);

            if ($value === null) {
                return null;
            }

            $normalised = strtolower(trim((string) $value));

            return match ($normalised) {
                'on', 'true', '1', 'enabled' => 'on',
                'off', 'false', '0', 'disabled' => 'off',
                default => null,
            };
        } catch (\Throwable $e) {
            Log::warning('FeatureFlag::get falling back to default — Redis unreachable', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
