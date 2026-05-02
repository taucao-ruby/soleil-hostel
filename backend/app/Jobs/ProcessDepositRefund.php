<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Booking;
use App\Models\DepositEvent;
use App\Services\StripeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Issue a Stripe refund for a deposit transition that resolved to
 * REFUNDED or PARTIAL_REFUND (CONC-005).
 *
 * The deposit FSM transition has already been written to deposit_events by
 * Deposit::transitionTo before this job is dispatched, so the booking's
 * deposit_status reflects the BUSINESS DECISION. This job is the I/O step
 * that turns that decision into a Stripe refund.
 *
 * Outcomes:
 * - Stripe refund succeeds  -> append a deposit_events row capturing the
 *   stripe_refund_id (no FSM transition; status stays at the resolved state).
 * - Stripe refund fails     -> append a deposit_events row capturing the
 *   error; the job retries up to $tries times. The FSM is NOT rolled back —
 *   the business decision stands; only the Stripe call is retried.
 *
 * Idempotency: StripeService::createRefund passes a deterministic
 * idempotency key derived from the booking id, so repeated dispatches
 * cannot double-refund.
 */
final class ProcessDepositRefund implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [60, 120, 300, 900, 1800];

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public readonly int $bookingId,
        public readonly int $depositEventId,
        public readonly int $refundAmount,
        public readonly string $reason,
    ) {}

    public function handle(StripeService $stripe): void
    {
        $booking = Booking::find($this->bookingId);
        if (! $booking instanceof Booking) {
            Log::warning('ProcessDepositRefund: booking missing, skipping', [
                'booking_id' => $this->bookingId,
                'deposit_event_id' => $this->depositEventId,
            ]);

            return;
        }

        if ($this->refundAmount <= 0) {
            Log::warning('ProcessDepositRefund: non-positive amount, skipping', [
                'booking_id' => $this->bookingId,
                'amount' => $this->refundAmount,
            ]);

            return;
        }

        if (blank($booking->payment_intent_id)) {
            Log::warning('ProcessDepositRefund: no payment_intent_id, skipping', [
                'booking_id' => $this->bookingId,
            ]);

            return;
        }

        try {
            $refundId = $stripe->createRefund($booking, $this->refundAmount, $this->reason);
        } catch (\Throwable $e) {
            Log::error('ProcessDepositRefund: stripe refund failed', [
                'booking_id' => $this->bookingId,
                'deposit_event_id' => $this->depositEventId,
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
            ]);

            $this->appendOutcomeEvent(
                booking: $booking,
                outcome: 'stripe_refund_failed',
                refundId: null,
                errorMessage: $e->getMessage(),
            );

            throw $e;
        }

        $this->appendOutcomeEvent(
            booking: $booking,
            outcome: 'stripe_refund_succeeded',
            refundId: $refundId,
            errorMessage: null,
        );

        Log::info('ProcessDepositRefund: stripe refund succeeded', [
            'booking_id' => $this->bookingId,
            'refund_id' => $refundId,
            'amount' => $this->refundAmount,
        ]);
    }

    /**
     * Append an audit event capturing the Stripe call outcome.
     *
     * No FSM transition: from_status == to_status == current deposit_status.
     * The metadata column carries the outcome detail.
     */
    private function appendOutcomeEvent(
        Booking $booking,
        string $outcome,
        ?string $refundId,
        ?string $errorMessage,
    ): void {
        $current = $booking->fresh()?->deposit_status ?? $booking->deposit_status;

        DepositEvent::create([
            'booking_id' => $booking->id,
            'from_status' => $current,
            'to_status' => $current,
            'refund_percent' => 0,
            'refund_amount' => $this->refundAmount,
            'reason' => $outcome,
            'actor_id' => null,
            'actor_email' => null,
            'actor_role' => null,
            'metadata' => array_filter([
                'parent_event_id' => $this->depositEventId,
                'stripe_refund_id' => $refundId,
                'error' => $errorMessage,
                'attempt' => $this->attempts(),
            ], static fn ($v) => $v !== null),
        ]);
    }
}
