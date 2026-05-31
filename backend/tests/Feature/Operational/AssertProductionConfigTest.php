<?php

declare(strict_types=1);

namespace Tests\Feature\Operational;

use Tests\TestCase;

final class AssertProductionConfigTest extends TestCase
{
    public function test_command_passes_when_production_runtime_config_is_secure(): void
    {
        // Comprehensive "all green" production config: production env, debug off,
        // secure session cookie, redis password set, HTTPS non-loopback APP_URL,
        // a single HTTPS allow-listed origin, no origin patterns, and a valid
        // whsec_ Stripe signing secret.
        $this->setProductionConfig(
            environment: 'production',
            debug: false,
            sessionSecure: true,
            redisPassword: 'redis-secret',
            appUrl: 'https://soleilhotel.com',
            corsAllowedOrigins: ['https://soleilhotel.com'],
            corsAllowedOriginsPatterns: [],
            stripeWebhookSecret: 'whsec_live_example_secret'
        );

        $this->artisan('app:assert-production-config')
            ->expectsOutput('Production config OK.')
            ->assertSuccessful();
    }

    public function test_command_fails_when_production_session_cookie_is_not_secure(): void
    {
        $this->setProductionConfig(sessionSecure: false);

        $this->artisan('app:assert-production-config')
            ->expectsOutput('Production configuration assertion failed.')
            ->expectsOutputToContain('SESSION_SECURE_COOKIE must be true when APP_ENV=production.')
            ->assertFailed();
    }

    public function test_command_fails_when_production_debug_is_true(): void
    {
        $this->setProductionConfig(debug: true);

        $this->artisan('app:assert-production-config')
            ->expectsOutput('Production configuration assertion failed.')
            ->expectsOutputToContain('APP_DEBUG must resolve to false in production.')
            ->expectsOutputToContain("config('app.debug'): true")
            ->assertFailed();
    }

    public function test_command_fails_when_runtime_environment_is_not_production(): void
    {
        $this->setProductionConfig(environment: 'staging');

        $this->artisan('app:assert-production-config')
            ->expectsOutput('Production configuration assertion failed.')
            ->expectsOutputToContain('APP_ENV must resolve to production for this production assertion.')
            ->assertFailed();
    }

    public function test_command_fails_when_redis_password_is_missing_in_non_local_environment(): void
    {
        $this->setProductionConfig(redisPassword: null);

        $this->artisan('app:assert-production-config')
            ->expectsOutput('Production configuration assertion failed.')
            ->expectsOutputToContain('REDIS_PASSWORD must be set in non-local environments.')
            ->assertFailed();
    }

    public function test_command_fails_when_cors_allows_localhost_origin(): void
    {
        $this->setProductionConfig(corsAllowedOrigins: ['http://localhost:5173']);

        $this->artisan('app:assert-production-config')
            ->expectsOutput('Production configuration assertion failed.')
            ->expectsOutputToContain('CORS_ALLOWED_ORIGINS must not contain localhost/loopback origins')
            ->assertFailed();
    }

    public function test_command_fails_when_cors_allows_loopback_ip_origin(): void
    {
        $this->setProductionConfig(corsAllowedOrigins: ['http://127.0.0.1:3000']);

        $this->artisan('app:assert-production-config')
            ->expectsOutput('Production configuration assertion failed.')
            ->expectsOutputToContain('CORS_ALLOWED_ORIGINS must not contain localhost/loopback origins')
            ->assertFailed();
    }

    public function test_command_fails_when_cors_allows_wildcard_origin(): void
    {
        $this->setProductionConfig(corsAllowedOrigins: ['*']);

        $this->artisan('app:assert-production-config')
            ->expectsOutput('Production configuration assertion failed.')
            ->expectsOutputToContain("CORS_ALLOWED_ORIGINS must not contain the wildcard '*'")
            ->assertFailed();
    }

    public function test_command_fails_when_cors_allows_non_https_production_origin(): void
    {
        $this->setProductionConfig(corsAllowedOrigins: ['http://soleilhotel.com']);

        $this->artisan('app:assert-production-config')
            ->expectsOutput('Production configuration assertion failed.')
            ->expectsOutputToContain('CORS_ALLOWED_ORIGINS entries must use HTTPS in production')
            ->assertFailed();
    }

    public function test_command_fails_when_stripe_webhook_secret_is_missing(): void
    {
        $this->setProductionConfig(stripeWebhookSecret: null);

        $this->artisan('app:assert-production-config')
            ->expectsOutput('Production configuration assertion failed.')
            ->expectsOutputToContain('STRIPE_WEBHOOK_SECRET (cashier.webhook.secret) must be configured')
            ->assertFailed();
    }

    /**
     * Every dimension defaults to a production-safe value so each test only has
     * to override the single setting it exercises; the shared default is itself
     * the "all green" config asserted by the passing test above.
     *
     * @param  list<string>  $corsAllowedOrigins
     * @param  list<string>  $corsAllowedOriginsPatterns
     */
    private function setProductionConfig(
        string $environment = 'production',
        bool $debug = false,
        bool $sessionSecure = true,
        ?string $redisPassword = 'redis-secret',
        string $appUrl = 'https://soleilhotel.com',
        array $corsAllowedOrigins = ['https://soleilhotel.com'],
        array $corsAllowedOriginsPatterns = [],
        ?string $stripeWebhookSecret = 'whsec_test_secret'
    ): void {
        $this->app->detectEnvironment(fn () => $environment);

        config([
            'app.env' => $environment,
            'app.debug' => $debug,
            'app.url' => $appUrl,
            'session.secure' => $sessionSecure,
            'database.redis.default.password' => $redisPassword,
            'cors.allowed_origins' => $corsAllowedOrigins,
            'cors.allowed_origins_patterns' => $corsAllowedOriginsPatterns,
            'cashier.webhook.secret' => $stripeWebhookSecret,
        ]);
    }
}
