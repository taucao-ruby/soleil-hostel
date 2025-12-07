<?php

namespace Tests\Feature\RateLimiting;

use Tests\TestCase;
use App\Models\User;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdvancedRateLimitMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->room = Room::factory()->create();

        // Register test routes with rate limits
        $this->registerTestRoutes();
    }

    private function registerTestRoutes(): void
    {
        \Illuminate\Support\Facades\Route::post('/api/test/login', function () {
            return response()->json(['success' => true]);
        })->middleware('rate-limit:sliding:5:60');

        \Illuminate\Support\Facades\Route::post('/api/test/booking', function () {
            return response()->json(['success' => true]);
        })->middleware('auth:sanctum')
           ->middleware('rate-limit:sliding:3:60,token:20:1');
    }

    public function test_middleware_allows_requests_within_limit(): void
    {
        $response = $this->post('/api/test/login', ['email' => 'test@example.com']);
        $this->assertEquals(200, $response->status());
        $this->assertTrue($response->json('success'));
    }

    public function test_middleware_returns_429_when_limit_exceeded(): void
    {
        // Make 5 requests (at limit)
        for ($i = 0; $i < 5; $i++) {
            $this->post('/api/test/login', ['email' => 'test@example.com']);
        }

        // 6th request should be throttled
        $response = $this->post('/api/test/login', ['email' => 'test@example.com']);
        $this->assertEquals(429, $response->status());
    }

    public function test_middleware_includes_retry_after_header(): void
    {
        // Max out limit
        for ($i = 0; $i < 5; $i++) {
            $this->post('/api/test/login', ['email' => 'test@example.com']);
        }

        // Should be throttled with retry-after
        $response = $this->post('/api/test/login', ['email' => 'test@example.com']);
        $this->assertEquals(429, $response->status());
        $this->assertNotNull($response->header('Retry-After'));
        $this->assertGreaterThan(0, (int) $response->header('Retry-After'));
    }

    public function test_middleware_includes_rate_limit_headers(): void
    {
        $response = $this->post('/api/test/login', ['email' => 'test@example.com']);

        $this->assertNotNull($response->header('X-RateLimit-Limit'));
        $this->assertNotNull($response->header('X-RateLimit-Remaining'));
        $this->assertNotNull($response->header('X-RateLimit-Reset'));
    }

    public function test_different_users_have_separate_limits(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // User 1 makes 3 requests
        for ($i = 0; $i < 3; $i++) {
            $response = $this->actingAs($user1, 'sanctum')
                ->post('/api/test/booking', ['room_id' => $this->room->id]);
            $this->assertEquals(200, $response->status());
        }

        // User 2 should still be able to make requests
        $response = $this->actingAs($user2, 'sanctum')
            ->post('/api/test/booking', ['room_id' => $this->room->id]);
        $this->assertNotEquals(429, $response->status());
    }

    public function test_authenticated_user_gets_higher_limits(): void
    {
        // Premium user (if implemented)
        $premiumUser = User::factory()->create(['subscription_tier' => 'premium']);

        // Should allow more requests
        for ($i = 0; $i < 9; $i++) {
            $response = $this->actingAs($premiumUser, 'sanctum')
                ->post('/api/test/booking', ['room_id' => $this->room->id]);
            $this->assertEquals(200, $response->status());
        }
    }
}
