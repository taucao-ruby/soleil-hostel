<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Enums\BookingStatus;
use App\Exceptions\BookingTransitionException;
use App\Jobs\SendBookingConfirmationEmail;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use App\Notifications\BookingConfirmed;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * SendBookingConfirmationEmailTest — BL-4 regression harness.
 *
 * Pre-fix behavior (encoded in the now-removed
 * BookingNotificationTest::confirmation_notification_rate_limited):
 *   When the dispatch-site rate limit was hit, BookingService confirmed the
 *   booking but silently swallowed the notification. A bulk admin confirm of
 *   N>5 bookings for the same guest dropped (N-5) emails with no signal.
 *
 * Post-fix behavior (asserted here):
 *   - confirmBooking() always dispatches a SendBookingConfirmationEmail job;
 *     no silent suppression at the service layer.
 *   - Recipient throttling lives in queue middleware that RELEASES the job
 *     back to the queue when the recipient limit is hit, never drops it.
 *   - The job re-hydrates the Booking in handle() and idempotency-guards on
 *     current status, so post-dispatch cancellations don't deliver stale emails.
 */
class SendBookingConfirmationEmailTest extends TestCase
{
    use RefreshDatabase;

    private BookingService $bookingService;

    private User $admin;

    private User $guest;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bookingService = app(BookingService::class);
        $this->admin = User::factory()->admin()->create();
        $this->guest = User::factory()->create(['email_verified_at' => now()]);
        $this->room = Room::factory()->create();

        // Both the legacy dispatch-site key (now gone) and the new recipient
        // queue-middleware key — clear to keep tests deterministic across runs.
        RateLimiter::clear('booking-confirm-email:'.$this->guest->id);
        RateLimiter::clear('booking-confirmation-email-recipient:'.$this->guest->id);
    }

    private function makePendingBooking(): Booking
    {
        return Booking::factory()->create([
            'user_id' => $this->guest->id,
            'room_id' => $this->room->id,
            'status' => BookingStatus::PENDING,
        ]);
    }

    // ─── BL-4 core: bulk confirm preserves every notification intent ──────────

    public function test_bulk_admin_confirms_for_same_guest_dispatch_one_job_per_booking(): void
    {
        Bus::fake([SendBookingConfirmationEmail::class]);

        $bookings = collect(range(1, 6))->map(fn () => $this->makePendingBooking());

        $this->actingAs($this->admin);

        $bookings->each(fn (Booking $b) => $this->bookingService->confirmBooking($b));

        // Pre-fix: only 5 jobs (or 5 inline notifies) would have happened.
        // Post-fix: every confirmation produces exactly one delivery intent.
        Bus::assertDispatchedTimes(SendBookingConfirmationEmail::class, 6);

        // All recipients must still be the same guest (proves the bulk-same-guest
        // path the BL-4 finding called out is correctly modeled by the test).
        foreach ($bookings as $booking) {
            Bus::assertDispatched(
                SendBookingConfirmationEmail::class,
                fn (SendBookingConfirmationEmail $job): bool => $job->bookingId === $booking->id
                    && $job->actorId === $this->admin->id
            );
        }
    }

    public function test_confirm_dispatches_job_with_actor_id_captured(): void
    {
        Bus::fake([SendBookingConfirmationEmail::class]);

        $booking = $this->makePendingBooking();

        $this->actingAs($this->admin);
        $this->bookingService->confirmBooking($booking);

        Bus::assertDispatched(
            SendBookingConfirmationEmail::class,
            fn (SendBookingConfirmationEmail $job): bool => $job->bookingId === $booking->id
                && $job->actorId === $this->admin->id
        );
    }

    public function test_confirm_dispatches_job_with_null_actor_when_unauthenticated(): void
    {
        Bus::fake([SendBookingConfirmationEmail::class]);

        $booking = $this->makePendingBooking();

        // No actingAs — system/console-initiated confirmation.
        $this->bookingService->confirmBooking($booking);

        Bus::assertDispatched(
            SendBookingConfirmationEmail::class,
            fn (SendBookingConfirmationEmail $job): bool => $job->bookingId === $booking->id
                && $job->actorId === null
        );
    }

    // ─── Pre-commit failure must not dispatch ────────────────────────────────

    public function test_failed_transition_inside_transaction_prevents_dispatch(): void
    {
        Bus::fake([SendBookingConfirmationEmail::class]);

        // Booking is already CONFIRMED → transitionTo will throw
        // BookingTransitionException BEFORE the dispatch line runs.
        $booking = Booking::factory()->create([
            'user_id' => $this->guest->id,
            'room_id' => $this->room->id,
            'status' => BookingStatus::CONFIRMED,
        ]);

        $this->actingAs($this->admin);

        try {
            $this->bookingService->confirmBooking($booking);
            $this->fail('Expected BookingTransitionException');
        } catch (BookingTransitionException) {
            // expected
        }

        Bus::assertNotDispatched(SendBookingConfirmationEmail::class);
    }

    // ─── Job handle() — delivery + idempotency ───────────────────────────────

    public function test_job_handle_delivers_notification_when_booking_confirmed(): void
    {
        Notification::fake();

        $booking = Booking::factory()->create([
            'user_id' => $this->guest->id,
            'room_id' => $this->room->id,
            'status' => BookingStatus::CONFIRMED,
        ]);

        (new SendBookingConfirmationEmail($booking->id, $this->admin->id))->handle();

        Notification::assertSentTo(
            $this->guest,
            BookingConfirmed::class,
            fn (BookingConfirmed $n): bool => $n->booking->id === $booking->id
        );
    }

    public function test_job_handle_skips_when_booking_no_longer_confirmed(): void
    {
        Notification::fake();

        // Booking was CONFIRMED at dispatch time but cancelled by the time the
        // worker picks the job up. Idempotency guard must short-circuit.
        $booking = Booking::factory()->create([
            'user_id' => $this->guest->id,
            'room_id' => $this->room->id,
            'status' => BookingStatus::CANCELLED,
        ]);

        (new SendBookingConfirmationEmail($booking->id))->handle();

        Notification::assertNothingSent();
    }

    public function test_job_handle_skips_when_booking_missing(): void
    {
        Notification::fake();

        // Job survived a force-delete race. Must not throw, must not deliver.
        (new SendBookingConfirmationEmail(bookingId: 999_999_999))->handle();

        Notification::assertNothingSent();
    }

    public function test_job_handle_skips_when_user_account_missing(): void
    {
        Notification::fake();

        $orphanUserId = User::factory()->create()->id;
        $booking = Booking::factory()->create([
            'user_id' => $orphanUserId,
            'room_id' => $this->room->id,
            'status' => BookingStatus::CONFIRMED,
        ]);

        // Hard-delete the user *after* the booking exists.
        User::whereKey($orphanUserId)->forceDelete();

        (new SendBookingConfirmationEmail($booking->id))->handle();

        Notification::assertNothingSent();
    }

    // ─── Recipient id resolution drives the throttle key ────────────────────

    public function test_recipient_user_id_resolves_from_database(): void
    {
        $booking = $this->makePendingBooking();

        $job = new SendBookingConfirmationEmail($booking->id);

        $this->assertSame($this->guest->id, $job->recipientUserId());
    }

    public function test_recipient_user_id_returns_zero_when_booking_missing(): void
    {
        $job = new SendBookingConfirmationEmail(bookingId: 999_999_999);

        // Zero is a stable sentinel — the job's handle() short-circuits in the
        // missing-booking branch anyway, so the throttle bucket is moot.
        $this->assertSame(0, $job->recipientUserId());
    }

    // ─── Job metadata contract ──────────────────────────────────────────────

    public function test_job_targets_notifications_queue_with_retry_metadata(): void
    {
        $job = new SendBookingConfirmationEmail(bookingId: 1);

        $this->assertSame('notifications', $job->queue);
        $this->assertSame(3, $job->tries);
        $this->assertSame([60, 300, 900], $job->backoff);
    }
}
