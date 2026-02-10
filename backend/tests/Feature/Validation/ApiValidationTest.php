<?php

namespace Tests\Feature\Validation;

use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * ApiValidationTest — TST-006
 *
 * Tests API validation edge cases: malformed JSON, wrong Content-Type,
 * booking date validation, registration validation, and contact form validation.
 */
class ApiValidationTest extends TestCase
{
    protected User $verifiedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->verifiedUser = User::factory()->create([
            'email_verified_at' => now(),
            'password' => bcrypt('password123'),
        ]);
    }

    // ========================================================================
    // Malformed / Invalid Request Tests
    // ========================================================================

    /**
     * Sending malformed JSON should return a client error (not 500).
     */
    public function test_malformed_json_returns_error(): void
    {
        $response = $this->call(
            'POST',
            '/api/contact',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            '{invalid json body'
        );

        // Should return 422 (validation error — parsed body is empty) or 400
        $this->assertTrue(
            in_array($response->status(), [400, 422]),
            "Expected 400 or 422 for malformed JSON, got {$response->status()}"
        );
    }

    /**
     * Sending wrong Content-Type should still be handled gracefully.
     */
    public function test_wrong_content_type_returns_error(): void
    {
        $response = $this->call(
            'POST',
            '/api/contact',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'text/plain',
                'HTTP_ACCEPT' => 'application/json',
            ],
            'This is plain text, not JSON'
        );

        // Should return a client error (validation failure), not a 500
        $this->assertTrue(
            $response->status() >= 400 && $response->status() < 500,
            "Expected 4xx error for wrong Content-Type, got {$response->status()}"
        );
    }

    // ========================================================================
    // Booking Validation Tests
    // ========================================================================

    /**
     * Booking with a past check-in date should be rejected.
     */
    public function test_booking_requires_future_dates(): void
    {
        $room = Room::factory()->create(['status' => 'available']);

        $response = $this->actingAs($this->verifiedUser, 'sanctum')
            ->postJson('/api/v1/bookings', [
                'room_id' => $room->id,
                'check_in' => Carbon::yesterday()->format('Y-m-d'),
                'check_out' => Carbon::tomorrow()->format('Y-m-d'),
                'guest_name' => 'Test Guest',
                'guest_email' => 'guest@example.com',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('check_in');
    }

    /**
     * Booking check_out must be after check_in.
     */
    public function test_booking_check_out_must_be_after_check_in(): void
    {
        $room = Room::factory()->create(['status' => 'available']);

        $checkIn = Carbon::now()->addDays(5)->format('Y-m-d');
        $checkOut = Carbon::now()->addDays(3)->format('Y-m-d'); // Before check_in

        $response = $this->actingAs($this->verifiedUser, 'sanctum')
            ->postJson('/api/v1/bookings', [
                'room_id' => $room->id,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'guest_name' => 'Test Guest',
                'guest_email' => 'guest@example.com',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('check_out');
    }

    // ========================================================================
    // Registration Validation Tests
    // ========================================================================

    /**
     * Registration with a weak (too short) password should be rejected.
     */
    public function test_registration_requires_strong_password(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Weak Password User',
            'email' => 'weak@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('password');
    }

    /**
     * Registration with an already-registered email should be rejected.
     */
    public function test_registration_rejects_duplicate_email(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Duplicate Email User',
            'email' => $this->verifiedUser->email,
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }

    // ========================================================================
    // Contact Form Validation Tests
    // ========================================================================

    /**
     * Contact form with invalid email should be rejected.
     */
    public function test_contact_form_requires_valid_email(): void
    {
        $response = $this->postJson('/api/contact', [
            'name' => 'Test User',
            'email' => 'not-an-email',
            'message' => 'Test message body here.',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }

    /**
     * POST with completely empty body should return 422.
     */
    public function test_empty_request_body_returns_422(): void
    {
        $response = $this->postJson('/api/contact', []);

        $response->assertStatus(422);
    }
}
