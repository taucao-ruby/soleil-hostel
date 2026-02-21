<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class CorsHeadersTest extends TestCase
{
    public function test_locations_endpoint_sets_cors_headers_for_allowed_origin(): void
    {
        Config::set('cors.allowed_origins', ['http://172.16.0.2:5173']);
        Config::set('cors.allowed_origins_patterns', []);

        $response = $this
            ->withHeaders(['Origin' => 'http://172.16.0.2:5173'])
            ->getJson('/api/locations');

        $response->assertHeader('Access-Control-Allow-Origin', 'http://172.16.0.2:5173');
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
        $response->assertHeader('Vary', 'Origin');
    }

    public function test_locations_endpoint_does_not_set_allow_origin_for_disallowed_origin(): void
    {
        Config::set('cors.allowed_origins', ['http://localhost:5173']);
        Config::set('cors.allowed_origins_patterns', []);

        $response = $this
            ->withHeaders(['Origin' => 'http://172.16.0.2:5173'])
            ->getJson('/api/locations');

        $response->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_locations_preflight_options_returns_cors_headers_for_allowed_origin(): void
    {
        Config::set('cors.allowed_origins', ['http://172.16.0.2:5173']);
        Config::set('cors.allowed_origins_patterns', []);

        $response = $this
            ->withHeaders([
                'Origin' => 'http://172.16.0.2:5173',
                'Access-Control-Request-Method' => 'GET',
                'Access-Control-Request-Headers' => 'Content-Type, X-XSRF-TOKEN',
            ])
            ->options('/api/locations');

        $response->assertStatus(200);
        $response->assertHeader('Access-Control-Allow-Origin', 'http://172.16.0.2:5173');
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
        $response->assertHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->assertHeader('Vary', 'Origin');
    }

    public function test_login_httponly_preflight_options_returns_cors_headers_for_allowed_origin(): void
    {
        Config::set('cors.allowed_origins', ['http://172.16.0.2:5173']);
        Config::set('cors.allowed_origins_patterns', []);

        $response = $this
            ->withHeaders([
                'Origin' => 'http://172.16.0.2:5173',
                'Access-Control-Request-Method' => 'POST',
                'Access-Control-Request-Headers' => 'Content-Type, X-XSRF-TOKEN, X-CSRF-TOKEN',
            ])
            ->options('/api/auth/login-httponly');

        $response->assertStatus(200);
        $response->assertHeader('Access-Control-Allow-Origin', 'http://172.16.0.2:5173');
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
        $response->assertHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->assertHeader('Vary', 'Origin');
    }
}
