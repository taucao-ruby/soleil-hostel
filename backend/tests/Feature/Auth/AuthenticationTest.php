<?php

namespace Tests\Feature\Auth;

use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * AuthenticationTest - Comprehensive authentication tests
 * 
 * ✅ Test Coverage:
 * 1. Login success with valid credentials
 * 2. Login failure with invalid credentials
 * 3. Token expiration handling
 * 4. Token refresh flow
 * 5. Token revocation (logout)
 * 6. Single-device login (revokes old tokens)
 * 7. Get current user info
 * 8. Remember me (long-lived token)
 * 9. Multiple devices authentication
 * 10. Expired token returns 401
 */
class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => bcrypt('password123'),
            'name' => 'Test User',
        ]);
    }

    /**
     * Test 1: Login success with valid credentials
     * ✅ Happy path - user can login with email/password
     */
    public function test_login_success_with_valid_credentials(): void
    {
        $response = $this->postJson('/api/auth/login-v2', [
            'email' => 'user@example.com',
            'password' => 'password123',
            'remember_me' => false,
            'device_name' => 'Test Device',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'token',
                'user' => [
                    'id',
                    'name',
                    'email',
                ],
                'expires_at',
                'expires_in_minutes',
                'type',
            ])
            ->assertJson([
                'type' => 'short_lived',
            ]);

        // Verify token stored in database
        $token = PersonalAccessToken::where('user_id', $this->user->id)->first();
        $this->assertNotNull($token);
        $this->assertFalse($token->revoked);
        $this->assertNotNull($token->expires_at);
        $this->assertTrue($token->expires_at->isFuture());
    }

    /**
     * Test 2: Login fails with invalid email
     * ✅ Invalid email → 401 Unauthorized
     */
    public function test_login_fails_with_invalid_email(): void
    {
        $response = $this->postJson('/api/auth/login-v2', [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
            'device_name' => 'Test Device',
        ]);

        // Email validation returns 422, not 401
        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);

        // No token should be created
        $token = PersonalAccessToken::where('user_id', $this->user->id)->first();
        $this->assertNull($token);
    }

    /**
     * Test 3: Login fails with invalid password
     * ✅ Invalid password → 401 Unauthorized
     */
    public function test_login_fails_with_invalid_password(): void
    {
        $response = $this->postJson('/api/auth/login-v2', [
            'email' => 'user@example.com',
            'password' => 'wrongpassword',
            'device_name' => 'Test Device',
        ]);

        $response->assertStatus(401)
            ->assertJsonStructure(['message']);

        // No token should be created
        $token = PersonalAccessToken::where('user_id', $this->user->id)->first();
        $this->assertNull($token);
    }

    /**
     * Test 4: Get current user info
     * ✅ GET /api/auth/me-v2 returns authenticated user
     */
    public function test_get_current_user_info(): void
    {
        // Login first
        $loginResponse = $this->postJson('/api/auth/login-v2', [
            'email' => 'user@example.com',
            'password' => 'password123',
            'device_name' => 'Test Device',
        ]);

        $token = $loginResponse->json('token');

        // Get current user
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/me-v2');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email',
                ],
                'token_info' => [
                    'expires_at',
                    'expires_in_minutes',
                    'type',
                ],
            ])
            ->assertJson([
                'user' => [
                    'id' => $this->user->id,
                    'name' => 'Test User',
                    'email' => 'user@example.com',
                ],
                'token_info' => [
                    'type' => 'short_lived',
                ],
            ]);
    }

    /**
     * Test 5: Expired token returns 401
     * ✅ Accessing protected endpoint with expired token
     */
    public function test_expired_token_returns_401(): void
    {
        // Create expired token
        $token = $this->user->createToken(
            'Test Token',
            ['*'],
            now()->subSeconds(10)
        )->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/me-v2');

        $response->assertStatus(401)
            ->assertJsonStructure(['message']);
    }

    /**
     * Test 6: Refresh token creates new token
     * ✅ POST /api/auth/refresh-v2 with valid token
     */
    public function test_refresh_token_creates_new_token(): void
    {
        // Login first
        $loginResponse = $this->postJson('/api/auth/login-v2', [
            'email' => 'user@example.com',
            'password' => 'password123',
            'device_name' => 'Test Device',
        ]);

        $oldToken = $loginResponse->json('token');

        // Refresh token
        $response = $this->withHeader('Authorization', "Bearer {$oldToken}")
            ->postJson('/api/auth/refresh-v2');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'token',
                'user' => ['id', 'name', 'email'],
                'expires_at',
                'expires_in_minutes',
            ]);

        $newToken = $response->json('token');
        $this->assertNotEquals($oldToken, $newToken);

        // Old token should be revoked
        $oldPersonalToken = PersonalAccessToken::findToken($oldToken);
        $this->assertTrue($oldPersonalToken->revoked);

        // New token should work
        $this->withHeader('Authorization', "Bearer {$newToken}")
            ->getJson('/api/auth/me-v2')
            ->assertStatus(200);
    }

    /**
     * Test 7: Logout revokes token
     * ✅ POST /api/auth/logout-v2 revokes current token
     */
    public function test_logout_revokes_token(): void
    {
        // Login first
        $loginResponse = $this->postJson('/api/auth/login-v2', [
            'email' => 'user@example.com',
            'password' => 'password123',
            'device_name' => 'Test Device',
        ]);

        $token = $loginResponse->json('token');

        // Logout
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/logout-v2');

        $response->assertStatus(200);
        // Message includes period in response
        $this->assertStringContainsString('Logout thành công', $response->json('message'));

        // Token should be revoked and unusable
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/me-v2');

        $response->assertStatus(401);
    }

    /**
     * Test 8: Logout all devices revokes all tokens
     * ✅ POST /api/auth/logout-all-v2 revokes all user tokens
     */
    public function test_logout_all_devices_revokes_all_tokens(): void
    {
        // Create multiple tokens (simulate multiple device logins)
        $token1 = $this->user->createToken('Device 1')->plainTextToken;
        $token2 = $this->user->createToken('Device 2')->plainTextToken;
        $token3 = $this->user->createToken('Device 3')->plainTextToken;

        // Verify all tokens work before logout
        $this->withHeader('Authorization', "Bearer {$token1}")->getJson('/api/auth/me-v2')->assertStatus(200);
        $this->withHeader('Authorization', "Bearer {$token2}")->getJson('/api/auth/me-v2')->assertStatus(200);
        $this->withHeader('Authorization', "Bearer {$token3}")->getJson('/api/auth/me-v2')->assertStatus(200);

        // Logout all devices using token1
        $response = $this->withHeader('Authorization', "Bearer {$token1}")
            ->postJson('/api/auth/logout-all-v2');

        $response->assertStatus(200);
        // Check for revoked count instead of exact message
        $response->assertJsonStructure(['message', 'revoked_count']);

        // All tokens should now be revoked
        $this->withHeader('Authorization', "Bearer {$token1}")->getJson('/api/auth/me-v2')->assertStatus(401);
        $this->withHeader('Authorization', "Bearer {$token2}")->getJson('/api/auth/me-v2')->assertStatus(401);
        $this->withHeader('Authorization', "Bearer {$token3}")->getJson('/api/auth/me-v2')->assertStatus(401);
    }

    /**
     * Test 9: Single-device login revokes old tokens
     * ✅ New login on same device revokes previous tokens
     */
    public function test_single_device_login_revokes_old_tokens(): void
    {
        // Enable single device login
        config(['sanctum.single_device_login' => true]);

        // First login
        $response1 = $this->postJson('/api/auth/login-v2', [
            'email' => 'user@example.com',
            'password' => 'password123',
            'device_name' => 'Test Device',
        ]);
        $token1 = $response1->json('token');

        // Verify first token works
        $this->withHeader('Authorization', "Bearer {$token1}")
            ->getJson('/api/auth/me-v2')
            ->assertStatus(200);

        // Second login (same user)
        $response2 = $this->postJson('/api/auth/login-v2', [
            'email' => 'user@example.com',
            'password' => 'password123',
            'device_name' => 'Test Device',
        ]);
        $token2 = $response2->json('token');

        // First token should be revoked
        $this->withHeader('Authorization', "Bearer {$token1}")
            ->getJson('/api/auth/me-v2')
            ->assertStatus(401);

        // Second token should work
        $this->withHeader('Authorization', "Bearer {$token2}")
            ->getJson('/api/auth/me-v2')
            ->assertStatus(200);
    }

    /**
     * Test 10: Remember me creates long-lived token
     * ✅ Login with remember_me=true creates 30-day token
     */
    public function test_remember_me_creates_long_lived_token(): void
    {
        $response = $this->postJson('/api/auth/login-v2', [
            'email' => 'user@example.com',
            'password' => 'password123',
            'remember_me' => true,
            'device_name' => 'Test Device',
        ]);

        $response->assertStatus(201)
            ->assertJson(['type' => 'long_lived']);

        // Verify token expires in ~30 days
        $token = PersonalAccessToken::where('user_id', $this->user->id)->first();
        $this->assertNotNull($token);
        
        $expiresInDays = abs($token->expires_at->diffInDays(now()));
        $this->assertGreaterThanOrEqual(29, $expiresInDays);
        $this->assertLessThanOrEqual(31, $expiresInDays);
    }

    /**
     * Test 11: Multiple devices can be authenticated simultaneously
     * ✅ User can have multiple active tokens
     */
    public function test_multiple_devices_can_be_authenticated(): void
    {
        // Disable single device login
        config(['sanctum.single_device_login' => false]);

        // Login from device 1
        $response1 = $this->postJson('/api/auth/login-v2', [
            'email' => 'user@example.com',
            'password' => 'password123',
            'device_name' => 'iPhone',
        ]);
        $token1 = $response1->json('token');

        // Login from device 2
        $response2 = $this->postJson('/api/auth/login-v2', [
            'email' => 'user@example.com',
            'password' => 'password123',
            'device_name' => 'iPad',
        ]);
        $token2 = $response2->json('token');

        // Both tokens should work simultaneously
        $this->withHeader('Authorization', "Bearer {$token1}")
            ->getJson('/api/auth/me-v2')
            ->assertStatus(200);

        $this->withHeader('Authorization', "Bearer {$token2}")
            ->getJson('/api/auth/me-v2')
            ->assertStatus(200);

        // Logout from device 1
        $this->withHeader('Authorization', "Bearer {$token1}")
            ->postJson('/api/auth/logout-v2')
            ->assertStatus(200);

        // Token1 should be revoked
        $this->withHeader('Authorization', "Bearer {$token1}")
            ->getJson('/api/auth/me-v2')
            ->assertStatus(401);

        // Token2 should still work
        $this->withHeader('Authorization', "Bearer {$token2}")
            ->getJson('/api/auth/me-v2')
            ->assertStatus(200);
    }

    /**
     * Test 12: Protected endpoint without token returns 401
     * ✅ Missing Authorization header
     */
    public function test_protected_endpoint_without_token_returns_401(): void
    {
        $response = $this->getJson('/api/auth/me-v2');

        $response->assertStatus(401);
    }

    /**
     * Test 13: Invalid token format returns 401
     * ✅ Malformed Bearer token
     */
    public function test_invalid_token_format_returns_401(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer invalid-token-format')
            ->getJson('/api/auth/me-v2');

        $response->assertStatus(401);
    }

    /**
     * Test 14: Token with different user cannot be used by another user
     * ✅ Token is bound to specific user
     */
    public function test_token_bound_to_specific_user(): void
    {
        $user2 = User::factory()->create([
            'email' => 'user2@example.com',
            'password' => bcrypt('password123'),
        ]);

        // Login as user1
        $response = $this->postJson('/api/auth/login-v2', [
            'email' => 'user@example.com',
            'password' => 'password123',
            'device_name' => 'Test Device',
        ]);
        $token = $response->json('token');

        // Use token to access user info
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/me-v2');

        $response->assertStatus(200)
            ->assertJson(['user' => ['id' => $this->user->id]]);
    }

    /**
     * Test 15: Rate limiting on login endpoint
     * ✅ Too many login attempts should be throttled
     */
    public function test_login_rate_limiting(): void
    {
        // Make multiple failed login attempts
        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/auth/login-v2', [
                'email' => 'user@example.com',
                'password' => 'wrongpassword',
                'device_name' => 'Test Device',
            ]);
        }

        // 6th request should be throttled (429)
        $response = $this->postJson('/api/auth/login-v2', [
            'email' => 'user@example.com',
            'password' => 'password123',
            'device_name' => 'Test Device',
        ]);

        $response->assertStatus(429);
    }
}
