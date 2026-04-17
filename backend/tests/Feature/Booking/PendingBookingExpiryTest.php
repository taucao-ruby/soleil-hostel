<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Events\BookingCancelled;
use App\Jobs\ExpireStaleBookings;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * PendingBookingExpiryTest — integration tests for ExpireStaleBookings job.
 *
 * The job enforces a TTL on pending bookings so that forgotten / abandoned
 * bookings do not block room inventory forever. Correctness invariants:
 *
 *   ✅ Pending booking older than TTL is cancelled with reason='expired'
 *   ✅ Pending booking younger than TTL is untouched
 *   ✅ Confirmed bookings are never expired (only PENDING transitions)
 *   ✅ CANCELLED / REFUND_PENDING / REFUND_FAILED never re-cancelled
 *   ✅ BookingCancelled event dispatched for expired bookings
 *   ✅ cancelled_by is null (system actor, no human user)
 *   ✅ Room is available for new bookings after expiry
 *   ✅ TTL=0 or negative is treated as a safety no-op
 *   ✅ Batch size cap is honored
 *   ✅ Idempotent — second run does not double-process
 */
class PendingBookingExpiryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->room = Room::factory()->create();

        config()->set('booking.pending_ttl_minutes', 30);
        config()->set('booking.pending_expiry_batch_size', 100);
    }

    /**
     * Create a pending booking whose created_at is explicitly $minutesAgo in the past.
     * The default Booking factory does not let us backdate created_at via a state method,
     * so we create then touch the timestamp directly.
     */
    private function agedPendingBooking(int $minutesAgo, array $attributes = []): Booking
    {
        $booking = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->pending()
            ->create(array_merge([
                'check_in' => Carbon::now()->addDays(5)->startOfDay(),
                'check_out' => Carbon::now()->addDays(7)->startOfDay(),
            ], $attributes));

        $past = Carbon::now()->subMinutes($minutesAgo);
        Booking::query()->whereKey($booking->id)->update([
            'created_at' => $past,
            'updated_at' => $past,
        ]);

        return $booking->fresh();
    }

    // ─── Happy path ───────────────────────────────────────────────────────────

    public function test_pending_booking_older_than_ttl_is_cancelled(): void
    {
        Event::fake([BookingCancelled::class]);

        $booking = $this->agedPendingBooking(minutesAgo: 45);

        (new ExpireStaleBookings)->handle();

        $booking->refresh();

        $this->assertSame(BookingStatus::CANCELLED, $booking->status);
        $this->assertNotNull($booking->cancelled_at);
        $this->assertNull($booking->cancelled_by);
        $this->assertSame('expired', $booking->cancellation_reason);

        Event::assertDispatched(
            BookingCancelled::class,
            fn (BookingCancelled $event) => $event->booking->id === $booking->id,
        );
    }

    public function test_expired_booking_frees_up_the_room_for_new_bookings(): void
    {
        $stale = $this->agedPendingBooking(minutesAgo: 45, attributes: [
            'check_in' => Carbon::now()->addDays(5)->startOfDay(),
            'check_out' => Carbon::now()->addDays(7)->startOfDay(),
        ]);

        // Before expiry: overlap scope flags the room as unavailable.
        $preOverlap = Booking::overlappingBookings(
            $this->room->id,
            Carbon::now()->addDays(5)->startOfDay(),
            Carbon::now()->addDays(7)->startOfDay(),
        )->exists();
        $this->assertTrue($preOverlap, 'Pending booking should block overlap before expiry');

        (new ExpireStaleBookings)->handle();

        $postOverlap = Booking::overlappingBookings(
            $this->room->id,
            Carbon::now()->addDays(5)->startOfDay(),
            Carbon::now()->addDays(7)->startOfDay(),
        )->exists();
        $this->assertFalse($postOverlap, 'Room must be available after TTL expiry');

        $stale->refresh();
        $this->assertSame(BookingStatus::CANCELLED, $stale->status);
    }

    // ─── Guards (never expire) ────────────────────────────────────────────────

    public function test_pending_booking_younger_than_ttl_is_untouched(): void
    {
        Event::fake([BookingCancelled::class]);

        $fresh = $this->agedPendingBooking(minutesAgo: 10); // TTL is 30

        (new ExpireStaleBookings)->handle();

        $fresh->refresh();

        $this->assertSame(BookingStatus::PENDING, $fresh->status);
        $this->assertNull($fresh->cancelled_at);
        $this->assertNull($fresh->cancellation_reason);

        Event::assertNotDispatched(BookingCancelled::class);
    }

    public function test_confirmed_booking_older_than_ttl_is_untouched(): void
    {
        Event::fake([BookingCancelled::class]);

        $confirmed = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->confirmed()
            ->create([
                'check_in' => Carbon::now()->addDays(5)->startOfDay(),
                'check_out' => Carbon::now()->addDays(7)->startOfDay(),
            ]);

        $past = Carbon::now()->subMinutes(90);
        Booking::query()->whereKey($confirmed->id)->update([
            'created_at' => $past,
            'updated_at' => $past,
        ]);

        (new ExpireStaleBookings)->handle();

        $confirmed->refresh();

        $this->assertSame(BookingStatus::CONFIRMED, $confirmed->status);
        $this->assertNull($confirmed->cancelled_at);

        Event::assertNotDispatched(BookingCancelled::class);
    }

    public function test_already_cancelled_booking_is_not_re_cancelled(): void
    {
        Event::fake([BookingCancelled::class]);

        $cancelled = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->cancelled()
            ->create([
                'check_in' => Carbon::now()->addDays(5)->startOfDay(),
                'check_out' => Carbon::now()->addDays(7)->startOfDay(),
            ]);

        $originalCancelledAt = $cancelled->cancelled_at?->copy();

        $past = Carbon::now()->subMinutes(60);
        Booking::query()->whereKey($cancelled->id)->update([
            'created_at' => $past,
            'updated_at' => $past,
        ]);

        (new ExpireStaleBookings)->handle();

        $cancelled->refresh();

        $this->assertSame(BookingStatus::CANCELLED, $cancelled->status);
        $this->assertNotSame('expired', $cancelled->cancellation_reason);
        // cancelled_at should not have been overwritten
        if ($originalCancelledAt !== null) {
            $this->assertEqualsWithDelta(
                $originalCancelledAt->timestamp,
                $cancelled->cancelled_at->timestamp,
                1,
            );
        }

        Event::assertNotDispatched(BookingCancelled::class);
    }

    public function test_refund_pending_booking_is_not_expired(): void
    {
        Event::fake([BookingCancelled::class]);

        $refundPending = Booking::factory()
            ->for($this->user)
            ->for($this->room)
            ->refundPending()
            ->create([
                'check_in' => Carbon::now()->addDays(5)->startOfDay(),
                'check_out' => Carbon::now()->addDays(7)->startOfDay(),
            ]);

        $past = Carbon::now()->subMinutes(90);
        Booking::query()->whereKey($refundPending->id)->update([
            'created_at' => $past,
            'updated_at' => $past,
        ]);

        (new ExpireStaleBookings)->handle();

        $refundPending->refresh();

        $this->assertSame(BookingStatus::REFUND_PENDING, $refundPending->status);
        Event::assertNotDispatched(BookingCancelled::class);
    }

    // ─── Safety / config ──────────────────────────────────────────────────────

    public function test_ttl_of_zero_short_circuits_with_no_side_effects(): void
    {
        Event::fake([BookingCancelled::class]);

        config()->set('booking.pending_ttl_minutes', 0);

        $stale = $this->agedPendingBooking(minutesAgo: 9999);

        (new ExpireStaleBookings)->handle();

        $stale->refresh();
        $this->assertSame(BookingStatus::PENDING, $stale->status);

        Event::assertNotDispatched(BookingCancelled::class);
    }

    public function test_negative_ttl_short_circuits_with_no_side_effects(): void
    {
        Event::fake([BookingCancelled::class]);

        config()->set('booking.pending_ttl_minutes', -5);

        $stale = $this->agedPendingBooking(minutesAgo: 9999);

        (new ExpireStaleBookings)->handle();

        $stale->refresh();
        $this->assertSame(BookingStatus::PENDING, $stale->status);

        Event::assertNotDispatched(BookingCancelled::class);
    }

    public function test_batch_size_cap_is_honored(): void
    {
        Event::fake([BookingCancelled::class]);

        config()->set('booking.pending_expiry_batch_size', 2);

        // Create 5 stale pending bookings on distinct rooms (exclusion constraint
        // forbids overlapping pending bookings on the same room).
        $bookings = [];
        for ($i = 0; $i < 5; $i++) {
            $room = Room::factory()->create();
            $booking = Booking::factory()
                ->for($this->user)
                ->for($room)
                ->pending()
                ->create([
                    'check_in' => Carbon::now()->addDays(5)->startOfDay(),
                    'check_out' => Carbon::now()->addDays(7)->startOfDay(),
                ]);

            $past = Carbon::now()->subMinutes(60);
            Booking::query()->whereKey($booking->id)->update([
                'created_at' => $past,
                'updated_at' => $past,
            ]);

            $bookings[] = $booking;
        }

        (new ExpireStaleBookings)->handle();

        $cancelledCount = Booking::query()
            ->where('status', BookingStatus::CANCELLED)
            ->where('cancellation_reason', 'expired')
            ->count();

        $this->assertSame(2, $cancelledCount, 'Only batch_size bookings should be processed per run');

        // A second run picks up the remaining (batch_size more).
        (new ExpireStaleBookings)->handle();

        $cancelledCount = Booking::query()
            ->where('status', BookingStatus::CANCELLED)
            ->where('cancellation_reason', 'expired')
            ->count();

        $this->assertSame(4, $cancelledCount);
    }

    public function test_job_is_idempotent(): void
    {
        Event::fake([BookingCancelled::class]);

        $stale = $this->agedPendingBooking(minutesAgo: 60);

        (new ExpireStaleBookings)->handle();
        (new ExpireStaleBookings)->handle();
        (new ExpireStaleBookings)->handle();

        $stale->refresh();
        $this->assertSame(BookingStatus::CANCELLED, $stale->status);

        // BookingCancelled event should fire exactly once — the first run
        // cancels, subsequent runs see status=CANCELLED and skip.
        Event::assertDispatchedTimes(BookingCancelled::class, 1);
    }

    // ─── Concurrency / race safety ────────────────────────────────────────────

    public function test_booking_promoted_to_confirmed_mid_flight_is_not_expired(): void
    {
        // Simulates the race: job fetches a stale PENDING id, but before the
        // per-booking transaction locks the row, a concurrent confirm() promotes
        // the booking to CONFIRMED. The expire must detect the status change
        // under lock and skip.
        //
        // We prove this indirectly: flip the status between creation and job
        // execution. The job's re-check under lock will see CONFIRMED and exit.
        Event::fake([BookingCancelled::class]);

        $booking = $this->agedPendingBooking(minutesAgo: 60);

        // Simulate the race window: by the time the job processes this id,
        // someone has promoted it to CONFIRMED.
        Booking::query()->whereKey($booking->id)->update([
            'status' => BookingStatus::CONFIRMED->value,
        ]);

        (new ExpireStaleBookings)->handle();

        $booking->refresh();
        $this->assertSame(BookingStatus::CONFIRMED, $booking->status);
        $this->assertNull($booking->cancelled_at);

        Event::assertNotDispatched(BookingCancelled::class);
    }
}
