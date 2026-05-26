<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\BookingStatus;
use App\Events\BookingCancelled;
use App\Models\Booking;
use App\Models\User;
use App\Services\Payment\StripeRefundEventRecorder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Cashier;

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
     * Lazily-resolved refund ledger recorder. Not set in the constructor so the
     * job stays queue-serializable; the recorder is stateless and resolved from
     * the container on first use.
     */
    private ?StripeRefundEventRecorder $refundLedger = null;

    private function refundLedger(): StripeRefundEventRecorder
    {
        return $this->refundLedger ??= app(StripeRefundEventRecorder::class);
    }

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
     *
     * Eager-loads user; user_id can be NULL because the FK uses ON DELETE
     * SET NULL (bookings survive guest account deletion). Null-user bookings
     * still need reconciliation — see resolveStripeClientFor (CONC-006).
     */
    private function reconcilePendingRefunds(int $staleMinutes, int $batchSize): void
    {
        Booking::query()
            ->with('user')
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
            ->with('user')
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
     * Resolve a Stripe client for a booking even when the booking has no user.
     *
     * The FK bookings.user_id is ON DELETE SET NULL, so a deleted guest
     * leaves user_id = NULL. We:
     *  1. Use Booking::user (already eager-loaded) when present.
     *  2. Otherwise fall back to the application-level Stripe client.
     *
     * Returns null if no client can be resolved (skips reconciliation).
     */
    private function resolveStripeClientFor(Booking $booking): ?\Stripe\StripeClient
    {
        $user = $booking->user;
        if ($user instanceof User) {
            return $user->stripe();
        }

        if (blank(config('cashier.secret'))) {
            return null;
        }

        return Cashier::stripe();
    }

    /**
     * Resolve the recipient email for reconciliation notifications.
     *
     * Order: user.email -> booking.guest_email. Returns null if neither is
     * available — caller logs a warning and continues (CONC-006).
     */
    private function resolveRecipientEmail(Booking $booking): ?string
    {
        $userEmail = $booking->user?->email;
        if (filled($userEmail)) {
            return $userEmail;
        }

        $guestEmail = $booking->guest_email;
        if (filled($guestEmail)) {
            return $guestEmail;
        }

        return null;
    }

    /**
     * Reconcile a single booking with Stripe.
     */
    private function reconcileBooking(Booking $booking): void
    {
        try {
            $stripe = $this->resolveStripeClientFor($booking);
            if ($stripe === null) {
                Log::warning('Reconciliation skipped: no stripe client available', [
                    'booking_id' => $booking->id,
                    'user_id' => $booking->user_id,
                ]);

                return;
            }

            // Check if refund_id exists (refund was initiated)
            if ($booking->refund_id) {
                $this->verifyExistingRefund($booking, $stripe);

                return;
            }

            // No refund_id means refund was never initiated
            // Check payment intent status
            $this->checkPaymentIntentRefunds($booking, $stripe);

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
    private function verifyExistingRefund(Booking $booking, \Stripe\StripeClient $stripe): void
    {
        $refund = $stripe->refunds->retrieve($booking->refund_id);

        if ($refund->status === 'succeeded') {
            try {
                DB::transaction(function () use ($booking, $refund) {
                    $this->refundLedger()->record(
                        $booking,
                        (string) $refund->id,
                        (int) $refund->amount,
                        (string) $refund->currency,
                        StripeRefundEventRecorder::reconcileEventKey((string) $refund->id),
                    );

                    $transitioned = $booking->transitionTo(BookingStatus::CANCELLED);
                    $transitioned->forceFill([
                        'refund_status' => 'succeeded',
                        'refund_amount' => $refund->amount,
                        'refund_error' => null,
                    ])->save();

                    event(new BookingCancelled($transitioned));
                });
            } catch (UniqueConstraintViolationException) {
                Log::info('Reconcile: refund already in ledger; webhook won the race', [
                    'booking_id' => $booking->id,
                    'refund_id' => $refund->id,
                ]);

                return;
            }

            Log::info('Reconciled pending refund', [
                'booking_id' => $booking->id,
                'refund_id' => $refund->id,
            ]);
        } elseif ($refund->status === 'failed') {
            try {
                DB::transaction(function () use ($booking, $refund) {
                    $this->refundLedger()->record(
                        $booking,
                        (string) $refund->id,
                        (int) $refund->amount,
                        (string) $refund->currency,
                        StripeRefundEventRecorder::reconcileEventKey((string) $refund->id),
                    );

                    $transitioned = $booking->transitionTo(BookingStatus::REFUND_FAILED);
                    $transitioned->forceFill([
                        'refund_status' => 'failed',
                        'refund_error' => $refund->failure_reason ?? 'Unknown failure',
                    ])->save();
                });
            } catch (UniqueConstraintViolationException) {
                Log::info('Reconcile: failed refund already in ledger; webhook won the race', [
                    'booking_id' => $booking->id,
                    'refund_id' => $refund->id,
                ]);

                return;
            }

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
    private function checkPaymentIntentRefunds(Booking $booking, \Stripe\StripeClient $stripe): void
    {
        $paymentIntent = $stripe->paymentIntents->retrieve(
            $booking->payment_intent_id,
            ['expand' => ['latest_charge.refunds']]
        );

        $charge = $paymentIntent->latest_charge;
        if (! $charge instanceof \Stripe\Charge) {
            // No charge found or charge not expanded - may need manual intervention
            Log::info('No charge found for stale pending booking', [
                'booking_id' => $booking->id,
            ]);

            return;
        }

        $refunds = $charge->refunds;
        if (! $refunds instanceof \Stripe\Collection || empty($refunds->data)) {
            // No refunds found - may need manual intervention
            Log::info('No refunds found for stale pending booking', [
                'booking_id' => $booking->id,
            ]);

            return;
        }

        // Check the latest refund
        $latestRefund = $refunds->data[0];

        if ($latestRefund->status === 'succeeded') {
            try {
                DB::transaction(function () use ($booking, $latestRefund) {
                    $this->refundLedger()->record(
                        $booking,
                        (string) $latestRefund->id,
                        (int) $latestRefund->amount,
                        (string) $latestRefund->currency,
                        StripeRefundEventRecorder::reconcileEventKey((string) $latestRefund->id),
                    );

                    $transitioned = $booking->transitionTo(BookingStatus::CANCELLED);
                    $transitioned->forceFill([
                        'refund_id' => $latestRefund->id,
                        'refund_status' => 'succeeded',
                        'refund_amount' => $latestRefund->amount,
                        'refund_error' => null,
                    ])->save();

                    event(new BookingCancelled($transitioned));
                });
            } catch (UniqueConstraintViolationException) {
                Log::info('Reconcile: discovered refund already in ledger; webhook won the race', [
                    'booking_id' => $booking->id,
                    'refund_id' => $latestRefund->id,
                ]);

                return;
            }

            Log::info('Discovered and reconciled refund', [
                'booking_id' => $booking->id,
                'refund_id' => $latestRefund->id,
            ]);
        }
    }

    /**
     * Retry a failed refund.
     *
     * Null-safe (CONC-006): when the guest user has been deleted (user_id
     * NULL), we still try to issue the refund through the application-level
     * Stripe client and skip the notification path. The booking-level
     * guest_email is logged so an operator can manually follow up.
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
                $booking = $booking->transitionTo(BookingStatus::CANCELLED);
                $booking->forceFill([
                    'refund_error' => null,
                ])->save();
                event(new BookingCancelled($booking));

                return;
            }

            $recipientEmail = $this->resolveRecipientEmail($booking);
            if ($recipientEmail === null) {
                Log::warning('ReconcileRefunds: no email for booking; refund will be issued without notification', [
                    'booking_id' => $booking->id,
                    'user_id' => $booking->user_id,
                ]);
            }

            $refund = $this->issueStripeRefund($booking, $refundAmount);
            if ($refund === null) {
                // No Stripe client available — skip; do NOT throw.
                Log::warning('ReconcileRefunds: no stripe client for booking, skipping retry', [
                    'booking_id' => $booking->id,
                ]);

                return;
            }

            try {
                DB::transaction(function () use ($booking, $refund, $refundAmount) {
                    $this->refundLedger()->record(
                        $booking,
                        (string) $refund->id,
                        $refundAmount,
                        (string) $refund->currency,
                        StripeRefundEventRecorder::reconcileIssueEventKey((string) $refund->id),
                    );

                    $transitioned = $booking->transitionTo(BookingStatus::CANCELLED);
                    $transitioned->forceFill([
                        'refund_id' => $refund->id,
                        'refund_status' => 'succeeded',
                        'refund_amount' => $refundAmount,
                        'refund_error' => null,
                    ])->save();

                    event(new BookingCancelled($transitioned));
                });
            } catch (UniqueConstraintViolationException) {
                // The just-issued refund is already in the ledger (a fast
                // charge.refunded webhook won the race). The booking is already
                // terminal via that path; treat as reconciled, not a failure —
                // must not fall through to the REFUND_FAILED handler below.
                Log::info('Reconcile: issued refund already in ledger; webhook won the race', [
                    'booking_id' => $booking->id,
                    'refund_id' => $refund->id,
                ]);

                return;
            }

            Log::info('Retry refund succeeded', [
                'booking_id' => $booking->id,
                'refund_id' => $refund->id,
            ]);

        } catch (\Throwable $e) {
            $booking = $booking->fresh();
            if (! $booking instanceof Booking) {
                Log::warning('Refund reconciliation: booking not found after refresh — skipping', [
                    'job' => self::class,
                    'attempt' => $attemptNumber,
                    'error' => $e->getMessage(),
                ]);

                return;
            }
            if ($booking->status !== BookingStatus::REFUND_FAILED) {
                $booking = $booking->transitionTo(BookingStatus::REFUND_FAILED);
            }

            $booking->forceFill([
                'refund_error' => "[Attempt {$attemptNumber}] ".$e->getMessage(),
            ])->save();

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
        if (! $errorMessage) {
            return 0;
        }

        if (preg_match('/\[Attempt (\d+)\]/', $errorMessage, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }

    /**
     * Issue a Stripe refund, falling back to the application-level Cashier
     * client when the booking has no associated user (CONC-006).
     */
    private function issueStripeRefund(Booking $booking, int $refundAmount): ?\Stripe\Refund
    {
        $user = $booking->user;
        if ($user instanceof User) {
            return $user->refund(
                $booking->payment_intent_id,
                ['amount' => $refundAmount],
            );
        }

        $stripe = $this->resolveStripeClientFor($booking);
        if ($stripe === null) {
            return null;
        }

        return $stripe->refunds->create([
            'payment_intent' => $booking->payment_intent_id,
            'amount' => $refundAmount,
            'metadata' => [
                'booking_id' => (string) $booking->id,
                'reason' => 'reconcile_orphan_user',
                'kind' => 'reconcile_refund',
            ],
        ]);
    }
}
