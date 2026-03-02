<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * Verifies that the debug /test route has been removed (M-09).
 */
class DebugRouteTest extends TestCase
{
    public function test_debug_test_route_is_not_accessible(): void
    {
        $response = $this->get('/test');

        // Should return 404 since the route was removed
        $response->assertStatus(404);
    }

    public function test_root_web_route_returns_api_info(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Soleil Hostel API']);
    }
}
