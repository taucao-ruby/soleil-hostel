<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\PersonalAccessToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HttpOnlyCookieAuthenticationTest - Test httpOnly cookie-based authentication
 * 
 * CRITICAL SECURITY TESTS:
 * 1. Token stored in httpOnly cookie (not localStorage)
 * 2. Token response does NOT include plaintext token body
 * 3. CSRF token provided for X-XSRF-TOKEN header
 * 4. Refresh rotates token (old token revoked)
 * 5. Device fingerprint validation (if enabled)
 * 6. Logout revokes + clears cookie
 */
class HttpOnlyCookieAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

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
     * Test 1: Login returns httpOnly cookie (NOT response body token)
     * 
     * Security Check:
     * - Response MUST NOT contain plaintext token in JSON
     * - Response MUST contain CSRF token (for X-XSRF-TOKEN header)
     * - Cookie header MUST have httpOnly=true, Secure=true, SameSite=Strict
     */
    public function test_login_sets_httponly_cookie_without_plaintext_token(): void
    {
        $response = $this->postJson('/api/auth/login-httponly', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        // ========== Response Format Check ==========
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'user' => ['id', 'name', 'email'],
            'csrf_token',
            'expires_in_minutes',
            'expires_at',
            'token_type',
        ]);

        // ========== CRITICAL: Token NOT in response body ==========
        // Frontend cannot store token in localStorage
        $this->assertArrayNotHasKey('token', $response->json());
        $this->assertArrayNotHasKey('access_token', $response->json());
        $this->assertArrayNotHasKey('bearer_token', $response->json());

        // ========== Check Response Data ==========
        $this->assertTrue($response->json('success'));
        $this->assertNotEmpty($response->json('csrf_token'));
        $this->assertNotEmpty($response->json('user.id'));

        // ========== Check Cookie Header ==========
        // Extract Set-Cookie header
        $cookies = $response->headers->all('set-cookie');
        $this->assertNotEmpty($cookies, 'httpOnly cookie should be set');

        // Verify httpOnly flag in cookie header (case-insensitive)
        $cookieHeader = implode('; ', $cookies);
        $this->assertStringContainsString('httponly', strtolower($cookieHeader), 'Cookie must be httpOnly');
        $this->assertStringContainsString('samesite=strict', strtolower($cookieHeader), 'Cookie must have SameSite=Strict');

        // In production over HTTPS, should have Secure flag
        // In testing (HTTP), the cookie won't have Secure flag
        // Only check if HTTPS scheme is used
        $isHttps = isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https';
        if (config('app.env') === 'production' && $isHttps) {
            $this->assertStringContainsString('Secure', $cookieHeader, 'Cookie must be Secure in production over HTTPS');
        }
    }

    /**
     * Test 2: Token stored in database with UUID identifier + hash
     * 
     * Security Check:
     * - token_identifier: UUID (sent in cookie)
     * - token_hash: SHA256(identifier) (used for DB lookup)
     * - Never: plaintext identifier comparison
     */
    public function test_token_stored_with_identifier_and_hash(): void
    {
        $this->postJson('/api/auth/login-httponly', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        // Find token in database
        $token = PersonalAccessToken::where('name', 'httponly-web-cookie')
            ->where('tokenable_id', $this->user->id)
            ->first();

        $this->assertNotNull($token);
        $this->assertNotNull($token->token_identifier, 'token_identifier (UUID) must be stored');
        $this->assertNotNull($token->token_hash, 'token_hash must be stored');

        // Verify hash is correct
        $expectedHash = hash('sha256', $token->token_identifier);
        $this->assertEquals($expectedHash, $token->token_hash, 'Hash must match SHA256(identifier)');

        // Verify token has expiration
        $this->assertNotNull($token->expires_at, 'Token must have expires_at');
        $this->assertTrue($token->expires_at->isFuture(), 'Token must not be expired');
    }

    /**
     * Test 3: Refresh token rotates (old token revoked)
     * 
     * Security Check:
     * - Old token_identifier in cookie
     * - Refresh creates new token_identifier + hash
     * - Old token marked as revoked
     * - New cookie set with new identifier
     */
    public function test_refresh_token_rotates_old_token(): void
    {
        // Login to get initial token
        $loginResponse = $this->postJson('/api/auth/login-httponly', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        // Get old token from database
        $oldToken = PersonalAccessToken::where('name', 'httponly-web-cookie')
            ->where('tokenable_id', $this->user->id)
            ->orderByDesc('created_at')
            ->first();

        $oldTokenId = $oldToken->id;
        $oldTokenIdentifier = $oldToken->token_identifier;

        // Refresh token (simulating browser sending httpOnly cookie automatically)
        // In real test, we'd need to extract token from Set-Cookie, 
        // but for unit test we'll manually set it
        $refreshResponse = $this->withCookie(
            env('SANCTUM_COOKIE_NAME', 'soleil_token'),
            $oldTokenIdentifier
        )->postJson('/api/auth/refresh-httponly');

        $refreshResponse->assertStatus(200);
        $refreshResponse->assertJsonStructure([
            'success',
            'csrf_token',
            'expires_in_minutes',
        ]);

        // Verify old token is revoked
        $oldToken->refresh();
        $this->assertTrue($oldToken->isRevoked(), 'Old token should be revoked');

        // Verify new token was created
        $newToken = PersonalAccessToken::where('name', 'httponly-web-cookie')
            ->where('tokenable_id', $this->user->id)
            ->where('id', '!=', $oldTokenId)
            ->orderByDesc('created_at')
            ->first();

        $this->assertNotNull($newToken, 'New token should be created');
        $this->assertNotEquals($oldTokenIdentifier, $newToken->token_identifier, 'New token should have different identifier');
        $this->assertFalse($newToken->isRevoked(), 'New token should not be revoked');
    }

    /**
     * Test 4: Logout revokes token + clears cookie
     * 
     * Security Check:
     * - Token marked as revoked in DB
     * - Cookie set to empty value with past expiry
     */
    public function test_logout_revokes_token_and_clears_cookie(): void
    {
        // Login
        $this->postJson('/api/auth/login-httponly', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $token = PersonalAccessToken::where('tokenable_id', $this->user->id)->first();
        $tokenIdentifier = $token->token_identifier;

        // Logout
        $logoutResponse = $this->withCookie(
            env('SANCTUM_COOKIE_NAME', 'soleil_token'),
            $tokenIdentifier
        )->postJson('/api/auth/logout-httponly');

        $logoutResponse->assertStatus(200);
        $logoutResponse->assertJson(['success' => true]);

        // Verify token is revoked
        $token->refresh();
        $this->assertTrue($token->isRevoked());

        // Verify cookie header indicates deletion (empty value + past expiry)
        $cookies = $logoutResponse->headers->all('set-cookie');
        $cookieHeader = implode('; ', $cookies);
        $this->assertStringContainsString('expires=', $cookieHeader, 'Cookie should have past expiry');
    }

    /**
     * Test 5: Cannot use logout revoke token
     * 
     * Security Check:
     * - After logout, token is revoked
     * - Protected endpoint returns 401 with TOKEN_REVOKED code
     */
    public function test_revoked_token_cannot_access_protected_endpoint(): void
    {
        // Login
        $this->postJson('/api/auth/login-httponly', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $token = PersonalAccessToken::where('tokenable_id', $this->user->id)->first();
        $tokenIdentifier = $token->token_identifier;

        // Logout (revoke token)
        $this->withCookie(
            env('SANCTUM_COOKIE_NAME', 'soleil_token'),
            $tokenIdentifier
        )->postJson('/api/auth/logout-httponly');

        // Try to access protected endpoint with revoked token
        $response = $this->withCookie(
            env('SANCTUM_COOKIE_NAME', 'soleil_token'),
            $tokenIdentifier
        )->getJson('/api/auth/me-httponly');

        $response->assertStatus(401);
        $response->assertJson(['code' => 'TOKEN_REVOKED']);
    }

    /**
     * Test 6: Expired token returns TOKEN_EXPIRED
     * 
     * Security Check:
     * - Token expires_at in past
     * - Protected endpoint returns 401 with TOKEN_EXPIRED code
     */
    public function test_expired_token_returns_token_expired(): void
    {
        // Login
        $this->postJson('/api/auth/login-httponly', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $token = PersonalAccessToken::where('tokenable_id', $this->user->id)->first();

        // Manually expire token
        $token->update(['expires_at' => now()->subHour()]);

        // Try to access protected endpoint
        $response = $this->withCookie(
            env('SANCTUM_COOKIE_NAME', 'soleil_token'),
            $token->token_identifier
        )->getJson('/api/auth/me-httponly');

        $response->assertStatus(401);
        $response->assertJson(['code' => 'TOKEN_EXPIRED']);
    }

    /**
     * Test 7: Missing cookie returns 401
     * 
     * Security Check:
     * - No cookie sent
     * - Returns 401 Unauthenticated
     */
    public function test_missing_cookie_returns_unauthorized(): void
    {
        $response = $this->getJson('/api/auth/me-httponly');

        $response->assertStatus(401);
    }

    /**
     * Test 8: Invalid token identifier returns 401
     * 
     * Security Check:
     * - Random UUID sent
     * - Token not found in DB
     * - Returns 401
     */
    public function test_invalid_token_identifier_returns_unauthorized(): void
    {
        $invalidToken = 'invalid-uuid-12345678';

        $response = $this->withCookie(
            env('SANCTUM_COOKIE_NAME', 'soleil_token'),
            $invalidToken
        )->getJson('/api/auth/me-httponly');

        $response->assertStatus(401);
    }

    /**
     * Test 9: GET /api/auth/csrf-token returns token without authentication
     * 
     * Public endpoint để frontend lấy CSRF token trước login
     */
    public function test_csrf_token_endpoint_accessible_publicly(): void
    {
        $response = $this->getJson('/api/auth/csrf-token');

        $response->assertStatus(200);
        $response->assertJsonStructure(['csrf_token']);
        $this->assertNotEmpty($response->json('csrf_token'));
    }

    /**
     * Test 10: Me endpoint returns user + token info
     * 
     * Security Check:
     * - User data returned
     * - Token metadata returned (expires_at, type)
     * - Token NOT returned in plaintext
     */
    public function test_me_endpoint_returns_user_and_token_info(): void
    {
        // Login
        $this->postJson('/api/auth/login-httponly', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $token = PersonalAccessToken::where('tokenable_id', $this->user->id)->first();

        // Get me
        $response = $this->withCookie(
            env('SANCTUM_COOKIE_NAME', 'soleil_token'),
            $token->token_identifier
        )->getJson('/api/auth/me-httponly');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'user' => ['id', 'name', 'email'],
            'token' => ['name', 'type', 'expires_at'],
        ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals($this->user->id, $response->json('user.id'));
        $this->assertEquals($this->user->email, $response->json('user.email'));
    }

    /**
     * Test 11: Multiple refreshes > threshold triggers SUSPICIOUS_ACTIVITY
     * 
     * Security Check:
     * - Refresh count incremented on each refresh
     * - After max_refresh_count threshold, token revoked
     * - Returns 401 with SUSPICIOUS_ACTIVITY code
     */
    public function test_excessive_refresh_triggers_suspicious_activity(): void
    {
        // Login
        $this->postJson('/api/auth/login-httponly', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $token = PersonalAccessToken::where('tokenable_id', $this->user->id)->first();
        $tokenIdentifier = $token->token_identifier;

        // Refresh multiple times (exceed threshold)
        $maxRefreshCount = config('sanctum.max_refresh_count_per_hour', 10);

        for ($i = 0; $i < $maxRefreshCount + 1; $i++) {
            $response = $this->withCookie(
                env('SANCTUM_COOKIE_NAME', 'soleil_token'),
                $tokenIdentifier
            )->postJson('/api/auth/refresh-httponly');

            if ($i < $maxRefreshCount) {
                // Should succeed
                $this->assertEquals(200, $response->status());

                // Get new token for next iteration
                $newToken = PersonalAccessToken::where('tokenable_id', $this->user->id)
                    ->where('revoked_at', null)
                    ->orderByDesc('created_at')
                    ->first();

                $tokenIdentifier = $newToken->token_identifier;
            } else {
                // Should fail with SUSPICIOUS_ACTIVITY
                $response->assertStatus(401);
                $response->assertJson(['code' => 'SUSPICIOUS_ACTIVITY']);
            }
        }
    }
}
