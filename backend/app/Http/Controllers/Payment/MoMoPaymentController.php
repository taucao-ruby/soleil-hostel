<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payment;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\MoMoWebhookEvent;
use App\Services\MoMoService;
use App\Services\Payment\MoMoIpnHandler;
use App\Services\Payment\MoMoIpnOutcome;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * HTTP surface for the additive MoMo (sandbox) payment path.
 *
 * `create` is an authenticated guest action (the 'pay' policy authorizes it).
 * `ipn` is a PUBLIC server→server callback whose ONLY authentication is the MoMo
 * HMAC signature — no session, no Sanctum token, no CSRF. The controller therefore
 * verifies the signature itself and fails closed on anything it cannot prove:
 * a missing secret is a 500 (server misconfiguration), a malformed/forged request
 * is a 400, and confirmation only ever runs on a signature-verified payload via the
 * audited MoMoIpnHandler → BookingService::markPaidAndConfirm entry point.
 */
final class MoMoPaymentController extends Controller
{
    public function __construct(
        private readonly MoMoService $momoService,
        private readonly MoMoIpnHandler $ipnHandler,
    ) {}

    /**
     * Start a MoMo payment for a payable booking. Mirrors
     * BookingPaymentController::createPaymentIntent, minus any payment-intent
     * reuse branch: MoMo stores nothing on `bookings` — each create mints a fresh
     * order and confirmation idempotency lives in the IPN ledger + handler.
     */
    public function create(Booking $booking): JsonResponse
    {
        $this->authorize('pay', $booking);

        try {
            $this->assertBookingPayable($booking);
        } catch (RuntimeException $e) {
            return $this->paymentRejectedResponse($e->getMessage());
        }

        $started = $this->momoService->createPayment($booking);

        return response()->json([
            'success' => true,
            'data' => [
                'payUrl' => $started->payUrl,
                'qrCodeUrl' => $started->qrCodeUrl,
                'deeplink' => $started->deeplink,
                'orderId' => $started->orderId,
            ],
        ]);
    }

    /**
     * Public MoMo IPN (server→server payment notification) endpoint. Fail-closed
     * ladder — each rung returns before the next runs:
     *
     *   F1 secret not configured        -> 500 (server misconfiguration, not attacker input)
     *   F2 malformed JSON body          -> 400
     *   F3 bad/missing signature        -> 400 (constant-time verify, fail-closed)
     *   F4 duplicate (order_id,trans_id)-> 204 (INSERT-first dedup is the linearization point)
     *   F5 handler error                -> 500 (so MoMo retries)
     *   F6 outcome -> record + 204 ack
     *
     * No authorize()/auth middleware: the signature IS the auth (the public route
     * is T7). The secret and signature are never logged or echoed.
     */
    public function ipn(Request $request): Response
    {
        // F1 — server misconfiguration, distinct from attacker input.
        $secret = config('services.momo.secret_key');

        if (! is_string($secret) || $secret === '') {
            Log::error('MoMo IPN rejected: MOMO_SECRET_KEY is not configured');

            return response()->json(['message' => 'MoMo IPN signature verification is not configured.'], 500);
        }

        // F2 — never feed a non-array to the verifier.
        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload)) {
            Log::warning('MoMo IPN rejected: malformed JSON body');

            return response()->json(['message' => 'Invalid MoMo IPN payload.'], 400);
        }

        // F3 — nothing downstream runs on an unverified payload.
        if (! $this->momoService->verifyIpnSignature($payload)) {
            Log::warning('MoMo IPN rejected: invalid signature', [
                'order_id' => (string) data_get($payload, 'orderId', ''),
            ]);

            return response()->json(['message' => 'Invalid MoMo IPN signature.'], 400);
        }

        // F4 — INSERT-first claim against UNIQUE(order_id, trans_id). A replayed
        // delivery throws here and acks 204 before any side effect runs. trans_id
        // is coerced to '' (never null) so the dedup key stays non-null.
        try {
            $event = DB::transaction(fn () => MoMoWebhookEvent::create([
                'order_id' => (string) data_get($payload, 'orderId', ''),
                'request_id' => (string) data_get($payload, 'requestId', ''),
                'trans_id' => (string) data_get($payload, 'transId', ''),
                'type' => 'momo.ipn',
                'status' => 'processing',
                'result_code' => (int) data_get($payload, 'resultCode'),
                'payload' => $payload,
            ]));
        } catch (UniqueConstraintViolationException) {
            return response()->noContent();
        }

        // F5 — a genuine downstream failure leaves the row 'failed' and 500s so
        // MoMo retries. markFailed sanitizes the stored message.
        try {
            $outcome = $this->ipnHandler->applyToBooking($payload);
        } catch (\Throwable $e) {
            Log::error('MoMo IPN: handler failed', [
                'order_id' => (string) data_get($payload, 'orderId', ''),
                'error' => $e->getMessage(),
            ]);

            $event->markFailed($e);

            return response()->json(['message' => 'MoMo IPN processing failed.'], 500);
        }

        // F6 — every non-throwing outcome acks 204 so MoMo stops retrying.
        // AmountMismatch is signature-valid but permanent (a retry won't fix it)
        // and T5 did NOT confirm the booking — record it failed for operator
        // review, still 204 to stop the retry storm.
        match ($outcome) {
            MoMoIpnOutcome::Confirmed,
            MoMoIpnOutcome::AlreadyConfirmed,
            MoMoIpnOutcome::BookingNotFound,
            MoMoIpnOutcome::InvalidState => $event->markProcessed(),
            MoMoIpnOutcome::AmountMismatch => $event->markFailed(
                'MoMo IPN amount mismatch for order '.(string) data_get($payload, 'orderId', ''),
            ),
        };

        return response()->noContent();
    }

    private function assertBookingPayable(Booking $booking): void
    {
        if ($booking->trashed() || $booking->status !== BookingStatus::PENDING) {
            throw new RuntimeException('Booking is not payable.');
        }

        if (! $booking->payment_policy->requiresStripePaymentIntent()) {
            throw new RuntimeException('Booking does not require online payment.');
        }

        if ((int) $booking->amount <= 0) {
            throw new RuntimeException('Booking amount must be greater than zero.');
        }
    }

    private function paymentRejectedResponse(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], 422);
    }
}
