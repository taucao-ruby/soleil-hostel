<?php

namespace Tests\Unit\RateLimiting;

use App\Services\RateLimitService;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

class AdvancedRateLimitServiceTest extends UnitTestCase
{
    private RateLimitService $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create fresh service for each test (uses memory fallback if Redis unavailable)
        $this->service = new RateLimitService();
    }

    #[Test]
    public function sliding_window_allows_within_limit(): void
    {
        $key = 'test:login:' . fake()->ipv4();
        $limits = [
            [
                'type' => 'sliding_window',
                'max' => 5,
                'window' => 60,
            ]
        ];
        
        for ($i = 0; $i < 5; $i++) {
            $result = $this->service->check($key, $limits);
            $this->assertTrue($result['allowed']);
        }
    }

    #[Test]
    public function sliding_window_blocks_exceeding_limit(): void
    {
        $key = 'test:login:' . fake()->ipv4();
        $limits = [
            [
                'type' => 'sliding_window',
                'max' => 3,
                'window' => 60,
            ]
        ];
        
        // First 3 should pass
        for ($i = 0; $i < 3; $i++) {
            $result = $this->service->check($key, $limits);
            $this->assertTrue($result['allowed']);
        }
        
        // 4th should fail
        $result = $this->service->check($key, $limits);
        $this->assertFalse($result['allowed']);
        $this->assertGreaterThan(0, $result['retry_after']);
    }

    #[Test]
    public function token_bucket_allows_bursts(): void
    {
        $key = 'test:api:' . fake()->ipv4();
        $limits = [
            [
                'type' => 'token_bucket',
                'capacity' => 20,
                'refill_rate' => 2,
            ]
        ];
        
        // First 20 requests should succeed (consuming all tokens)
        for ($i = 0; $i < 20; $i++) {
            $result = $this->service->check($key, $limits);
            $this->assertTrue($result['allowed']);
        }
        
        // 21st should fail (no tokens available)
        $result = $this->service->check($key, $limits);
        $this->assertFalse($result['allowed']);
    }

    #[Test]
    public function multiple_limits_all_must_pass(): void
    {
        $key = 'test:booking:user:1';
        $limits = [
            [
                'id' => 'per_minute',
                'type' => 'sliding_window',
                'max' => 10,
                'window' => 60,
            ],
            [
                'id' => 'per_hour',
                'type' => 'sliding_window',
                'max' => 50,
                'window' => 3600,
            ],
        ];
        
        // 10 requests should pass all limits
        for ($i = 0; $i < 10; $i++) {
            $result = $this->service->check($key, $limits);
            $this->assertTrue($result['allowed']);
        }
        
        // 11th fails the first limit
        $result = $this->service->check($key, $limits);
        $this->assertFalse($result['allowed']);
    }

    #[Test]
    public function reset_clears_limit(): void
    {
        $key = 'test:reset:' . fake()->ipv4();
        $limits = [
            [
                'type' => 'sliding_window',
                'max' => 5,
                'window' => 60,
            ]
        ];
        
        // Fill the limit
        for ($i = 0; $i < 5; $i++) {
            $this->service->check($key, $limits);
        }
        
        // Should be blocked
        $result = $this->service->check($key, $limits);
        $this->assertFalse($result['allowed']);
        
        // Reset the key
        $this->service->reset($key);
        
        // Should be allowed again (memory fallback works)
        $result = $this->service->check($key, $limits);
        $this->assertTrue($result['allowed']);
    }

    #[Test]
    public function status_returns_current_state(): void
    {
        $key = 'test:status:' . fake()->ipv4();
        $limits = [
            [
                'type' => 'sliding_window',
                'max' => 5,
                'window' => 60,
            ]
        ];
        
        // Make 2 requests
        $this->service->check($key, $limits);
        $this->service->check($key, $limits);
        
        // Check status
        $status = $this->service->getStatus($key, $limits);
        
        $this->assertIsObject($status);
        $this->assertEquals($key, $status->key);
    }

    #[Test]
    public function metrics_track_requests(): void
    {
        $limits = [
            [
                'type' => 'sliding_window',
                'max' => 10,
                'window' => 60,
            ]
        ];
        
        // Make some requests
        for ($i = 0; $i < 3; $i++) {
            $this->service->check('test:metrics:key', $limits);
        }
        
        // Get metrics
        $metrics = $this->service->getMetrics();
        
        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('total_checks', $metrics);
        $this->assertArrayHasKey('allowed', $metrics);
        $this->assertArrayHasKey('throttled', $metrics);
    }

    #[Test]
    public function composite_key_building(): void
    {
        $user_id = 1;
        $ip = '192.168.1.1';
        $room_id = 100;
        $endpoint = 'booking.create';
        
        $key = "user:{$user_id}:ip:{$ip}:room:{$room_id}:endpoint:{$endpoint}";
        $limits = [
            [
                'type' => 'sliding_window',
                'max' => 3,
                'window' => 60,
            ]
        ];
        
        // 3 requests should work
        for ($i = 0; $i < 3; $i++) {
            $result = $this->service->check($key, $limits);
            $this->assertTrue($result['allowed']);
        }
        
        // 4th should fail
        $result = $this->service->check($key, $limits);
        $this->assertFalse($result['allowed']);
    }

    #[Test]
    public function degradation_to_memory_fallback(): void
    {
        $key = 'test:fallback:' . fake()->ipv4();
        $limits = [
            [
                'type' => 'sliding_window',
                'max' => 5,
                'window' => 60,
            ]
        ];
        
        // Under normal conditions (array cache), it should work
        $result = $this->service->check($key, $limits);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('allowed', $result);
        $this->assertArrayHasKey('remaining', $result);
    }
}
