<?php

namespace Tests\Feature\RateLimiting;

use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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
    public function test_booking_rate_limit_5_per_minute_per_user_and_ip(): void
    {
        // Use Sanctum guard for API authentication
        $this->actingAs($this->user, 'sanctum');
        $ip = '203.0.113.61';

        // Attempt 5 bookings within 1 minute (should not be rate limited).
        for ($i = 0; $i < 5; $i++) {
            $response = $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->postJson('/api/bookings', [
                    'room_id' => $this->room->id,
                    'check_in' => now()->addDays(1 + $i * 5)->format('Y-m-d'),
                    'check_out' => now()->addDays(3 + $i * 5)->format('Y-m-d'),
                    'guest_name' => 'Test Guest '.$i,
                    'guest_email' => 'guest'.$i.'@example.com',
                ]);

            // Should not be rate limited yet (business validation may still reject).
            $this->assertNotEquals(429, $response->status());
        }

        // 6th attempt should be rate limited.
        $response = $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/bookings', [
                'room_id' => $this->room->id,
                'check_in' => now()->addDays(50)->format('Y-m-d'),
                'check_out' => now()->addDays(52)->format('Y-m-d'),
                'guest_name' => 'Test Guest 6',
                'guest_email' => 'guest6@example.com',
            ]);

        $this->assertEquals(429, $response->status());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_booking_rate_limit_different_users_separate(): void
    {
        $user1 = $this->user;
        $user2 = User::factory()->create();
        $ip = '203.0.113.62';

        // User 1: Make 5 requests (hit actor+IP limit)
        $this->actingAs($user1, 'sanctum');
        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->post('/api/bookings', [
                    'room_id' => $this->room->id,
                    'check_in' => now()->addDays($i + 1)->format('Y-m-d'),
                    'check_out' => now()->addDays($i + 3)->format('Y-m-d'),
                    'guest_name' => 'Guest '.$i,
                    'guest_email' => 'guest'.$i.'@example.com',
                    'guest_phone' => '+8491234567'.$i,
                ]);
        }

        // User 2: Should have fresh limit
        $this->actingAs($user2, 'sanctum');
        $response = $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post('/api/bookings', [
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
