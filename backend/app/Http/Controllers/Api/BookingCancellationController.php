<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\BookingCancellationException;
use App\Exceptions\RefundFailedException;
use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\CancellationService;
use Illuminate\Http\JsonResponse;

/**
 * Handle booking cancellation requests.
 *
 * Single-action controller for dedicated cancellation endpoint.
 * Cancellation is separate from general CRUD to:
 * 1. Clearly express intent
 * 2. Simplify authorization (dedicated policy method)
 * 3. Handle refund side-effects cleanly
 */
final class BookingCancellationController extends Controller
{
    public function __construct(
        private readonly CancellationService $cancellationService,
    ) {}

    /**
     * Cancel a booking and process refund if applicable.
     *
     * POST /api/bookings/{booking}/cancel
     *
     * Success responses:
     * - 200: Booking cancelled (or already cancelled - idempotent)
     *
     * Error responses:
     * - 403: Not authorized to cancel this booking
     * - 404: Booking not found
     * - 422: Booking cannot be cancelled (business rule violation)
     * - 502: Refund processing failed (Stripe error)
     */
    public function __invoke(Booking $booking): JsonResponse
    {
        // Policy authorization (checks ownership, status, timing)
        $this->authorize('cancel', $booking);

        try {
            $booking = $this->cancellationService->cancel(
                $booking,
                auth()->user()
            );

            return $this->successResponse($booking);

        } catch (BookingCancellationException $e) {
            return $this->cancellationErrorResponse($e);

        } catch (RefundFailedException $e) {
            return $this->refundErrorResponse($e);
        }
    }

    /**
     * Build success response with cancelled booking data.
     */
    private function successResponse(Booking $booking): JsonResponse
    {
        $message = $booking->refund_amount
            ? 'Booking cancelled. Refund of $' . number_format($booking->refund_amount / 100, 2) . ' has been processed.'
            : 'Booking cancelled successfully.';

        return response()->json([
            'message' => $message,
            'data' => new BookingResource($booking),
        ]);
    }

    /**
     * Build error response for cancellation policy violations.
     */
    private function cancellationErrorResponse(BookingCancellationException $e): JsonResponse
    {
        return response()->json([
            'message' => $e->getMessage(),
            'error' => $e->getErrorCode(),
        ], $e->getHttpStatusCode());
    }

    /**
     * Build error response for refund failures.
     *
     * Returns 502 Bad Gateway to indicate external service failure.
     * The booking is left in refund_failed state for retry.
     */
    private function refundErrorResponse(RefundFailedException $e): JsonResponse
    {
        return response()->json([
            'message' => 'Refund processing failed. Your booking has been marked for cancellation and our team will process the refund manually.',
            'error' => 'refund_failed',
            'retryable' => $e->isRetryable(),
        ], $e->getHttpStatusCode());
    }
}
