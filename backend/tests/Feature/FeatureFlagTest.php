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

        // Skip if Redis is unreachable in this environment (CI provides Redis;
        // local sometimes does not). Other test files already follow this pattern.
        try {
            Redis::ping();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Redis not available: '.$e->getMessage());
        }

        Redis::del('feature:test.flag');
        Redis::del('feature:test.other');
        Cache::forget('feature_flag:local:test.flag');
        Cache::forget('feature_flag:local:test.other');
    }

    public function test_get_returns_default_when_flag_absent(): void
    {
        $this->assertTrue(FeatureFlag::get('test.flag', true));
        $this->assertFalse(FeatureFlag::get('test.flag', false));
    }

    public function test_set_on_then_get_returns_true(): void
    {
        FeatureFlag::set('test.flag', true);

        $this->assertTrue(FeatureFlag::get('test.flag', false));
    }

    public function test_set_off_then_get_returns_false(): void
    {
        FeatureFlag::set('test.flag', false);

        $this->assertFalse(FeatureFlag::get('test.flag', true));
    }

    public function test_forget_falls_back_to_default(): void
    {
        FeatureFlag::set('test.flag', true);
        $this->assertTrue(FeatureFlag::get('test.flag', false));

        FeatureFlag::forget('test.flag');

        $this->assertFalse(FeatureFlag::get('test.flag', false));
        $this->assertTrue(FeatureFlag::get('test.flag', true));
    }

    public function test_redis_change_propagates_within_local_cache_ttl(): void
    {
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
        $this->assertLessThanOrEqual(
            31,
            FeatureFlag::LOCAL_CACHE_TTL_SECONDS,
            'Local cache TTL must keep cross-instance propagation under 31 seconds.'
        );
    }

    public function test_legacy_string_values_normalize(): void
    {
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
}
