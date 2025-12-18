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
 * BookingSoftDeleteTest - Comprehensive soft delete tests
 * 
 * ✅ Test Coverage:
 * 1. Soft delete preserves booking data
 * 2. Soft deleted bookings excluded from normal queries
 * 3. Soft delete records deleted_by audit trail
 * 4. Admin can view trashed bookings
 * 5. Admin can restore trashed bookings
 * 6. Admin can force delete trashed bookings
 * 7. Restore fails if date conflicts exist
 * 8. Regular users cannot access admin trash endpoints
 * 9. Soft deleted bookings don't block new bookings
 * 10. Bulk restore works correctly
 * 
 * AUDIT & COMPLIANCE:
 * - Verifies deleted_at and deleted_by are recorded
 * - Ensures data preservation for GDPR/SOX compliance
 * - Tests recovery workflow for accidental deletions
 */
class BookingSoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $admin;
    protected Room $room;
    protected Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->user()->create([
            'email' => 'user@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->admin = User::factory()->admin()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->room = Room::factory()->create();

        $this->booking = Booking::factory()
            ->forUser($this->user)
            ->forRoom($this->room)
            ->confirmed()
            ->create([
                'check_in' => Carbon::now()->addDays(5)->startOfDay(),
                'check_out' => Carbon::now()->addDays(8)->startOfDay(),
            ]);
    }

    // ===== SOFT DELETE BEHAVIOR TESTS =====

    /**
     * Test 1: Deleting a booking uses soft delete (data preserved)
     */
    public function test_delete_uses_soft_delete_and_preserves_data(): void
    {
        $bookingId = $this->booking->id;

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/bookings/{$bookingId}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Booking cancelled successfully',
            ]);

        // Booking should NOT appear in normal queries
        $this->assertDatabaseMissing('bookings', [
            'id' => $bookingId,
            'deleted_at' => null,
        ]);

        // But data should still exist with deleted_at set
        $this->assertSoftDeleted('bookings', [
            'id' => $bookingId,
        ]);
    }

    /**
     * Test 2: Soft deleted bookings are excluded from normal queries
     */
    public function test_soft_deleted_bookings_excluded_from_index(): void
    {
        // Delete the booking
        $this->booking->delete();

        // User's index should not include the deleted booking
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/bookings');

        $response->assertStatus(200);
        
        $bookingIds = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertNotContains($this->booking->id, $bookingIds);
    }

    /**
     * Test 3: Soft delete records deleted_by for audit trail
     */
    public function test_soft_delete_records_audit_trail(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/bookings/{$this->booking->id}");

        $response->assertStatus(200);

        $booking = Booking::withTrashed()->find($this->booking->id);
        
        $this->assertNotNull($booking->deleted_at);
        $this->assertEquals($this->user->id, $booking->deleted_by);
    }

    /**
     * Test 4: Admin deleting user's booking records admin as deleted_by
     */
    public function test_admin_delete_records_admin_as_deleter(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/bookings/{$this->booking->id}");

        $response->assertStatus(200);

        $booking = Booking::withTrashed()->find($this->booking->id);
        
        $this->assertEquals($this->admin->id, $booking->deleted_by);
    }

    // ===== ADMIN TRASH VIEW TESTS =====

    /**
     * Test 5: Admin can view trashed bookings
     */
    public function test_admin_can_view_trashed_bookings(): void
    {
        // Soft delete the booking
        $this->booking->softDeleteWithAudit($this->user->id);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/bookings/trashed');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $trashedIds = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($this->booking->id, $trashedIds);
    }

    /**
     * Test 6: Regular user cannot access admin trash view
     */
    public function test_regular_user_cannot_view_trashed(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/admin/bookings/trashed');

        $response->assertStatus(403);
    }

    /**
     * Test 7: Admin can view all bookings including trashed
     */
    public function test_admin_can_view_all_bookings_including_trashed(): void
    {
        // Create active booking
        $activeBooking = Booking::factory()
            ->forUser($this->user)
            ->forRoom($this->room)
            ->create([
                'check_in' => Carbon::now()->addDays(20)->startOfDay(),
                'check_out' => Carbon::now()->addDays(22)->startOfDay(),
            ]);

        // Soft delete the original booking
        $this->booking->softDeleteWithAudit($this->user->id);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/bookings');

        $response->assertStatus(200);

        $bookingIds = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($this->booking->id, $bookingIds);
        $this->assertContains($activeBooking->id, $bookingIds);
    }

    // ===== RESTORE TESTS =====

    /**
     * Test 8: Admin can restore a soft deleted booking
     */
    public function test_admin_can_restore_trashed_booking(): void
    {
        $this->booking->softDeleteWithAudit($this->user->id);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/bookings/{$this->booking->id}/restore");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Booking restored successfully.');

        // Booking should be active again
        $booking = Booking::find($this->booking->id);
        $this->assertNotNull($booking);
        $this->assertNull($booking->deleted_at);
        $this->assertNull($booking->deleted_by);
    }

    /**
     * Test 9: Regular user cannot restore bookings
     */
    public function test_regular_user_cannot_restore(): void
    {
        $this->booking->softDeleteWithAudit($this->user->id);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/admin/bookings/{$this->booking->id}/restore");

        $response->assertStatus(403);
    }

    /**
     * Test 10: Restore fails if date conflict exists
     */
    public function test_restore_fails_with_date_conflict(): void
    {
        // Soft delete the original booking
        $this->booking->softDeleteWithAudit($this->user->id);

        // Create a new booking for the same dates
        Booking::factory()
            ->forUser($this->user)
            ->forRoom($this->room)
            ->confirmed()
            ->create([
                'check_in' => $this->booking->check_in,
                'check_out' => $this->booking->check_out,
            ]);

        // Try to restore - should fail due to conflict
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/bookings/{$this->booking->id}/restore");

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Cannot restore booking: date range conflicts with existing bookings.');
    }

    // ===== FORCE DELETE TESTS =====

    /**
     * Test 11: Admin can permanently delete a trashed booking
     */
    public function test_admin_can_force_delete_trashed_booking(): void
    {
        $bookingId = $this->booking->id;
        $this->booking->softDeleteWithAudit($this->user->id);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/bookings/{$bookingId}/force");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Booking permanently deleted.');

        // Booking should be completely gone
        $this->assertDatabaseMissing('bookings', ['id' => $bookingId]);
        $this->assertNull(Booking::withTrashed()->find($bookingId));
    }

    /**
     * Test 12: Cannot force delete active (non-trashed) booking
     */
    public function test_cannot_force_delete_active_booking(): void
    {
        // Booking is not trashed
        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/bookings/{$this->booking->id}/force");

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Trashed booking not found. Only soft-deleted bookings can be permanently deleted.');
    }

    /**
     * Test 13: Regular user cannot force delete
     */
    public function test_regular_user_cannot_force_delete(): void
    {
        $this->booking->softDeleteWithAudit($this->user->id);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/admin/bookings/{$this->booking->id}/force");

        $response->assertStatus(403);
    }

    // ===== SOFT DELETE & AVAILABILITY TESTS =====

    /**
     * Test 14: Soft deleted bookings don't block new bookings
     */
    public function test_soft_deleted_bookings_dont_block_new_bookings(): void
    {
        // Delete the original booking
        $this->booking->softDeleteWithAudit($this->user->id);

        // Create a new booking for the same dates - should succeed
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/bookings', [
                'room_id' => $this->room->id,
                'check_in' => $this->booking->check_in->format('Y-m-d'),
                'check_out' => $this->booking->check_out->format('Y-m-d'),
                'guest_name' => 'New Guest',
                'guest_email' => 'new@example.com',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    // ===== BULK OPERATIONS TESTS =====

    /**
     * Test 15: Admin can bulk restore multiple bookings
     */
    public function test_admin_can_bulk_restore_bookings(): void
    {
        // Create more bookings with different dates to avoid conflicts
        $booking2 = Booking::factory()
            ->forUser($this->user)
            ->forRoom($this->room)
            ->create([
                'check_in' => Carbon::now()->addDays(15)->startOfDay(),
                'check_out' => Carbon::now()->addDays(18)->startOfDay(),
            ]);

        $booking3 = Booking::factory()
            ->forUser($this->user)
            ->forRoom($this->room)
            ->create([
                'check_in' => Carbon::now()->addDays(25)->startOfDay(),
                'check_out' => Carbon::now()->addDays(28)->startOfDay(),
            ]);

        // Soft delete all
        $this->booking->softDeleteWithAudit($this->user->id);
        $booking2->softDeleteWithAudit($this->user->id);
        $booking3->softDeleteWithAudit($this->user->id);

        // Bulk restore
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/bookings/restore-bulk', [
                'ids' => [$this->booking->id, $booking2->id, $booking3->id],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.restored_count', 3);

        // All should be active
        $this->assertNotNull(Booking::find($this->booking->id));
        $this->assertNotNull(Booking::find($booking2->id));
        $this->assertNotNull(Booking::find($booking3->id));
    }

    // ===== EDGE CASES =====

    /**
     * Test 16: Show specific trashed booking
     */
    public function test_admin_can_view_specific_trashed_booking(): void
    {
        $this->booking->softDeleteWithAudit($this->user->id);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/admin/bookings/trashed/{$this->booking->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $this->booking->id)
            ->assertJsonPath('data.is_trashed', true);
    }

    /**
     * Test 17: Attempting to restore non-existent booking returns 404
     */
    public function test_restore_nonexistent_booking_returns_404(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/bookings/99999/restore');

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Trashed booking not found.');
    }

    /**
     * Test 18: Model trashed() method works correctly
     */
    public function test_booking_model_trashed_method(): void
    {
        $this->assertFalse($this->booking->trashed());

        $this->booking->delete();

        $booking = Booking::withTrashed()->find($this->booking->id);
        $this->assertTrue($booking->trashed());
    }

    /**
     * Test 19: deletedBy relationship works correctly
     */
    public function test_deleted_by_relationship(): void
    {
        $this->booking->softDeleteWithAudit($this->admin->id);

        $booking = Booking::withTrashed()
            ->with('deletedBy')
            ->find($this->booking->id);

        $this->assertNotNull($booking->deletedBy);
        $this->assertEquals($this->admin->id, $booking->deletedBy->id);
        $this->assertEquals($this->admin->email, $booking->deletedBy->email);
    }
}
