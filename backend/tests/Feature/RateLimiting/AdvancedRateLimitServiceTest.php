<?php

namespace Tests\Feature\RateLimiting;

use Tests\TestCase;
use App\Models\User;
use App\Models\Room;
use App\Services\RateLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdvancedRateLimitServiceTest extends TestCase
{
    use RefreshDatabase;

    private RateLimitService $limiter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->limiter = app(RateLimitService::class);
    }

    public function test_sliding_window_allows_within_limit(): void
    {
        $limits = [
            [
                'type' => 'sliding_window',
                'window' => 60,
                'max' => 5,
            ],
        ];

        // First 5 requests should be allowed
        for ($i = 0; $i < 5; $i++) {
            $result = $this->limiter->check('test:user:1', $limits);
            $this->assertTrue($result['allowed'], "Request $i should be allowed");
            $this->assertGreaterThanOrEqual(5 - $i - 1, $result['remaining']);
        }

        // 6th request should be throttled
        $result = $this->limiter->check('test:user:1', $limits);
        $this->assertFalse($result['allowed'], '6th request should be throttled');
        $this->assertEquals(0, $result['remaining']);
        $this->assertGreaterThan(0, $result['retry_after']);
    }

    public function test_token_bucket_allows_bursts(): void
    {
        $limits = [
            [
                'type' => 'token_bucket',
                'capacity' => 10,
                'refill_rate' => 1,
                'cost' => 1,
            ],
        ];

        // Should allow 10 requests in burst
        for ($i = 0; $i < 10; $i++) {
            $result = $this->limiter->check('test:bucket:1', $limits);
            $this->assertTrue($result['allowed'], "Burst request $i should be allowed");
        }

        // 11th should be rejected
        $result = $this->limiter->check('test:bucket:1', $limits);
        $this->assertFalse($result['allowed']);
    }

    public function test_multiple_limits_all_must_pass(): void
    {
        $limits = [
            [
                'type' => 'sliding_window',
                'window' => 60,
                'max' => 3,
            ],
            [
                'type' => 'token_bucket',
                'capacity' => 5,
                'refill_rate' => 1,
                'cost' => 1,
            ],
        ];

        // Should be limited by sliding window (3) before token bucket (5)
        for ($i = 0; $i < 3; $i++) {
            $result = $this->limiter->check('test:multi:1', $limits);
            $this->assertTrue($result['allowed']);
        }

        $result = $this->limiter->check('test:multi:1', $limits);
        $this->assertFalse($result['allowed']);
    }

    public function test_reset_clears_limit(): void
    {
        $limits = [
            [
                'type' => 'sliding_window',
                'window' => 60,
                'max' => 2,
            ],
        ];

        // Max out limit
        $this->limiter->check('test:reset:1', $limits);
        $this->limiter->check('test:reset:1', $limits);

        // Should be throttled
        $result = $this->limiter->check('test:reset:1', $limits);
        $this->assertFalse($result['allowed']);

        // Reset
        $this->limiter->reset('test:reset:1');

        // Should be allowed again
        $result = $this->limiter->check('test:reset:1', $limits);
        $this->assertTrue($result['allowed']);
    }

    public function test_status_returns_current_state(): void
    {
        $limits = [
            [
                'type' => 'sliding_window',
                'window' => 60,
                'max' => 5,
            ],
        ];

        $key = 'test:status:1';
        $this->limiter->check($key, $limits);
        $this->limiter->check($key, $limits);

        $status = $this->limiter->getStatus($key, $limits);
        $this->assertEquals($key, $status->key);
        $this->assertNotEmpty($status->limits);
    }

    public function test_metrics_track_requests(): void
    {
        $limits = [
            [
                'type' => 'sliding_window',
                'window' => 60,
                'max' => 2,
            ],
        ];

        $this->limiter->check('test:metrics:1', $limits);
        $this->limiter->check('test:metrics:1', $limits);
        $this->limiter->check('test:metrics:1', $limits);

        $metrics = $this->limiter->getMetrics();
        $this->assertGreaterThan(0, $metrics['total_checks']);
        $this->assertGreaterThan(0, $metrics['throttled']);
    }
}
