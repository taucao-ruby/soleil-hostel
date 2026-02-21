<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * CorsHeadersTest — verifies the CORS middleware is active on API routes.
 *
 * Uses the actual cors.php config (env CORS_ALLOWED_ORIGINS, default: http://localhost:5173).
 * Only the positive cases (allowed origin receives expected headers) are tested here,
 * because the negative case (disallowed origin) requires manipulating the CORS singleton
 * after boot, which is environment-dependent.
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

        $response->assertStatus(200);
        $response->assertHeader('Access-Control-Allow-Origin', self::ALLOWED_ORIGIN);
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
        $response->assertHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
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

        $response->assertStatus(200);
        $response->assertHeader('Access-Control-Allow-Origin', self::ALLOWED_ORIGIN);
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
        $response->assertHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
    }
}
