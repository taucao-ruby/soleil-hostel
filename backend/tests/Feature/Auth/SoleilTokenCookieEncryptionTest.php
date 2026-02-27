<?php

namespace Tests\Feature\Auth;

use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * SoleilTokenCookieEncryptionTest — Regression suite for EncryptCookies exception
 *
 * Verifies that the `$middleware->encryptCookies(except: ['soleil_token'])`
 * configuration in bootstrap/app.php does NOT introduce security regressions:
 *
 *  TEST-1: soleil_token cookie value is a plain UUID, not an encrypted payload
 *  TEST-2: Other cookies (laravel_session) remain encrypted by EncryptCookies
 *  TEST-3: Cookie UUID resolves user via v1 protected endpoint (cookie fallback)
 *  TEST-4: Security headers are still present after the middleware change
 *
 * Evidence chain (see COMPACT.md 2026-02-26 entry):
 *  - bootstrap/app.php:63-65 — encryptCookies(except: ['soleil_token'])
 *  - HttpOnlyTokenController.php:84 — $tokenIdentifier = Str::uuid()->toString()
 *  - HttpOnlyTokenController.php:130-140 — cookie set with httpOnly=true, SameSite=Strict
 *  - CheckHttpOnlyTokenValid.php:54-56 — hash('sha256', $tokenIdentifier) → token_hash lookup
 *  - CheckTokenNotRevokedAndNotExpired.php:42-44 — fallback to cookie auth when no Bearer
 *  - SecurityHeaders.php — prepended globally in bootstrap/app.php:39
 */
class SoleilTokenCookieEncryptionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'encryption-test@example.com',
            'password' => bcrypt('password123'),
            'email_verified_at' => now(),
        ]);
    }

    // =====================================================================
    // TEST-1: soleil_token cookie value is plain UUID, NOT encrypted
    // =====================================================================

    /**
     * After login, the soleil_token Set-Cookie value must be a plain UUID v4.
     *
     * Heuristics to detect encryption:
     *  - UUID v4 matches /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i
     *  - Encrypted payloads start with "eyJ" (base64 JSON from Laravel encrypter)
     *  - Encrypted payloads contain "|" (HMAC separator in some serialisation formats)
     *  - Encrypted payloads are significantly longer than 36 chars (UUID length)
     */
    public function test_soleil_token_cookie_is_plain_uuid_not_encrypted(): void
    {
        $response = $this->withSession([])->postJson('/api/auth/login-httponly', [
            'email' => 'encryption-test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);

        // Extract soleil_token from Set-Cookie headers
        $cookieName = config('sanctum.cookie_name', 'soleil_token');
        $cookieValue = $this->extractCookieValue($response, $cookieName);

        $this->assertNotNull($cookieValue, "Set-Cookie for '{$cookieName}' must be present in login response");

        // ASSERT: Value matches UUID v4 pattern (36 chars, hex groups with dashes)
        $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
        $this->assertMatchesRegularExpression(
            $uuidPattern,
            $cookieValue,
            "soleil_token cookie value must be a valid UUID v4, got: '{$cookieValue}'"
        );

        // ASSERT: Value is exactly 36 characters (UUID length)
        $this->assertEquals(36, strlen($cookieValue), 'UUID must be exactly 36 characters');

        // ASSERT: Does NOT start with "eyJ" (base64-encoded JSON — Laravel encryption prefix)
        $this->assertStringStartsNotWith(
            'eyJ',
            $cookieValue,
            'Cookie value must NOT look like a base64/encrypted payload'
        );

        // ASSERT: Does NOT contain "|" (HMAC separator used in some serialized formats)
        $this->assertStringNotContainsString(
            '|',
            $cookieValue,
            'Cookie value must NOT contain pipe separator (encrypted payload indicator)'
        );

        // ASSERT: Cookie matches the token_identifier stored in DB
        $token = PersonalAccessToken::where('tokenable_id', $this->user->id)
            ->where('name', 'httponly-web-cookie')
            ->first();

        $this->assertNotNull($token, 'Token record must exist in DB');
        $this->assertEquals(
            $token->token_identifier,
            $cookieValue,
            'Cookie value must exactly match the token_identifier stored in DB'
        );

        // ASSERT: DB hash matches SHA256(cookie value) — confirms lookup will work
        $this->assertEquals(
            hash('sha256', $cookieValue),
            $token->token_hash,
            'token_hash in DB must equal SHA256 of the cookie value'
        );
    }

    // =====================================================================
    // TEST-2: Control cookies remain encrypted
    // =====================================================================

    /**
     * At least one other cookie (laravel_session) must remain encrypted.
     *
     * The login-httponly route uses ['web'] middleware, which includes
     * StartSession and EncryptCookies. The laravel_session cookie should
     * be encrypted (not excluded from EncryptCookies).
     *
     * If no control cookie is present in the test environment, the test
     * skips with an explicit message rather than false-passing.
     */
    public function test_control_cookie_remains_encrypted(): void
    {
        $response = $this->withSession([])->postJson('/api/auth/login-httponly', [
            'email' => 'encryption-test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);

        // Look for a control cookie that should still be encrypted
        // Candidates: laravel_session, XSRF-TOKEN
        $cookies = $response->headers->all('set-cookie');

        $controlCookieValue = null;
        $controlCookieName = null;

        foreach (['laravel_session', 'XSRF-TOKEN'] as $candidateName) {
            $value = $this->extractCookieValue($response, $candidateName);
            if ($value !== null) {
                $controlCookieValue = $value;
                $controlCookieName = $candidateName;
                break;
            }
        }

        if ($controlCookieValue === null) {
            $this->markTestSkipped(
                'No control cookie (laravel_session / XSRF-TOKEN) found in login response. '
                .'This may require SESSION_DRIVER=cookie or web middleware to be active. '
                .'Verify manually that other cookies are still encrypted in staging/production.'
            );
        }

        // ASSERT: Control cookie does NOT match UUID pattern (it should be encrypted)
        $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
        $this->assertDoesNotMatchRegularExpression(
            $uuidPattern,
            $controlCookieValue,
            "{$controlCookieName} cookie should NOT be a plain UUID — it must be encrypted"
        );

        // ASSERT: Encrypted cookie is significantly longer than a plain UUID (36 chars)
        // Laravel encrypted values are typically 150+ characters
        $this->assertGreaterThan(
            36,
            strlen($controlCookieValue),
            "{$controlCookieName} cookie value should be longer than 36 chars (encrypted payload)"
        );
    }

    // =====================================================================
    // TEST-3: Cookie UUID resolves user via v1 protected endpoint
    // =====================================================================

    /**
     * A valid soleil_token cookie UUID must authenticate the user through
     * the CheckHttpOnlyTokenValid middleware on the me-httponly endpoint.
     *
     * This verifies the full cookie → hash → DB lookup → user resolution chain
     * using a directly-inserted token record (not going through login).
     *
     * Route: GET /api/auth/me-httponly
     * Middleware: check_httponly_token (CheckHttpOnlyTokenValid)
     */
    public function test_cookie_uuid_resolves_user_on_protected_endpoint(): void
    {
        // Arrange: create token record directly (simulating post-login state)
        $tokenIdentifier = Str::uuid()->toString();
        $tokenHash = hash('sha256', $tokenIdentifier);

        $this->insertTokenRecord($tokenIdentifier, $tokenHash);

        $cookieName = config('sanctum.cookie_name', 'soleil_token');

        // Act: call protected endpoint with cookie
        $response = $this->withHeader('Cookie', "{$cookieName}={$tokenIdentifier}")
            ->getJson('/api/auth/me-httponly');

        // Assert: authenticated successfully — user data returned
        $response->assertStatus(200);
        $response->assertJsonPath('data.user.email', $this->user->email);
    }

    /**
     * Cookie fallback in CheckTokenNotRevokedAndNotExpired passes auth on v1 routes.
     *
     * Route: GET /api/v1/bookings
     * Middleware: check_token_valid (falls back to CheckHttpOnlyTokenValid when no Bearer)
     *
     * Note: BookingController::index() uses auth()->id() which requires the
     * Sanctum guard user to be set. The cookie fallback sets $request->user()
     * but not auth()->guard('sanctum'), so the controller may return 500.
     * This test verifies the middleware layer authenticates (not 401) — the
     * controller compatibility is a separate concern.
     */
    public function test_cookie_fallback_passes_auth_middleware_on_v1_endpoint(): void
    {
        $tokenIdentifier = Str::uuid()->toString();
        $tokenHash = hash('sha256', $tokenIdentifier);

        $this->insertTokenRecord($tokenIdentifier, $tokenHash);

        $cookieName = config('sanctum.cookie_name', 'soleil_token');

        $response = $this->withHeader('Cookie', "{$cookieName}={$tokenIdentifier}")
            ->getJson('/api/v1/bookings');

        // Must NOT be 401 — proving the middleware authenticated the user
        $this->assertNotEquals(
            401,
            $response->status(),
            'Cookie auth must pass the check_token_valid middleware (not 401)'
        );
        // Must NOT be 403 — user is email-verified
        $this->assertNotEquals(
            403,
            $response->status(),
            'Email-verified user must not get 403'
        );
    }

    /**
     * An unknown UUID in the soleil_token cookie must return 401.
     */
    public function test_unknown_uuid_in_cookie_returns_401_on_v1_endpoint(): void
    {
        $fakeUuid = Str::uuid()->toString();
        $cookieName = config('sanctum.cookie_name', 'soleil_token');

        $response = $this->withHeader('Cookie', "{$cookieName}={$fakeUuid}")
            ->getJson('/api/v1/bookings');

        $response->assertStatus(401);
    }

    /**
     * A revoked token UUID in the cookie must return 401 with TOKEN_REVOKED code.
     */
    public function test_revoked_token_cookie_returns_401_on_v1_endpoint(): void
    {
        $tokenIdentifier = Str::uuid()->toString();
        $tokenHash = hash('sha256', $tokenIdentifier);

        $this->insertTokenRecord($tokenIdentifier, $tokenHash, [
            'revoked_at' => now()->subMinute()->toDateTimeString(),
        ]);

        $cookieName = config('sanctum.cookie_name', 'soleil_token');

        $response = $this->withHeader('Cookie', "{$cookieName}={$tokenIdentifier}")
            ->getJson('/api/v1/bookings');

        $response->assertStatus(401);
        $response->assertJson(['code' => 'TOKEN_REVOKED']);
    }

    /**
     * An expired token UUID in the cookie must return 401 with TOKEN_EXPIRED code.
     */
    public function test_expired_token_cookie_returns_401_on_v1_endpoint(): void
    {
        $tokenIdentifier = Str::uuid()->toString();
        $tokenHash = hash('sha256', $tokenIdentifier);

        $this->insertTokenRecord($tokenIdentifier, $tokenHash, [
            'expires_at' => now()->subHour()->toDateTimeString(),
        ]);

        $cookieName = config('sanctum.cookie_name', 'soleil_token');

        $response = $this->withHeader('Cookie', "{$cookieName}={$tokenIdentifier}")
            ->getJson('/api/v1/bookings');

        $response->assertStatus(401);
        $response->assertJson(['code' => 'TOKEN_EXPIRED']);
    }

    // =====================================================================
    // TEST-4: Security headers still present
    // =====================================================================

    /**
     * SecurityHeaders middleware is globally prepended (bootstrap/app.php:39).
     * Verify that the EncryptCookies exception did not affect the middleware
     * pipeline — all expected security headers must still be present.
     *
     * In test env, APP_DEBUG=false (phpunit.xml) → SecurityHeaders treats
     * this as production-like, so all headers including HSTS should be set.
     */
    public function test_security_headers_present_on_api_response(): void
    {
        // Use the public ping endpoint — no auth or DB deps needed, still
        // goes through the global middleware pipeline.
        $response = $this->getJson('/api/ping');

        $response->assertStatus(200);

        // Core security headers (SecurityHeaders.php applies all of these)
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
        $response->assertHeader('Cross-Origin-Embedder-Policy', 'credentialless');
        $response->assertHeader('Cross-Origin-Resource-Policy', 'same-origin');

        // HSTS — set in both dev and prod mode in SecurityHeaders.php
        $this->assertNotEmpty(
            $response->headers->get('Strict-Transport-Security'),
            'Strict-Transport-Security header must be present'
        );

        // CSP — always set (dev vs prod variants, but always present)
        $this->assertNotEmpty(
            $response->headers->get('Content-Security-Policy'),
            'Content-Security-Policy header must be present'
        );

        // Permissions-Policy — disables dangerous browser APIs
        $this->assertNotEmpty(
            $response->headers->get('Permissions-Policy'),
            'Permissions-Policy header must be present'
        );
    }

    /**
     * Security headers must also be present on authenticated httpOnly
     * cookie endpoints — verifies global middleware is not stripped
     * by route-specific middleware groups.
     */
    public function test_security_headers_present_on_httponly_authenticated_endpoint(): void
    {
        // Arrange: create valid token
        $tokenIdentifier = Str::uuid()->toString();
        $tokenHash = hash('sha256', $tokenIdentifier);

        $this->insertTokenRecord($tokenIdentifier, $tokenHash);

        $cookieName = config('sanctum.cookie_name', 'soleil_token');

        $response = $this->withHeader('Cookie', "{$cookieName}={$tokenIdentifier}")
            ->getJson('/api/auth/me-httponly');

        $response->assertStatus(200);

        // Same core headers must be present on authenticated endpoints
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertNotEmpty(
            $response->headers->get('Content-Security-Policy'),
            'CSP must be present on authenticated httpOnly endpoints'
        );
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    /**
     * Insert a token record into personal_access_tokens using DB::table.
     *
     * Uses the same insert pattern as HttpOnlyTokenController::login()
     * to avoid SQLite datatype mismatch issues with Eloquent forceCreate.
     *
     * @param  array<string, mixed>  $overrides  Column overrides (e.g., revoked_at, expires_at)
     */
    private function insertTokenRecord(string $tokenIdentifier, string $tokenHash, array $overrides = []): void
    {
        $defaults = [
            'name' => 'httponly-web-cookie',
            'token' => $tokenHash,
            'token_identifier' => $tokenIdentifier,
            'token_hash' => $tokenHash,
            'abilities' => json_encode(['*']),
            'tokenable_id' => $this->user->id,
            'tokenable_type' => 'App\\Models\\User',
            'expires_at' => now()->addHour()->toDateTimeString(),
            'type' => 'short_lived',
            'device_id' => Str::uuid()->toString(),
            'refresh_count' => 0,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ];

        DB::table('personal_access_tokens')->insert(array_merge($defaults, $overrides));
    }

    /**
     * Parse Set-Cookie headers and extract the value for a given cookie name.
     *
     * Handles URL-encoded values (e.g., XSRF-TOKEN is URL-encoded by Laravel).
     *
     * @return string|null The cookie value, or null if not found
     */
    private function extractCookieValue($response, string $cookieName): ?string
    {
        $cookies = $response->headers->all('set-cookie');

        foreach ($cookies as $cookieHeader) {
            // Set-Cookie format: "name=value; path=/; ..."
            // Extract "name=value" before the first ";"
            $parts = explode(';', $cookieHeader, 2);
            $nameValue = trim($parts[0]);

            // Split on first "=" only (value may contain "=")
            $eqPos = strpos($nameValue, '=');
            if ($eqPos === false) {
                continue;
            }

            $name = substr($nameValue, 0, $eqPos);
            $value = substr($nameValue, $eqPos + 1);

            if ($name === $cookieName) {
                return urldecode($value);
            }
        }

        return null;
    }
}
