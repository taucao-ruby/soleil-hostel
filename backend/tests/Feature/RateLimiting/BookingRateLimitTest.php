<?php

namespace Tests\Feature\RateLimiting;

use Tests\TestCase;
use App\Models\User;
use App\Models\Room;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BookingRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->room = Room::factory()->create();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_booking_rate_limit_3_per_minute_per_user(): void
    {
        // Use Sanctum guard for API authentication
        $this->actingAs($this->user, 'sanctum');

        // Attempt 3 bookings within 1 minute (should succeed or fail gracefully)
        for ($i = 0; $i < 3; $i++) {
            $response = $this->post('/api/bookings', [
                'room_id' => $this->room->id,
                'check_in' => now()->addDays(1 + $i * 5)->format('Y-m-d'),
                'check_out' => now()->addDays(3 + $i * 5)->format('Y-m-d'),
                'guest_name' => 'Test Guest ' . $i,
                'guest_email' => 'guest' . $i . '@example.com',
                'guest_phone' => '+8491234567' . $i,
            ]);

            // Should not be rate limited yet
            $this->assertNotEquals(429, $response->status());
        }

        // 4th attempt should be rate limited
        $response = $this->post('/api/bookings', [
            'room_id' => $this->room->id,
            'check_in' => now()->addDays(50)->format('Y-m-d'),
            'check_out' => now()->addDays(52)->format('Y-m-d'),
            'guest_name' => 'Test Guest 4',
            'guest_email' => 'guest4@example.com',
            'guest_phone' => '+84912345674',
        ]);

        $this->assertEquals(429, $response->status());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_booking_rate_limit_different_users_separate(): void
    {
        $user1 = $this->user;
        $user2 = User::factory()->create();

        // User 1: Make 3 requests (hit limit)
        $this->actingAs($user1, 'sanctum');
        for ($i = 0; $i < 3; $i++) {
            $this->post('/api/bookings', [
                'room_id' => $this->room->id,
                'check_in' => now()->addDays($i + 1)->format('Y-m-d'),
                'check_out' => now()->addDays($i + 3)->format('Y-m-d'),
                'guest_name' => 'Guest ' . $i,
                'guest_email' => 'guest' . $i . '@example.com',
                'guest_phone' => '+8491234567' . $i,
            ]);
        }

        // User 2: Should have fresh limit
        $this->actingAs($user2, 'sanctum');
        $response = $this->post('/api/bookings', [
            'room_id' => $this->room->id,
            'check_in' => now()->addDays(10)->format('Y-m-d'),
            'check_out' => now()->addDays(12)->format('Y-m-d'),
            'guest_name' => 'Guest 2',
            'guest_email' => 'guest2@example.com',
            'guest_phone' => '+84912345679',
        ]);

        // Should NOT be rate limited (different user = separate limit)
        $this->assertNotEquals(429, $response->status());
    }
}
