<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * CsrfProtectionTest — TST-005
 *
 * Verifies CSRF protection configuration and that API routes correctly
 * bypass CSRF in favor of token authentication.
 *
 * NOTE: Laravel's VerifyCsrfToken middleware has a built-in runningUnitTests()
 * check that skips CSRF verification when APP_ENV=testing. Therefore behavioral
 * CSRF testing (expecting 419 responses) is not possible in standard PHPUnit tests.
 * Instead, we verify the middleware is structurally registered and that the
 * Sanctum CSRF cookie endpoint works correctly.
 */
class CsrfProtectionTest extends TestCase
{
    /**
     * VerifyCsrfToken middleware is registered in the web middleware group.
     *
     * This structural test confirms that CSRF protection is configured
     * even though the Laravel test runner skips enforcement during tests.
     */
    public function test_web_middleware_group_includes_csrf_protection(): void
    {
        $kernel = $this->app->make(\Illuminate\Contracts\Http\Kernel::class);
        $middlewareGroups = $kernel->getMiddlewareGroups();

        $this->assertArrayHasKey('web', $middlewareGroups);
        $this->assertContains(
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            $middlewareGroups['web'],
            'ValidateCsrfToken should be in the web middleware group'
        );
    }

    /**
     * API routes use token auth, not CSRF.
     * POST to a public API route works without any CSRF token.
     */
    public function test_api_routes_use_token_auth_not_csrf(): void
    {
        $response = $this->postJson('/api/contact', [
            'name' => 'CSRF Test User',
            'email' => 'csrf@example.com',
            'message' => 'Testing that API routes bypass CSRF protection.',
        ]);

        // Should succeed (201) — API routes don't require CSRF
        $response->assertStatus(201);
    }

    /**
     * GET /sanctum/csrf-cookie sets the XSRF-TOKEN cookie.
     * This is how SPA clients obtain a CSRF token for Sanctum cookie-based auth.
     */
    public function test_csrf_token_endpoint_returns_token(): void
    {
        $response = $this->get('/sanctum/csrf-cookie');

        $response->assertStatus(204);
        $response->assertCookie('XSRF-TOKEN');
    }
}
