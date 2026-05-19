<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\StripeWebhookEvent;
use App\Services\Payment\PaymentIntentApplyOutcome;
use App\Services\Payment\StripePaymentIntentSucceededHandler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Exception\RateLimitException;
use Stripe\StripeClient;
use Throwable;

/**
 * Reconcile Stripe webhook events stuck in 'processing' state.
 *
 * Failure mode this addresses:
 *   The live webhook controller INSERTs a stripe_webhook_events row with
 *   status='processing' before mutating the booking. If the worker/request
 *   dies between the INSERT (StripeWebhookController:54-59) and the final
 *   markProcessed/markFailed call, the row stays 'processing' forever. The
 *   stripe_event_id UNIQUE constraint then absorbs every subsequent Stripe
 *   retry — silently — leaving the booking PENDING with no operator signal.
 *
 * Behavior:
 *   1. Select rows where status='processing' AND created_at < cutoff AND
 *      type IN (StripeWebhookEvent::RECONCILABLE_TYPES). Bounded batch.
 *   2. Atomically CLAIM rows under SELECT ... FOR UPDATE SKIP LOCKED so
 *      concurrent reapers do not double-process. The claim is short — no
 *      Stripe HTTP call happens inside the claim transaction.
 *   3. After the claim transaction commits, fetch the PaymentIntent from
 *      Stripe (the source of truth) and verify status/amount/currency.
 *   4. Apply the idempotent business effect via the shared
 *      StripePaymentIntentSucceededHandler — the same handler the live
 *      webhook controller uses.
 *   5. Mark the webhook event processed on success, failed on unrecoverable
 *      error. Transient errors (rate limit, connection) leave the row
 *      processing so the next run retries, but record the sanitized error
 *      context on the row.
 */
final class ReconcileStuckStripeWebhookEvents extends Command
{
    protected $signature = 'webhook:reconcile-stuck-events
        {--minutes=15 : Minimum event age (in minutes) before reconciliation is attempted}
        {--limit=50 : Maximum events processed per run}';

    protected $description = 'Reconcile Stripe webhook events stuck in processing state by re-checking Stripe and replaying the idempotent business effect.';

    public function handle(StripePaymentIntentSucceededHandler $handler): int
    {
        $minutes = max(1, (int) $this->option('minutes'));
        $limit = max(1, (int) $this->option('limit'));
        $cutoff = now()->subMinutes($minutes);

        $claimed = $this->claimStaleEvents($cutoff, $limit);

        if ($claimed->isEmpty()) {
            $this->info("No stuck stripe_webhook_events older than {$minutes} minute(s).");

            return self::SUCCESS;
        }

        $stripe = $this->resolveStripeClient();

        if ($stripe === null) {
            $this->warn('Stripe client unavailable (cashier.secret empty); claimed events remain in processing.');

            return self::SUCCESS;
        }

        $processed = 0;
        $failed = 0;
        $deferred = 0;

        foreach ($claimed as $event) {
            $outcome = $this->reconcileOne($event, $stripe, $handler);

            match ($outcome) {
                'processed' => $processed++,
                'failed' => $failed++,
                'deferred' => $deferred++,
            };
        }

        $this->info(sprintf(
            'Reconciliation finished: processed=%d failed=%d deferred=%d (claimed=%d)',
            $processed,
            $failed,
            $deferred,
            $claimed->count(),
        ));

        Log::info('stripe_webhook_reconciler.run_complete', [
            'claimed' => $claimed->count(),
            'processed' => $processed,
            'failed' => $failed,
            'deferred' => $deferred,
            'cutoff_minutes' => $minutes,
        ]);

        return self::SUCCESS;
    }

    /**
     * Atomically claim stale processing rows.
     *
     * Concurrency: the SELECT ... FOR UPDATE inside the transaction
     * serializes claim against other reapers; the UPDATE bumps
     * reconcile_started_at and reconcile_attempts so the same row will not
     * be re-claimed mid-flight even if a future run shortens --minutes.
     * The transaction is short and does NOT make any Stripe HTTP call.
     *
     * @return \Illuminate\Support\Collection<int, StripeWebhookEvent>
     */
    private function claimStaleEvents(\Illuminate\Support\Carbon $cutoff, int $limit): \Illuminate\Support\Collection
    {
        return DB::transaction(function () use ($cutoff, $limit): \Illuminate\Support\Collection {
            /** @var \Illuminate\Support\Collection<int, StripeWebhookEvent> $rows */
            $rows = StripeWebhookEvent::query()
                ->staleProcessing($cutoff)
                ->orderBy('created_at')
                ->limit($limit)
                ->lockForUpdate()
                ->get();

            if ($rows->isEmpty()) {
                return $rows;
            }

            StripeWebhookEvent::query()
                ->whereIn('id', $rows->pluck('id')->all())
                ->update([
                    'reconcile_started_at' => now(),
                    'reconcile_attempts' => DB::raw('reconcile_attempts + 1'),
                ]);

            return $rows->fresh();
        });
    }

    /**
     * Reconcile a single claimed event. Returns 'processed', 'failed', or
     * 'deferred' (transient — left in processing for the next run).
     */
    private function reconcileOne(
        StripeWebhookEvent $event,
        StripeClient $stripe,
        StripePaymentIntentSucceededHandler $handler,
    ): string {
        $paymentIntentId = $event->paymentIntentId();

        if ($paymentIntentId === null) {
            $event->markFailed('webhook payload missing data.object.id');

            Log::warning('stripe_webhook_reconciler.payload_missing_id', [
                'stripe_event_id' => $event->stripe_event_id,
                'type' => $event->type,
            ]);

            return 'failed';
        }

        if ($event->type !== 'payment_intent.succeeded') {
            // Defense in depth: scopeStaleProcessing already filters by
            // RECONCILABLE_TYPES, but make the per-event guard explicit so a
            // future expansion of the scope cannot silently land us in a
            // dispatch-by-string codepath.
            $event->markFailed('unsupported event type: '.$event->type);

            return 'failed';
        }

        try {
            $paymentIntent = $stripe->paymentIntents->retrieve($paymentIntentId);
        } catch (InvalidRequestException $e) {
            // Permanent: Stripe says the PaymentIntent does not exist. The
            // webhook event references a PaymentIntent we cannot verify, so
            // mark failed with explicit context for human review.
            $event->markFailed($e);

            Log::warning('stripe_webhook_reconciler.payment_intent_not_found', [
                'stripe_event_id' => $event->stripe_event_id,
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage(),
            ]);

            return 'failed';
        } catch (RateLimitException|ApiConnectionException $e) {
            // Transient: leave the event in processing so the next reaper
            // run retries; the error column carries the last-failure context.
            $event->recordReconciliationError($e);

            Log::warning('stripe_webhook_reconciler.transient_stripe_error', [
                'stripe_event_id' => $event->stripe_event_id,
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage(),
            ]);

            return 'deferred';
        } catch (ApiErrorException $e) {
            // Other Stripe API errors (auth, server) — treat as transient by
            // default; failing one row should not poison the queue, and
            // operators see the error column populated by the next run.
            $event->recordReconciliationError($e);

            Log::error('stripe_webhook_reconciler.stripe_api_error', [
                'stripe_event_id' => $event->stripe_event_id,
                'payment_intent_id' => $paymentIntentId,
            ]);

            return 'deferred';
        }

        if (($paymentIntent->status ?? null) !== 'succeeded') {
            $event->markFailed(sprintf(
                'PaymentIntent %s status is %s, not succeeded',
                $paymentIntentId,
                (string) ($paymentIntent->status ?? 'unknown'),
            ));

            return 'failed';
        }

        if (! $this->paymentIntentMatchesBooking($paymentIntent, $paymentIntentId, $event)) {
            return 'failed';
        }

        try {
            $outcome = $handler->applyToBooking($paymentIntentId);
        } catch (Throwable $e) {
            $event->markFailed($e);

            Log::error('stripe_webhook_reconciler.business_effect_failed', [
                'stripe_event_id' => $event->stripe_event_id,
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage(),
            ]);

            return 'failed';
        }

        return $this->applyOutcome($event, $outcome, $paymentIntentId);
    }

    /**
     * Verify the PaymentIntent's metadata matches the local booking the
     * webhook event refers to. Defense in depth against PaymentIntent
     * confusion (e.g., webhook payload tampering — though Cashier signature
     * verification catches that earlier — or operator error).
     */
    private function paymentIntentMatchesBooking(
        object $paymentIntent,
        string $paymentIntentId,
        StripeWebhookEvent $event,
    ): bool {
        $booking = Booking::query()
            ->where('payment_intent_id', $paymentIntentId)
            ->first();

        if ($booking === null) {
            // No local booking: the handler will return BookingNotFound and
            // we mark processed (an orphan webhook is operationally benign).
            return true;
        }

        $remoteAmount = (int) ($paymentIntent->amount ?? 0);
        $remoteCurrency = strtolower((string) ($paymentIntent->currency ?? ''));
        $localAmount = (int) $booking->amount;
        $localCurrency = strtolower((string) config('cashier.currency', 'vnd'));

        if ($localAmount > 0 && $remoteAmount !== $localAmount) {
            $event->markFailed(sprintf(
                'PaymentIntent %s amount mismatch: remote=%d local=%d',
                $paymentIntentId,
                $remoteAmount,
                $localAmount,
            ));

            return false;
        }

        if ($remoteCurrency !== '' && $remoteCurrency !== $localCurrency) {
            $event->markFailed(sprintf(
                'PaymentIntent %s currency mismatch: remote=%s local=%s',
                $paymentIntentId,
                $remoteCurrency,
                $localCurrency,
            ));

            return false;
        }

        return true;
    }

    private function applyOutcome(
        StripeWebhookEvent $event,
        PaymentIntentApplyOutcome $outcome,
        string $paymentIntentId,
    ): string {
        match ($outcome) {
            PaymentIntentApplyOutcome::Confirmed => Log::info('stripe_webhook_reconciler.booking_confirmed', [
                'stripe_event_id' => $event->stripe_event_id,
                'payment_intent_id' => $paymentIntentId,
            ]),
            PaymentIntentApplyOutcome::AlreadyConfirmed => Log::info('stripe_webhook_reconciler.already_confirmed', [
                'stripe_event_id' => $event->stripe_event_id,
                'payment_intent_id' => $paymentIntentId,
            ]),
            PaymentIntentApplyOutcome::BookingNotFound => Log::warning('stripe_webhook_reconciler.booking_not_found', [
                'stripe_event_id' => $event->stripe_event_id,
                'payment_intent_id' => $paymentIntentId,
            ]),
            PaymentIntentApplyOutcome::InvalidState => null,
        };

        if ($outcome === PaymentIntentApplyOutcome::InvalidState) {
            $event->markFailed(sprintf(
                'Booking for PaymentIntent %s is not in PENDING/CONFIRMED state; manual review required',
                $paymentIntentId,
            ));

            return 'failed';
        }

        $event->update([
            'reconcile_finished_at' => now(),
        ]);

        $event->markProcessed();

        return 'processed';
    }

    private function resolveStripeClient(): ?StripeClient
    {
        // Resolve via the container WITHOUT contextual parameters, otherwise
        // Laravel's $needsContextualBuild flag bypasses the instance() cache
        // (which is how tests inject a fake via $this->app->instance(...))
        // and falls through to the bind closure that constructs a real
        // StripeClient. The container's bind closure in AppServiceProvider
        // already reads cashier.secret, so calling without params gives the
        // same production behavior as Cashier::stripe().
        if (blank(config('cashier.secret')) && ! app()->resolved(StripeClient::class)) {
            // Dev/test environment without a Stripe key AND no test override.
            // Make the reaper a no-op rather than throwing inside the SDK.
            return null;
        }

        /** @var StripeClient $client */
        $client = app(StripeClient::class);

        return $client;
    }
}
