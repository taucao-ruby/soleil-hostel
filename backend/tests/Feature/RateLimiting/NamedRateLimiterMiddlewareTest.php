<?php

namespace Tests\Feature\RateLimiting;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class NamedRateLimiterMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_sensitive_routes_use_named_rate_limiters(): void
    {
        $this->assertRouteHasMiddleware('auth.login', 'throttle:login');
        $this->assertRouteHasMiddleware('auth.login-v2', 'throttle:login');
        $this->assertRouteHasMiddleware('auth.login-httponly', 'throttle:login');

        $this->assertRouteHasMiddleware('auth.refresh', 'throttle:refresh-token');
        $this->assertRouteHasMiddleware('auth.refresh-v2', 'throttle:refresh-token');
        $this->assertRouteHasMiddleware('auth.refresh-httponly', 'throttle:refresh-token');

        $this->assertRouteHasMiddleware('bookings.store', 'throttle:booking');
        $this->assertRouteHasMiddleware('v1.bookings.store', 'throttle:booking');
    }

    public function test_refresh_httponly_throttles_invalid_attempts_before_token_lookup(): void
    {
        $ip = '203.0.113.41';

        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->postJson('/api/auth/refresh-httponly')
                ->assertUnauthorized();
        }

        $response = $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/auth/refresh-httponly');

        $response->assertStatus(429);
        $this->assertNotNull($response->headers->get('Retry-After'));
    }

    public function test_bearer_refresh_throttles_invalid_attempts_before_token_lookup(): void
    {
        $ip = '203.0.113.44';

        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->postJson('/api/auth/refresh-v2')
                ->assertUnauthorized();
        }

        $response = $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/auth/refresh-v2');

        $response->assertStatus(429);
        $this->assertNotNull($response->headers->get('Retry-After'));
    }

    public function test_login_named_limiter_blocks_repeated_failed_attempts(): void
    {
        User::factory()->create([
            'email' => 'named-login-limit@example.com',
            'password' => Hash::make('password123'),
        ]);

        $ip = '203.0.113.42';

        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->postJson('/api/auth/login-v2', [
                    'email' => 'named-login-limit@example.com',
                    'password' => 'wrongpassword',
                    'device_name' => 'Rate Limit Test',
                ])
                ->assertUnauthorized();
        }

        $response = $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/auth/login-v2', [
                'email' => 'named-login-limit@example.com',
                'password' => 'wrongpassword',
                'device_name' => 'Rate Limit Test',
            ]);

        $response->assertStatus(429);
        $this->assertNotNull($response->headers->get('Retry-After'));
    }

    public function test_booking_named_limiter_blocks_authenticated_creation_attempts(): void
    {
        $user = User::factory()->create();
        $ip = '203.0.113.43';

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user, 'sanctum')
                ->withServerVariables(['REMOTE_ADDR' => $ip])
                ->postJson('/api/v1/bookings', [])
                ->assertUnprocessable();
        }

        $response = $this->actingAs($user, 'sanctum')
            ->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/v1/bookings', []);

        $response->assertStatus(429);
        $this->assertNotNull($response->headers->get('Retry-After'));
    }

    private function assertRouteHasMiddleware(string $routeName, string $expectedMiddleware): void
    {
        $route = Route::getRoutes()->getByName($routeName);

        $this->assertNotNull($route, "Route [{$routeName}] must exist.");
        $this->assertContains($expectedMiddleware, $route->gatherMiddleware());
    }
}
