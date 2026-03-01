<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Events\BookingCreated;
use App\Events\BookingDeleted;
use App\Events\BookingUpdated;
use App\Exceptions\BookingCancellationException;
use App\Exceptions\RefundFailedException;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Requests\UpdateBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\BookingService;
use App\Services\CancellationService;
use App\Services\CreateBookingService;
use App\Services\RoomService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class BookingController extends Controller
{
    use ApiResponse;

    public function __construct(
        private CreateBookingService $createBookingService,
        private BookingService $bookingService,
        private CancellationService $cancellationService,
        private RoomService $roomService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $bookings = $this->bookingService->getUserBookings(auth()->id());

        return response()->json([
            'success' => true,
            'data' => BookingResource::collection($bookings),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * Uses CreateBookingService to prevent double-booking
     * Service handles the transaction, pessimistic locking, and retry logic
     *
     * INPUT SANITIZATION:
     * - FormRequest validation rejects invalid input
     * - Booking model trait auto-purifies guest_name (HTML Purifier, not regex)
     * - Regex blacklist = 99% bypass. HTML Purifier = 0% bypass.
     */
    public function store(StoreBookingRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            // Delegate to service to create booking (includes pessimistic locking)
            $booking = $this->createBookingService->create(
                roomId: $validated['room_id'],
                checkIn: $validated['check_in'],
                checkOut: $validated['check_out'],
                guestName: $validated['guest_name'],
                guestEmail: $validated['guest_email'],
                userId: auth()->id(),
                additionalData: []
            );

            // Dispatch event for cache invalidation
            event(new BookingCreated($booking));

            return response()->json([
                'success' => true,
                'message' => __('booking.created'),
                'data' => new BookingResource($booking->load('room')),
            ], 201);
        } catch (RuntimeException $e) {
            // Handle business errors from the service (room unavailable, not found, etc.)
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            // Log unexpected errors
            Log::error('Booking creation failed: '.$e->getMessage(), [
                'user_id' => auth()->id(),
                'room_id' => $validated['room_id'] ?? null,
                'exception' => class_basename($e),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('booking.create_error'),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Booking $booking): JsonResponse
    {
        // Authorize via policy
        $this->authorize('view', $booking);

        $cachedBooking = $this->bookingService->getBookingById($booking->id);

        return response()->json([
            'success' => true,
            'data' => new BookingResource($cachedBooking),
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * Uses CreateBookingService to update, ensuring no overlap with existing bookings
     */
    public function update(UpdateBookingRequest $request, Booking $booking): JsonResponse
    {
        // Authorize update
        $this->authorize('update', $booking);

        $validated = $request->validated();

        try {
            // Store original booking data BEFORE updating (to compare changes later)
            $originalBookingData = (object) $booking->toArray();

            // Call service to update — convert date strings to Carbon instances
            // Pass guest_name and guest_email via additionalData
            $booking = $this->createBookingService->update(
                booking: $booking,
                checkIn: \Carbon\Carbon::createFromFormat('Y-m-d', $validated['check_in'])->startOfDay(),
                checkOut: \Carbon\Carbon::createFromFormat('Y-m-d', $validated['check_out'])->startOfDay(),
                additionalData: [
                    'guest_name' => $validated['guest_name'] ?? $booking->guest_name,
                    'guest_email' => $validated['guest_email'] ?? $booking->guest_email,
                ]
            );

            // Dispatch event for notification - pass actual Booking model and original data
            event(new BookingUpdated($booking, $originalBookingData));

            return response()->json([
                'success' => true,
                'message' => __('booking.updated'),
                'data' => new BookingResource($booking->load('room')),
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Booking update failed: '.$e->getMessage(), [
                'booking_id' => $booking->id,
                'exception' => class_basename($e),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('booking.update_error'),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage (soft delete).
     *
     * Uses soft delete to preserve audit trail - booking data is NOT permanently removed.
     * Records who deleted the booking and when for compliance.
     *
     * Regular users: Can only soft delete their own bookings
     * Admins: Can soft delete any booking
     */
    public function destroy(Booking $booking): JsonResponse
    {
        // Authorize deletion
        $this->authorize('delete', $booking);

        // Dispatch event for cache invalidation BEFORE deleting
        // This avoids Laravel trying to restore a deleted model from the event
        event(new BookingDeleted($booking));

        // Soft delete booking with audit trail (records deleted_by)
        $this->bookingService->softDelete($booking, auth()->id());

        return response()->json([
            'success' => true,
            'message' => __('booking.deleted'),
        ], 200);
    }

    /**
     * Confirm a pending booking.
     *
     * Changes booking status from 'pending' to 'confirmed' and triggers
     * a queued confirmation email notification to the guest.
     *
     * Authorization: Only admins can confirm bookings
     * Rate limiting: Max 5 confirmation emails per user per minute
     */
    public function confirm(Booking $booking): JsonResponse
    {
        $this->authorize('confirm', $booking);

        if ($booking->status !== BookingStatus::PENDING) {
            return response()->json([
                'success' => false,
                'message' => __('booking.confirm_invalid_status', ['status' => $booking->status->value]),
            ], 422);
        }

        try {
            $booking = $this->bookingService->confirmBooking($booking);

            return response()->json([
                'success' => true,
                'message' => __('booking.confirmed'),
                'data' => new BookingResource($booking->load('room')),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Cancel a booking with optional refund.
     *
     * This endpoint handles the complete cancellation flow:
     * 1. Validates cancellation eligibility (ownership, status, timing)
     * 2. Calculates refund amount based on cancellation policy
     * 3. Processes refund via Stripe (if payment exists)
     * 4. Updates booking status
     * 5. Sends cancellation notification
     *
     * Authorization: Users can cancel their own bookings, admins can cancel any
     * Idempotent: Re-cancelling an already cancelled booking returns success
     */
    public function cancel(Booking $booking): JsonResponse
    {
        $this->authorize('cancel', $booking);

        try {
            $booking = $this->cancellationService->cancel(
                $booking,
                auth()->user()
            );

            // Build response message based on refund status
            $message = $this->buildCancellationMessage($booking);

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => new BookingResource($booking->load('room')),
            ]);

        } catch (BookingCancellationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => $e->getErrorCode(),
            ], $e->getHttpStatusCode());

        } catch (RefundFailedException $e) {
            return response()->json([
                'success' => false,
                'message' => __('booking.refund_failed'),
                'error' => 'refund_failed',
                'retryable' => $e->isRetryable(),
            ], $e->getHttpStatusCode());
        }
    }

    /**
     * Build appropriate cancellation message based on refund status.
     */
    private function buildCancellationMessage(Booking $booking): string
    {
        if ($booking->refund_amount && $booking->refund_amount > 0) {
            $formattedAmount = number_format($booking->refund_amount / 100, 2);

            return (string) __('booking.cancel_with_refund', ['amount' => '$'.$formattedAmount]);
        }

        if ($booking->payment_intent_id && $booking->refund_amount === 0) {
            return (string) __('booking.cancel_no_refund');
        }

        return (string) __('booking.cancelled');
    }
}
