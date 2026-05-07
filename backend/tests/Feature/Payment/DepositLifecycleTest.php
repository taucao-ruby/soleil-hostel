<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Enums\BookingStatus;
use App\Enums\DepositStatus;
use App\Exceptions\DepositTransitionException;
use App\Jobs\ProcessDepositRefund;
use App\Models\Booking;
use App\Models\DepositEvent;
use App\Models\Room;
use App\Models\User;
use App\Services\CancellationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * CONC-005 — Deposit lifecycle tied to booking lifecycle.
 *
 * Verifies that cancelling a booking with a held deposit always transitions
 * the deposit out of 'collected' (held) into one of the terminal states
 * (REFUNDED / PARTIAL_REFUND / FORFEITED) and writes an append-only audit
 * row to deposit_events.
 */
final class DepositLifecycleTest extends TestCase
{
    private User $guest;

    private Room $room;

    private CancellationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guest = User::factory()->create();
        $this->room = Room::factory()->create();
        $this->service = app(CancellationService::class);
    }

    // ===== Policy-driven transitions =====

    public function test_full_refund_window_transitions_deposit_to_refunded_and_queues_job(): void
    {
        Bus::fake([ProcessDepositRefund::class]);

        // Freeze at noon, check-in 3 days out at midnight = 60h > 48h full window.
        $this->travelTo(now()->startOfDay()->addHours(12));
        $booking = $this->bookingWithHeldDeposit(
            checkInOffsetDays: 3,
            depositAmount: 20_000,
        );

        $this->service->cancel($booking, $this->guest);

        $booking->refresh();
        $this->assertSame(DepositStatus::REFUNDED, $booking->deposit_status);

        $event = DepositEvent::where('booking_id', $booking->id)->firstOrFail();
        $this->assertSame(DepositStatus::COLLECTED, $event->from_status);
        $this->assertSame(DepositStatus::REFUNDED, $event->to_status);
        $this->assertSame(100, $event->refund_percent);
        $this->assertSame(20_000, $event->refund_amount);
        $this->assertSame('cancelled_within_full_refund_window', $event->reason);
        $this->assertSame($this->guest->id, $event->actor_id);

        Bus::assertDispatched(ProcessDepositRefund::class, function (ProcessDepositRefund $job) use ($booking) {
            return $job->bookingId === $booking->id
                && $job->refundAmount === 20_000
                && $job->reason === 'cancelled_within_full_refund_window';
        });
    }

    public function test_partial_refund_window_transitions_deposit_to_partial_refund_and_queues_job(): void
    {
        Bus::fake([ProcessDepositRefund::class]);

        // Freeze at noon; check-in 2 days at midnight = 36h, in (24h, 48h) partial window.
        $this->travelTo(now()->startOfDay()->addHours(12));
        $booking = $this->bookingWithHeldDeposit(
            checkInOffsetDays: 2,
            depositAmount: 10_000,
        );

        $this->service->cancel($booking, $this->guest);

        $booking->refresh();
        $this->assertSame(DepositStatus::PARTIAL_REFUND, $booking->deposit_status);

        $event = DepositEvent::where('booking_id', $booking->id)->firstOrFail();
        $this->assertSame(DepositStatus::COLLECTED, $event->from_status);
        $this->assertSame(DepositStatus::PARTIAL_REFUND, $event->to_status);
        $this->assertSame(50, $event->refund_percent); // partial_refund_pct default
        $this->assertSame(5_000, $event->refund_amount);

        Bus::assertDispatched(ProcessDepositRefund::class);
    }

    public function test_no_refund_window_forfeits_deposit_without_queueing_job(): void
    {
        Bus::fake([ProcessDepositRefund::class]);

        // Freeze at noon; check-in tomorrow midnight = 12h < 24h partial window.
        $this->travelTo(now()->startOfDay()->addHours(12));
        $booking = $this->bookingWithHeldDeposit(
            checkInOffsetDays: 1,
            depositAmount: 10_000,
        );

        $this->service->cancel($booking, $this->guest);

        $booking->refresh();
        $this->assertSame(DepositStatus::FORFEITED, $booking->deposit_status);

        $event = DepositEvent::where('booking_id', $booking->id)->firstOrFail();
        $this->assertSame(DepositStatus::COLLECTED, $event->from_status);
        $this->assertSame(DepositStatus::FORFEITED, $event->to_status);
        $this->assertSame(0, $event->refund_percent);
        $this->assertSame(0, $event->refund_amount);
        $this->assertSame('cancelled_within_no_refund_window', $event->reason);

        Bus::assertNotDispatched(ProcessDepositRefund::class);
    }

    public function test_cancelling_booking_without_deposit_writes_no_event(): void
    {
        Bus::fake([ProcessDepositRefund::class]);

        $booking = Booking::factory()
            ->for($this->guest)
            ->for($this->room)
            ->confirmed()
            ->create([
                'check_in' => Carbon::now()->addDays(5),
                'check_out' => Carbon::now()->addDays(7),
                'deposit_amount' => null,
                'deposit_status' => DepositStatus::NONE,
            ]);

        $this->service->cancel($booking, $this->guest);

        $booking->refresh();
        $this->assertSame(DepositStatus::NONE, $booking->deposit_status);
        $this->assertSame(0, DepositEvent::where('booking_id', $booking->id)->count());

        Bus::assertNotDispatched(ProcessDepositRefund::class);
    }

    public function test_force_cancel_forfeits_held_deposit(): void
    {
        Bus::fake([ProcessDepositRefund::class]);

        $this->travelTo(now()->startOfDay()->addHours(12));
        $admin = User::factory()->admin()->create();
        $booking = $this->bookingWithHeldDeposit(
            checkInOffsetDays: 5,
            depositAmount: 15_000,
        );

        $this->service->forceCancel($booking, $admin, 'fraud_suspected');

        $booking->refresh();
        $this->assertSame(BookingStatus::CANCELLED, $booking->status);
        $this->assertSame(DepositStatus::FORFEITED, $booking->deposit_status);

        $event = DepositEvent::where('booking_id', $booking->id)->firstOrFail();
        $this->assertSame(DepositStatus::FORFEITED, $event->to_status);
        $this->assertSame(0, $event->refund_percent);
        $this->assertStringContainsString('force_cancelled', (string) $event->reason);

        Bus::assertNotDispatched(ProcessDepositRefund::class);
    }

    // ===== FSM invariants =====

    public function test_transition_to_same_status_is_idempotent_and_writes_no_extra_event(): void
    {
        $booking = $this->bookingWithHeldDeposit(
            checkInOffsetDays: 5,
            depositAmount: 10_000,
        );

        // First transition: COLLECTED -> REFUNDED, writes one event.
        $booking->deposit->transitionTo(
            refundPercent: 100,
            reason: 'cancelled_within_full_refund_window',
            actor: $this->guest,
        );

        $this->assertSame(1, DepositEvent::where('booking_id', $booking->id)->count());

        // Second transition with same target is a no-op.
        $booking->refresh();
        $booking->deposit->transitionTo(
            refundPercent: 100,
            reason: 'cancelled_within_full_refund_window',
            actor: $this->guest,
        );

        $this->assertSame(1, DepositEvent::where('booking_id', $booking->id)->count());
    }

    public function test_transition_from_terminal_state_throws(): void
    {
        $booking = $this->bookingWithHeldDeposit(
            checkInOffsetDays: 5,
            depositAmount: 10_000,
        );

        // Move to FORFEITED.
        $booking->deposit->transitionTo(
            refundPercent: 0,
            reason: 'cancelled_within_no_refund_window',
            actor: $this->guest,
        );
        $booking->refresh();

        $this->expectException(DepositTransitionException::class);
        $booking->deposit->transitionTo(
            refundPercent: 100,
            reason: 'late_override',
            actor: $this->guest,
        );
    }

    public function test_transition_on_booking_without_deposit_throws_not_held(): void
    {
        $booking = Booking::factory()
            ->for($this->guest)
            ->for($this->room)
            ->confirmed()
            ->create([
                'deposit_amount' => null,
                'deposit_status' => DepositStatus::NONE,
            ]);

        $this->expectException(DepositTransitionException::class);
        $this->expectExceptionMessageMatches('/does not have a held deposit/');

        $booking->deposit->transitionTo(
            refundPercent: 100,
            reason: 'no_deposit',
            actor: $this->guest,
        );
    }

    public function test_invalid_refund_percent_is_rejected(): void
    {
        $booking = $this->bookingWithHeldDeposit(
            checkInOffsetDays: 5,
            depositAmount: 10_000,
        );

        $this->expectException(\InvalidArgumentException::class);

        $booking->deposit->transitionTo(
            refundPercent: 150,
            reason: 'bug',
            actor: $this->guest,
        );
    }

    // ===== Append-only invariant on deposit_events =====

    public function test_deposit_event_row_cannot_be_updated(): void
    {
        $booking = $this->bookingWithHeldDeposit(
            checkInOffsetDays: 5,
            depositAmount: 10_000,
        );

        $event = $booking->deposit->transitionTo(
            refundPercent: 100,
            reason: 'cancelled_within_full_refund_window',
            actor: $this->guest,
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/append-only/');

        $event->reason = 'tampered';
        $event->save();
    }

    public function test_deposit_event_row_cannot_be_deleted(): void
    {
        $booking = $this->bookingWithHeldDeposit(
            checkInOffsetDays: 5,
            depositAmount: 10_000,
        );

        $event = $booking->deposit->transitionTo(
            refundPercent: 100,
            reason: 'cancelled_within_full_refund_window',
            actor: $this->guest,
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/append-only/');

        $event->delete();
    }

    // ===== CancellationPolicy snapshot =====

    public function test_cancellation_policy_picks_full_refund_inside_window(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(12));
        $booking = $this->bookingWithHeldDeposit(
            checkInOffsetDays: 5,
            depositAmount: 10_000,
        );

        $policy = $booking->cancellationPolicy();
        $this->assertSame(100, $policy->refundPercent);
        $this->assertSame('cancelled_within_full_refund_window', $policy->reason);
        $this->assertTrue($policy->isFullRefund());
    }

    public function test_cancellation_policy_picks_partial_refund_inside_window(): void
    {
        // Freeze at noon; check-in 2 days at midnight = 36h, in (24h, 48h) partial window.
        $this->travelTo(now()->startOfDay()->addHours(12));
        $booking = $this->bookingWithHeldDeposit(
            checkInOffsetDays: 2,
            depositAmount: 10_000,
        );

        $policy = $booking->cancellationPolicy();
        $this->assertSame(50, $policy->refundPercent);
        $this->assertSame('cancelled_within_partial_refund_window', $policy->reason);
        $this->assertTrue($policy->isPartialRefund());
    }

    public function test_cancellation_policy_picks_forfeit_in_no_refund_window(): void
    {
        // Freeze at noon; check-in tomorrow midnight = 12h < 24h.
        $this->travelTo(now()->startOfDay()->addHours(12));
        $booking = $this->bookingWithHeldDeposit(
            checkInOffsetDays: 1,
            depositAmount: 10_000,
        );

        $policy = $booking->cancellationPolicy();
        $this->assertSame(0, $policy->refundPercent);
        $this->assertSame('cancelled_within_no_refund_window', $policy->reason);
        $this->assertTrue($policy->isForfeit());
    }

    // ===== Helpers =====

    /**
     * Build a confirmed booking with a held deposit, check_in $checkInOffsetDays
     * days away at midnight.
     *
     * Caller is expected to have called $this->travelTo(...) first to freeze
     * the clock; check_in is cast to 'date' so day-based offsets are stable.
     *
     * The booking has NO payment_intent_id by default — this isolates the
     * deposit FSM from the (separate) booking-level Stripe refund path.
     */
    private function bookingWithHeldDeposit(int $checkInOffsetDays, int $depositAmount): Booking
    {
        $checkIn = Carbon::now()->addDays($checkInOffsetDays)->startOfDay();
        $checkOut = $checkIn->copy()->addDays(2);

        return Booking::factory()
            ->for($this->guest)
            ->for($this->room)
            ->confirmed()
            ->create([
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'amount' => null,
                'payment_intent_id' => null,
                'deposit_amount' => $depositAmount,
                'deposit_status' => DepositStatus::COLLECTED,
                'deposit_collected_at' => Carbon::now()->subHour(),
            ]);
    }
}
