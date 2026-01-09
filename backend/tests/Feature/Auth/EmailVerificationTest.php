<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Email Verification Feature Tests
 * 
 * Tests the complete email verification flow:
 * 1. Unverified users cannot access protected routes
 * 2. Verification link works correctly
 * 3. Expired verification links are rejected
 * 4. Re-verification when email changes
 */
class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    // ========== VERIFICATION REQUIRED TESTS ==========

    /** @test */
    public function unverified_user_cannot_access_verified_routes()
    {
        // Arrange: Create unverified user
        $user = User::factory()->unverified()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // Act: Try to access bookings (requires verification)
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/bookings');

        // Assert: Should be forbidden
        $response->assertStatus(403);
    }

    /** @test */
    public function verified_user_can_access_verified_routes()
    {
        // Arrange: Create verified user
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        // Act: Access bookings
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/bookings');

        // Assert: Should succeed (200 or empty bookings)
        $response->assertStatus(200);
    }

    // ========== VERIFICATION STATUS TESTS ==========

    /** @test */
    public function can_check_verification_status_for_unverified_user()
    {
        // Arrange
        $user = User::factory()->unverified()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // Act
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/email/verification-status');

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'verified' => false,
                'email' => $user->email,
            ])
            ->assertJsonPath('email_verified_at', null);
    }

    /** @test */
    public function can_check_verification_status_for_verified_user()
    {
        // Arrange
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        // Act
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/email/verification-status');

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'verified' => true,
                'email' => $user->email,
            ]);
        
        $this->assertNotNull($response->json('email_verified_at'));
    }

    // ========== VERIFICATION LINK TESTS ==========

    /** @test */
    public function user_can_verify_email_with_valid_signed_url()
    {
        Event::fake([Verified::class]);

        // Arrange
        $user = User::factory()->unverified()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // Generate signed verification URL
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        // Extract path from URL for API call
        $path = parse_url($verificationUrl, PHP_URL_PATH);
        $query = parse_url($verificationUrl, PHP_URL_QUERY);

        // Act
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->getJson("{$path}?{$query}");

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'verified' => true,
            ]);

        // Refresh user from database
        $user->refresh();
        $this->assertNotNull($user->email_verified_at);

        // Event should be dispatched
        Event::assertDispatched(Verified::class, function ($event) use ($user) {
            return $event->user->id === $user->id;
        });
    }

    /** @test */
    public function already_verified_user_gets_success_response()
    {
        // Arrange
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $path = parse_url($verificationUrl, PHP_URL_PATH);
        $query = parse_url($verificationUrl, PHP_URL_QUERY);

        // Act
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->getJson("{$path}?{$query}");

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Email already verified.',
            ]);
    }

    // ========== EXPIRED LINK TESTS ==========

    /** @test */
    public function expired_verification_link_is_rejected()
    {
        // Arrange
        $user = User::factory()->unverified()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // Generate expired URL (in the past)
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->subMinutes(1), // Already expired
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $path = parse_url($verificationUrl, PHP_URL_PATH);
        $query = parse_url($verificationUrl, PHP_URL_QUERY);

        // Act
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->getJson("{$path}?{$query}");

        // Assert: Should be forbidden (invalid signature)
        $response->assertStatus(403);

        // User should still be unverified
        $user->refresh();
        $this->assertNull($user->email_verified_at);
    }

    /** @test */
    public function verification_link_expires_after_configured_time()
    {
        // Arrange
        $user = User::factory()->unverified()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // Generate URL that expires in 60 minutes
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $path = parse_url($verificationUrl, PHP_URL_PATH);
        $query = parse_url($verificationUrl, PHP_URL_QUERY);

        // Travel 2 days into the future
        $this->travel(2)->days();

        // Act
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->getJson("{$path}?{$query}");

        // Assert: Should be forbidden (expired)
        $response->assertStatus(403);
    }

    // ========== RESEND VERIFICATION TESTS ==========

    /** @test */
    public function user_can_request_verification_email_resend()
    {
        // Arrange
        $user = User::factory()->unverified()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // Act
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson('/api/email/verification-notification');

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Verification link sent to your email.',
            ]);

        // Notification should be sent
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    /** @test */
    public function verified_user_cannot_request_resend()
    {
        // Arrange
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        // Act
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson('/api/email/verification-notification');

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Email already verified.',
            ]);

        // No notification should be sent
        Notification::assertNothingSent();
    }

    // ========== EMAIL CHANGE RE-VERIFICATION TESTS ==========

    /** @test */
    public function email_change_clears_verification_status()
    {
        // Arrange: Start with verified user
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->assertNotNull($user->email_verified_at);

        // Act: Change email (simulating user update)
        $user->email = 'newemail@example.com';
        $user->email_verified_at = null; // Must clear on email change
        $user->save();

        // Assert
        $user->refresh();
        $this->assertNull($user->email_verified_at);
        $this->assertEquals('newemail@example.com', $user->email);
    }

    /** @test */
    public function user_must_reverify_after_email_change()
    {
        // Arrange: Create verified user
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        // Verify can access protected route initially
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/bookings');
        $response->assertStatus(200);

        // Act: Change email and clear verification
        $user->update([
            'email' => 'changed@example.com',
            'email_verified_at' => null,
        ]);

        // Assert: Can no longer access protected route
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/bookings');
        
        $response->assertStatus(403);
    }

    // ========== RATE LIMITING TESTS ==========

    /** @test */
    public function verification_resend_is_rate_limited()
    {
        // Arrange
        $user = User::factory()->unverified()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // Act: Send many requests
        for ($i = 0; $i < 5; $i++) {
            $this->withHeaders([
                'Authorization' => "Bearer {$token}",
            ])->postJson('/api/email/verification-notification');
        }

        // 6th request should be rate limited
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson('/api/email/verification-notification');

        // Assert: Should be rate limited (429)
        $response->assertStatus(429);
    }

    // ========== VERIFICATION NOTICE TESTS ==========

    /** @test */
    public function verification_notice_returns_unverified_status()
    {
        // Arrange
        $user = User::factory()->unverified()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // Act
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/email/verify');

        // Assert
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
        // Arrange
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        // Act
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/email/verify');

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Email already verified.',
                'verified' => true,
            ]);
    }

    // ========== REGISTRATION SENDS VERIFICATION EMAIL TEST ==========

    /** @test */
    public function registration_sends_verification_email()
    {
        // Act: Register new user
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        // Assert: Registration successful
        $response->assertStatus(201);

        // Verification email should be sent
        $user = User::where('email', 'newuser@example.com')->first();
        $this->assertNotNull($user);
        
        Notification::assertSentTo($user, VerifyEmail::class);
    }
}
