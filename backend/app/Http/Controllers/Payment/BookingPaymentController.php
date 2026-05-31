<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payment;

use App\Enums\BookingStatus;
use App\Enums\PaymentPolicy;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\BookingService;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class BookingPaymentController extends Controller
{
    public function __construct(
        private readonly StripeService $stripeService,
        private readonly BookingService $bookingService,
    ) {}

    public function createPaymentIntent(Booking $booking): JsonResponse
    {
        $this->authorize('pay', $booking);

        try {
            $this->assertBookingPayable($booking);
        } catch (RuntimeException $e) {
            return $this->paymentRejectedResponse($e->getMessage());
        }

        if (is_string($booking->payment_intent_id) && $booking->payment_intent_id !== '') {
            $paymentIntent = $this->stripeService->retrievePaymentIntent($booking->payment_intent_id);

            try {
                $this->stripeService->assertPaymentIntentMatchesBooking($paymentIntent, $booking);
            } catch (RuntimeException $e) {
                return $this->paymentRejectedResponse($e->getMessage());
            }

            return $this->paymentIntentResponse($booking, $paymentIntent);
        }

        $started = $this->stripeService->createPaymentIntent($booking);

        Booking::query()
            ->whereKey($booking->id)
            ->where('status', BookingStatus::PENDING->value)
            ->whereNull('payment_intent_id')
            ->update([
                'payment_intent_id' => $started->id,
                'payment_status' => PaymentStatus::fromStripePaymentIntentStatus($started->status)->value,
                'payment_currency' => $started->currency,
                'amount_capturable' => $started->amountCapturable,
                'amount_received' => $started->amountReceived,
                'payment_failed_reason' => null,
                'updated_at' => now(),
            ]);

        $booking = $booking->fresh();
        if (! $booking instanceof Booking) {
            throw new RuntimeException('Booking disappeared while starting payment.');
        }

        return response()->json([
            'success' => true,
            'data' => [
                'client_secret' => $started->clientSecret,
                'payment_policy' => $booking->payment_policy->value,
                'payment_status' => $booking->payment_status->value,
            ],
        ]);
    }

    public function verify(Booking $booking): JsonResponse
    {
        $this->authorize('pay', $booking);

        if (! is_string($booking->payment_intent_id) || $booking->payment_intent_id === '') {
            return response()->json([
                'success' => false,
                'message' => 'Booking has no PaymentIntent to verify.',
            ], 422);
        }

        $paymentIntent = $this->stripeService->retrievePaymentIntent($booking->payment_intent_id);

        try {
            if ((string) data_get($paymentIntent, 'id') !== $booking->payment_intent_id) {
                throw new RuntimeException("Stripe PaymentIntent id mismatch for booking #{$booking->id}.");
            }

            $this->stripeService->assertPaymentIntentMatchesBooking($paymentIntent, $booking);
            $this->assertPaymentIntentUserMatchesBooking($paymentIntent, $booking);
        } catch (RuntimeException $e) {
            return $this->paymentRejectedResponse($e->getMessage());
        }

        $status = (string) data_get($paymentIntent, 'status', '');

        if ($booking->payment_policy === PaymentPolicy::PREPAID && $status === 'succeeded') {
            $booking = $this->bookingService->markPaidAndConfirm(
                $booking,
                (int) data_get($paymentIntent, 'amount_received', $booking->amount),
                (int) data_get($paymentIntent, 'amount_capturable', 0),
            );

            return $this->verifiedBookingResponse($booking);
        }

        if ($booking->payment_policy === PaymentPolicy::AUTHORIZE_THEN_CAPTURE && $status === 'requires_capture') {
            $booking = $this->markAuthorizedAndConfirm($booking, $paymentIntent);

            return $this->verifiedBookingResponse($booking);
        }

        $paymentStatus = PaymentStatus::fromStripePaymentIntentStatus($status);
        $this->syncIncompletePayment($booking, $paymentStatus, $paymentIntent);

        $httpStatus = in_array($paymentStatus, [
            PaymentStatus::REQUIRES_PAYMENT_METHOD,
            PaymentStatus::REQUIRES_CONFIRMATION,
            PaymentStatus::REQUIRES_ACTION,
            PaymentStatus::PROCESSING,
        ], true) ? 202 : 422;

        return response()->json([
            'success' => false,
            'message' => 'Payment is not complete.',
            'data' => new BookingResource($booking->fresh()),
        ], $httpStatus);
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

    private function paymentIntentResponse(Booking $booking, mixed $paymentIntent): JsonResponse
    {
        $clientSecret = data_get($paymentIntent, 'client_secret');
        $status = (string) data_get($paymentIntent, 'status', 'requires_payment_method');
        $paymentStatus = PaymentStatus::fromStripePaymentIntentStatus($status);

        $booking->forceFill([
            'payment_status' => $paymentStatus,
            'amount_capturable' => (int) data_get($paymentIntent, 'amount_capturable', 0),
            'amount_received' => (int) data_get($paymentIntent, 'amount_received', 0),
            'payment_failed_reason' => null,
        ])->save();

        return response()->json([
            'success' => true,
            'data' => [
                'client_secret' => is_string($clientSecret) ? $clientSecret : null,
                'payment_policy' => $booking->payment_policy->value,
                'payment_status' => $booking->payment_status->value,
            ],
        ]);
    }

    private function markAuthorizedAndConfirm(Booking $booking, mixed $paymentIntent): Booking
    {
        return DB::transaction(function () use ($booking, $paymentIntent): Booking {
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

            if ($locked->status === BookingStatus::CONFIRMED) {
                return $locked->fresh();
            }

            return $this->bookingService->confirmBooking($locked);
        });
    }

    private function syncIncompletePayment(
        Booking $booking,
        PaymentStatus $paymentStatus,
        mixed $paymentIntent,
    ): void {
        $booking->forceFill([
            'payment_status' => $paymentStatus,
            'amount_capturable' => (int) data_get($paymentIntent, 'amount_capturable', 0),
            'amount_received' => (int) data_get($paymentIntent, 'amount_received', 0),
            'payment_failed_reason' => $paymentStatus === PaymentStatus::FAILED
                ? $this->safePaymentFailureReason($paymentIntent)
                : null,
        ])->save();
    }

    private function assertPaymentIntentUserMatchesBooking(mixed $paymentIntent, Booking $booking): void
    {
        $metadataUserId = (string) data_get($paymentIntent, 'metadata.user_id', '');

        if ($metadataUserId !== '' && $metadataUserId !== (string) $booking->user_id) {
            throw new RuntimeException("Stripe PaymentIntent user mismatch for booking #{$booking->id}.");
        }
    }

    private function safePaymentFailureReason(mixed $paymentIntent): ?string
    {
        $message = data_get($paymentIntent, 'last_payment_error.message');

        if (! is_string($message) || $message === '') {
            return null;
        }

        return mb_substr($message, 0, 1000);
    }

    private function verifiedBookingResponse(Booking $booking): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Payment verified.',
            'data' => new BookingResource($booking->load('room')),
        ]);
    }

    private function paymentRejectedResponse(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], 422);
    }
}
