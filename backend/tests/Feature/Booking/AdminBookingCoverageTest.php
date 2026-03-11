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
 * AdminBookingCoverageTest — RBAC coverage for admin booking endpoints.
 *
 * Read endpoints (index, trashed, showTrashed): moderator+ allowed
 * Write endpoints (restore, force-delete, bulk-restore): admin-only
 * User role: denied on all admin endpoints
 *
 * Covers: FU-2 (PERMISSION_MATRIX), Phase 3 (moderator activation)
 */
class AdminBookingCoverageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $moderator;

    private User $user;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->moderator = User::factory()->create(['role' => UserRole::MODERATOR]);
        $this->user = User::factory()->user()->create();
        $this->room = Room::factory()->create();
    }

    private function createTrashedBooking(): Booking
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->user->id,
            'room_id' => $this->room->id,
            'check_in' => Carbon::tomorrow()->addDays(10),
            'check_out' => Carbon::tomorrow()->addDays(12),
            'status' => 'confirmed',
        ]);

        $booking->delete();

        return $booking;
    }

    // ========== READ: Admin booking index (moderator+) ==========

    public function test_moderator_can_access_admin_booking_index(): void
    {
        $response = $this->actingAs($this->moderator, 'sanctum')
            ->getJson('/api/v1/admin/bookings');

        $response->assertStatus(200);
    }

    public function test_user_cannot_access_admin_booking_index(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/admin/bookings');

        $response->assertStatus(403);
    }

    // ========== READ: Trashed index (moderator+) ==========

    public function test_moderator_can_access_admin_trashed_index(): void
    {
        $response = $this->actingAs($this->moderator, 'sanctum')
            ->getJson('/api/v1/admin/bookings/trashed');

        $response->assertStatus(200);
    }

    public function test_user_cannot_access_admin_trashed_index(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/admin/bookings/trashed');

        $response->assertStatus(403);
    }

    // ========== READ: Trashed show (moderator+) ==========

    public function test_admin_can_view_trashed_booking_via_v1(): void
    {
        $booking = $this->createTrashedBooking();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/admin/bookings/trashed/{$booking->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_moderator_can_view_trashed_booking_via_v1(): void
    {
        $booking = $this->createTrashedBooking();

        $response = $this->actingAs($this->moderator, 'sanctum')
            ->getJson("/api/v1/admin/bookings/trashed/{$booking->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_user_cannot_view_trashed_booking_via_v1(): void
    {
        $booking = $this->createTrashedBooking();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/admin/bookings/trashed/{$booking->id}");

        $response->assertStatus(403);
    }

    // ========== WRITE: Restore (admin only) ==========

    public function test_user_cannot_restore_booking(): void
    {
        $booking = $this->createTrashedBooking();

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/admin/bookings/{$booking->id}/restore");

        $response->assertStatus(403);
    }

    public function test_moderator_cannot_restore_booking(): void
    {
        $booking = $this->createTrashedBooking();

        $response = $this->actingAs($this->moderator, 'sanctum')
            ->postJson("/api/v1/admin/bookings/{$booking->id}/restore");

        $response->assertStatus(403);
    }

    // ========== WRITE: Force delete (admin only) ==========

    public function test_user_cannot_force_delete_booking(): void
    {
        $booking = $this->createTrashedBooking();

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/admin/bookings/{$booking->id}/force");

        $response->assertStatus(403);
    }

    public function test_moderator_cannot_force_delete_booking(): void
    {
        $booking = $this->createTrashedBooking();

        $response = $this->actingAs($this->moderator, 'sanctum')
            ->deleteJson("/api/v1/admin/bookings/{$booking->id}/force");

        $response->assertStatus(403);
    }

    // ========== WRITE: Bulk restore (admin only) ==========

    public function test_moderator_cannot_bulk_restore_bookings(): void
    {
        $booking = $this->createTrashedBooking();

        $response = $this->actingAs($this->moderator, 'sanctum')
            ->postJson('/api/v1/admin/bookings/restore-bulk', [
                'ids' => [$booking->id],
            ]);

        $response->assertStatus(403);
    }

    public function test_user_cannot_bulk_restore_bookings(): void
    {
        $booking = $this->createTrashedBooking();

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/admin/bookings/restore-bulk', [
                'ids' => [$booking->id],
            ]);

        $response->assertStatus(403);
    }

    public function test_guest_cannot_bulk_restore_bookings(): void
    {
        $response = $this->postJson('/api/v1/admin/bookings/restore-bulk', [
            'ids' => [1],
        ]);

        $response->assertStatus(401);
    }
}
