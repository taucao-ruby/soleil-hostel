<?php

namespace Tests\Feature\Auth;

use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Notifications\EmailVerificationCodeNotification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Email Verification — OTP Code Flow Feature Tests
 *
 * Tests the complete 6-digit OTP code verification flow:
 * 1. Send code, verify code, status check
 * 2. Invalid/expired/exhausted code handling
 * 3. Cooldown enforcement
 * 4. User binding (one user cannot use another's code)
 * 5. Replay protection (consumed code cannot be reused)
 * 6. Registration sends initial code
 * 7. Login auto-resend for unverified users
 */
class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    // ========== HELPER ==========

    /**
     * Create a verification code record directly in the database
     * and return the raw plaintext code.
     */
    private function createCodeForUser(User $user): string
    {
        $rawCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        EmailVerificationCode::create([
            'user_id' => $user->id,
            'code_hash' => hash('sha256', $rawCode),
            'expires_at' => now()->addMinutes(15),
            'attempts' => 0,
            'max_attempts' => 5,
            'last_sent_at' => now(),
        ]);

        return $rawCode;
    }

    // ========== VERIFICATION REQUIRED TESTS ==========

    /** @test */
    public function unverified_user_cannot_access_verified_routes()
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/bookings');

        $response->assertStatus(403);
    }

    /** @test */
    public function verified_user_can_access_verified_routes()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/bookings');

        $response->assertStatus(200);
    }

    // ========== SEND CODE TESTS ==========

    /** @test */
    public function unverified_user_can_request_verification_code()
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson('/api/email/send-code');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Verification code sent to your email.',
            ]);

        Notification::assertSentTo($user, EmailVerificationCodeNotification::class);

        $this->assertDatabaseHas('email_verification_codes', [
            'user_id' => $user->id,
        ]);
    }

    /** @test */
    public function verified_user_requesting_code_gets_already_verified()
    {
        Notification::fake();

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson('/api/email/send-code');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Email already verified.',
                'verified' => true,
            ]);

        Notification::assertNothingSent();
    }

    /** @test */
    public function send_code_enforces_cooldown()
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // First request succeeds
        $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson('/api/email/send-code')->assertStatus(200);

        // Second request within cooldown is rejected
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson('/api/email/send-code');

        $response->assertStatus(429)
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonStructure(['cooldown_remaining_seconds']);
    }

    /** @test */
    public function send_code_invalidates_previous_active_codes()
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        // Issue first code
        app(\App\Services\EmailVerificationCodeService::class)->issue($user);

        $this->assertDatabaseHas('email_verification_codes', [
            'user_id' => $user->id,
            'consumed_at' => null,
        ]);

        // Travel past cooldown
        $this->travel(61)->seconds();

        // Issue second code
        app(\App\Services\EmailVerificationCodeService::class)->issue($user);

        // First code should be consumed (invalidated)
        $codes = EmailVerificationCode::where('user_id', $user->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $codes);
        $this->assertNotNull($codes[0]->consumed_at); // old code invalidated
        $this->assertNull($codes[1]->consumed_at);     // new code active
    }

    // ========== VERIFY CODE TESTS ==========

    /** @test */
    public function user_can_verify_with_correct_code()
    {
        Event::fake([Verified::class]);

        $user = User::factory()->unverified()->create();
        $rawCode = $this->createCodeForUser($user);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson('/api/email/verify-code', [
            'code' => $rawCode,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Email verified successfully.',
                'verified' => true,
            ]);

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);

        Event::assertDispatched(Verified::class, function ($event) use ($user) {
            return $event->user->id === $user->id;
        });
    }

    /** @test */
    public function invalid_code_returns_error_with_attempts_remaining()
    {
        $user = User::factory()->unverified()->create();
        $this->createCodeForUser($user);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson('/api/email/verify-code', [
            'code' => '000000',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid verification code.',
            ])
            ->assertJsonStructure(['attempts_remaining']);

        $user->refresh();
        $this->assertNull($user->email_verified_at);
    }

    /** @test */
    public function already_verified_user_gets_idempotent_response()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson('/api/email/verify-code', [
            'code' => '123456',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Email already verified.',
                'verified' => true,
            ]);
    }

    /** @test */
    public function expired_code_returns_error()
    {
        $user = User::factory()->unverified()->create();
        $rawCode = $this->createCodeForUser($user);
        $token = $user->createToken('test-token')->plainTextToken;

        // Travel past the 15-minute expiry
        $this->travel(16)->minutes();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson('/api/email/verify-code', [
            'code' => $rawCode,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $user->refresh();
        $this->assertNull($user->email_verified_at);
    }

    /** @test */
    public function exhausted_attempts_returns_error()
    {
        $user = User::factory()->unverified()->create();
        $this->createCodeForUser($user);
        $token = $user->createToken('test-token')->plainTextToken;

        // Exhaust all 5 attempts
        for ($i = 0; $i < 5; $i++) {
            $this->withHeaders([
                'Authorization' => "Bearer {$token}",
            ])->postJson('/api/email/verify-code', [
                'code' => '000000',
            ]);
        }

        // 6th attempt should show exhausted
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson('/api/email/verify-code', [
            'code' => '111111',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $user->refresh();
        $this->assertNull($user->email_verified_at);
    }

    /** @test */
    public function no_active_code_returns_error()
    {
        $user = User::factory()->unverified()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // No code issued — attempt to verify
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson('/api/email/verify-code', [
            'code' => '123456',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'No active verification code found. Please request a new one.',
            ]);
    }

    /** @test */
    public function consumed_code_cannot_be_replayed()
    {
        Event::fake([Verified::class]);

        $user = User::factory()->unverified()->create();
        $rawCode = $this->createCodeForUser($user);
        $token = $user->createToken('test-token')->plainTextToken;

        // First verify succeeds
        $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson('/api/email/verify-code', [
            'code' => $rawCode,
        ])->assertStatus(200);

        // Code record should be consumed
        $codeRecord = EmailVerificationCode::where('user_id', $user->id)->first();
        $this->assertNotNull($codeRecord->consumed_at);

        // No active codes remain for this user
        $activeCount = EmailVerificationCode::where('user_id', $user->id)
            ->active()
            ->count();
        $this->assertEquals(0, $activeCount);
    }

    /** @test */
    public function code_validation_fails_for_non_6_digit_input()
    {
        $user = User::factory()->unverified()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // Too short
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson('/api/email/verify-code', [
            'code' => '123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('code');

        // Non-numeric
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson('/api/email/verify-code', [
            'code' => 'abcdef',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('code');

        // Missing
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson('/api/email/verify-code', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }

    // ========== USER BINDING TESTS ==========

    /** @test */
    public function user_cannot_verify_with_another_users_code()
    {
        $userA = User::factory()->unverified()->create();
        $userB = User::factory()->unverified()->create();

        // Issue a code for user A
        $rawCode = $this->createCodeForUser($userA);

        // User B tries to use user A's code
        $tokenB = $userB->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$tokenB}",
        ])->postJson('/api/email/verify-code', [
            'code' => $rawCode,
        ]);

        // User B should get NoActiveCode (they have no code of their own)
        $response->assertStatus(422);

        $userB->refresh();
        $this->assertNull($userB->email_verified_at);

        $userA->refresh();
        $this->assertNull($userA->email_verified_at);
    }

    // ========== VERIFICATION STATUS TESTS ==========

    /** @test */
    public function can_check_verification_status_for_unverified_user()
    {
        $user = User::factory()->unverified()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/email/verification-status');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'verified' => false,
                'email' => $user->email,
            ])
            ->assertJsonPath('email_verified_at', null)
            ->assertJsonStructure(['cooldown_remaining_seconds']);
    }

    /** @test */
    public function can_check_verification_status_for_verified_user()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/email/verification-status');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'verified' => true,
                'email' => $user->email,
            ]);

        $this->assertNotNull($response->json('email_verified_at'));
    }

    // ========== VERIFICATION NOTICE TESTS ==========

    /** @test */
    public function verification_notice_returns_unverified_status()
    {
        $user = User::factory()->unverified()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/email/verify');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Please verify your email address.',
                'verified' => false,
            ]);
    }

    /** @test */
    public function verification_notice_returns_verified_status()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/email/verify');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Email already verified.',
                'verified' => true,
            ]);
    }

    // ========== EMAIL CHANGE RE-VERIFICATION TESTS ==========

    /** @test */
    public function email_change_clears_verification_status()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->assertNotNull($user->email_verified_at);

        $changed = $user->changeEmail('newemail@example.com');
        $user->save();

        $this->assertTrue($changed);
        $user->refresh();
        $this->assertNull($user->email_verified_at);
        $this->assertEquals('newemail@example.com', $user->email);
    }

    /** @test */
    public function user_must_reverify_after_email_change()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $user->changeEmail('changed@example.com');
        $user->save();

        $user->refresh();
        $this->assertNull($user->email_verified_at);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/bookings');

        $response->assertStatus(403);
    }

    // ========== CHANGE EMAIL METHOD TESTS ==========

    /** @test */
    public function change_email_method_returns_true_when_email_changes()
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'email_verified_at' => now(),
        ]);

        $changed = $user->changeEmail('new@example.com');

        $this->assertTrue($changed);
        $this->assertEquals('new@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    /** @test */
    public function change_email_method_returns_false_when_email_stays_same()
    {
        $user = User::factory()->create([
            'email' => 'same@example.com',
            'email_verified_at' => now(),
        ]);

        $originalVerifiedAt = $user->email_verified_at;

        $changed = $user->changeEmail('same@example.com');

        $this->assertFalse($changed);
        $this->assertEquals('same@example.com', $user->email);
        $this->assertEquals($originalVerifiedAt, $user->email_verified_at);
    }

    // ========== REGISTRATION SENDS VERIFICATION CODE TEST ==========

    /** @test */
    public function registration_sends_verification_code()
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'newuser@example.com',
            'password' => 'P@ssworD123',
            'password_confirmation' => 'P@ssworD123',
        ]);

        $response->assertStatus(201);

        $user = User::where('email', 'newuser@example.com')->first();
        $this->assertNotNull($user);

        // email_verified_at must be NULL — registration must not auto-verify.
        $this->assertNull($user->email_verified_at);

        Notification::assertSentTo($user, EmailVerificationCodeNotification::class);

        $this->assertDatabaseHas('email_verification_codes', [
            'user_id' => $user->id,
        ]);
    }

    /** @test */
    public function registration_verification_notification_is_queued()
    {
        // EmailVerificationCodeNotification implements ShouldQueue.
        // In production (QUEUE_CONNECTION=redis/database) this means the notification
        // is pushed to the queue and ONLY delivered if a worker is running.
        // In local dev: set QUEUE_CONNECTION=sync in .env (see .env.example) so
        // emails fire synchronously without a separate queue:work process.
        // This test is a contract assertion — if ShouldQueue is removed by mistake,
        // this fails and alerts the team that the notification is now synchronous.
        $this->assertInstanceOf(
            \Illuminate\Contracts\Queue\ShouldQueue::class,
            new \App\Notifications\EmailVerificationCodeNotification('123456', 15),
            'EmailVerificationCodeNotification must implement ShouldQueue for production queue delivery. '.
            'Ensure QUEUE_CONNECTION=sync for local dev or run php artisan queue:work.'
        );
    }

    // ========== UNAUTHENTICATED ACCESS TESTS ==========

    /** @test */
    public function unauthenticated_user_cannot_send_code()
    {
        $response = $this->postJson('/api/email/send-code');

        $response->assertStatus(401);
    }

    /** @test */
    public function unauthenticated_user_cannot_verify_code()
    {
        $response = $this->postJson('/api/email/verify-code', [
            'code' => '123456',
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function unauthenticated_user_cannot_check_status()
    {
        $response = $this->getJson('/api/email/verification-status');

        $response->assertStatus(401);
    }

    // ========== ME ENDPOINT VERIFIED-STATE CONTRACT TESTS ==========

    /** @test */
    public function me_endpoint_returns_email_verified_at_null_for_unverified_user()
    {
        $user = User::factory()->unverified()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.email_verified_at', null);
    }

    /** @test */
    public function me_endpoint_returns_email_verified_at_after_verification()
    {
        Event::fake([Verified::class]);

        $user = User::factory()->unverified()->create();
        $rawCode = $this->createCodeForUser($user);
        $token = $user->createToken('test-token')->plainTextToken;

        // Verify the code
        $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson('/api/email/verify-code', [
            'code' => $rawCode,
        ])->assertStatus(200);

        // Now check /me — email_verified_at must be present
        $meResponse = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/auth/me');

        $meResponse->assertStatus(200);
        $this->assertNotNull(
            $meResponse->json('data.email_verified_at'),
            'email_verified_at must be non-null in /me response after successful verification'
        );
    }
}
