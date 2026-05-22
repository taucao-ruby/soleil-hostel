<?php

declare(strict_types=1);

namespace Tests\Feature\Operational;

use Tests\TestCase;

final class AssertProductionConfigTest extends TestCase
{
    public function test_command_passes_when_production_runtime_config_is_secure(): void
    {
        $this->setProductionConfig(environment: 'production', sessionSecure: true, redisPassword: 'redis-secret');

        $this->artisan('app:assert-production-config')
            ->expectsOutput('Production config OK.')
            ->assertSuccessful();
    }

    public function test_command_fails_when_production_session_cookie_is_not_secure(): void
    {
        $this->setProductionConfig(environment: 'production', sessionSecure: false, redisPassword: 'redis-secret');

        $this->artisan('app:assert-production-config')
            ->expectsOutput('Config assertion failed: SESSION_SECURE_COOKIE must be true when APP_ENV=production.')
            ->assertFailed();
    }

    public function test_command_fails_when_redis_password_is_missing_in_non_local_environment(): void
    {
        $this->setProductionConfig(environment: 'staging', sessionSecure: true, redisPassword: null);

        $this->artisan('app:assert-production-config')
            ->expectsOutput('Config assertion failed: REDIS_PASSWORD must be set in non-local environments.')
            ->assertFailed();
    }

    private function setProductionConfig(string $environment, bool $sessionSecure, ?string $redisPassword): void
    {
        config([
            'app.env' => $environment,
            'session.secure' => $sessionSecure,
            'database.redis.default.password' => $redisPassword,
        ]);
    }
}
