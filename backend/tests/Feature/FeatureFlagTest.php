<?php

namespace Tests\Feature;

use App\Services\FeatureFlag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * Batch 4 / 3E — Redis-backed feature flag contract.
 *
 * Tests cover:
 *   - get() returns the default when no Redis entry exists
 *   - set('on'/'off') makes get() return true/false
 *   - 30s local cache eventually converges across simulated instances
 *   - forget() drops the flag and falls back to default
 *
 * Cache propagation: a "second instance" is simulated by manipulating the
 * cache TTL with Cache::put — we cannot literally fork two PHP processes in
 * a feature test, so we exercise the contract that any process whose local
 * cache has expired sees the new Redis value within LOCAL_CACHE_TTL_SECONDS.
 */
class FeatureFlagTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Always reset the local cache — that lives in the array driver in tests
        // and never requires Redis to be present.
        Cache::forget('feature_flag:local:test.flag');
        Cache::forget('feature_flag:local:test.other');
    }

    /**
     * Skip a test if real Redis is unreachable. Mock-based tests don't need this.
     * Real-Redis tests call this at the top of the method body so they degrade
     * gracefully on dev machines without phpredis while still running in CI.
     */
    private function requireRedis(): void
    {
        try {
            Redis::ping();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Redis not available: '.$e->getMessage());
        }

        Redis::del('feature:test.flag');
        Redis::del('feature:test.other');
    }

    public function test_get_returns_default_when_flag_absent(): void
    {
        $this->requireRedis();

        $this->assertTrue(FeatureFlag::get('test.flag', true));
        $this->assertFalse(FeatureFlag::get('test.flag', false));
    }

    public function test_set_on_then_get_returns_true(): void
    {
        $this->requireRedis();

        FeatureFlag::set('test.flag', true);

        $this->assertTrue(FeatureFlag::get('test.flag', false));
    }

    public function test_set_off_then_get_returns_false(): void
    {
        $this->requireRedis();

        FeatureFlag::set('test.flag', false);

        $this->assertFalse(FeatureFlag::get('test.flag', true));
    }

    public function test_forget_falls_back_to_default(): void
    {
        $this->requireRedis();

        FeatureFlag::set('test.flag', true);
        $this->assertTrue(FeatureFlag::get('test.flag', false));

        FeatureFlag::forget('test.flag');

        $this->assertFalse(FeatureFlag::get('test.flag', false));
        $this->assertTrue(FeatureFlag::get('test.flag', true));
    }

    public function test_redis_change_propagates_within_local_cache_ttl(): void
    {
        $this->requireRedis();

        // Instance A sets flag to ON and reads it (warming its local cache).
        FeatureFlag::set('test.flag', true);
        $this->assertTrue(FeatureFlag::get('test.flag', false));

        // Instance B sets flag to OFF directly in Redis, simulating an operator
        // running `feature:toggle` against the shared Redis from another node.
        Redis::set('feature:test.flag', 'off');

        // Within the same process, Instance A's local cache may still report
        // ON until the 30-second TTL elapses — that is the documented contract.
        // Simulate the post-TTL window by clearing the local cache (any node
        // whose local cache expired sees the same convergence behavior).
        Cache::forget('feature_flag:local:test.flag');

        $this->assertFalse(
            FeatureFlag::get('test.flag', true),
            'Flag flip in Redis must be visible after local cache expires.'
        );
    }

    public function test_local_cache_ttl_is_at_most_31_seconds(): void
    {
        // Pin the TTL contract — propagation guarantee depends on this constant.
        // Pure-PHP assertion, no Redis required.
        $this->assertLessThanOrEqual(
            31,
            FeatureFlag::LOCAL_CACHE_TTL_SECONDS,
            'Local cache TTL must keep cross-instance propagation under 31 seconds.'
        );
    }

    public function test_legacy_string_values_normalize(): void
    {
        $this->requireRedis();

        Redis::set('feature:test.flag', 'true');
        Cache::forget('feature_flag:local:test.flag');
        $this->assertTrue(FeatureFlag::get('test.flag', false));

        Redis::set('feature:test.flag', '0');
        Cache::forget('feature_flag:local:test.flag');
        $this->assertFalse(FeatureFlag::get('test.flag', true));

        Redis::set('feature:test.flag', 'enabled');
        Cache::forget('feature_flag:local:test.flag');
        $this->assertTrue(FeatureFlag::get('test.flag', false));
    }

    // ========== killSwitch() — sticky-off contract ==========

    public function test_kill_switch_returns_false_when_flag_absent(): void
    {
        $this->requireRedis();

        // No Redis entry → sticky-off, regardless of any imagined "default".
        // killSwitch deliberately exposes no $default parameter.
        $this->assertFalse(FeatureFlag::killSwitch('test.flag'));
    }

    public function test_kill_switch_returns_true_when_explicitly_on(): void
    {
        $this->requireRedis();

        FeatureFlag::set('test.flag', true);

        $this->assertTrue(FeatureFlag::killSwitch('test.flag'));
    }

    public function test_kill_switch_returns_false_when_explicitly_off(): void
    {
        $this->requireRedis();

        FeatureFlag::set('test.flag', false);

        $this->assertFalse(FeatureFlag::killSwitch('test.flag'));
    }

    public function test_kill_switch_returns_false_when_redis_unreachable(): void
    {
        // Even if a config-style default would be ON, killSwitch must not
        // resurrect the flag while Redis is down. This is the load-bearing
        // invariant for AUTH-004 / Batch 8 ai_harness.enabled hardening.
        Cache::forget('feature_flag:local:test.flag');

        Redis::shouldReceive('get')
            ->with('feature:test.flag')
            ->andThrow(new \RuntimeException('Connection refused'));

        $this->assertFalse(FeatureFlag::killSwitch('test.flag'));
    }

    public function test_kill_switch_does_not_cache_absent_or_unreachable_result(): void
    {
        // A transient Redis outage must not smear "off" across the local-cache
        // TTL after Redis recovers. Two consecutive calls should both hit Redis,
        // so the operator can re-enable mid-incident without waiting 30s.
        Cache::forget('feature_flag:local:test.flag');

        Redis::shouldReceive('get')
            ->with('feature:test.flag')
            ->twice() // would fail with `once()` if the second call hit the cache
            ->andReturn(null);

        $this->assertFalse(FeatureFlag::killSwitch('test.flag'));
        $this->assertFalse(FeatureFlag::killSwitch('test.flag'));
    }

    public function test_kill_switch_caches_explicit_state(): void
    {
        // Conversely, a CONFIRMED on/off result IS cached — that is the whole
        // point of the local cache layer. We assert this contract so a future
        // refactor can't accidentally remove caching for the hot path.
        Cache::forget('feature_flag:local:test.flag');

        Redis::shouldReceive('get')
            ->with('feature:test.flag')
            ->once()
            ->andReturn('on');

        $this->assertTrue(FeatureFlag::killSwitch('test.flag'));
        $this->assertTrue(FeatureFlag::killSwitch('test.flag')); // served from cache
    }

    // ========== get() — Redis-down fallback contract ==========

    public function test_get_falls_back_to_supplied_default_when_redis_unreachable(): void
    {
        // get() retains the legacy "trust the caller's default" behaviour. Soft
        // toggles like booking.expire_pending depend on this so a Redis blip
        // does not stall the queue.
        Cache::forget('feature_flag:local:test.flag');

        Redis::shouldReceive('get')
            ->with('feature:test.flag')
            ->andThrow(new \RuntimeException('Connection refused'));

        $this->assertTrue(FeatureFlag::get('test.flag', true));
        $this->assertFalse(FeatureFlag::get('test.flag', false));
    }
}
