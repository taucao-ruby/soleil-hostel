<?php

namespace Tests\Feature\Audit;

use App\Models\AdminAuditLog;
use App\Models\Booking;
use App\Models\Location;
use App\Models\Review;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AdminAuditLogTest — Validates that sensitive admin operations create audit log entries.
 *
 * Covers: G-06 (no centralized audit log), G-07 (no SoD for force-delete),
 * G-11 (force-delete destroys audit trail), G-12 (admin review delete no reason).
 */
class AdminAuditLogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $user;

    private Room $room;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->user = User::factory()->user()->create();
        $this->location = Location::factory()->create();
        $this->room = Room::factory()->create(['location_id' => $this->location->id]);
    }

    // ========== BOOKING FORCE DELETE ==========

    public function test_force_delete_creates_audit_log(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->user->id,
            'room_id' => $this->room->id,
            'check_in' => Carbon::tomorrow()->addDays(10),
            'check_out' => Carbon::tomorrow()->addDays(12),
            'status' => 'confirmed',
        ]);
        $booking->delete();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/admin/bookings/{$booking->id}/force", [
                'reason' => 'GDPR right to be forgotten request #1234',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_id' => $this->admin->id,
            'action' => 'booking.force_delete',
            'resource_type' => 'booking',
            'resource_id' => $booking->id,
        ]);

        $log = AdminAuditLog::where('action', 'booking.force_delete')->first();
        $this->assertNotNull($log);
        $this->assertEquals('GDPR right to be forgotten request #1234', $log->metadata['reason']);
        $this->assertEquals($this->user->id, $log->metadata['user_id']);
        $this->assertNotNull($log->ip_address);
    }

    public function test_force_delete_preserves_audit_after_record_destroyed(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->user->id,
            'room_id' => $this->room->id,
            'check_in' => Carbon::tomorrow()->addDays(20),
            'check_out' => Carbon::tomorrow()->addDays(22),
            'status' => 'cancelled',
        ]);
        $bookingId = $booking->id;
        $booking->delete();

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/admin/bookings/{$bookingId}/force");

        // Booking is permanently gone
        $this->assertDatabaseMissing('bookings', ['id' => $bookingId]);

        // But audit log survives
        $this->assertDatabaseHas('admin_audit_logs', [
            'resource_id' => $bookingId,
            'action' => 'booking.force_delete',
        ]);
    }

    // ========== BOOKING RESTORE ==========

    public function test_restore_creates_audit_log(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->user->id,
            'room_id' => $this->room->id,
            'check_in' => Carbon::tomorrow()->addDays(30),
            'check_out' => Carbon::tomorrow()->addDays(32),
            'status' => 'confirmed',
        ]);
        $booking->delete();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/bookings/{$booking->id}/restore");

        $response->assertStatus(200);

        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_id' => $this->admin->id,
            'action' => 'booking.restore',
            'resource_type' => 'booking',
            'resource_id' => $booking->id,
        ]);
    }

    public function test_bulk_restore_creates_audit_logs_per_booking(): void
    {
        $booking1 = Booking::factory()->create([
            'user_id' => $this->user->id,
            'room_id' => $this->room->id,
            'check_in' => Carbon::tomorrow()->addDays(40),
            'check_out' => Carbon::tomorrow()->addDays(42),
            'status' => 'confirmed',
        ]);
        $booking1->delete();

        $room2 = Room::factory()->create(['location_id' => $this->location->id]);
        $booking2 = Booking::factory()->create([
            'user_id' => $this->user->id,
            'room_id' => $room2->id,
            'check_in' => Carbon::tomorrow()->addDays(40),
            'check_out' => Carbon::tomorrow()->addDays(42),
            'status' => 'confirmed',
        ]);
        $booking2->delete();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/bookings/restore-bulk', [
                'ids' => [$booking1->id, $booking2->id],
            ]);

        $response->assertStatus(200);

        $logs = AdminAuditLog::where('action', 'booking.restore')->get();
        $this->assertCount(2, $logs);
    }

    // ========== ROOM CUD ==========

    public function test_room_create_creates_audit_log(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/rooms', [
                'location_id' => $this->location->id,
                'name' => 'Audit Test Room',
                'description' => 'Created for audit test',
                'price' => 150.00,
                'max_guests' => 3,
                'status' => 'available',
            ]);

        $response->assertStatus(201);

        $roomId = $response->json('data.id');
        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_id' => $this->admin->id,
            'action' => 'room.create',
            'resource_type' => 'room',
            'resource_id' => $roomId,
        ]);
    }

    public function test_room_update_creates_audit_log(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/rooms/{$this->room->id}", [
                'location_id' => $this->location->id,
                'name' => 'Updated Room Name',
                'description' => 'Updated description',
                'price' => 200.00,
                'max_guests' => 4,
                'status' => 'available',
                'lock_version' => $this->room->lock_version,
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_id' => $this->admin->id,
            'action' => 'room.update',
            'resource_type' => 'room',
            'resource_id' => $this->room->id,
        ]);
    }

    public function test_room_delete_creates_audit_log(): void
    {
        $room = Room::factory()->create(['location_id' => $this->location->id]);
        $roomId = $room->id;

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/rooms/{$roomId}");

        $response->assertStatus(200);

        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_id' => $this->admin->id,
            'action' => 'room.delete',
            'resource_type' => 'room',
            'resource_id' => $roomId,
        ]);
    }

    // ========== REVIEW ADMIN DELETE ==========

    public function test_admin_review_delete_creates_audit_log_with_reason(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->user->id,
            'room_id' => $this->room->id,
            'check_in' => Carbon::yesterday()->subDays(5),
            'check_out' => Carbon::yesterday(),
            'status' => 'confirmed',
        ]);

        $review = Review::create([
            'title' => 'Bad review',
            'content' => 'Inappropriate content',
            'rating' => 1,
            'booking_id' => $booking->id,
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
            'guest_name' => $this->user->name,
            'guest_email' => $this->user->email,
            'approved' => true,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/reviews/{$review->id}", [
                'reason' => 'Violates community guidelines - abusive language',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_id' => $this->admin->id,
            'action' => 'review.admin_delete',
            'resource_type' => 'review',
            'resource_id' => $review->id,
        ]);

        $log = AdminAuditLog::where('action', 'review.admin_delete')->first();
        $this->assertEquals('Violates community guidelines - abusive language', $log->metadata['reason']);
        $this->assertEquals($this->user->id, $log->metadata['review_owner_id']);
    }

    public function test_owner_review_delete_does_not_create_audit_log(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->user->id,
            'room_id' => $this->room->id,
            'check_in' => Carbon::yesterday()->subDays(10),
            'check_out' => Carbon::yesterday()->subDays(5),
            'status' => 'confirmed',
        ]);

        $review = Review::create([
            'title' => 'My review',
            'content' => 'Good stay',
            'rating' => 4,
            'booking_id' => $booking->id,
            'room_id' => $this->room->id,
            'user_id' => $this->user->id,
            'guest_name' => $this->user->name,
            'guest_email' => $this->user->email,
            'approved' => true,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/reviews/{$review->id}");

        $this->assertDatabaseMissing('admin_audit_logs', [
            'action' => 'review.admin_delete',
            'resource_id' => $review->id,
        ]);
    }

    // ========== AUDIT LOG MODEL ==========

    public function test_audit_log_records_ip_address(): void
    {
        $room = Room::factory()->create(['location_id' => $this->location->id]);

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/rooms/{$room->id}");

        $log = AdminAuditLog::where('action', 'room.delete')->first();
        $this->assertNotNull($log);
        $this->assertNotNull($log->ip_address);
    }

    public function test_audit_log_actor_relationship(): void
    {
        $room = Room::factory()->create(['location_id' => $this->location->id]);

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/rooms/{$room->id}");

        $log = AdminAuditLog::where('action', 'room.delete')->first();
        $this->assertNotNull($log->actor);
        $this->assertEquals($this->admin->id, $log->actor->id);
    }
}
