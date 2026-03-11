<?php

namespace Tests\Feature\Booking;

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BookingIndexAuthorizationTest — Validates booking index implicit safety.
 *
 * The booking index endpoint has no explicit policy guard — it relies on
 * getUserBookings(auth()->id()) to filter results. These tests validate
 * that cross-user data leakage cannot occur.
 *
 * Covers: G-03 (RBAC audit — implicit safety validation)
 */
class BookingIndexAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $user1;

    private User $user2;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user1 = User::factory()->create(['role' => UserRole::USER]);
        $this->user2 = User::factory()->create(['role' => UserRole::USER]);
        $this->admin = User::factory()->create(['role' => UserRole::ADMIN]);
    }

    public function test_user_only_sees_own_bookings(): void
    {
        $room = Room::factory()->create();

        // Create booking for user1
        Booking::factory()->create([
            'user_id' => $this->user1->id,
            'room_id' => $room->id,
            'check_in' => Carbon::tomorrow(),
            'check_out' => Carbon::tomorrow()->addDays(2),
        ]);

        // Create booking for user2
        Booking::factory()->create([
            'user_id' => $this->user2->id,
            'room_id' => $room->id,
            'check_in' => Carbon::tomorrow()->addDays(5),
            'check_out' => Carbon::tomorrow()->addDays(7),
        ]);

        $response = $this->actingAs($this->user1, 'sanctum')
            ->getJson('/api/v1/bookings');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $bookings = $response->json('data');
        $this->assertCount(1, $bookings);
    }

    public function test_user2_cannot_see_user1_bookings(): void
    {
        $room = Room::factory()->create();

        Booking::factory()->create([
            'user_id' => $this->user1->id,
            'room_id' => $room->id,
            'check_in' => Carbon::tomorrow(),
            'check_out' => Carbon::tomorrow()->addDays(2),
        ]);

        $response = $this->actingAs($this->user2, 'sanctum')
            ->getJson('/api/v1/bookings');

        $response->assertStatus(200);

        $bookings = $response->json('data');
        $this->assertCount(0, $bookings);
    }

    public function test_admin_index_returns_only_own_bookings_not_all(): void
    {
        $room = Room::factory()->create();

        // Create booking owned by user1
        Booking::factory()->create([
            'user_id' => $this->user1->id,
            'room_id' => $room->id,
            'check_in' => Carbon::tomorrow(),
            'check_out' => Carbon::tomorrow()->addDays(2),
        ]);

        // Admin should see 0 bookings on the user-facing index (not admin index)
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/bookings');

        $response->assertStatus(200);

        $bookings = $response->json('data');
        $this->assertCount(0, $bookings);
    }

    public function test_guest_cannot_access_booking_index(): void
    {
        $response = $this->getJson('/api/v1/bookings');

        $response->assertStatus(401);
    }
}
