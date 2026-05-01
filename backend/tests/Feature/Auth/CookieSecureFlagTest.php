<?php

namespace Tests\Feature\Auth;

use App\Exceptions\EnvironmentConfigException;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CookieSecureFlagTest extends TestCase
{
    public function test_auth_cookie_is_secure_when_session_secure_cookie_is_enabled(): void
    {
        $this->setCookieSecurityConfig('production', true);

        $user = $this->createLoginUser('secure-cookie@example.com');

        $loginResponse = $this->loginWithHttpOnlyCookie($user);
        $loginResponse->assertOk();
        $this->assertAuthCookieSecureFlag($loginResponse, true);

        $token = $this->activeHttpOnlyTokenFor($user);
        $refreshResponse = $this->withHeader('Cookie', $this->requestCookieHeader($token))
            ->postJson('/api/auth/refresh-httponly');
        $refreshResponse->assertOk();
        $this->assertAuthCookieSecureFlag($refreshResponse, true);

        $token = $this->activeHttpOnlyTokenFor($user);
        $logoutResponse = $this->withHeader('Cookie', $this->requestCookieHeader($token))
            ->postJson('/api/auth/logout-httponly');
        $logoutResponse->assertOk();
        $this->assertAuthCookieSecureFlag($logoutResponse, true);

        $unifiedUser = $this->createLoginUser('secure-unified-cookie@example.com');
        $this->loginWithHttpOnlyCookie($unifiedUser)->assertOk();
        $unifiedUser->createToken('Other Device');

        $unifiedToken = $this->activeHttpOnlyTokenFor($unifiedUser);
        $unifiedLogoutAllResponse = $this->actingAs($unifiedUser)
            ->withHeader('Cookie', $this->requestCookieHeader($unifiedToken))
            ->postJson('/api/auth/unified/logout-all');
        $unifiedLogoutAllResponse->assertOk();
        $this->assertAuthCookieSecureFlag($unifiedLogoutAllResponse, true);
    }

    public function test_auth_cookie_is_not_secure_when_session_secure_cookie_is_disabled(): void
    {
        $this->setCookieSecurityConfig('local', false);

        $user = $this->createLoginUser('local-cookie@example.com');

        $response = $this->loginWithHttpOnlyCookie($user);
        $response->assertOk();

        $this->assertAuthCookieSecureFlag($response, false);
    }

    public function test_production_boot_fails_when_session_secure_cookie_is_disabled(): void
    {
        $this->setCookieSecurityConfig('production', false);

        $this->expectException(EnvironmentConfigException::class);
        $this->expectExceptionMessage('SESSION_SECURE_COOKIE must be true when APP_ENV=production.');

        (new AppServiceProvider($this->app))->boot();
    }

    private function setCookieSecurityConfig(string $environment, bool $secure): void
    {
        config([
            'app.env' => $environment,
            'session.secure' => $secure,
        ]);
    }

    private function createLoginUser(string $email): User
    {
        return User::factory()->create([
            'email' => $email,
            'password' => Hash::make('secret123'),
            'email_verified_at' => now(),
        ]);
    }

    private function loginWithHttpOnlyCookie(User $user): TestResponse
    {
        return $this->withSession([])->postJson('/api/auth/login-httponly', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);
    }

    private function activeHttpOnlyTokenFor(User $user): PersonalAccessToken
    {
        $token = PersonalAccessToken::where('tokenable_id', $user->getKey())
            ->where('name', 'httponly-web-cookie')
            ->whereNull('revoked_at')
            ->latest('created_at')
            ->first();

        if (! $token instanceof PersonalAccessToken) {
            $this->fail('Expected an active httpOnly token for the test user.');
        }

        return $token;
    }

    private function requestCookieHeader(PersonalAccessToken $token): string
    {
        return config('sanctum.cookie_name', 'soleil_token').'='.$token->token_identifier;
    }

    private function assertAuthCookieSecureFlag(TestResponse $response, bool $expectedSecure): void
    {
        $cookieHeader = $this->authCookieSetHeader($response);
        $hasSecureFlag = preg_match('/(?:^|;\s*)secure(?:;|$)/i', $cookieHeader) === 1;

        $this->assertSame(
            $expectedSecure,
            $hasSecureFlag,
            'The httpOnly auth cookie Secure flag must follow config(session.secure).'
        );
    }

    private function authCookieSetHeader(TestResponse $response): string
    {
        $cookieName = config('sanctum.cookie_name', 'soleil_token');

        foreach ($response->headers->all('set-cookie') as $cookieHeader) {
            if (str_starts_with($cookieHeader, $cookieName.'=')) {
                return $cookieHeader;
            }
        }

        $this->fail("Set-Cookie header for {$cookieName} was not present.");
    }
}
