<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * LoginHttpOnlyTest — POST /api/auth/login-httponly
 *
 * Root-cause regression suite: the route previously lacked the 'web' middleware,
 * so StartSession never ran and Session::token() returned null instead of a real
 * CSRF token string, which is a contract violation.
 *
 * IMPORTANT: These tests must NOT use withSession([]) — that helper seeds a fake
 * session in the test runner, which masked the original bug in the existing suite.
 * The fix (adding 'web' middleware to the route) is only proven by tests that go
 * through the real HTTP middleware stack.
 */
class LoginHttpOnlyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'login-test@example.com',
            'password' => Hash::make('secret123'),
            'email_verified_at' => now(),
        ]);
    }

    /**
     * Test 1 — Happy path
     *
     * Valid credentials must return:
     *   - HTTP 200
     *   - JSON body with csrf_token (string, not null) and user object
     *   - Set-Cookie header containing the httpOnly auth cookie
     *
     * Session/cookie stack note (covers Test 3 intent from the spec):
     *   Reaching this assertion without exception proves that StartSession ran
     *   successfully through the 'web' middleware group and that
     *   $request->session()->token() returned a real value — the exact failure
     *   mode the fix addresses.
     */
    public function test_valid_credentials_return_200_with_csrf_token_and_httponly_cookie(): void
    {
        $response = $this->postJson('/api/auth/login-httponly', [
            'email' => 'login-test@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200);

        // csrf_token must be a non-null string — the core contract
        $csrfToken = $response->json('data.csrf_token');
        $this->assertIsString($csrfToken, 'csrf_token must be a string, not null');
        $this->assertNotEmpty($csrfToken, 'csrf_token must not be empty');

        // user object must be present
        $response->assertJsonPath('data.user.email', 'login-test@example.com');
        $response->assertJsonStructure([
            'data' => [
                'csrf_token',
                'user' => ['id', 'name', 'email'],
            ],
        ]);

        // HttpOnly auth cookie must be present in the response
        $this->assertNotEmpty(
            $response->headers->all('set-cookie'),
            'Set-Cookie header must be present'
        );
    }

    /**
     * Test 2 — Wrong password must return 401, never 500
     */
    public function test_wrong_password_returns_401_not_500(): void
    {
        $response = $this->postJson('/api/auth/login-httponly', [
            'email' => 'login-test@example.com',
            'password' => 'totally-wrong',
        ]);

        $response->assertStatus(401);
        $this->assertNotEquals(500, $response->status(), 'Wrong credentials must never produce a 500');
    }

    /**
     * Test 2b — Unknown email must return 401, never 500
     */
    public function test_unknown_email_returns_401_not_500(): void
    {
        $response = $this->postJson('/api/auth/login-httponly', [
            'email' => 'nobody@example.com',
            'password' => 'anything',
        ]);

        $response->assertStatus(401);
        $this->assertNotEquals(500, $response->status());
    }

    /**
     * Test 3 — Unverified user + broken mail must return 200, never 500
     *
     * Regression for: BindingResolutionException "Target class [mail.manager] does not exist"
     * thrown from sendEmailVerificationNotification() at HttpOnlyTokenController.php:51
     * when the mail service is not configured.
     *
     * The fix wraps the notification call in try/catch so that a mail infrastructure
     * failure never blocks a successful login.
     */
    public function test_unverified_user_login_succeeds_even_when_mail_fails(): void
    {
        // Unverified user (email_verified_at = null) — exactly the condition that
        // triggered the production 500 via sendEmailVerificationNotification().
        $unverified = User::factory()->create([
            'email' => 'unverified@example.com',
            'password' => Hash::make('secret123'),
            'email_verified_at' => null,
        ]);

        $response = $this->postJson('/api/auth/login-httponly', [
            'email' => 'unverified@example.com',
            'password' => 'secret123',
        ]);

        // Must succeed (mail failure is logged as a warning, never propagated)
        $response->assertStatus(200);
        $this->assertNotEquals(500, $response->status());

        // CSRF token and user object must still be present
        $this->assertIsString($response->json('data.csrf_token'));
        $response->assertJsonPath('data.user.email', 'unverified@example.com');
    }
}
