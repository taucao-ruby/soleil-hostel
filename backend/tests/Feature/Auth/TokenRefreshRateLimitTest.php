<?php

namespace Tests\Feature\Auth;

use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TokenRefreshRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'refresh-rate@example.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);
    }

    public function test_http_only_refresh_allows_requests_up_to_configured_hourly_limit(): void
    {
        config(['sanctum.max_token_refreshes_per_hour' => 3]);

        $this->loginHttpOnly();

        for ($i = 1; $i <= 3; $i++) {
            $this->refreshHttpOnly($this->activeToken())->assertOk();
        }

        $this->assertSame(3, (int) $this->activeToken()->refresh_count);
    }

    public function test_http_only_refresh_rejects_after_hourly_limit_without_replacement_or_counter_increment(): void
    {
        config(['sanctum.max_token_refreshes_per_hour' => 2]);

        $this->loginHttpOnly();

        for ($i = 1; $i <= 2; $i++) {
            $this->refreshHttpOnly($this->activeToken())->assertOk();
        }

        $activeBefore = $this->activeToken();
        $refreshCountBefore = (int) $activeBefore->refresh_count;
        $activeTokenCountBefore = $this->activeTokenCount();

        $blocked = $this->refreshHttpOnly($activeBefore);

        $blocked->assertStatus(429)
            ->assertJson([
                'message' => 'Too many token refresh attempts. Please try again later.',
            ]);

        $activeBefore->refresh();
        $this->assertNull($activeBefore->revoked_at);
        $this->assertSame($refreshCountBefore, (int) $activeBefore->refresh_count);
        $this->assertSame($activeTokenCountBefore, $this->activeTokenCount());
        $this->assertSame($activeBefore->getKey(), $this->activeToken()->getKey());
    }

    public function test_http_only_refresh_allows_again_after_hourly_window_resets(): void
    {
        config([
            'sanctum.max_token_refreshes_per_hour' => 1,
            'sanctum.short_lived_token_expiration_minutes' => 120,
        ]);

        $this->loginHttpOnly();

        $this->refreshHttpOnly($this->activeToken())->assertOk();
        $blockedToken = $this->activeToken();
        $this->refreshHttpOnly($blockedToken)->assertStatus(429);

        $this->travel(61)->minutes();

        $this->refreshHttpOnly($blockedToken)->assertOk();
    }

    public function test_high_lifetime_refresh_count_does_not_block_fresh_hourly_window(): void
    {
        config(['sanctum.max_token_refreshes_per_hour' => 1]);

        $this->loginHttpOnly();

        $token = $this->activeToken();
        $token->update(['refresh_count' => 999]);

        $this->refreshHttpOnly($token)->assertOk();

        $this->assertSame(1000, (int) $this->activeToken()->refresh_count);
    }

    public function test_bearer_refresh_uses_hourly_limiter_without_revoking_on_limit_breach(): void
    {
        config(['sanctum.max_token_refreshes_per_hour' => 1]);

        $plainTextToken = $this->loginBearer();

        $first = $this->refreshBearer($plainTextToken);
        $first->assertOk();

        $replacement = $first->json('data.token');
        $blocked = $this->refreshBearer($replacement);

        $blocked->assertStatus(429)
            ->assertJson([
                'message' => 'Too many token refresh attempts. Please try again later.',
            ]);

        $active = $this->activeToken();
        $this->assertNull($active->revoked_at);
        $this->assertSame(1, (int) $active->refresh_count);
        $this->assertSame(1, $this->activeTokenCount());
        $this->assertNull($blocked->json('data.token'));
    }

    public function test_sanctum_refresh_limit_config_keeps_legacy_env_fallback_and_new_key_precedence(): void
    {
        $legacyOnly = $this->loadSanctumConfigWithEnv([
            'SANCTUM_MAX_REFRESH_COUNT_PER_HOUR' => '7',
            'SANCTUM_MAX_TOKEN_REFRESHES_PER_HOUR' => null,
        ]);

        $this->assertSame(7, $legacyOnly['max_token_refreshes_per_hour']);

        $both = $this->loadSanctumConfigWithEnv([
            'SANCTUM_MAX_REFRESH_COUNT_PER_HOUR' => '7',
            'SANCTUM_MAX_TOKEN_REFRESHES_PER_HOUR' => '4',
        ]);

        $this->assertSame(4, $both['max_token_refreshes_per_hour']);
    }

    private function loginHttpOnly(): void
    {
        $this->postJson('/api/auth/login-httponly', [
            'email' => 'refresh-rate@example.com',
            'password' => 'password123',
        ])->assertOk();
    }

    private function loginBearer(): string
    {
        $response = $this->withHeader('User-Agent', 'RefreshRateTest/1.0')
            ->postJson('/api/auth/login-v2', [
                'email' => 'refresh-rate@example.com',
                'password' => 'password123',
                'device_name' => 'Refresh Rate Test',
            ]);

        $response->assertStatus(201);

        return (string) $response->json('data.token');
    }

    private function refreshHttpOnly(PersonalAccessToken $token): TestResponse
    {
        return $this->withHeader(
            'Cookie',
            config('sanctum.cookie_name', 'soleil_token').'='.$token->token_identifier
        )->postJson('/api/auth/refresh-httponly');
    }

    private function refreshBearer(string $plainTextToken): TestResponse
    {
        return $this->withHeaders([
            'Authorization' => 'Bearer '.$plainTextToken,
            'User-Agent' => 'RefreshRateTest/1.0',
        ])->postJson('/api/auth/refresh-v2');
    }

    private function activeToken(): PersonalAccessToken
    {
        return PersonalAccessToken::where('tokenable_id', $this->user->getKey())
            ->where('tokenable_type', User::class)
            ->whereNull('revoked_at')
            ->orderByDesc('id')
            ->firstOrFail();
    }

    private function activeTokenCount(): int
    {
        return PersonalAccessToken::where('tokenable_id', $this->user->getKey())
            ->where('tokenable_type', User::class)
            ->whereNull('revoked_at')
            ->count();
    }

    /**
     * @param  array<string, string|null>  $values
     * @return array<string, mixed>
     */
    private function loadSanctumConfigWithEnv(array $values): array
    {
        $keys = [
            'SANCTUM_MAX_REFRESH_COUNT_PER_HOUR',
            'SANCTUM_MAX_TOKEN_REFRESHES_PER_HOUR',
        ];
        $original = [];

        foreach ($keys as $key) {
            $original[$key] = [
                'env' => $_ENV[$key] ?? null,
                'server' => $_SERVER[$key] ?? null,
                'getenv' => getenv($key) === false ? null : getenv($key),
            ];

            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);
        }

        foreach ($values as $key => $value) {
            if ($value === null) {
                continue;
            }

            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv("{$key}={$value}");
        }

        try {
            return require base_path('config/sanctum.php');
        } finally {
            foreach ($keys as $key) {
                unset($_ENV[$key], $_SERVER[$key]);
                putenv($key);

                if ($original[$key]['env'] !== null) {
                    $_ENV[$key] = $original[$key]['env'];
                }

                if ($original[$key]['server'] !== null) {
                    $_SERVER[$key] = $original[$key]['server'];
                }

                if ($original[$key]['getenv'] !== null) {
                    putenv($key.'='.$original[$key]['getenv']);
                }
            }
        }
    }
}
