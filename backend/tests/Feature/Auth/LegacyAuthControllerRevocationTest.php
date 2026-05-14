<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\AuthController;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Audit-trail invariant for the legacy App\Http\Controllers\AuthController:
 * logout()/refresh() must revoke (set revoked_at) instead of hard-deleting the
 * token row, so reconciliation/incident-response queries keep the trail.
 *
 * These methods are not routed (routes/api.php sends /auth/logout + /auth/refresh
 * to the v2 Auth\AuthController), so they are invoked directly here. They remain
 * shipped code until the July 2026 legacy-auth sunset.
 */
class LegacyAuthControllerRevocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_logout_revokes_current_token_without_deleting_audit_row(): void
    {
        [$user, $token] = $this->userWithCurrentToken(Str::random(40));

        $request = Request::create('/api/auth/logout', 'POST');
        $request->setUserResolver(fn () => $user);

        $response = app(AuthController::class)->logout($request);

        $this->assertSame(200, $response->getStatusCode());

        // Audit row preserved, not hard-deleted...
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->id]);

        // ...and marked revoked.
        $token->refresh();
        $this->assertNotNull($token->revoked_at);
        $this->assertTrue($token->isRevoked());
    }

    public function test_legacy_refresh_revokes_old_token_without_deleting_audit_row(): void
    {
        [$user, $oldToken] = $this->userWithCurrentToken(Str::random(40));

        $request = Request::create('/api/auth/refresh', 'POST');
        $request->setUserResolver(fn () => $user);

        $response = app(AuthController::class)->refresh($request);

        $this->assertSame(200, $response->getStatusCode());

        // Old token row preserved and marked revoked.
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $oldToken->id]);
        $oldToken->refresh();
        $this->assertNotNull($oldToken->revoked_at);

        // Existing response contract preserved: a fresh bearer token is issued.
        $payload = $response->getData(true);
        $this->assertSame('Bearer', $payload['data']['token_type']);
        $this->assertNotEmpty($payload['data']['access_token']);

        // The issued token is a distinct, active row.
        $newToken = PersonalAccessToken::where('token', hash('sha256', $payload['data']['access_token']))->first();
        $this->assertNotNull($newToken);
        $this->assertNotSame($oldToken->id, $newToken->id);
        $this->assertNull($newToken->revoked_at);
    }

    public function test_revoked_legacy_token_cannot_authenticate_after_logout(): void
    {
        $plainTextToken = Str::random(40);
        [$user] = $this->userWithCurrentToken($plainTextToken);

        $request = Request::create('/api/auth/logout', 'POST');
        $request->setUserResolver(fn () => $user);
        app(AuthController::class)->logout($request);

        // check_token_valid middleware must reject the revoked token.
        $this->getJson('/api/auth/me-v2', [
            'Authorization' => "Bearer {$plainTextToken}",
        ])
            ->assertStatus(401)
            ->assertJson(['code' => 'TOKEN_REVOKED']);
    }

    /**
     * Create a persisted user whose currentAccessToken() resolves to a fresh,
     * active token — mirroring what CheckTokenNotRevokedAndNotExpired binds.
     *
     * @return array{0: User, 1: PersonalAccessToken}
     */
    private function userWithCurrentToken(string $plainTextToken): array
    {
        $user = User::factory()->create();

        $token = $user->tokens()->create([
            'name' => 'auth_token',
            'token' => hash('sha256', $plainTextToken),
            'abilities' => ['*'],
            'expires_at' => now()->addHour(),
            'type' => 'short_lived',
            'device_id' => Str::uuid()->toString(),
            'refresh_count' => 0,
        ]);

        $user->withAccessToken($token);

        return [$user, $token];
    }
}
