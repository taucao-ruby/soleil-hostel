<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Room;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Requests\UpdateBookingRequest;
use App\Http\Resources\BookingResource;
use App\Events\BookingUpdated;
use App\Events\BookingDeleted;
use App\Services\CreateBookingService;
use App\Services\BookingService;
use App\Services\RoomService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class BookingController extends Controller
{
    public function __construct(
        private CreateBookingService $createBookingService,
        private BookingService $bookingService,
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
            'data' => BookingResource::collection($bookings)
        ]);
    }

    /**
     * Store a newly created resource in storage.
     * 
     * Dùng CreateBookingService để đảm bảo không double-booking
     * Service sẽ handle transaction + pessimistic locking + retry logic
     * 
     * INPUT SANITIZATION:
     * - FormRequest validation sẽ reject invalid input
     * - Booking model trait sẽ auto-purify guest_name (HTML Purifier, không regex)
     * - Regex blacklist = 99% bypass. HTML Purifier = 0% bypass.
     */
    public function store(StoreBookingRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            // Gọi service để tạo booking (có pessimistic locking)
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
            event(new \App\Events\BookingCreated($booking));

            return response()->json([
                'success' => true,
                'message' => 'Booking created successfully',
                'data' => new BookingResource($booking->load('room')),
            ], 201);
        } catch (RuntimeException $e) {
            // Xử lý lỗi từ service (phòng đặt, không tồn tại, v.v.)
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            // Log error nếu cần
            \Log::error('Booking creation failed: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'room_id' 
                => $validated['room_id'] ?? null,
                'exception' => class_basename($e),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating the booking. Please try again.',
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Booking $booking): JsonResponse
    {
        // Kiểm tra authorization dùng policy
        $this->authorize('view', $booking);

        $cachedBooking = $this->bookingService->getBookingById($booking->id);

        return response()->json([
            'success' => true,
            'data' => new BookingResource($cachedBooking)
        ]);
    }

    /**
     * Update the specified resource in storage.
     * 
     * Dùng CreateBookingService để update, đảm bảo không overlap
     */
    public function update(UpdateBookingRequest $request, Booking $booking): JsonResponse
    {
        // Kiểm tra authorization
        $this->authorize('update', $booking);

        $validated = $request->validated();

        try {
            // Store original booking data BEFORE updating (to avoid serialization issues)
            $originalBookingData = $booking->toArray();

            // Gọi service update - convert strings to Carbon dates
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

            // Dispatch event for cache invalidation using array data instead of models
            // This avoids Laravel trying to restore deleted models from the event
            event(new BookingUpdated((object) $booking->toArray(), (object) $originalBookingData));

            return response()->json([
                'success' => true,
                'message' => 'Booking updated successfully',
                'data' => new BookingResource($booking->load('room')),
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            \Log::error('Booking update failed: ' . $e->getMessage(), [
                'booking_id' => $booking->id,
                'exception' => class_basename($e),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating the booking.',
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
        // Kiểm tra authorization
        $this->authorize('delete', $booking);

        // Dispatch event for cache invalidation BEFORE deleting
        // This avoids Laravel trying to restore a deleted model from the event
        event(new BookingDeleted($booking));

        // Soft delete booking with audit trail (records deleted_by)
        $this->bookingService->softDelete($booking, auth()->id());

        return response()->json([
            'success' => true,
            'message' => 'Booking cancelled successfully',
        ], 200);
    }
}

