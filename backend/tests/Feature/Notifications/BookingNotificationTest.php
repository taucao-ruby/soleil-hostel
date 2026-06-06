<?php

namespace Tests\Feature\Notifications;

use App\Enums\BookingStatus;
use App\Exceptions\BookingTransitionException;
use App\Jobs\SendBookingConfirmationEmail;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use App\Notifications\BookingCancelled;
use App\Notifications\BookingConfirmed;
use App\Notifications\BookingUpdated;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
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
#[\PHPUnit\Framework\Attributes\Group('booking')]
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

        // Legacy dispatch-site key (now removed by BL-4 fix) plus the new
        // queue-middleware recipient key — clear both so prior runs cannot
        // bleed into the per-recipient throttle bucket exercised by these tests.
        RateLimiter::clear('booking-confirm-email:'.$this->user->id);
        RateLimiter::clear('booking-confirmation-email-recipient:'.$this->user->id);
    }

    // ========== BOOKING CONFIRMED NOTIFICATION TESTS ==========

    /** @test */
    public function confirmation_email_job_is_dispatched_when_booking_confirmed(): void
    {
        // Post-BL-4 contract: BookingService dispatches a queued job; the job
        // (not the service) talks to the notification system. Recipient
        // throttling lives in the job's queue middleware.
        Bus::fake([SendBookingConfirmationEmail::class]);

        $booking = Booking::factory()->create([
            'user_id' => $this->user->id,
            'room_id' => $this->room->id,
            'status' => BookingStatus::PENDING,
        ]);

        $this->bookingService->confirmBooking($booking);

        Bus::assertDispatched(
            SendBookingConfirmationEmail::class,
            fn (SendBookingConfirmationEmail $job): bool => $job->bookingId === $booking->id
        );
    }

    /** @test */
    public function confirmation_notification_is_queued_on_correct_queue(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->user->id,
            'room_id' => $this->room->id,
            'status' => BookingStatus::PENDING,
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
            'status' => BookingStatus::CANCELLED,
        ]);

        $notification = new BookingConfirmed($booking);
        $result = $notification->toMail($this->user);

        $this->assertNull($result, 'toMail() should return null for non-confirmed bookings');
    }

    /** @test */
    public function recipient_rate_limit_does_not_drop_dispatch_intent(): void
    {
        // BL-4 regression: pre-fix, exhausting the dispatch-site rate limiter
        // caused BookingService to confirm the booking but silently swallow
        // the notification — losing comms intent. Post-fix, the dispatch is
        // unconditional; recipient throttling happens later inside the queued
        // job's middleware (delay-not-drop). Even with the recipient bucket
        // pre-exhausted, a job MUST still be dispatched.
        Bus::fake([SendBookingConfirmationEmail::class]);

        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit('booking-confirmation-email-recipient:'.$this->user->id, 60);
        }

        $booking = Booking::factory()->create([
            'user_id' => $this->user->id,
            'room_id' => $this->room->id,
            'status' => BookingStatus::PENDING,
        ]);

        $this->bookingService->confirmBooking($booking);

        $this->assertEquals(BookingStatus::CONFIRMED, $booking->fresh()->status);
        Bus::assertDispatchedTimes(SendBookingConfirmationEmail::class, 1);
    }

    /** @test */
    public function cannot_confirm_already_confirmed_booking(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->user->id,
            'room_id' => $this->room->id,
            'status' => BookingStatus::CONFIRMED,
        ]);

        $this->expectException(BookingTransitionException::class);
        $this->expectExceptionMessage("cannot transition from 'confirmed' to 'confirmed'");

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
            'status' => BookingStatus::CONFIRMED,
        ]);

        $this->bookingService->cancelBooking($booking, $this->admin->id);

        // CancellationService dispatches BookingCancelled event → SendBookingCancellation listener
        // sends notification via on-demand routing to guest_email (not to User model)
        Notification::assertSentOnDemand(
            BookingCancelled::class,
            fn ($notification, $channels, $notifiable) => $notification->booking->id === $booking->id
                && $notifiable->routes['mail'] === $booking->guest_email
        );
    }

    /** @test */
    public function cancellation_notification_skipped_when_booking_not_cancelled(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->user->id,
            'room_id' => $this->room->id,
            'status' => BookingStatus::CONFIRMED,
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
            'status' => BookingStatus::CANCELLED,
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
            'status' => BookingStatus::CANCELLED,
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
            'status' => BookingStatus::CONFIRMED,
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
        // Post-BL-4: the API path dispatches a queued
        // SendBookingConfirmationEmail job; the underlying BookingConfirmed
        // notification is delivered by the job's handle(), not by the service.
        Bus::fake([SendBookingConfirmationEmail::class]);

        $booking = Booking::factory()->create([
            'user_id' => $this->user->id,
            'room_id' => $this->room->id,
            'status' => BookingStatus::PENDING,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/bookings/{$booking->id}/confirm");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => __('booking.confirmed'),
            ]);

        $this->assertEquals(BookingStatus::CONFIRMED, $booking->fresh()->status);
        Bus::assertDispatched(
            SendBookingConfirmationEmail::class,
            fn (SendBookingConfirmationEmail $job): bool => $job->bookingId === $booking->id
                && $job->actorId === $this->admin->id
        );
    }

    /** @test */
    public function regular_user_cannot_confirm_booking(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->user->id,
            'room_id' => $this->room->id,
            'status' => BookingStatus::PENDING,
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
            'status' => BookingStatus::CONFIRMED,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => __('booking.cancelled'),
            ]);

        $this->assertEquals(BookingStatus::CANCELLED, $booking->fresh()->status);
        // Notification is sent to guest_email via on-demand notification (Notification::route())
        Notification::assertSentOnDemand(
            BookingCancelled::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === $booking->guest_email
        );
    }

    /** @test */
    public function user_cannot_cancel_other_users_booking(): void
    {
        $otherUser = User::factory()->create(['email_verified_at' => now()]);

        $booking = Booking::factory()->create([
            'user_id' => $otherUser->id,
            'room_id' => $this->room->id,
            'status' => BookingStatus::CONFIRMED,
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
            'status' => BookingStatus::CONFIRMED,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertOk();
        $this->assertEquals(BookingStatus::CANCELLED, $booking->fresh()->status);
    }

    // ========== NOTIFICATION PROPERTIES TESTS ==========

    /** @test */
    public function confirmed_notification_has_correct_properties(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->user->id,
            'room_id' => $this->room->id,
            'status' => BookingStatus::CONFIRMED,
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
            'status' => BookingStatus::CANCELLED,
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
