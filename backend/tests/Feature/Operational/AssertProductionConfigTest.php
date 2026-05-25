<?php

declare(strict_types=1);

namespace Tests\Feature\Operational;

use Tests\TestCase;

final class AssertProductionConfigTest extends TestCase
{
    public function test_command_passes_when_production_runtime_config_is_secure(): void
    {
        $this->setProductionConfig(
            environment: 'production',
            debug: false,
            sessionSecure: true,
            redisPassword: 'redis-secret'
        );

        $this->artisan('app:assert-production-config')
            ->expectsOutput('Production config OK.')
            ->assertSuccessful();
    }

    public function test_command_fails_when_production_session_cookie_is_not_secure(): void
    {
        $this->setProductionConfig(
            environment: 'production',
            debug: false,
            sessionSecure: false,
            redisPassword: 'redis-secret'
        );

        $this->artisan('app:assert-production-config')
            ->expectsOutput('Production configuration assertion failed.')
            ->expectsOutputToContain('SESSION_SECURE_COOKIE must be true when APP_ENV=production.')
            ->assertFailed();
    }

    public function test_command_fails_when_production_debug_is_true(): void
    {
        $this->setProductionConfig(
            environment: 'production',
            debug: true,
            sessionSecure: true,
            redisPassword: 'redis-secret'
        );

        $this->artisan('app:assert-production-config')
            ->expectsOutput('Production configuration assertion failed.')
            ->expectsOutputToContain('APP_DEBUG must resolve to false in production.')
            ->expectsOutputToContain("config('app.debug'): true")
            ->assertFailed();
    }

    public function test_command_fails_when_runtime_environment_is_not_production(): void
    {
        $this->setProductionConfig(
            environment: 'staging',
            debug: false,
            sessionSecure: true,
            redisPassword: 'redis-secret'
        );

        $this->artisan('app:assert-production-config')
            ->expectsOutput('Production configuration assertion failed.')
            ->expectsOutputToContain('APP_ENV must resolve to production for this production assertion.')
            ->assertFailed();
    }

    public function test_command_fails_when_redis_password_is_missing_in_non_local_environment(): void
    {
        $this->setProductionConfig(
            environment: 'production',
            debug: false,
            sessionSecure: true,
            redisPassword: null
        );

        $this->artisan('app:assert-production-config')
            ->expectsOutput('Production configuration assertion failed.')
            ->expectsOutputToContain('REDIS_PASSWORD must be set in non-local environments.')
            ->assertFailed();
    }

    private function setProductionConfig(
        string $environment,
        bool $debug,
        bool $sessionSecure,
        ?string $redisPassword
    ): void {
        $this->app->detectEnvironment(fn () => $environment);

        config([
            'app.env' => $environment,
            'app.debug' => $debug,
            'session.secure' => $sessionSecure,
            'database.redis.default.password' => $redisPassword,
        ]);
    }
}
