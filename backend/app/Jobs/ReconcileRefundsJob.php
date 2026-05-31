<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\BookingStatus;
use App\Enums\RefundStatus;
use App\Events\BookingCancelled;
use App\Models\Booking;
use App\Models\User;
use App\Services\Payment\ExistingRefundMatch;
use App\Services\Payment\StripeRefundEventRecorder;
use App\Services\StripeService;
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

    /**
     * Lazily-resolved Stripe service. Like the recorder, kept out of the
     * constructor so the job stays queue-serializable; it owns the refund-create
     * contract (stable idempotency key + metadata) for the retry path (PAY-01).
     */
    private ?StripeService $stripeService = null;

    private function refundLedger(): StripeRefundEventRecorder
    {
        return $this->refundLedger ??= app(StripeRefundEventRecorder::class);
    }

    private function stripeService(): StripeService
    {
        return $this->stripeService ??= app(StripeService::class);
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
        $retryWaitMinutes = 15;

        // chunkById (not chunk): the claim below mutates updated_at, which is
        // part of the chunk() WHERE clause; offset paging would then skip rows.
        // Keying pages by id is immune to that mutation.
        Booking::query()
            ->with('user')
            ->where('status', BookingStatus::REFUND_FAILED)
            ->where('updated_at', '<', now()->subMinutes($retryWaitMinutes)) // Wait before retry
            ->whereNotNull('payment_intent_id')
            ->whereNull('refund_id') // No successful refund yet
            ->chunkById($batchSize, function ($bookings) use ($maxAttempts, $retryWaitMinutes) {
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

                    // PAY-01 concurrency claim: atomically lease this row so two
                    // workers cannot both issue a refund for the same booking.
                    // The claim bumps updated_at — a racing worker's identical
                    // claim then matches zero rows — and a crashed claim is
                    // re-leased once it ages past the wait window again (the
                    // existing retry window doubles as the reaper). No row lock
                    // is held across the Stripe HTTP call below.
                    if (! $this->claimFailedRefund($booking, $retryWaitMinutes)) {
                        continue;
                    }

                    $fresh = $booking->fresh();
                    if (! $fresh instanceof Booking) {
                        continue;
                    }

                    $this->retryRefund($fresh, $retryCount + 1);
                }
            });
    }

    /**
     * Atomically lease a failed-refund row for retry (PAY-01 concurrency guard).
     *
     * Compare-and-swap on updated_at: the conditional UPDATE only matches while
     * the row is still past the retry window and stamps updated_at = now().
     * PostgreSQL serializes concurrent UPDATEs on the row and re-checks the
     * WHERE against the committed value, so a second worker matches zero rows.
     * Status is intentionally left unchanged (no spurious BookingStatusChanged),
     * and no row lock is held while the caller then talks to Stripe.
     *
     * @return bool true if this worker won the claim
     */
    private function claimFailedRefund(Booking $booking, int $retryWaitMinutes): bool
    {
        $affected = Booking::query()
            ->whereKey($booking->getKey())
            ->where('status', BookingStatus::REFUND_FAILED)
            ->whereNull('refund_id')
            ->where('updated_at', '<', now()->subMinutes($retryWaitMinutes))
            ->update(['updated_at' => now()]);

        return $affected === 1;
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

            $stripe = $this->resolveStripeClientFor($booking);
            if ($stripe === null) {
                // No Stripe client available — skip; do NOT throw.
                Log::warning('ReconcileRefunds: no stripe client for booking, skipping retry', [
                    'booking_id' => $booking->id,
                ]);

                return;
            }

            $recipientEmail = $this->resolveRecipientEmail($booking);
            if ($recipientEmail === null) {
                Log::warning('ReconcileRefunds: no email for booking; refund will be issued without notification', [
                    'booking_id' => $booking->id,
                    'user_id' => $booking->user_id,
                ]);
            }

            // PAY-01 pre-check: never create a second Stripe refund if one
            // already exists for this booking's payment_intent — a prior attempt
            // that timed out after Stripe accepted it, the live cancellation
            // path, or a concurrent worker. Mirrors reconcilePendingRefunds'
            // discovery, run *before* any create.
            $match = $this->findExistingStripeRefundForBooking($booking, $stripe, $refundAmount);

            if ($match->ambiguous) {
                $this->markRefundAmbiguous($booking, $attemptNumber, $match->candidateRefundIds);

                return;
            }

            if ($match->refund instanceof \Stripe\Refund) {
                $this->syncExistingRefund($booking, $match->refund);

                return;
            }

            // No existing refund -> issue one through the centralized service so
            // the create always carries a stable idempotency key + reconcilable
            // metadata (PAY-01). The key is derived from the durable booking +
            // payment_intent, so this and any retry use the SAME key.
            $refund = $this->stripeService()->createReconciliationRefund($stripe, $booking, $refundAmount);

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
     * Pre-check: find an existing Stripe refund on this booking's payment_intent
     * before issuing a new one (PAY-01).
     *
     * Retrieves the PaymentIntent with its charge's refunds expanded — the same
     * shape reconcilePendingRefunds already uses — and classifies candidates:
     *   - identity match: metadata.soleil_refund_event_id == our idempotency key
     *     (a refund a prior reconciler attempt issued).
     *   - fallback match: amount + currency equal and booking_id metadata, when
     *     present, equals this booking (covers the live cancellation paths,
     *     which carry booking_id but not soleil_refund_event_id, and Cashier
     *     refunds, which carry no metadata at all).
     * Only refunds in a usable state (pending / requires_action / succeeded)
     * block a fresh attempt; failed/canceled refunds do not. Exactly one match
     * -> sync it; more than one -> ambiguous (manual reconciliation); none ->
     * safe to create.
     */
    private function findExistingStripeRefundForBooking(
        Booking $booking,
        \Stripe\StripeClient $stripe,
        int $expectedAmount,
    ): ExistingRefundMatch {
        $paymentIntent = $stripe->paymentIntents->retrieve(
            $booking->payment_intent_id,
            ['expand' => ['latest_charge.refunds']],
        );

        $charge = $paymentIntent->latest_charge;
        if (! $charge instanceof \Stripe\Charge) {
            return ExistingRefundMatch::none();
        }

        $refunds = $charge->refunds;
        if (! $refunds instanceof \Stripe\Collection || empty($refunds->data)) {
            return ExistingRefundMatch::none();
        }

        $expectedKey = $this->stripeService()->bookingRefundIdempotencyKey($booking);
        // A refund is always in its charge's currency, so validate candidates
        // against the charge (when Stripe exposes it) rather than a config value
        // that can diverge from the booking's actual settlement currency. If the
        // charge omits a currency, fall back to amount + booking_id alone rather
        // than rejecting an otherwise-valid match.
        $chargeCurrency = strtolower((string) data_get($charge, 'currency', ''));

        $byIdentity = [];
        $byAmount = [];

        foreach ($refunds->data as $candidate) {
            if (! $candidate instanceof \Stripe\Refund) {
                continue;
            }

            if (! in_array((string) $candidate->status, ['pending', 'requires_action', 'succeeded'], true)) {
                continue;
            }

            $metaEventId = (string) data_get($candidate, 'metadata.soleil_refund_event_id', '');
            if ($metaEventId !== '' && $metaEventId === $expectedKey) {
                $byIdentity[] = $candidate;

                continue;
            }

            $metaBookingId = (string) data_get($candidate, 'metadata.booking_id', '');
            $sameBooking = $metaBookingId === '' || $metaBookingId === (string) $booking->id;
            $sameAmount = (int) $candidate->amount === $expectedAmount;
            $sameCurrency = $chargeCurrency === ''
                || strtolower((string) $candidate->currency) === $chargeCurrency;

            if ($sameBooking && $sameAmount && $sameCurrency) {
                $byAmount[] = $candidate;
            }
        }

        // An explicit identity match is authoritative; fall back to amount only
        // when no refund carries our identity stamp.
        $matches = $byIdentity !== [] ? $byIdentity : $byAmount;

        if (count($matches) === 1) {
            return ExistingRefundMatch::match($matches[0]);
        }

        if (count($matches) > 1) {
            return ExistingRefundMatch::ambiguous(
                array_map(static fn (\Stripe\Refund $r): string => (string) $r->id, $matches),
            );
        }

        return ExistingRefundMatch::none();
    }

    /**
     * Sync local state from a pre-existing Stripe refund instead of creating a
     * duplicate (PAY-01).
     *
     * succeeded -> record the ledger row and finalize the booking (same shape as
     * checkPaymentIntentRefunds). pending / requires_action -> adopt the refund
     * id and move to refund_pending so reconcilePendingRefunds finalizes it once
     * Stripe reaches a terminal state; the ledger write is deferred to that
     * terminal pass (mirrors verifyExistingRefund, which never records a pending
     * refund).
     */
    private function syncExistingRefund(Booking $booking, \Stripe\Refund $refund): void
    {
        if ((string) $refund->status === 'succeeded') {
            try {
                DB::transaction(function () use ($booking, $refund) {
                    $this->refundLedger()->record(
                        $booking,
                        (string) $refund->id,
                        (int) $refund->amount,
                        (string) $refund->currency,
                        StripeRefundEventRecorder::reconcileIssueEventKey((string) $refund->id),
                    );

                    $transitioned = $booking->transitionTo(BookingStatus::CANCELLED);
                    $transitioned->forceFill([
                        'refund_id' => $refund->id,
                        'refund_status' => 'succeeded',
                        'refund_amount' => $refund->amount,
                        'refund_error' => null,
                    ])->save();

                    event(new BookingCancelled($transitioned));
                });
            } catch (UniqueConstraintViolationException) {
                Log::info('Reconcile: pre-existing refund already in ledger; webhook won the race', [
                    'booking_id' => $booking->id,
                    'refund_id' => $refund->id,
                ]);

                return;
            }

            Log::info('ReconcileRefunds: synced pre-existing succeeded refund, skipped duplicate create', [
                'booking_id' => $booking->id,
                'refund_id' => $refund->id,
            ]);

            return;
        }

        // pending / requires_action: hand off to the pending reconciler.
        // SH-05 / F-73: normalize before persistence so a raw Stripe status
        // (e.g. 'requires_action') never lands in bookings.refund_status. The
        // booking is entering REFUND_PENDING, so the coherent projection is
        // 'pending'; tryFromStripe maps pending/requires_action -> pending and we
        // fail safe to PENDING for anything unexpected reaching this branch.
        $transitioned = $booking->transitionTo(BookingStatus::REFUND_PENDING);
        $transitioned->forceFill([
            'refund_id' => $refund->id,
            'refund_status' => (RefundStatus::tryFromStripe((string) $refund->status) ?? RefundStatus::PENDING)->value,
        ])->save();

        Log::info('ReconcileRefunds: adopted pre-existing in-flight refund, deferred to pending reconciler', [
            'booking_id' => $booking->id,
            'refund_id' => $refund->id,
            'refund_status' => $refund->status,
        ]);
    }

    /**
     * Record an ambiguous pre-check outcome without creating a refund (PAY-01).
     *
     * The booking stays REFUND_FAILED so an operator can reconcile manually; the
     * attempt marker advances so the row eventually ages out of the retry set
     * via the max-attempts cap instead of re-warning indefinitely.
     *
     * @param  list<string>  $candidateRefundIds
     */
    private function markRefundAmbiguous(Booking $booking, int $attemptNumber, array $candidateRefundIds): void
    {
        Log::warning('ReconcileRefunds: ambiguous existing Stripe refunds; skipping create, manual reconciliation required', [
            'booking_id' => $booking->id,
            'payment_intent_id' => $booking->payment_intent_id,
            'candidate_refund_ids' => $candidateRefundIds,
        ]);

        $booking->forceFill([
            'refund_status' => 'failed',
            'refund_error' => sprintf(
                '[Attempt %d] Ambiguous existing Stripe refunds; manual reconciliation required. Candidates: %s',
                $attemptNumber,
                implode(', ', $candidateRefundIds),
            ),
        ])->save();
    }
}
