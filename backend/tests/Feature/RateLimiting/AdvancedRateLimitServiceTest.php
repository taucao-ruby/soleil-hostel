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
    private string $testId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->limiter = app(RateLimitService::class);
        // Generate unique test ID to avoid key collisions in parallel runs
        $this->testId = uniqid('test_' . getmypid() . '_', true);
    }

    /**
     * Generate a unique key for this test execution
     */
    private function uniqueKey(string $suffix): string
    {
        return "{$this->testId}:{$suffix}";
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

        $key = $this->uniqueKey('user:1');

        // First 5 requests should be allowed
        for ($i = 0; $i < 5; $i++) {
            $result = $this->limiter->check($key, $limits);
            $this->assertTrue($result['allowed'], "Request $i should be allowed");
            $this->assertGreaterThanOrEqual(5 - $i - 1, $result['remaining']);
        }

        // 6th request should be throttled
        $result = $this->limiter->check($key, $limits);
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

        $key = $this->uniqueKey('bucket:1');

        // Should allow 10 requests in burst
        for ($i = 0; $i < 10; $i++) {
            $result = $this->limiter->check($key, $limits);
            $this->assertTrue($result['allowed'], "Burst request $i should be allowed");
        }

        // 11th should be rejected
        $result = $this->limiter->check($key, $limits);
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

        $key = $this->uniqueKey('multi:1');

        // Should be limited by sliding window (3) before token bucket (5)
        for ($i = 0; $i < 3; $i++) {
            $result = $this->limiter->check($key, $limits);
            $this->assertTrue($result['allowed']);
        }

        $result = $this->limiter->check($key, $limits);
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

        $key = $this->uniqueKey('reset:1');

        // Max out limit
        $this->limiter->check($key, $limits);
        $this->limiter->check($key, $limits);

        // Should be throttled
        $result = $this->limiter->check($key, $limits);
        $this->assertFalse($result['allowed']);

        // Reset
        $this->limiter->reset($key);

        // Should be allowed again
        $result = $this->limiter->check($key, $limits);
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

        $key = $this->uniqueKey('status:1');
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

        $key = $this->uniqueKey('metrics:1');
        $this->limiter->check($key, $limits);
        $this->limiter->check($key, $limits);
        $this->limiter->check($key, $limits);

        $metrics = $this->limiter->getMetrics();
        $this->assertGreaterThanOrEqual(3, $metrics['total_checks']);
        $this->assertGreaterThanOrEqual(1, $metrics['throttled']);
    }
}
