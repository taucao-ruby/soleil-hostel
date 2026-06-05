<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Enums\BookingStatus;
use App\Enums\PaymentPolicy;
use App\Enums\PaymentStatus;
use App\Events\BookingCancelled;
use App\Jobs\ExpireStaleBookings;
use App\Jobs\ProcessPaymentCancellationOutbox;
use App\Models\Booking;
use App\Models\PaymentCancellationTask;
use App\Models\Room;
use App\Models\User;
use App\Services\Payment\PaymentIntentCancellationOutcome;
use App\Services\StripeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\MockInterface;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\AuthenticationException;
use Tests\TestCase;

/**
 * PAY-03 — Stripe PaymentIntent cancellation must never run inside the expiry
 * row-lock transaction, and must be reliable, idempotent, retriable, and
 * auditable when run off the lock.
 *
 * Coverage map:
 *   1. Transaction boundary  — expiry commits and frees the room with zero Stripe I/O.
 *   2. Stripe-hang isolation — room availability is released independent of Stripe.
 *   3. Idempotency           — repeated expiry => one task, one BookingCancelled.
 *   4. Worker success        — cancel called with stable idempotency key, off any lock.
 *   5. Transient failure     — task retried with backoff; booking stays terminal.
 *   6. Terminal Stripe state — non-cancellable PI => failed_permanent, no resurrection.
 *   7. Safety guards         — mismatched intent / non-terminal booking refused.
 *   8. Circuit breaker       — exhausted attempts fail permanently, no infinite loop.
 */
#[\PHPUnit\Framework\Attributes\Group('booking')]
final class PaymentCancellationOutboxTest extends TestCase
{
    use RefreshDatabase;

    private function agedPendingBooking(string $paymentIntentId = 'pi_outbox_pending'): Booking
    {
        config()->set('booking.pending_ttl_minutes', 30);

        $booking = Booking::factory()
            ->for(User::factory())
            ->for(Room::factory()->available()->ready())
            ->pending()
            ->create([
                'payment_intent_id' => $paymentIntentId,
                'payment_policy' => PaymentPolicy::PREPAID,
                'payment_status' => PaymentStatus::REQUIRES_PAYMENT_METHOD,
                'payment_currency' => 'vnd',
                'amount' => 300000,
                'check_in' => Carbon::now()->addDays(5)->startOfDay(),
                'check_out' => Carbon::now()->addDays(7)->startOfDay(),
            ]);

        $past = Carbon::now()->subMinutes(45);
        Booking::query()->whereKey($booking->id)->update([
            'created_at' => $past,
            'updated_at' => $past,
        ]);

        return $booking->fresh();
    }

    private function cancelledBookingWithTask(
        string $paymentIntentId = 'pi_outbox_1',
        array $taskOverrides = [],
        ?string $bookingPaymentIntentId = null,
    ): array {
        $booking = Booking::factory()
            ->for(User::factory())
            ->for(Room::factory()->available()->ready())
            ->cancelled()
            ->create([
                'payment_intent_id' => $bookingPaymentIntentId ?? $paymentIntentId,
                'amount' => 300000,
                'cancellation_reason' => ExpireStaleBookings::EXPIRED_REASON,
            ]);

        $task = PaymentCancellationTask::create(array_merge([
            'booking_id' => $booking->id,
            'payment_intent_id' => $paymentIntentId,
            'action' => PaymentCancellationTask::ACTION_CANCEL,
            'status' => PaymentCancellationTask::STATUS_PENDING,
            'attempts' => 0,
            'available_at' => now(),
        ], $taskOverrides));

        return [$booking->fresh(), $task->fresh()];
    }

    private function runDrainer(): void
    {
        (new ProcessPaymentCancellationOutbox)->handle(app(StripeService::class));
    }

    // ── 1 & 2: transaction boundary + Stripe-hang isolation ──────────────────

    public function test_expiry_frees_room_and_enqueues_task_without_any_stripe_call(): void
    {
        $booking = $this->agedPendingBooking();

        $checkIn = Carbon::now()->addDays(5)->startOfDay();
        $checkOut = Carbon::now()->addDays(7)->startOfDay();

        $this->assertTrue(
            Booking::overlappingBookings($booking->room_id, $checkIn, $checkOut)->exists(),
            'Pending booking should block the room before expiry',
        );

        // Expiry must not call Stripe at all — that is the PAY-03 lock-hold defect.
        $this->mock(StripeService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('cancelPaymentIntentForBooking');
            $mock->shouldNotReceive('cancelPaymentIntent');
        });

        (new ExpireStaleBookings)->handle();

        $booking->refresh();
        $this->assertSame(BookingStatus::CANCELLED, $booking->status);

        // Room availability is released purely by the local DB commit, with no
        // dependency on Stripe — so a hung Stripe cancel can never block a
        // concurrent createBooking overlap-lock for this room.
        $this->assertFalse(
            Booking::overlappingBookings($booking->room_id, $checkIn, $checkOut)->exists(),
            'Room must be free immediately after the expiry transaction commits',
        );

        $task = PaymentCancellationTask::query()->where('booking_id', $booking->id)->sole();
        $this->assertSame(PaymentCancellationTask::STATUS_PENDING, $task->status);
        $this->assertSame('pi_outbox_pending', $task->payment_intent_id);
    }

    // ── 3: idempotency ───────────────────────────────────────────────────────

    public function test_repeated_expiry_creates_single_task_and_one_event(): void
    {
        Event::fake([BookingCancelled::class]);

        $booking = $this->agedPendingBooking();

        (new ExpireStaleBookings)->handle();
        (new ExpireStaleBookings)->handle();
        (new ExpireStaleBookings)->handle();

        $this->assertSame(
            1,
            PaymentCancellationTask::query()->where('booking_id', $booking->id)->count(),
            'Duplicate expiry runs must not enqueue duplicate cancellation tasks',
        );
        Event::assertDispatchedTimes(BookingCancelled::class, 1);
    }

    // ── 4: worker success, proven off any DB lock with a stable key ──────────

    public function test_worker_cancels_with_idempotency_key_outside_any_lock_and_marks_succeeded(): void
    {
        [$booking, $task] = $this->cancelledBookingWithTask();

        // RefreshDatabase wraps each test in an outer transaction, so the
        // baseline level is 1 here. The PAY-03 proof is that the Stripe call
        // opens NO ADDITIONAL transaction/lock beyond that baseline (the claim
        // transaction has already committed by the time we reach Stripe).
        $baselineTxLevel = DB::transactionLevel();

        $this->mock(StripeService::class, function (MockInterface $mock) use ($booking, $baselineTxLevel): void {
            $mock->shouldReceive('cancelPaymentIntentForBooking')
                ->once()
                ->with(
                    Mockery::on(fn (Booking $b): bool => $b->id === $booking->id),
                    'booking:'.$booking->id.':payment_intent_cancel:v1',
                )
                ->andReturnUsing(function () use ($baselineTxLevel): PaymentIntentCancellationOutcome {
                    $this->assertSame($baselineTxLevel, DB::transactionLevel());

                    return PaymentIntentCancellationOutcome::Canceled;
                });
        });

        $this->runDrainer();

        $task->refresh();
        $this->assertSame(PaymentCancellationTask::STATUS_SUCCEEDED, $task->status);
        $this->assertNotNull($task->processed_at);
        $this->assertSame(1, $task->attempts);

        $this->assertSame(BookingStatus::CANCELLED, $booking->fresh()->status);
    }

    public function test_worker_treats_already_canceled_as_success(): void
    {
        [, $task] = $this->cancelledBookingWithTask();

        $this->mock(StripeService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('cancelPaymentIntentForBooking')
                ->once()
                ->andReturn(PaymentIntentCancellationOutcome::AlreadyCanceled);
        });

        $this->runDrainer();

        $this->assertSame(PaymentCancellationTask::STATUS_SUCCEEDED, $task->fresh()->status);
    }

    // ── 5: transient failure => retry with backoff ───────────────────────────

    public function test_worker_transient_error_marks_retrying_with_backoff(): void
    {
        [$booking, $task] = $this->cancelledBookingWithTask();

        $this->mock(StripeService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('cancelPaymentIntentForBooking')
                ->once()
                ->andThrow(new ApiConnectionException('Connection timed out'));
        });

        $this->runDrainer();

        $task->refresh();
        $this->assertSame(PaymentCancellationTask::STATUS_RETRYING, $task->status);
        $this->assertSame(1, $task->attempts);
        $this->assertTrue($task->available_at->greaterThan(now()), 'Backoff must push available_at into the future');
        $this->assertSame('ApiConnectionException', $task->last_error_code);
        $this->assertNull($task->processed_at);

        // Booking stays terminal regardless of the Stripe failure.
        $this->assertSame(BookingStatus::CANCELLED, $booking->fresh()->status);
    }

    public function test_worker_auth_error_fails_permanently(): void
    {
        [, $task] = $this->cancelledBookingWithTask();

        $this->mock(StripeService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('cancelPaymentIntentForBooking')
                ->once()
                ->andThrow(new AuthenticationException('No API key provided'));
        });

        $this->runDrainer();

        $task->refresh();
        $this->assertSame(PaymentCancellationTask::STATUS_FAILED_PERMANENT, $task->status);
        $this->assertSame('AuthenticationException', $task->last_error_code);
    }

    // ── 6: terminal Stripe state => failed_permanent, no resurrection ────────

    public function test_worker_not_cancellable_marks_failed_permanent_and_keeps_booking_cancelled(): void
    {
        [$booking, $task] = $this->cancelledBookingWithTask();

        $this->mock(StripeService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('cancelPaymentIntentForBooking')
                ->once()
                ->andReturn(PaymentIntentCancellationOutcome::NotCancellable);
        });

        $this->runDrainer();

        $task->refresh();
        $this->assertSame(PaymentCancellationTask::STATUS_FAILED_PERMANENT, $task->status);
        $this->assertSame('payment_intent_not_cancellable', $task->last_error_code);
        $this->assertNotNull($task->processed_at);

        // Never resurrect a booking because cancellation could not complete.
        $this->assertSame(BookingStatus::CANCELLED, $booking->fresh()->status);
    }

    // ── 7: safety guards ─────────────────────────────────────────────────────

    public function test_worker_refuses_when_payment_intent_no_longer_matches(): void
    {
        // Task points at pi_old, but the booking now carries pi_new.
        [, $task] = $this->cancelledBookingWithTask(
            paymentIntentId: 'pi_old',
            bookingPaymentIntentId: 'pi_new',
        );

        $this->mock(StripeService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('cancelPaymentIntentForBooking');
        });

        $this->runDrainer();

        $task->refresh();
        $this->assertSame(PaymentCancellationTask::STATUS_FAILED_PERMANENT, $task->status);
        $this->assertSame('payment_intent_mismatch', $task->last_error_code);
    }

    public function test_worker_refuses_when_booking_not_terminal(): void
    {
        $booking = $this->agedPendingBooking('pi_live'); // still PENDING

        $task = PaymentCancellationTask::create([
            'booking_id' => $booking->id,
            'payment_intent_id' => 'pi_live',
            'action' => PaymentCancellationTask::ACTION_CANCEL,
            'status' => PaymentCancellationTask::STATUS_PENDING,
            'attempts' => 0,
            'available_at' => now(),
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('cancelPaymentIntentForBooking');
        });

        $this->runDrainer();

        $task->refresh();
        $this->assertSame(PaymentCancellationTask::STATUS_FAILED_PERMANENT, $task->status);
        $this->assertSame('booking_not_terminal', $task->last_error_code);
        $this->assertSame(BookingStatus::PENDING, $booking->fresh()->status);
    }

    // ── 8: circuit breaker — no infinite retry loop ──────────────────────────

    public function test_budget_exhausted_task_is_failed_without_calling_stripe(): void
    {
        config()->set('booking.reconciliation.payment_cancellation.max_attempts', 10);

        [, $task] = $this->cancelledBookingWithTask(taskOverrides: [
            'status' => PaymentCancellationTask::STATUS_RETRYING,
            'attempts' => 10,
            'available_at' => now()->subMinute(),
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('cancelPaymentIntentForBooking');
        });

        $this->runDrainer();

        $task->refresh();
        $this->assertSame(PaymentCancellationTask::STATUS_FAILED_PERMANENT, $task->status);
        $this->assertSame('budget_exhausted', $task->last_error_code);
    }

    public function test_transient_failure_on_last_attempt_fails_permanently(): void
    {
        config()->set('booking.reconciliation.payment_cancellation.max_attempts', 1);

        [, $task] = $this->cancelledBookingWithTask();

        $this->mock(StripeService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('cancelPaymentIntentForBooking')
                ->once()
                ->andThrow(new ApiConnectionException('Connection timed out'));
        });

        $this->runDrainer();

        $task->refresh();
        $this->assertSame(PaymentCancellationTask::STATUS_FAILED_PERMANENT, $task->status);
        $this->assertSame('ApiConnectionException', $task->last_error_code);
    }
}
