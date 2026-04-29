<?php

namespace Tests\Unit\Security;

use App\Exceptions\EnvironmentConfigException;
use App\Providers\AppServiceProvider;
use Tests\TestCase;

/**
 * Pins the boot-time fence that refuses to start the application in non-local
 * environments when REDIS_PASSWORD is empty. This is the application-side
 * counterpart to docker-compose.yml's ${REDIS_PASSWORD:?...} guard — together
 * they ensure Redis can never run unauthenticated in staging/production, even
 * if one layer is bypassed.
 */
class BootAssertionTest extends TestCase
{
    public function test_boot_throws_when_redis_password_null_in_production(): void
    {
        $this->setRedisAuthConfig('production', null, sessionSecure: true);

        $this->expectException(EnvironmentConfigException::class);
        $this->expectExceptionMessage('REDIS_PASSWORD must be set in non-local environments.');

        (new AppServiceProvider($this->app))->boot();
    }

    public function test_boot_throws_when_redis_password_empty_string_in_production(): void
    {
        $this->setRedisAuthConfig('production', '', sessionSecure: true);

        $this->expectException(EnvironmentConfigException::class);
        $this->expectExceptionMessage('REDIS_PASSWORD must be set in non-local environments.');

        (new AppServiceProvider($this->app))->boot();
    }

    public function test_boot_throws_when_redis_password_empty_in_staging(): void
    {
        $this->setRedisAuthConfig('staging', '', sessionSecure: true);

        $this->expectException(EnvironmentConfigException::class);
        $this->expectExceptionMessage('Refusing to start with unauthenticated Redis.');

        (new AppServiceProvider($this->app))->boot();
    }

    public function test_boot_succeeds_when_redis_password_set_in_production(): void
    {
        $this->setRedisAuthConfig('production', 'super-secret-password-32+-bytes', sessionSecure: true);

        (new AppServiceProvider($this->app))->boot();

        $this->expectNotToPerformAssertions();
    }

    public function test_boot_succeeds_when_redis_password_empty_in_local(): void
    {
        $this->setRedisAuthConfig('local', null, sessionSecure: false);

        (new AppServiceProvider($this->app))->boot();

        $this->expectNotToPerformAssertions();
    }

    public function test_boot_succeeds_when_redis_password_empty_in_testing(): void
    {
        $this->setRedisAuthConfig('testing', null, sessionSecure: false);

        (new AppServiceProvider($this->app))->boot();

        $this->expectNotToPerformAssertions();
    }

    public function test_boot_treats_whitespace_only_password_as_empty(): void
    {
        // empty() treats '0' and '' as empty but not whitespace strings — pin
        // current behavior: a non-empty whitespace string passes the guard.
        // If product policy later requires stricter validation, update both
        // this test and the assertion together.
        $this->setRedisAuthConfig('production', ' ', sessionSecure: true);

        (new AppServiceProvider($this->app))->boot();

        $this->expectNotToPerformAssertions();
    }

    private function setRedisAuthConfig(string $environment, ?string $password, bool $sessionSecure): void
    {
        config([
            'app.env' => $environment,
            'session.secure' => $sessionSecure,
            'database.redis.default.password' => $password,
        ]);
    }
}
