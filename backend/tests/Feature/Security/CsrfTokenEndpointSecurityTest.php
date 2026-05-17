<?php

namespace Tests\Feature\Security;

use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class CsrfTokenEndpointSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_anonymous_request_to_supplementary_csrf_token_is_denied(): void
    {
        $this->getJson('/api/auth/csrf-token')
            ->assertStatus(401);
    }

    public function test_authenticated_cookie_request_can_fetch_supplementary_csrf_token(): void
    {
        $user = User::factory()->create([
            'email' => 'csrf-cookie@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->postJson('/api/auth/login-httponly', [
            'email' => 'csrf-cookie@example.com',
            'password' => 'password123',
        ])->assertStatus(200);

        $token = PersonalAccessToken::where('tokenable_id', $user->id)->firstOrFail();
        $cookieName = config('sanctum.cookie_name', 'soleil_token');

        $response = $this
            ->withSession([])
            ->withHeader('Cookie', "{$cookieName}={$token->token_identifier}")
            ->getJson('/api/auth/csrf-token');

        $response->assertStatus(200);
        $response->assertJsonStructure(['csrf_token']);
        $this->assertNotEmpty($response->json('csrf_token'));
    }

    public function test_supplementary_csrf_token_response_is_not_cacheable(): void
    {
        $user = User::factory()->create();
        $plainTextToken = $this->plainTextTokenFor($user);

        $response = $this
            ->withSession([])
            ->withHeader('Authorization', "Bearer {$plainTextToken}")
            ->getJson('/api/auth/csrf-token');

        $response->assertStatus(200);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control', ''));
        $response->assertHeader('Pragma', 'no-cache');
    }

    public function test_supplementary_csrf_token_rate_limiter_applies_to_authenticated_user(): void
    {
        $user = User::factory()->create();
        $plainTextToken = $this->plainTextTokenFor($user);
        RateLimiter::clear('csrf-token:user:'.$user->getAuthIdentifier());

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this
                ->withSession([])
                ->withHeader('Authorization', "Bearer {$plainTextToken}")
                ->getJson('/api/auth/csrf-token')
                ->assertStatus(200);
        }

        $this
            ->withSession([])
            ->withHeader('Authorization', "Bearer {$plainTextToken}")
            ->getJson('/api/auth/csrf-token')
            ->assertStatus(429);

        RateLimiter::clear('csrf-token:user:'.$user->getAuthIdentifier());
    }

    public function test_sanctum_csrf_cookie_bootstrap_remains_public(): void
    {
        $response = $this->get('/sanctum/csrf-cookie');

        $response->assertStatus(204);
        $response->assertCookie('XSRF-TOKEN');
    }

    private function plainTextTokenFor(User $user): string
    {
        return $user
            ->createToken('csrf-token-security-test', ['*'], now()->addHour())
            ->plainTextToken;
    }
}
