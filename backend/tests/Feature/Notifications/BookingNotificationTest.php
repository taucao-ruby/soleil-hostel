<?php

namespace Tests\Feature\Notifications;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use App\Notifications\BookingCancelled;
use App\Notifications\BookingConfirmed;
use App\Notifications\BookingUpdated;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Feature tests for booking notification system.
 * 
 * Tests cover:
 * - Notification dispatch on status changes
 * - Queue integration
 * - Idempotency guards
 * - Rate limiting
 * 
 * @see docs/backend/BOOKING_CONFIRMATION_NOTIFICATION_ARCHITECTURE.md
 */
class BookingNotificationTest extends TestCase
{
    use RefreshDatabase;

    private BookingService $bookingService;
    private User $admin;
    private User $user;
    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->bookingService = app(BookingService::class);
        
        $this->admin = User::factory()->admin()->create();
        $this->user = User::factory()->create(['email_verified_at' => now()]);
        $this->room = Room::factory()->create();
        
        // Clear rate limiter before each test
        RateLimiter::clear('booking-confirm-email:' . $this->user->id);
    }

    // ========== BOOKING CONFIRMED NOTIFICATION TESTS ==========

    /** @test */
    public function confirmation_notification_is_dispatched_when_booking_confirmed(): void
    {
        Notification::fake();

        $booking = Booking::factory()->create([
            'user_id' => $this->user->id,
            'room_id' => $this->room->id,
            'status' => Booking::STATUS_PENDING,
        ]);

        $this->bookingService->confirmBooking($booking);

        Notification::assertSentTo(
            $this->user,
            BookingConfirmed::class,
            fn ($notification) => $notification->booking->id === $booking->id
        );
    }

    /** @test */
    public function confirmation_notification_is_queued_on_correct_queue(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->user->id,
            'room_id' => $this->room->id,
            'status' => Booking::STATUS_PENDING,
        ]);

        $notification = new BookingConfirmed($booking);
        
        // Verify the notification is configured for the correct queue
        $this->assertEquals('notifications', $notification->queue);
        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $notification);
    }

    /** @test */
    public function confirmation_notification_skipped_when_booking_not_confirmed(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->user->id,
            'room_id' => $this->room->id,
            'status' => Booking::STATUS_CANCELLED,
        ]);

        $notification = new BookingConfirmed($booking);
        $result = $notification->toMail($this->user);

        $this->assertNull($result, 'toMail() should return null for non-confirmed bookings');
    }

    /** @test */
    public function confirmation_notification_rate_limited(): void
    {
        Notification::fake();

        // Exhaust rate limit (5 per minute)
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit('booking-confirm-email:' . $this->user->id, 60);
        }

        $booking = Booking::factory()->create([
            'user_id' => $this->user->id,
            'room_id' => $this->room->id,
            'status' => Booking::STATUS_PENDING,
        ]);

        $this->bookingService->confirmBooking($booking);

        // Booking should be confirmed but notification not sent
        $this->assertEquals(Booking::STATUS_CONFIRMED, $booking->fresh()->status);
        Notification::assertNothingSentTo($this->user);
    }

    /** @test */
    public function cannot_confirm_already_confirmed_booking(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->user->id,
            'room_id' => $this->room->id,
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Cannot confirm booking: current status is 'confirmed'");

        $this->bookingService->confirmBooking($booking);
    }

    // ========== BOOKING CANCELLED NOTIFICATION TESTS ==========

    /** @test */
    public function cancellation_notification_is_dispatched_when_booking_cancelled(): void
    {
        Notification::fake();

        $booking = Booking::factory()->create([
            'user_id' => $this->user->id,
            'room_id' => $this->room->id,
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        $this->bookingService->cancelBooking($booking, $this->admin->id);

        Notification::assertSentTo(
            $this->user,
            BookingCancelled::class,
            fn ($notification) => $notification->booking->id === $booking->id
        );
    }

    /** @test */
    public function cancellation_notification_skipped_when_booking_not_cancelled(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->user->id,
            'room_id' => $this->room->id,
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        $notification = new BookingCancelled($booking);
        $result = $notification->toMail($this->user);

        $this->assertNull($result, 'toMail() should return null for non-cancelled bookings');
    }

    /** @test */
    public function cannot_cancel_already_cancelled_booking(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->user->id,
            'room_id' => $this->room->id,
            'status' => Booking::STATUS_CANCELLED,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Booking is already cancelled');

        $this->bookingService->cancelBooking($booking);
    }

    // ========== BOOKING UPDATED NOTIFICATION TESTS ==========

    /** @test */
    public function updated_notification_skipped_for_cancelled_booking(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->user->id,
            'room_id' => $this->room->id,
            'status' => Booking::STATUS_CANCELLED,
        ]);

        $notification = new BookingUpdated($booking, ['check_in' => '2026-02-01']);
        $result = $notification->toMail($this->user);

        $this->assertNull($result, 'toMail() should return null for cancelled bookings');
    }

    /** @test */
    public function updated_notification_includes_changes(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->user->id,
            'room_id' => $this->room->id,
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        $changes = ['check_in' => '2026-02-01', 'check_out' => '2026-02-05'];
        $notification = new BookingUpdated($booking, $changes);
        
        $arrayData = $notification->toArray($this->user);

        $this->assertEquals($changes, $arrayData['changes']);
    }

    // ========== API ENDPOINT TESTS ==========

    /** @test */
    public function admin_can_confirm_booking_via_api(): void
    {
        Notification::fake();

        $booking = Booking::factory()->create([
            'user_id' => $this->user->id,
            'room_id' => $this->room->id,
            'status' => Booking::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/bookings/{$booking->id}/confirm");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Booking confirmed successfully. Confirmation email queued.',
            ]);

        $this->assertEquals(Booking::STATUS_CONFIRMED, $booking->fresh()->status);
        Notification::assertSentTo($this->user, BookingConfirmed::class);
    }

    /** @test */
    public function regular_user_cannot_confirm_booking(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->user->id,
            'room_id' => $this->room->id,
            'status' => Booking::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/bookings/{$booking->id}/confirm");

        $response->assertForbidden();
    }

    /** @test */
    public function user_can_cancel_own_booking_via_api(): void
    {
        Notification::fake();

        $booking = Booking::factory()->create([
            'user_id' => $this->user->id,
            'room_id' => $this->room->id,
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Booking cancelled successfully. Cancellation email queued.',
            ]);

        $this->assertEquals(Booking::STATUS_CANCELLED, $booking->fresh()->status);
        Notification::assertSentTo($this->user, BookingCancelled::class);
    }

    /** @test */
    public function user_cannot_cancel_other_users_booking(): void
    {
        $otherUser = User::factory()->create(['email_verified_at' => now()]);
        
        $booking = Booking::factory()->create([
            'user_id' => $otherUser->id,
            'room_id' => $this->room->id,
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertForbidden();
    }

    /** @test */
    public function admin_can_cancel_any_booking(): void
    {
        Notification::fake();

        $booking = Booking::factory()->create([
            'user_id' => $this->user->id,
            'room_id' => $this->room->id,
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertOk();
        $this->assertEquals(Booking::STATUS_CANCELLED, $booking->fresh()->status);
    }

    // ========== NOTIFICATION PROPERTIES TESTS ==========

    /** @test */
    public function confirmed_notification_has_correct_properties(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->user->id,
            'room_id' => $this->room->id,
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        $notification = new BookingConfirmed($booking);

        $this->assertEquals(3, $notification->tries);
        $this->assertEquals([60, 300, 900], $notification->backoff);
        $this->assertTrue($notification->deleteWhenMissingModels);
    }

    /** @test */
    public function cancelled_notification_has_correct_properties(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->user->id,
            'room_id' => $this->room->id,
            'status' => Booking::STATUS_CANCELLED,
        ]);

        $notification = new BookingCancelled($booking);

        $this->assertEquals(3, $notification->tries);
        $this->assertEquals([60, 300, 900], $notification->backoff);
        $this->assertTrue($notification->deleteWhenMissingModels);
    }

    /** @test */
    public function updated_notification_has_correct_properties(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->user->id,
            'room_id' => $this->room->id,
        ]);

        $notification = new BookingUpdated($booking);

        $this->assertEquals(3, $notification->tries);
        $this->assertEquals([60, 300, 900], $notification->backoff);
        $this->assertTrue($notification->deleteWhenMissingModels);
    }
}
