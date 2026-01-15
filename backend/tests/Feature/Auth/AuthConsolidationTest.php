<?php

namespace Tests\Feature\Auth;

use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AuthConsolidationTest - Tests for auth endpoint consolidation
 * 
 * Tests:
 * 1. Deprecation headers on legacy endpoints
 * 2. Unified endpoint mode detection
 * 3. Unified /auth/unified/me works for both modes
 * 4. Unified /auth/unified/logout works for both modes
 * 5. Unified /auth/unified/logout-all works for both modes
 * 6. Backward compatibility of legacy endpoints
 */
class AuthConsolidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);
    }

    // ========== DEPRECATION HEADER TESTS ==========

    /**
     * Test 1: Legacy /auth/login returns deprecation headers
     */
    public function test_legacy_login_returns_deprecation_headers(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        // Should still work (backward compatible)
        $response->assertSuccessful();

        // Should have deprecation headers
        $response->assertHeader('Deprecation');
        $response->assertHeader('Sunset');
        $response->assertHeader('X-Deprecation-Notice');
        $response->assertHeader('Link');

        // Verify Link header points to successor
        $this->assertStringContainsString('/api/auth/login-v2', $response->headers->get('Link'));
        $this->assertStringContainsString('successor-version', $response->headers->get('Link'));
    }

    /**
     * Test 2: Legacy /auth/logout returns deprecation headers
     */
    public function test_legacy_logout_returns_deprecation_headers(): void
    {
        // Login first to get token
        $loginResponse = $this->postJson('/api/auth/login-v2', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);
        $token = $loginResponse->json('token');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/logout');

        // Should still work
        $response->assertSuccessful();

        // Should have deprecation headers
        $response->assertHeader('Deprecation');
        $response->assertHeader('Sunset');
        $response->assertHeader('X-Deprecation-Notice');
    }

    /**
     * Test 3: Legacy /auth/me returns deprecation headers
     */
    public function test_legacy_me_returns_deprecation_headers(): void
    {
        $loginResponse = $this->postJson('/api/auth/login-v2', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);
        $token = $loginResponse->json('token');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/me');

        $response->assertSuccessful();
        $response->assertHeader('Deprecation');
        $response->assertHeader('Sunset');
    }

    /**
     * Test 4: Legacy /auth/refresh returns deprecation headers
     */
    public function test_legacy_refresh_returns_deprecation_headers(): void
    {
        $loginResponse = $this->postJson('/api/auth/login-v2', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);
        $token = $loginResponse->json('token');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/refresh');

        $response->assertSuccessful();
        $response->assertHeader('Deprecation');
        $response->assertHeader('Sunset');
    }

    /**
     * Test 5: v2 endpoints do NOT have deprecation headers
     */
    public function test_v2_endpoints_have_no_deprecation_headers(): void
    {
        $response = $this->postJson('/api/auth/login-v2', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(201);
        $this->assertNull($response->headers->get('Deprecation'));
        $this->assertNull($response->headers->get('Sunset'));
    }

    // ========== UNIFIED ENDPOINT TESTS (BEARER MODE) ==========

    /**
     * Test 6: Unified /auth/unified/me works with Bearer token
     */
    public function test_unified_me_works_with_bearer_token(): void
    {
        $loginResponse = $this->postJson('/api/auth/login-v2', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);
        $token = $loginResponse->json('token');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/unified/me');

        $response->assertStatus(200);
        $response->assertJsonStructure(['user' => ['id', 'name', 'email']]);
        $response->assertJson(['user' => ['email' => 'test@example.com']]);
    }

    /**
     * Test 7: Unified /auth/unified/logout works with Bearer token
     */
    public function test_unified_logout_works_with_bearer_token(): void
    {
        $loginResponse = $this->postJson('/api/auth/login-v2', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);
        $token = $loginResponse->json('token');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/unified/logout');

        $response->assertStatus(200);

        // Token should be revoked
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/me-v2')
            ->assertStatus(401);
    }

    /**
     * Test 8: Unified /auth/unified/logout-all works with Bearer token
     */
    public function test_unified_logout_all_works_with_bearer_token(): void
    {
        // Create multiple tokens
        $token1 = $this->user->createToken('Device 1')->plainTextToken;
        $token2 = $this->user->createToken('Device 2')->plainTextToken;

        // Logout all using token1
        $response = $this->withHeader('Authorization', "Bearer {$token1}")
            ->postJson('/api/auth/unified/logout-all');

        $response->assertStatus(200);
        $response->assertJsonStructure(['message', 'revoked_count']);

        // Both tokens should be revoked
        $this->withHeader('Authorization', "Bearer {$token1}")->getJson('/api/auth/me-v2')->assertStatus(401);
        $this->withHeader('Authorization', "Bearer {$token2}")->getJson('/api/auth/me-v2')->assertStatus(401);
    }

    // ========== UNIFIED ENDPOINT TESTS (COOKIE MODE) ==========

    /**
     * Test 9: Unified /auth/unified/me works with HttpOnly cookie
     */
    public function test_unified_me_works_with_httponly_cookie(): void
    {
        // Login via httponly
        $this->postJson('/api/auth/login-httponly', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $token = PersonalAccessToken::where('tokenable_id', $this->user->id)
            ->where('name', 'httponly-web-cookie')
            ->first();
        $tokenIdentifier = $token->token_identifier;

        $cookieName = env('SANCTUM_COOKIE_NAME', 'soleil_token');
        $response = $this->withHeader('Cookie', "{$cookieName}={$tokenIdentifier}")
            ->getJson('/api/auth/unified/me');

        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'user' => ['id', 'name', 'email']]);
    }

    /**
     * Test 10: Unified /auth/unified/logout works with HttpOnly cookie
     */
    public function test_unified_logout_works_with_httponly_cookie(): void
    {
        $this->postJson('/api/auth/login-httponly', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $token = PersonalAccessToken::where('tokenable_id', $this->user->id)
            ->where('name', 'httponly-web-cookie')
            ->first();
        $tokenIdentifier = $token->token_identifier;

        $cookieName = env('SANCTUM_COOKIE_NAME', 'soleil_token');
        $response = $this->withHeader('Cookie', "{$cookieName}={$tokenIdentifier}")
            ->postJson('/api/auth/unified/logout');

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Token should be revoked
        $token->refresh();
        $this->assertTrue($token->isRevoked());
    }

    /**
     * Test 11: Unified /auth/unified/logout-all works with HttpOnly cookie
     */
    public function test_unified_logout_all_works_with_httponly_cookie(): void
    {
        // Create httponly token
        $this->postJson('/api/auth/login-httponly', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        // Also create a bearer token
        $this->user->createToken('Other Device');

        $httpOnlyToken = PersonalAccessToken::where('tokenable_id', $this->user->id)
            ->where('name', 'httponly-web-cookie')
            ->first();
        $tokenIdentifier = $httpOnlyToken->token_identifier;

        $cookieName = env('SANCTUM_COOKIE_NAME', 'soleil_token');
        $response = $this->withHeader('Cookie', "{$cookieName}={$tokenIdentifier}")
            ->postJson('/api/auth/unified/logout-all');

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertGreaterThanOrEqual(2, $response->json('revoked_count'));

        // All tokens should be revoked
        $activeTokens = PersonalAccessToken::where('tokenable_id', $this->user->id)
            ->whereNull('revoked_at')
            ->count();
        $this->assertEquals(0, $activeTokens);
    }

    // ========== EDGE CASE TESTS ==========

    /**
     * Test 12: Unified endpoints return 401 without authentication
     */
    public function test_unified_endpoints_return_401_without_auth(): void
    {
        $this->getJson('/api/auth/unified/me')->assertStatus(401);
        $this->postJson('/api/auth/unified/logout')->assertStatus(401);
        $this->postJson('/api/auth/unified/logout-all')->assertStatus(401);
    }

    /**
     * Test 13: Unified endpoints return 401 with expired token
     */
    public function test_unified_endpoints_return_401_with_expired_token(): void
    {
        $token = $this->user->createToken('Test', ['*'], now()->subHour())->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/unified/me')
            ->assertStatus(401);
    }

    /**
     * Test 14: Unified endpoints return 401 with revoked token
     */
    public function test_unified_endpoints_return_401_with_revoked_token(): void
    {
        $tokenResult = $this->user->createToken('Test');
        $token = $tokenResult->plainTextToken;

        // Revoke the token
        $tokenModel = PersonalAccessToken::findToken($token);
        $tokenModel->revoke();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/unified/me')
            ->assertStatus(401);
    }

    /**
     * Test 15: Cookie mode takes precedence when both auth methods present
     */
    public function test_cookie_mode_takes_precedence(): void
    {
        // Create bearer token for user1
        $user1 = $this->user;
        $bearerToken = $user1->createToken('Bearer Device')->plainTextToken;

        // Create httponly token for user2
        $user2 = User::factory()->create([
            'email' => 'user2@example.com',
            'password' => bcrypt('password123'),
        ]);
        $this->postJson('/api/auth/login-httponly', [
            'email' => 'user2@example.com',
            'password' => 'password123',
        ]);
        $httpOnlyToken = PersonalAccessToken::where('tokenable_id', $user2->id)
            ->where('name', 'httponly-web-cookie')
            ->first();

        // Send request with both
        $cookieName = env('SANCTUM_COOKIE_NAME', 'soleil_token');
        $response = $this->withHeader('Authorization', "Bearer {$bearerToken}")
            ->withHeader('Cookie', "{$cookieName}={$httpOnlyToken->token_identifier}")
            ->getJson('/api/auth/unified/me');

        // Should return user2 (cookie mode) not user1 (bearer)
        $response->assertStatus(200);
        $response->assertJson(['user' => ['email' => 'user2@example.com']]);
    }

    // ========== BACKWARD COMPATIBILITY TESTS ==========

    /**
     * Test 16: Legacy /auth/login still works with same response format
     */
    public function test_legacy_login_maintains_response_format(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'user',
                'access_token',
                'token_type',
            ],
        ]);
        $response->assertJson(['success' => true, 'data' => ['token_type' => 'Bearer']]);
    }

    /**
     * Test 17: v2 /auth/login-v2 maintains its response format
     */
    public function test_v2_login_maintains_response_format(): void
    {
        $response = $this->postJson('/api/auth/login-v2', [
            'email' => 'test@example.com',
            'password' => 'password123',
            'remember_me' => false,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'token',
            'user' => ['id', 'name', 'email'],
            'expires_at',
            'expires_in_minutes',
            'expires_in_seconds',
            'type',
            'device_id',
        ]);
    }

    /**
     * Test 18: HttpOnly /auth/login-httponly maintains its response format
     */
    public function test_httponly_login_maintains_response_format(): void
    {
        $response = $this->postJson('/api/auth/login-httponly', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'user' => ['id', 'name', 'email'],
            'expires_in_minutes',
            'expires_at',
            'token_type',
            'csrf_token',
        ]);
        // Should NOT have token in body
        $this->assertArrayNotHasKey('token', $response->json());
    }
}
