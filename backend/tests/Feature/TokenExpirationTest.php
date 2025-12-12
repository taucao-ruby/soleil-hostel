<?php

namespace Tests\Feature;

use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TokenExpirationTest - Test token expiration + refresh + logout
 * 
 * Test scenarios:
 * 1. Login → Token created với expires_at
 * 2. Token hết hạn → 401
 * 3. Refresh token → Token mới được cấp, token cũ revoke
 * 4. Logout → Token bị revoke, không thể dùng tiếp
 * 5. Refresh with expired token → 401
 * 6. Logout all devices → Revoke tất cả token
 * 7. Single device login → Logout device cũ
 * 8. Suspicious activity → Revoke token khi refresh quá nhiều
 * 9. Token expiration info → GET /api/auth/me-v2
 * 10. Long-lived token → Remember me feature
 */
class TokenExpirationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);
    }

    /**
     * Test 1: Login → Token created với expires_at
     * 
     * POST /api/auth/login-v2
     * {
     *   "email": "test@example.com",
     *   "password": "password123",
     *   "remember_me": false
     * }
     * 
     * Expected:
     * - Status 201
     * - Token returned
     * - expires_at set (short_lived: 1 giờ)
     * - type = "short_lived"
     */
    public function test_login_creates_token_with_expiration(): void
    {
        $response = $this->postJson('/api/auth/login-v2', [
            'email' => 'test@example.com',
            'password' => 'password123',
            'remember_me' => false,
            'device_name' => 'Test Browser',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'token',
                'expires_at',
                'expires_in_minutes',
                'type',
            ])
            ->assertJson([
                'type' => 'short_lived',
            ]);

        // Verify token in database
        $token = PersonalAccessToken::where('tokenable_id', $this->user->id)
            ->where('tokenable_type', 'App\\Models\\User')
            ->first();

        $this->assertNotNull($token);
        $this->assertNotNull($token->expires_at);
        $this->assertEquals('short_lived', $token->type);
        $this->assertTrue($token->expires_at->isFuture());
    }

    /**
     * Test 2: Token hết hạn → 401
     * 
     * Setup: Token với expires_at = now - 1 giây (đã hết hạn)
     * 
     * GET /api/auth/me-v2 (protected endpoint)
     * 
     * Expected:
     * - Status 401
     * - Message: "Token đã hết hạn"
     * - Code: "TOKEN_EXPIRED"
     */
    public function test_expired_token_returns_401(): void
    {
        // Create token với expires_at = now - 1 giây
        $token = $this->user->createToken(
            name: 'Test Token',
            abilities: ['*'],
            expiresAt: now()->subSecond(),
        );

        // Try to access protected endpoint
        $response = $this->getJson('/api/auth/me-v2', [
            'Authorization' => "Bearer {$token->plainTextToken}",
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'code' => 'TOKEN_EXPIRED',
            ]);
    }

    /**
     * Test 3: Refresh token → Token mới được cấp, token cũ revoke
     * 
     * POST /api/auth/refresh-v2
     * Headers: Authorization: Bearer <old_token>
     * 
     * Expected:
     * - Status 200
     * - New token returned
     * - Old token revoked (revoked_at set)
     * - New token not revoked
     * - New token has new expires_at
     */
    public function test_refresh_token_creates_new_and_revokes_old(): void
    {
        // Create old token
        $oldToken = $this->user->createToken(
            name: 'Test Token',
            abilities: ['*'],
            expiresAt: now()->addHour(),
        );

        $oldTokenModel = PersonalAccessToken::where(
            'token',
            hash('sha256', $oldToken->plainTextToken)
        )->first();

        $this->assertNull($oldTokenModel->revoked_at);

        // Call refresh endpoint
        $response = $this->postJson('/api/auth/refresh-v2', [], [
            'Authorization' => "Bearer {$oldToken->plainTextToken}",
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'token',
                'expires_at',
            ])
            ->assertJson([
                'old_token_status' => 'revoked',
            ]);

        // Verify old token revoked
        $oldTokenModel->refresh();
        $this->assertNotNull($oldTokenModel->revoked_at);

        // Verify new token created and not revoked
        $newToken = $response->json('token');
        $newTokenModel = PersonalAccessToken::where(
            'token',
            hash('sha256', $newToken)
        )->first();

        $this->assertNotNull($newTokenModel);
        $this->assertNull($newTokenModel->revoked_at);
    }

    /**
     * Test 4: Logout → Token bị revoke, không thể dùng tiếp
     * 
     * POST /api/auth/logout-v2
     * Headers: Authorization: Bearer <token>
     * 
     * Expected:
     * - Status 200
     * - Token revoked_at set
     * - Subsequent requests with this token → 401
     */
    public function test_logout_revokes_token(): void
    {
        $token = $this->user->createToken(
            name: 'Test Token',
            abilities: ['*'],
            expiresAt: now()->addHour(),
        );

        // Logout
        $response = $this->postJson('/api/auth/logout-v2', [], [
            'Authorization' => "Bearer {$token->plainTextToken}",
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Logout thành công.',
            ]);

        // Verify token revoked
        $tokenModel = PersonalAccessToken::where(
            'token',
            hash('sha256', $token->plainTextToken)
        )->first();

        $this->assertNotNull($tokenModel->revoked_at);

        // Try to use revoked token
        $unauthorizedResponse = $this->getJson('/api/auth/me-v2', [
            'Authorization' => "Bearer {$token->plainTextToken}",
        ]);

        $unauthorizedResponse->assertStatus(401)
            ->assertJson([
                'code' => 'TOKEN_REVOKED',
            ]);
    }

    /**
     * Test 5: Refresh with expired token → 401
     * 
     * Setup: Token đã hết hạn (expires_at < now)
     * 
     * POST /api/auth/refresh-v2 (with expired token)
     * 
     * Expected:
     * - Status 401
     * - Cannot refresh expired token
     * - User must login again
     */
    public function test_cannot_refresh_expired_token(): void
    {
        // Create expired token
        $token = $this->user->createToken(
            name: 'Test Token',
            abilities: ['*'],
            expiresAt: now()->subHour(), // Expired 1 giờ trước
        );

        // Try to refresh expired token
        $response = $this->postJson('/api/auth/refresh-v2', [], [
            'Authorization' => "Bearer {$token->plainTextToken}",
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'code' => 'TOKEN_EXPIRED',
            ]);
    }

    /**
     * Test 6: Logout all devices → Revoke tất cả token
     * 
     * Setup: User có 3 token (device 1, 2, 3)
     * 
     * POST /api/auth/logout-all-v2 (from device 1)
     * 
     * Expected:
     * - Status 200
     * - Tất cả 3 token revoked
     * - revoked_count = 3
     */
    public function test_logout_all_devices_revokes_all_tokens(): void
    {
        // Create 3 tokens (3 devices)
        $token1 = $this->user->createToken('Device 1', ['*'], now()->addHour());
        $token2 = $this->user->createToken('Device 2', ['*'], now()->addHour());
        $token3 = $this->user->createToken('Device 3', ['*'], now()->addHour());

        // Logout all từ device 1
        $response = $this->postJson('/api/auth/logout-all-v2', [], [
            'Authorization' => "Bearer {$token1->plainTextToken}",
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'revoked_count' => 3,
            ]);

        // Verify tất cả token revoked
        $this->assertTrue(
            PersonalAccessToken::where('tokenable_id', $this->user->id)
                ->where('tokenable_type', 'App\\Models\\User')
                ->notRevoked()
                ->count() === 0
        );
    }

    /**
     * Test 7: Single device login → Logout device cũ
     * 
     * Setup: config('sanctum.single_device_login') = true
     * User đã login trên device 1
     * 
     * Login lại trên device 2:
     * POST /api/auth/login-v2 (device 2)
     * 
     * Expected:
     * - Device 1 token revoked
     * - Device 2 token created + active
     * - Only 1 active token per user
     */
    public function test_single_device_login_revokes_other_devices(): void
    {
        // Enable single device login
        config(['sanctum.single_device_login' => true]);

        // Login device 1
        $response1 = $this->postJson('/api/auth/login-v2', [
            'email' => 'test@example.com',
            'password' => 'password123',
            'device_name' => 'iPhone',
        ]);

        $token1 = $response1->json('token');

        // Login device 2
        $response2 = $this->postJson('/api/auth/login-v2', [
            'email' => 'test@example.com',
            'password' => 'password123',
            'device_name' => 'Android',
        ]);

        $token2 = $response2->json('token');

        // Verify device 1 token revoked
        $tokenModel1 = PersonalAccessToken::where(
            'token',
            hash('sha256', $token1)
        )->first();

        $this->assertNotNull($tokenModel1->revoked_at);

        // Verify device 2 token active
        $tokenModel2 = PersonalAccessToken::where(
            'token',
            hash('sha256', $token2)
        )->first();

        $this->assertNull($tokenModel2->revoked_at);

        // Only 1 active token
        $activeTokens = $this->user->tokens()
            ->notRevoked()
            ->notExpired()
            ->count();

        $this->assertEquals(1, $activeTokens);
    }

    /**
     * Test 8: Token expiration info → GET /api/auth/me-v2
     * 
     * GET /api/auth/me-v2
     * Headers: Authorization: Bearer <token>
     * 
     * Expected:
     * - User info
     * - Token info:
     *   - name (device name)
     *   - type (short_lived/long_lived)
     *   - expires_at
     *   - expires_in_minutes
     *   - expires_in_seconds
     *   - created_at
     *   - last_used_at
     */
    public function test_get_current_user_info_with_token_expiration(): void
    {
        $token = $this->user->createToken(
            name: 'iPhone',
            abilities: ['*'],
            expiresAt: now()->addMinutes(45),
        );

        $response = $this->getJson('/api/auth/me-v2', [
            'Authorization' => "Bearer {$token->plainTextToken}",
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email'],
                'token' => [
                    'name',
                    'type',
                    'expires_at',
                    'expires_in_minutes',
                    'expires_in_seconds',
                    'created_at',
                    'last_used_at',
                ],
            ])
            ->assertJson([
                'token' => [
                    'name' => 'iPhone',
                ],
            ]);

        // Verify expires_in_minutes ~ 45
        $expiresInMinutes = $response->json('token.expires_in_minutes');
        $this->assertGreaterThan(40, $expiresInMinutes);
        $this->assertLessThanOrEqual(45, $expiresInMinutes);
    }

    /**
     * Test 9: Long-lived token (Remember me)
     * 
     * POST /api/auth/login-v2
     * {
     *   "email": "test@example.com",
     *   "password": "password123",
     *   "remember_me": true
     * }
     * 
     * Expected:
     * - Token created với loại "long_lived"
     * - expires_at = now + 30 ngày
     * - expires_in_minutes ~ 43200 (30 ngày)
     */
    public function test_remember_me_creates_long_lived_token(): void
    {
        $response = $this->postJson('/api/auth/login-v2', [
            'email' => 'test@example.com',
            'password' => 'password123',
            'remember_me' => true,
            'device_name' => 'Mobile App',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'type' => 'long_lived',
            ]);

        // Verify expires_in_minutes ~ 43200 (30 ngày = 30*24*60 = 43200)
        $expiresInMinutes = $response->json('expires_in_minutes');
        $this->assertGreaterThan(43000, $expiresInMinutes);
        $this->assertLessThanOrEqual(43200, $expiresInMinutes);
    }

    /**
     * Test 10: Suspicious activity → Revoke token khi refresh quá nhiều
     * 
     * Setup:
     * - config('sanctum.max_refresh_count_per_hour') = 10
     * - Simulate 11 refresh attempts trong 1 giờ
     * 
     * Expected:
     * - 11th refresh → 401 (SUSPICIOUS_ACTIVITY)
     * - Token revoked
     */
    public function test_suspicious_activity_revokes_token(): void
    {
        // Set threshold = 3 (for testing)
        config(['sanctum.max_refresh_count_per_hour' => 3]);

        $token = $this->user->createToken(
            name: 'Test Token',
            abilities: ['*'],
            expiresAt: now()->addHour(),
        );

        // Refresh 4 times (more than threshold)
        for ($i = 0; $i < 4; $i++) {
            $response = $this->postJson('/api/auth/refresh-v2', [], [
                'Authorization' => "Bearer {$token->plainTextToken}",
            ]);

            if ($i < 3) {
                $this->assertEquals(200, $response->status());
                $token->plainTextToken = $response->json('token');
            } else {
                // 4th refresh → 401 (suspicious)
                $this->assertEquals(401, $response->status());
                $this->assertEquals('SUSPICIOUS_ACTIVITY', $response->json('code'));
            }
        }
    }
}

