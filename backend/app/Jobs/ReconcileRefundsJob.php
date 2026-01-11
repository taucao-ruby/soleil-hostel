<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\BookingStatus;
use App\Events\BookingCancelled;
use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Reconcile orphaned refund states.
 *
 * This job recovers from failure scenarios where:
 * 1. Stripe refund succeeded but process died before DB update
 * 2. Refund is stuck in pending state due to race condition
 * 3. Failed refunds that may now be retryable
 *
 * Runs every 5 minutes via scheduler.
 *
 * Design:
 * - Queries Stripe API to get actual refund status
 * - Updates local DB to match Stripe's source of truth
 * - Uses chunking to handle large volumes efficiently
 */
final class ReconcileRefundsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of retry attempts.
     */
    public int $tries = 3;

    /**
     * Retry backoff in seconds (exponential).
     */
    public array $backoff = [60, 300, 900];

    /**
     * Delete job if models are missing.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $staleThreshold = config('booking.reconciliation.stale_threshold_minutes', 5);
        $batchSize = config('booking.reconciliation.batch_size', 50);

        $this->reconcilePendingRefunds($staleThreshold, $batchSize);
        $this->retryFailedRefunds($batchSize);
    }

    /**
     * Reconcile bookings stuck in refund_pending state.
     */
    private function reconcilePendingRefunds(int $staleMinutes, int $batchSize): void
    {
        Booking::query()
            ->where('status', BookingStatus::REFUND_PENDING)
            ->where('updated_at', '<', now()->subMinutes($staleMinutes))
            ->whereNotNull('payment_intent_id')
            ->chunk($batchSize, function ($bookings) {
                foreach ($bookings as $booking) {
                    $this->reconcileBooking($booking);
                }
            });
    }

    /**
     * Retry failed refunds that may be recoverable.
     */
    private function retryFailedRefunds(int $batchSize): void
    {
        $maxAttempts = config('booking.reconciliation.max_attempts', 5);

        Booking::query()
            ->where('status', BookingStatus::REFUND_FAILED)
            ->where('updated_at', '<', now()->subMinutes(15)) // Wait before retry
            ->whereNotNull('payment_intent_id')
            ->whereNull('refund_id') // No successful refund yet
            ->chunk($batchSize, function ($bookings) use ($maxAttempts) {
                foreach ($bookings as $booking) {
                    // Track retry count in refund_error field
                    $retryCount = $this->extractRetryCount($booking->refund_error);

                    if ($retryCount >= $maxAttempts) {
                        Log::warning('Refund max retries exceeded', [
                            'booking_id' => $booking->id,
                            'retry_count' => $retryCount,
                        ]);
                        continue;
                    }

                    $this->retryRefund($booking, $retryCount + 1);
                }
            });
    }

    /**
     * Reconcile a single booking with Stripe.
     */
    private function reconcileBooking(Booking $booking): void
    {
        try {
            // Check if refund_id exists (refund was initiated)
            if ($booking->refund_id) {
                $this->verifyExistingRefund($booking);
                return;
            }

            // No refund_id means refund was never initiated
            // Check payment intent status
            $this->checkPaymentIntentRefunds($booking);

        } catch (\Throwable $e) {
            Log::warning('Reconciliation failed', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Verify status of an existing refund.
     */
    private function verifyExistingRefund(Booking $booking): void
    {
        $stripe = $booking->user->stripe();
        $refund = $stripe->refunds->retrieve($booking->refund_id);

        if ($refund->status === 'succeeded') {
            DB::transaction(function () use ($booking, $refund) {
                $booking->update([
                    'status' => BookingStatus::CANCELLED,
                    'refund_status' => 'succeeded',
                    'refund_amount' => $refund->amount,
                    'refund_error' => null,
                ]);

                event(new BookingCancelled($booking));
            });

            Log::info('Reconciled pending refund', [
                'booking_id' => $booking->id,
                'refund_id' => $refund->id,
            ]);
        } elseif ($refund->status === 'failed') {
            $booking->update([
                'status' => BookingStatus::REFUND_FAILED,
                'refund_status' => 'failed',
                'refund_error' => $refund->failure_reason ?? 'Unknown failure',
            ]);

            Log::warning('Refund failed on Stripe', [
                'booking_id' => $booking->id,
                'refund_id' => $refund->id,
                'reason' => $refund->failure_reason,
            ]);
        }
        // 'pending' status: leave as is, Stripe is still processing
    }

    /**
     * Check payment intent for any refunds.
     */
    private function checkPaymentIntentRefunds(Booking $booking): void
    {
        $stripe = $booking->user->stripe();
        $paymentIntent = $stripe->paymentIntents->retrieve(
            $booking->payment_intent_id,
            ['expand' => ['latest_charge.refunds']]
        );

        $charge = $paymentIntent->latest_charge;
        if (!$charge || !$charge->refunds->data) {
            // No refunds found - may need manual intervention
            Log::info('No refunds found for stale pending booking', [
                'booking_id' => $booking->id,
            ]);
            return;
        }

        // Check the latest refund
        $latestRefund = $charge->refunds->data[0];

        if ($latestRefund->status === 'succeeded') {
            DB::transaction(function () use ($booking, $latestRefund) {
                $booking->update([
                    'status' => BookingStatus::CANCELLED,
                    'refund_id' => $latestRefund->id,
                    'refund_status' => 'succeeded',
                    'refund_amount' => $latestRefund->amount,
                    'refund_error' => null,
                ]);

                event(new BookingCancelled($booking));
            });

            Log::info('Discovered and reconciled refund', [
                'booking_id' => $booking->id,
                'refund_id' => $latestRefund->id,
            ]);
        }
    }

    /**
     * Retry a failed refund.
     */
    private function retryRefund(Booking $booking, int $attemptNumber): void
    {
        try {
            Log::info('Retrying refund', [
                'booking_id' => $booking->id,
                'attempt' => $attemptNumber,
            ]);

            // Use the same calculation as CancellationService
            $refundAmount = $booking->calculateRefundAmount();

            if ($refundAmount <= 0) {
                // No refund needed - just cancel
                $booking->update([
                    'status' => BookingStatus::CANCELLED,
                    'refund_error' => null,
                ]);
                event(new BookingCancelled($booking));
                return;
            }

            $refund = $booking->user->refund(
                $booking->payment_intent_id,
                ['amount' => $refundAmount]
            );

            DB::transaction(function () use ($booking, $refund, $refundAmount) {
                $booking->update([
                    'status' => BookingStatus::CANCELLED,
                    'refund_id' => $refund->id,
                    'refund_status' => 'succeeded',
                    'refund_amount' => $refundAmount,
                    'refund_error' => null,
                ]);

                event(new BookingCancelled($booking));
            });

            Log::info('Retry refund succeeded', [
                'booking_id' => $booking->id,
                'refund_id' => $refund->id,
            ]);

        } catch (\Throwable $e) {
            $booking->update([
                'status' => BookingStatus::REFUND_FAILED,
                'refund_error' => "[Attempt {$attemptNumber}] " . $e->getMessage(),
            ]);

            Log::warning('Retry refund failed', [
                'booking_id' => $booking->id,
                'attempt' => $attemptNumber,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Extract retry count from error message.
     */
    private function extractRetryCount(?string $errorMessage): int
    {
        if (!$errorMessage) {
            return 0;
        }

        if (preg_match('/\[Attempt (\d+)\]/', $errorMessage, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }
}
