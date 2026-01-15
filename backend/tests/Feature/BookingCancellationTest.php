<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Events\BookingCancelled;
use App\Jobs\ReconcileRefundsJob;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use App\Notifications\BookingCancelled as BookingCancelledNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Laravel\Cashier\Cashier;
use Tests\TestCase;

class BookingCancellationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $admin;
    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->admin = User::factory()->admin()->create();
        $this->room = Room::factory()->create();
    }

    // ===== SUCCESS CASES =====

    public function test_user_can_cancel_their_own_booking(): void
    {
        Event::fake([BookingCancelled::class]);

        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->confirmed()
            ->create([
                'check_in' => now()->addDays(10),
                'check_out' => now()->addDays(12),
            ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', BookingStatus::CANCELLED->value);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::CANCELLED->value,
            'cancelled_by' => $this->user->id,
        ]);

        Event::assertDispatched(BookingCancelled::class);
    }

    public function test_admin_can_cancel_any_booking(): void
    {
        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->confirmed()
            ->create([
                'check_in' => now()->addDays(10),
                'check_out' => now()->addDays(12),
            ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertOk()
            ->assertJsonPath('data.status', BookingStatus::CANCELLED->value);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'cancelled_by' => $this->admin->id,
        ]);
    }

    public function test_cancellation_is_idempotent(): void
    {
        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->create([
                'status' => BookingStatus::CANCELLED,
                'cancelled_at' => now()->subHour(),
                'cancelled_by' => $this->user->id,
            ]);

        $originalCancelledAt = $booking->cancelled_at->toDateTimeString();

        $response = $this->actingAs($this->user)
            ->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertOk()
            ->assertJsonPath('data.status', BookingStatus::CANCELLED->value);

        // Verify no state change
        $booking->refresh();
        $this->assertEquals($originalCancelledAt, $booking->cancelled_at->toDateTimeString());
    }

    public function test_pending_booking_can_be_cancelled(): void
    {
        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->pending()
            ->create([
                'check_in' => now()->addDays(5),
                'check_out' => now()->addDays(7),
            ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertOk();
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::CANCELLED->value,
        ]);
    }

    // ===== REFUND CALCULATION TESTS =====

    public function test_full_refund_when_cancelled_more_than_48_hours_before_checkin(): void
    {
        // Set up a booking with payment, 3 days before check-in
        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->confirmed()
            ->create([
                'check_in' => now()->addDays(3), // 72 hours
                'check_out' => now()->addDays(5),
                'amount' => 10000, // $100.00
                'payment_intent_id' => 'pi_test_123',
            ]);

        $refundAmount = $booking->calculateRefundAmount();

        $this->assertEquals(10000, $refundAmount); // Full refund
        $this->assertEquals(100, $booking->getRefundPercentage());
    }

    public function test_partial_refund_when_cancelled_between_24_and_48_hours_before_checkin(): void
    {
        // Freeze time at midnight to ensure consistent date-based calculations
        // (check_in is cast as 'date' so time component is stripped)
        $this->travelTo(now()->startOfDay());

        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->confirmed()
            ->create([
                'check_in' => now()->addHours(36), // 36 hours from midnight = 36 hours exactly
                'check_out' => now()->addDays(3),
                'amount' => 10000,
                'payment_intent_id' => 'pi_test_123',
            ]);

        $refundAmount = $booking->calculateRefundAmount();

        $this->assertEquals(5000, $refundAmount); // 50% refund
        $this->assertEquals(50, $booking->getRefundPercentage());
    }

    public function test_no_refund_when_cancelled_less_than_24_hours_before_checkin(): void
    {
        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->confirmed()
            ->create([
                'check_in' => now()->addHours(12), // 12 hours
                'check_out' => now()->addDays(2),
                'amount' => 10000,
                'payment_intent_id' => 'pi_test_123',
            ]);

        $refundAmount = $booking->calculateRefundAmount();

        $this->assertEquals(0, $refundAmount);
        $this->assertEquals(0, $booking->getRefundPercentage());
    }

    // ===== AUTHORIZATION TESTS =====

    public function test_unauthorized_user_cannot_cancel_others_booking(): void
    {
        $otherUser = User::factory()->create();

        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->confirmed()
            ->create();

        $response = $this->actingAs($otherUser)
            ->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertForbidden();

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::CONFIRMED->value,
        ]);
    }

    public function test_unauthenticated_user_cannot_cancel_booking(): void
    {
        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->confirmed()
            ->create();

        $response = $this->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertUnauthorized();
    }

    // ===== EDGE CASES =====

    public function test_cannot_cancel_after_checkin_started(): void
    {
        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->confirmed()
            ->create([
                'check_in' => now()->subDay(), // Yesterday
                'check_out' => now()->addDays(2),
            ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertForbidden();
    }

    public function test_admin_can_cancel_after_checkin_started(): void
    {
        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->confirmed()
            ->create([
                'check_in' => now()->subDay(),
                'check_out' => now()->addDays(2),
            ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertOk();
    }

    public function test_cannot_cancel_refund_pending_booking(): void
    {
        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->create([
                'status' => BookingStatus::REFUND_PENDING,
                'check_in' => now()->addDays(5),
            ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertForbidden();
    }

    public function test_can_retry_refund_failed_booking(): void
    {
        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->create([
                'status' => BookingStatus::REFUND_FAILED,
                'check_in' => now()->addDays(5),
                'check_out' => now()->addDays(7),
                'refund_error' => 'Previous attempt failed',
            ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/bookings/{$booking->id}/cancel");

        // Should be allowed (refund_failed is retryable)
        $response->assertStatus(200)
            ->assertJsonPath('data.status', BookingStatus::CANCELLED->value);
    }

    public function test_soft_deleted_booking_returns_404(): void
    {
        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->confirmed()
            ->create();

        $booking->delete(); // Soft delete

        $response = $this->actingAs($this->user)
            ->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertNotFound();
    }

    // ===== STATE TRANSITION TESTS =====

    public function test_booking_without_payment_skips_refund(): void
    {
        Event::fake([BookingCancelled::class]);

        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->confirmed()
            ->create([
                'check_in' => now()->addDays(5),
                'payment_intent_id' => null, // No payment
                'amount' => null,
            ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertOk()
            ->assertJsonPath('data.status', BookingStatus::CANCELLED->value);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::CANCELLED->value,
            'refund_id' => null,
            'refund_status' => null,
        ]);

        Event::assertDispatched(BookingCancelled::class);
    }

    // ===== AUDIT TRAIL TESTS =====

    public function test_cancellation_records_timestamp_and_actor(): void
    {
        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->confirmed()
            ->create([
                'check_in' => now()->addDays(5),
            ]);

        $this->freezeTime();

        $response = $this->actingAs($this->admin)
            ->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertOk();

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'cancelled_at' => now()->toDateTimeString(),
            'cancelled_by' => $this->admin->id,
        ]);
    }

    // ===== STATUS HELPER TESTS =====

    public function test_booking_status_enum_helpers(): void
    {
        $this->assertTrue(BookingStatus::PENDING->isCancellable());
        $this->assertTrue(BookingStatus::CONFIRMED->isCancellable());
        $this->assertTrue(BookingStatus::REFUND_FAILED->isCancellable());
        $this->assertFalse(BookingStatus::CANCELLED->isCancellable());
        $this->assertFalse(BookingStatus::REFUND_PENDING->isCancellable());

        $this->assertTrue(BookingStatus::CANCELLED->isTerminal());
        $this->assertFalse(BookingStatus::CONFIRMED->isTerminal());

        $this->assertTrue(BookingStatus::REFUND_PENDING->isRefundInProgress());
        $this->assertFalse(BookingStatus::CANCELLED->isRefundInProgress());
    }

    public function test_booking_status_transitions(): void
    {
        // Valid transitions
        $this->assertTrue(BookingStatus::PENDING->canTransitionTo(BookingStatus::CONFIRMED));
        $this->assertTrue(BookingStatus::PENDING->canTransitionTo(BookingStatus::CANCELLED));
        $this->assertTrue(BookingStatus::CONFIRMED->canTransitionTo(BookingStatus::REFUND_PENDING));
        $this->assertTrue(BookingStatus::REFUND_PENDING->canTransitionTo(BookingStatus::CANCELLED));
        $this->assertTrue(BookingStatus::REFUND_PENDING->canTransitionTo(BookingStatus::REFUND_FAILED));
        $this->assertTrue(BookingStatus::REFUND_FAILED->canTransitionTo(BookingStatus::CANCELLED));

        // Invalid transitions
        $this->assertFalse(BookingStatus::CANCELLED->canTransitionTo(BookingStatus::CONFIRMED));
        $this->assertFalse(BookingStatus::CANCELLED->canTransitionTo(BookingStatus::PENDING));
    }
}
