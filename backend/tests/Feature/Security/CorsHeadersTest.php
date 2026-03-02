<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * CorsHeadersTest — verifies CORS is active on API routes.
 *
 * Uses Laravel's built-in HandleCors via config/cors.php.
 * Preflight responses return 204 (standard for HandleCors).
 */
class CorsHeadersTest extends TestCase
{
    /** Primary origin whitelisted in cors.php via CORS_ALLOWED_ORIGINS env. */
    private const ALLOWED_ORIGIN = 'http://localhost:5173';

    public function test_locations_endpoint_sets_cors_headers_for_allowed_origin(): void
    {
        $response = $this
            ->withHeaders(['Origin' => self::ALLOWED_ORIGIN])
            ->getJson('/api/locations');

        $response->assertHeader('Access-Control-Allow-Origin', self::ALLOWED_ORIGIN);
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_locations_preflight_options_returns_cors_headers_for_allowed_origin(): void
    {
        $response = $this
            ->withHeaders([
                'Origin' => self::ALLOWED_ORIGIN,
                'Access-Control-Request-Method' => 'GET',
                'Access-Control-Request-Headers' => 'Content-Type, X-XSRF-TOKEN',
            ])
            ->options('/api/locations');

        // Laravel's built-in HandleCors returns 204 for preflight
        $response->assertStatus(204);
        $response->assertHeader('Access-Control-Allow-Origin', self::ALLOWED_ORIGIN);
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_login_httponly_preflight_options_returns_cors_headers_for_allowed_origin(): void
    {
        $response = $this
            ->withHeaders([
                'Origin' => self::ALLOWED_ORIGIN,
                'Access-Control-Request-Method' => 'POST',
                'Access-Control-Request-Headers' => 'Content-Type, X-XSRF-TOKEN, X-CSRF-TOKEN',
            ])
            ->options('/api/auth/login-httponly');

        $response->assertStatus(204);
        $response->assertHeader('Access-Control-Allow-Origin', self::ALLOWED_ORIGIN);
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_disallowed_origin_does_not_receive_cors_headers(): void
    {
        $response = $this
            ->withHeaders(['Origin' => 'https://evil.example.com'])
            ->getJson('/api/locations');

        $response->assertHeaderMissing('Access-Control-Allow-Origin');
    }
}
