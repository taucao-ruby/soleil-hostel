<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;
    
    // Default headers container used by tests when manipulating headers
    protected array $headers = [];

    protected $withoutMiddleware = [
        \App\Http\Middleware\VerifyCsrfToken::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Override actingAs to support Sanctum token authentication
     */
    public function actingAs($user, $guard = null)
    {
        if (!$user) {
            // Clear any test headers and logout the auth guard to simulate unauthenticated requests
            $this->headers = [];
            $this->withHeaders([]);
            try {
                // Logout web guard and clear current user resolver
                if (auth()->guard('web')->check()) {
                    auth()->guard('web')->logout();
                }
                auth()->setUser(null);
            } catch (\Throwable $e) {
                // Ignore if logout is not available in this test context
            }

            return $this;
        }
        if ($guard === 'sanctum') {
            // Create a Sanctum token and add it to request headers
            $token = $user->createToken('test-token');
            $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken);
            // Also call parent actingAs to set up session-based auth context
            // This ensures auth() and authorize() work properly in the controller
            return parent::actingAs($user, 'web');
        }
        // For other guards, use parent implementation
        return parent::actingAs($user, $guard);
    }

    /**
     * Call artisan and suppress prompts for migrate:fresh
     */
    public function artisan($command, $parameters = [])
    {
        // Add both --force and --no-interaction flags for migrate:fresh to suppress prompts
        if ($command === 'migrate:fresh') {
            if (!isset($parameters['--no-interaction'])) {
                $parameters['--no-interaction'] = true;
            }
            if (!isset($parameters['--force'])) {
                $parameters['--force'] = true;
            }
        }
        
        return parent::artisan($command, $parameters);
    }

    protected function disableExceptionHandling()
    {
        $this->withoutExceptionHandling();
    }

    /**
     * Set an httpOnly cookie for the next request
     * Properly handles how Laravel test framework processes cookies
     */
    protected function withHttpOnlyCookie($name, $value)
    {
        return $this->withCookie($name, $value);
    }
}
