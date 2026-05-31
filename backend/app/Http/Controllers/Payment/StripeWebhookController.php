<?php

namespace App\Http\Controllers\Payment;

use App\Enums\BookingStatus;
use App\Enums\PaymentPolicy;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Booking;
use App\Models\StripeWebhookEvent;
use App\Services\Payment\PaymentIntentApplyOutcome;
use App\Services\Payment\StripePaymentIntentSucceededHandler;
use App\Services\Payment\StripeRefundEventRecorder;
use App\Services\StripeService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

/**
 * Stripe webhook handler — extends Cashier's built-in controller.
 *
 * Signature verification is performed explicitly in handleWebhook() rather than
 * via Cashier's optional VerifyWebhookSignature middleware. Cashier registers
 * that middleware ONLY when cashier.webhook.secret is truthy, so an empty/unset
 * secret would silently disable verification and accept every unsigned webhook —
 * an unauthenticated path into booking confirmation and refund state changes.
 * We fail closed instead: a missing secret rejects the request, and every
 * malformed or forged request returns a controlled 400 — never an unhandled 500
 * from invalid external input.
 *
 * To add custom event handling, create methods named:
 *   handle{EventType}(array $payload): Response
 * where EventType is the Stripe event in StudlyCase.
 * e.g., handlePaymentIntentSucceeded for payment_intent.succeeded
 */
class StripeWebhookController extends CashierWebhookController
{
    /**
     * Deliberately does NOT call parent::__construct(): Cashier's constructor
     * registers VerifyWebhookSignature only when cashier.webhook.secret is set
     * (rejecting with 403). Verification is owned by handleWebhook() so a
     * misconfigured/empty secret can never silently disable it.
     */
    public function __construct() {}

    /**
     * Verify the Stripe signature, then dispatch to the typed event handlers.
     *
     * Fail-closed contract:
     *   - secret not configured        -> 500 (server misconfiguration, not attacker input)
     *   - missing Stripe-Signature     -> 400
     *   - malformed JSON payload       -> 400
     *   - signature mismatch / expired -> 400
     *
     * On success, delegates to Cashier's dispatcher (parent::handleWebhook),
     * which routes to handlePaymentIntentSucceeded / handleChargeRefunded / etc.
     */
    public function handleWebhook(Request $request): Response
    {
        $secret = config('cashier.webhook.secret');

        if (! is_string($secret) || $secret === '') {
            Log::error('Stripe webhook rejected: STRIPE_WEBHOOK_SECRET is not configured');

            return response()->json(['message' => 'Webhook signature verification is not configured.'], 500);
        }

        $signature = $request->header('Stripe-Signature');

        if (! is_string($signature) || $signature === '') {
            return response()->json(['message' => 'Missing Stripe-Signature header.'], 400);
        }

        try {
            Webhook::constructEvent(
                $request->getContent(),
                $signature,
                $secret,
                (int) config('cashier.webhook.tolerance', 300),
            );
        } catch (UnexpectedValueException $e) {
            Log::warning('Stripe webhook rejected: invalid payload', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Invalid Stripe webhook payload.'], 400);
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe webhook rejected: invalid signature', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Invalid Stripe webhook signature.'], 400);
        }

        return parent::handleWebhook($request);
    }

    /**
     * Handle payment_intent.succeeded webhook.
     *
     * Confirms the associated booking when payment succeeds. Idempotent: if
     * the booking is already confirmed, no-op. The business effect is
     * delegated to StripePaymentIntentSucceededHandler so that this HTTP
     * path and the webhook:reconcile-stuck-events reaper share a single
     * audited entry point into the booking state machine.
     */
    protected function handlePaymentIntentSucceeded(array $payload): JsonResponse
    {
        $stripeEventId = $payload['id'] ?? null;
        $eventType = $payload['type'] ?? 'payment_intent.succeeded';
        $paymentIntent = $payload['data']['object'] ?? [];
        $paymentIntentId = $paymentIntent['id'] ?? null;

        if (! $stripeEventId || ! $paymentIntentId) {
            Log::warning('Stripe webhook: payment_intent.succeeded missing payment_intent_id');

            return response()->json(['handled' => false], 400);
        }

        // BL-3 idempotency: INSERT-first against the stripe_event_id UNIQUE
        // constraint. A SELECT-FOR-UPDATE-then-INSERT pattern would race
        // (two readers both find "no row" and both run side effects before
        // either INSERT lands); the constraint itself is the linearization
        // point. Duplicate delivery throws here and returns 200 immediately.
        // Locked in by tests/Feature/Payment/StripeWebhookIdempotencyTest.php.
        try {
            $webhookEvent = DB::transaction(fn () => StripeWebhookEvent::create([
                'stripe_event_id' => $stripeEventId,
                'type' => $eventType,
                'status' => 'processing',
                'payload' => $payload,
            ]));
        } catch (UniqueConstraintViolationException) {
            return response()->json(['handled' => true], 200);
        }

        Log::info('Stripe webhook: payment_intent.succeeded', [
            'payment_intent_id' => $paymentIntentId,
            'stripe_event_id' => $stripeEventId,
        ]);

        try {
            $outcome = app(StripePaymentIntentSucceededHandler::class)
                ->applyToBooking($paymentIntent);
        } catch (\Throwable $e) {
            Log::error('Stripe webhook: failed to confirm booking', [
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage(),
            ]);

            $webhookEvent->markFailed($e);

            return response()->json(['handled' => false], 500);
        }

        match ($outcome) {
            PaymentIntentApplyOutcome::Confirmed => Log::info('Stripe webhook: booking confirmed via payment', [
                'payment_intent_id' => $paymentIntentId,
            ]),
            PaymentIntentApplyOutcome::AlreadyConfirmed => Log::info('Stripe webhook: booking already confirmed', [
                'payment_intent_id' => $paymentIntentId,
            ]),
            PaymentIntentApplyOutcome::BookingNotFound => Log::warning('Stripe webhook: no booking found for payment_intent', [
                'payment_intent_id' => $paymentIntentId,
            ]),
            PaymentIntentApplyOutcome::InvalidState => Log::info('Stripe webhook: booking in non-pending state', [
                'payment_intent_id' => $paymentIntentId,
            ]),
        };

        // Preserves the pre-extraction HTTP behavior: every non-throwing
        // outcome (including AlreadyConfirmed and InvalidState) returns 200 so
        // Stripe stops retrying. The reaper applies a stricter rule for
        // InvalidState because its scope is operational health, not HTTP
        // ack semantics.
        $webhookEvent->markProcessed();

        return response()->json(['handled' => true]);
    }

    /**
     * Handle charge.refunded webhook.
     *
     * Updates booking refund status when Stripe processes a refund.
     * Idempotent: stripe_refund_events.stripe_refund_id is the durable replay guard.
     */
    protected function handleChargeRefunded(array $payload): JsonResponse
    {
        $stripeEventId = $payload['id'] ?? null;
        $eventType = $payload['type'] ?? 'charge.refunded';
        $chargeObject = $payload['data']['object'] ?? [];
        $chargeId = $chargeObject['id'] ?? null;
        $paymentIntentId = $chargeObject['payment_intent'] ?? null;

        if (! $stripeEventId || ! $paymentIntentId) {
            Log::warning('Stripe webhook: charge.refunded missing identifiers', [
                'stripe_event_id' => $stripeEventId,
                'charge_id' => $chargeId,
                'payment_intent_id' => $paymentIntentId,
            ]);

            return response()->json(['handled' => false], 400);
        }

        // Extract refund details from the charge's refunds list. Validate the
        // payload shape BEFORE claiming the webhook event, so a malformed event
        // returns 400 without leaving a dangling 'processing' row.
        $refunds = $chargeObject['refunds']['data'] ?? [];
        $latestRefund = $refunds[0] ?? null;

        if (! $latestRefund) {
            Log::warning('Stripe webhook: charge.refunded but no refund data', [
                'payment_intent_id' => $paymentIntentId,
            ]);

            return response()->json(['handled' => true]);
        }

        $refundId = $latestRefund['id'] ?? null;
        $refundStatus = $latestRefund['status'] ?? 'unknown';
        $refundAmount = (int) ($chargeObject['amount_refunded'] ?? $latestRefund['amount'] ?? 0);
        $currency = $chargeObject['currency'] ?? null;

        if (! $refundId || ! $currency) {
            Log::warning('Stripe webhook: charge.refunded missing refund metadata', [
                'stripe_event_id' => $stripeEventId,
                'charge_id' => $chargeId,
                'refund_id' => $refundId,
                'currency' => $currency,
            ]);

            return response()->json(['handled' => false], 400);
        }

        // BL-3 idempotency: INSERT-first claim on stripe_webhook_events.stripe_event_id,
        // identical to handlePaymentIntentSucceeded. A duplicate delivery throws here
        // and short-circuits to 200 (already processed) before any side effect runs.
        // The stripe_refund_events.stripe_refund_id UNIQUE is retained underneath as
        // the cross-source ledger dedup (synchronous cancellation / reconciler).
        try {
            $webhookEvent = DB::transaction(fn () => StripeWebhookEvent::create([
                'stripe_event_id' => $stripeEventId,
                'type' => $eventType,
                'status' => 'processing',
                'payload' => $payload,
            ]));
        } catch (UniqueConstraintViolationException) {
            return response()->json(['handled' => true], 200);
        }

        Log::info('Stripe webhook: charge.refunded', [
            'charge_id' => $chargeId,
            'payment_intent_id' => $paymentIntentId,
        ]);

        try {
            $result = DB::transaction(function () use (
                $paymentIntentId,
                $stripeEventId,
                $refundId,
                $refundStatus,
                $refundAmount,
                $currency
            ): array {
                $booking = Booking::where('payment_intent_id', $paymentIntentId)
                    ->lockForUpdate()
                    ->first();

                if (! $booking) {
                    return ['status' => 'missing_booking'];
                }

                // SH-05 / F-73: normalize the raw Stripe refund status into the
                // closed internal {pending, succeeded, failed} set BEFORE it can
                // touch bookings.refund_status. Fail closed — an unrecognized
                // Stripe status is never persisted as a raw provider string.
                $normalizedRefundStatus = RefundStatus::tryFromStripe($refundStatus);

                if ($normalizedRefundStatus === null) {
                    return [
                        'status' => 'unknown_refund_status',
                        'booking_id' => $booking->id,
                        'raw_status' => $refundStatus,
                    ];
                }

                $targetStatus = $normalizedRefundStatus === RefundStatus::SUCCEEDED
                    ? BookingStatus::CANCELLED
                    : BookingStatus::REFUND_FAILED;

                // Explicitly guard illegal state-machine transitions. A
                // charge.refunded carrying a non-succeeded refund for a booking
                // that never entered the refund flow (e.g. a still-CONFIRMED
                // booking, or an already-terminal CANCELLED one) would otherwise
                // attempt the forbidden CONFIRMED/CANCELLED -> REFUND_FAILED
                // transition, which transitionTo() throws on. We must NOT let that
                // throw fall through to a catch-all that 500s a *permanent*
                // business-state error into an endless Stripe retry, nor mutate
                // the booking. Report it as an invalid state; the caller acks 200
                // and leaves the booking untouched.
                if ($booking->status !== $targetStatus
                    && ! $booking->status->canTransitionTo($targetStatus)) {
                    return [
                        'status' => 'invalid_transition',
                        'booking_id' => $booking->id,
                        'from' => $booking->status->value,
                        'to' => $targetStatus->value,
                    ];
                }

                // Ledger first (authoritative refund history). UNIQUE(stripe_refund_id)
                // dedups against the synchronous cancellation path and the reconciler;
                // a duplicate throws UniqueConstraintViolationException, caught below.
                app(StripeRefundEventRecorder::class)->record(
                    $booking,
                    (string) $refundId,
                    $refundAmount,
                    (string) $currency,
                    (string) $stripeEventId,
                );

                if ($booking->status !== $targetStatus) {
                    $booking = $booking->transitionTo($targetStatus);
                }

                // bookings.refund_id is a latest-refund pointer for operational
                // lookup, NOT a refund ledger. Under partial refunds one charge
                // can produce multiple refunds; this column is overwritten by
                // the most recent one. Refund history, total refunded amount,
                // full-refund detection, and reconciliation MUST read from
                // stripe_refund_events. See docs/agents/ARCHITECTURE_FACTS.md
                // §bookings.refund_id semantics.
                $booking->forceFill([
                    'refund_id' => $refundId,
                    'refund_status' => $normalizedRefundStatus->value,
                    'refund_amount' => $refundAmount,
                    'refund_error' => $normalizedRefundStatus === RefundStatus::FAILED
                        ? 'Refund failed on Stripe'
                        : null,
                ])->save();

                return [
                    'status' => 'processed',
                    'booking_id' => $booking->id,
                ];
            });
        } catch (UniqueConstraintViolationException) {
            // The refund is already in the ledger: the synchronous cancellation
            // path or the reconciler won the race and owns the booking projection.
            // Idempotent replay — ack and mark the event processed.
            Log::info('Stripe webhook: refund event replay skipped (already in ledger)', [
                'stripe_event_id' => $stripeEventId,
                'refund_id' => $refundId,
            ]);

            $webhookEvent->markProcessed();

            return response()->json(['handled' => true]);
        } catch (\Throwable $e) {
            // Unexpected DB/runtime failure: DO NOT swallow into a 200. Mark the
            // event failed and return 500 so Stripe retries and the row surfaces
            // to operators. The booking (if any) is left untouched — the
            // transaction rolled back — and ReconcileRefundsJob remains the
            // durable recovery path for the refund projection + ledger.
            Log::error('Stripe webhook: failed to update refund status', [
                'payment_intent_id' => $paymentIntentId,
                'refund_id' => $refundId,
                'error' => $e->getMessage(),
            ]);

            $webhookEvent->markFailed($e);

            return response()->json(['handled' => false], 500);
        }

        if ($result['status'] === 'missing_booking') {
            Log::warning('Stripe webhook: no booking found for payment_intent', [
                'payment_intent_id' => $paymentIntentId,
            ]);

            $webhookEvent->markProcessed();

            return response()->json(['handled' => true]);
        }

        if ($result['status'] === 'invalid_transition') {
            // Permanent business-state error: a retry never makes an illegal
            // transition legal, so mark the event failed (terminal — charge.refunded
            // is not a reconcilable webhook type) for operator visibility and ack
            // 200 to stop the retry storm. The booking is NOT mutated.
            Log::warning('Stripe webhook: charge.refunded illegal state transition ignored', [
                'stripe_event_id' => $stripeEventId,
                'booking_id' => $result['booking_id'],
                'from' => $result['from'],
                'to' => $result['to'],
                'refund_id' => $refundId,
                'refund_status' => $refundStatus,
            ]);

            $webhookEvent->markFailed(sprintf(
                'Illegal booking transition %s -> %s ignored for refund %s (charge.refunded)',
                $result['from'],
                $result['to'],
                $refundId,
            ));

            return response()->json(['handled' => true]);
        }

        if ($result['status'] === 'unknown_refund_status') {
            // SH-05 / F-73 fail-closed: Stripe sent a refund status outside the
            // closed internal set. A retry never reclassifies it, so this is a
            // permanent error — mark the event failed for operator visibility and
            // ack 200 to stop the retry storm. The booking is NOT mutated and no
            // raw provider status leaks into bookings.refund_status.
            Log::warning('Stripe webhook: charge.refunded carried an unrecognized refund status; not persisted', [
                'stripe_event_id' => $stripeEventId,
                'booking_id' => $result['booking_id'],
                'refund_id' => $refundId,
                'raw_refund_status' => $result['raw_status'],
            ]);

            $webhookEvent->markFailed(sprintf(
                'Unrecognized Stripe refund status "%s" ignored for refund %s (charge.refunded)',
                (string) $result['raw_status'],
                $refundId,
            ));

            return response()->json(['handled' => true]);
        }

        Log::info('Stripe webhook: booking refund status updated', [
            'booking_id' => $result['booking_id'],
            'refund_id' => $refundId,
            'refund_status' => $refundStatus,
            'refund_amount' => $refundAmount,
        ]);

        $webhookEvent->markProcessed();

        return response()->json(['handled' => true]);
    }

    /**
     * Handle payment_intent.payment_failed webhook.
     *
     * Logs the failure. Booking remains in pending state for retry.
     *
     * The handler is idempotent under at-least-once delivery via the same
     * INSERT-first guard on `stripe_webhook_events.stripe_event_id` used by
     * the succeeded path. Logging is treated as a side effect: a duplicate
     * Stripe delivery short-circuits at the UNIQUE constraint before the
     * warning log fires, so log volume stays bounded and any future
     * business logic added here inherits the guarantee.
     */
    protected function handlePaymentIntentPaymentFailed(array $payload): JsonResponse
    {
        $stripeEventId = $payload['id'] ?? null;
        $eventType = $payload['type'] ?? 'payment_intent.payment_failed';
        $paymentIntent = $payload['data']['object'] ?? [];
        $paymentIntentId = $paymentIntent['id'] ?? null;
        $failureMessage = $paymentIntent['last_payment_error']['message'] ?? 'Unknown';

        if (! $stripeEventId) {
            // Malformed payload (no event id). Preserve the historical
            // always-2xx posture on payment_failed so Stripe does not retry
            // a payload we cannot reason about — duplicate-delivery defense
            // is moot when we have no identifier to dedupe against.
            Log::warning('Stripe webhook: payment_intent.payment_failed missing event id', [
                'payment_intent_id' => $paymentIntentId,
            ]);

            return response()->json(['handled' => true]);
        }

        // BL-3 idempotency: constraint-first INSERT against the stripe_event_id
        // UNIQUE. A SELECT-then-INSERT would race; the constraint itself is the
        // linearization point. DB::transaction provides a SAVEPOINT so a
        // duplicate INSERT under a surrounding test transaction rolls back to
        // a known state instead of aborting it (PG 25P02). Mirrors
        // handlePaymentIntentSucceeded — see StripeWebhookIdempotencyTest.
        try {
            $webhookEvent = DB::transaction(fn () => StripeWebhookEvent::create([
                'stripe_event_id' => $stripeEventId,
                'type' => $eventType,
                'status' => 'processing',
                'payload' => $payload,
            ]));
        } catch (UniqueConstraintViolationException) {
            return response()->json(['handled' => true]);
        }

        Log::warning('Stripe webhook: payment_intent.payment_failed', [
            'payment_intent_id' => $paymentIntentId,
            'failure_message' => $failureMessage,
            'stripe_event_id' => $stripeEventId,
        ]);

        try {
            if ($paymentIntentId) {
                $booking = Booking::where('payment_intent_id', $paymentIntentId)->first();

                if ($booking && $booking->status === BookingStatus::PENDING) {
                    app(StripeService::class)->assertPaymentIntentMatchesBooking($paymentIntent, $booking);

                    $booking->forceFill([
                        'payment_status' => PaymentStatus::FAILED,
                        'payment_failed_reason' => mb_substr((string) $failureMessage, 0, 1000),
                        'amount_capturable' => (int) data_get($paymentIntent, 'amount_capturable', 0),
                        'amount_received' => (int) data_get($paymentIntent, 'amount_received', 0),
                    ])->save();

                    Log::info('Stripe webhook: payment failed for pending booking', [
                        'booking_id' => $booking->id,
                    ]);
                }
            }

            $webhookEvent->markProcessed();
        } catch (\Throwable $e) {
            $webhookEvent->markFailed($e);

            return response()->json(['handled' => false], 500);
        }

        return response()->json(['handled' => true]);
    }

    /**
     * Handle payment_intent.canceled webhook.
     */
    protected function handlePaymentIntentCanceled(array $payload): JsonResponse
    {
        $stripeEventId = $payload['id'] ?? null;
        $eventType = $payload['type'] ?? 'payment_intent.canceled';
        $paymentIntent = $payload['data']['object'] ?? [];
        $paymentIntentId = $paymentIntent['id'] ?? null;

        if (! $stripeEventId || ! $paymentIntentId) {
            Log::warning('Stripe webhook: payment_intent.canceled missing identifiers', [
                'stripe_event_id' => $stripeEventId,
                'payment_intent_id' => $paymentIntentId,
            ]);

            return response()->json(['handled' => false], 400);
        }

        try {
            $webhookEvent = DB::transaction(fn () => StripeWebhookEvent::create([
                'stripe_event_id' => $stripeEventId,
                'type' => $eventType,
                'status' => 'processing',
                'payload' => $payload,
            ]));
        } catch (UniqueConstraintViolationException) {
            return response()->json(['handled' => true]);
        }

        try {
            DB::transaction(function () use ($paymentIntent, $paymentIntentId): void {
                $booking = Booking::where('payment_intent_id', $paymentIntentId)
                    ->lockForUpdate()
                    ->first();

                if ($booking === null) {
                    return;
                }

                app(StripeService::class)->assertPaymentIntentMatchesBooking($paymentIntent, $booking);

                $updates = [
                    'payment_status' => PaymentStatus::CANCELLED,
                    'payment_failed_reason' => 'PaymentIntent canceled on Stripe',
                    'amount_capturable' => (int) data_get($paymentIntent, 'amount_capturable', 0),
                    'amount_received' => (int) data_get($paymentIntent, 'amount_received', 0),
                ];

                if ($booking->status === BookingStatus::PENDING) {
                    $updates['status'] = BookingStatus::CANCELLED;
                    $updates['cancellation_reason'] = 'payment_intent_canceled';
                    $updates['cancelled_at'] = now();
                }

                $booking->forceFill($updates)->save();
            });

            $webhookEvent->markProcessed();
        } catch (\Throwable $e) {
            $webhookEvent->markFailed($e);

            return response()->json(['handled' => false], 500);
        }

        return response()->json(['handled' => true]);
    }

    /**
     * Handle payment_intent.amount_capturable_updated webhook.
     *
     * This only authorizes bookings that explicitly opted into manual capture.
     * Soleil v1 defaults to prepaid automatic capture, so this path is a
     * guarded compatibility hook rather than a new capture surface.
     */
    protected function handlePaymentIntentAmountCapturableUpdated(array $payload): JsonResponse
    {
        $stripeEventId = $payload['id'] ?? null;
        $eventType = $payload['type'] ?? 'payment_intent.amount_capturable_updated';
        $paymentIntent = $payload['data']['object'] ?? [];
        $paymentIntentId = $paymentIntent['id'] ?? null;

        if (! $stripeEventId || ! $paymentIntentId) {
            Log::warning('Stripe webhook: amount_capturable_updated missing identifiers', [
                'stripe_event_id' => $stripeEventId,
                'payment_intent_id' => $paymentIntentId,
            ]);

            return response()->json(['handled' => false], 400);
        }

        try {
            $webhookEvent = DB::transaction(fn () => StripeWebhookEvent::create([
                'stripe_event_id' => $stripeEventId,
                'type' => $eventType,
                'status' => 'processing',
                'payload' => $payload,
            ]));
        } catch (UniqueConstraintViolationException) {
            return response()->json(['handled' => true]);
        }

        try {
            $booking = Booking::where('payment_intent_id', $paymentIntentId)->first();

            if ($booking !== null) {
                app(StripeService::class)->assertPaymentIntentMatchesBooking($paymentIntent, $booking);

                if ($booking->payment_policy === PaymentPolicy::AUTHORIZE_THEN_CAPTURE) {
                    DB::transaction(function () use ($booking, $paymentIntent): void {
                        $locked = Booking::query()
                            ->whereKey($booking->id)
                            ->lockForUpdate()
                            ->firstOrFail();

                        $locked->forceFill([
                            'payment_status' => PaymentStatus::AUTHORIZED,
                            'amount_capturable' => (int) data_get($paymentIntent, 'amount_capturable', 0),
                            'amount_received' => (int) data_get($paymentIntent, 'amount_received', 0),
                            'authorized_at' => $locked->authorized_at ?? now(),
                            'payment_failed_reason' => null,
                        ])->save();
                    });
                }
            }

            $webhookEvent->markProcessed();
        } catch (\Throwable $e) {
            $webhookEvent->markFailed($e);

            throw $e;
        }

        return response()->json(['handled' => true]);
    }

    /**
     * Handle unhandled webhook events (log only).
     */
    protected function missingMethod($parameters = [])
    {
        return response()->json(['handled' => false]);
    }
}
