<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Tests\TestCase;

/**
 * PasswordResetTest — TST-003
 *
 * Password reset flow tests.
 *
 * ⚠️ NOTE: No password reset routes (POST /api/forgot-password, POST /api/reset-password)
 * are implemented in this application. These tests document the EXPECTED behavior
 * and serve as a specification for when the feature is built.
 *
 * Active tests verify the endpoints are not yet available (404).
 * Commented-out tests show the full specification to implement.
 */
class PasswordResetTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'resettest@example.com',
            'password' => bcrypt('OldPassword123!'),
        ]);
    }

    /**
     * POST /api/forgot-password — endpoint not implemented.
     *
     * @group password-reset
     * @group not-implemented
     */
    public function test_forgot_password_endpoint_not_implemented(): void
    {
        $response = $this->postJson('/api/forgot-password', [
            'email' => $this->user->email,
        ]);

        $response->assertStatus(404);
    }

    /**
     * POST /api/reset-password — endpoint not implemented.
     *
     * @group password-reset
     * @group not-implemented
     */
    public function test_reset_password_endpoint_not_implemented(): void
    {
        $response = $this->postJson('/api/reset-password', [
            'email' => $this->user->email,
            'token' => 'fake-token',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertStatus(404);
    }

    // =========================================================================
    // Specification tests — uncomment when password reset routes are implemented
    // =========================================================================

    // public function test_user_can_request_password_reset_email(): void
    // {
    //     \Illuminate\Support\Facades\Notification::fake();
    //
    //     $response = $this->postJson('/api/forgot-password', [
    //         'email' => $this->user->email,
    //     ]);
    //
    //     $response->assertStatus(200);
    //     \Illuminate\Support\Facades\Notification::assertSentTo(
    //         $this->user,
    //         \Illuminate\Auth\Notifications\ResetPassword::class
    //     );
    // }

    // public function test_password_reset_requires_valid_email(): void
    // {
    //     $response = $this->postJson('/api/forgot-password', [
    //         'email' => 'not-an-email',
    //     ]);
    //
    //     $response->assertStatus(422);
    //     $response->assertJsonValidationErrors('email');
    // }

    // public function test_password_reset_with_nonexistent_email_returns_200(): void
    // {
    //     // Should NOT reveal whether the email exists in the system
    //     $response = $this->postJson('/api/forgot-password', [
    //         'email' => 'nobody@example.com',
    //     ]);
    //
    //     $response->assertStatus(200);
    // }

    // public function test_user_can_reset_password_with_valid_token(): void
    // {
    //     $token = \Illuminate\Support\Facades\Password::createToken($this->user);
    //
    //     $response = $this->postJson('/api/reset-password', [
    //         'email' => $this->user->email,
    //         'token' => $token,
    //         'password' => 'NewPassword123!',
    //         'password_confirmation' => 'NewPassword123!',
    //     ]);
    //
    //     $response->assertStatus(200);
    //     $this->assertTrue(
    //         \Illuminate\Support\Facades\Hash::check('NewPassword123!', $this->user->fresh()->password)
    //     );
    // }

    // public function test_password_reset_fails_with_invalid_token(): void
    // {
    //     $response = $this->postJson('/api/reset-password', [
    //         'email' => $this->user->email,
    //         'token' => 'invalid-token-value',
    //         'password' => 'NewPassword123!',
    //         'password_confirmation' => 'NewPassword123!',
    //     ]);
    //
    //     $response->assertStatus(422);
    // }

    // public function test_password_reset_fails_with_expired_token(): void
    // {
    //     $token = \Illuminate\Support\Facades\Password::createToken($this->user);
    //
    //     // Travel past the token expiry timeout
    //     $this->travel(config('auth.passwords.users.expire', 60) + 1)->minutes();
    //
    //     $response = $this->postJson('/api/reset-password', [
    //         'email' => $this->user->email,
    //         'token' => $token,
    //         'password' => 'NewPassword123!',
    //         'password_confirmation' => 'NewPassword123!',
    //     ]);
    //
    //     $response->assertUnprocessable();
    // }
}
