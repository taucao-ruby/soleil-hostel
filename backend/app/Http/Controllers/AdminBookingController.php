<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;

/**
 * AdminBookingController - Admin-only booking management
 * 
 * Handles soft delete recovery and audit operations:
 * - View trashed (soft deleted) bookings
 * - Restore accidentally deleted bookings
 * - Permanently delete for GDPR "right to be forgotten"
 * 
 * All endpoints require ADMIN role via middleware.
 */
class AdminBookingController extends Controller
{
    public function __construct(
        private BookingService $bookingService
    ) {}

    /**
     * Display all bookings including soft deleted (admin view).
     * 
     * GET /api/admin/bookings
     * 
     * Returns all bookings with their deletion status for admin overview.
     */
    public function index(): JsonResponse
    {
        $bookings = Booking::withTrashed()
            ->with([
                'room' => fn($q) => $q->select(['id', 'name', 'price', 'created_at', 'updated_at']),
                'user' => fn($q) => $q->select(['id', 'name', 'email', 'role', 'created_at', 'updated_at']),
                'deletedBy' => fn($q) => $q->select(['id', 'name', 'email', 'role', 'created_at', 'updated_at']),
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => BookingResource::collection($bookings),
        ]);
    }

    /**
     * Display only trashed (soft deleted) bookings.
     * 
     * GET /api/admin/bookings/trashed
     * 
     * For admin "Trash" view to see deleted bookings that can be restored.
     */
    public function trashed(): JsonResponse
    {
        $bookings = $this->bookingService->getTrashedBookings();

        return response()->json([
            'success' => true,
            'data' => BookingResource::collection($bookings),
            'meta' => [
                'total_trashed' => $bookings->count(),
            ],
        ]);
    }

    /**
     * Show a specific trashed booking.
     * 
     * GET /api/admin/bookings/trashed/{id}
     */
    public function showTrashed(int $id): JsonResponse
    {
        $booking = $this->bookingService->getTrashedBookingById($id);

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Trashed booking not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new BookingResource($booking),
        ]);
    }

    /**
     * Restore a soft deleted booking.
     * 
     * POST /api/admin/bookings/{id}/restore
     * 
     * Restores booking to active state, clears deleted_at and deleted_by.
     * Booking will appear again in normal queries.
     */
    public function restore(int $id): JsonResponse
    {
        $booking = $this->bookingService->getTrashedBookingById($id);

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Trashed booking not found.',
            ], 404);
        }

        // Check for overlapping active bookings before restoring
        $hasOverlap = Booking::overlappingBookings(
            roomId: $booking->room_id,
            checkIn: $booking->check_in,
            checkOut: $booking->check_out,
            excludeBookingId: $booking->id
        )->exists();

        if ($hasOverlap) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot restore booking: date range conflicts with existing bookings.',
            ], 422);
        }

        $result = $this->bookingService->restore($booking);

        if ($result) {
            return response()->json([
                'success' => true,
                'message' => 'Booking restored successfully.',
                'data' => new BookingResource($booking->fresh(['room', 'user'])),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to restore booking.',
        ], 500);
    }

    /**
     * Permanently delete a soft deleted booking (force delete).
     * 
     * DELETE /api/admin/bookings/{id}/force
     * 
     * ⚠️ WARNING: This PERMANENTLY removes the booking from database.
     * Use only for GDPR "right to be forgotten" requests or after retention period.
     * This action is IRREVERSIBLE.
     */
    public function forceDelete(int $id): JsonResponse
    {
        $booking = $this->bookingService->getTrashedBookingById($id);

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Trashed booking not found. Only soft-deleted bookings can be permanently deleted.',
            ], 404);
        }

        $result = $this->bookingService->forceDelete($booking);

        if ($result) {
            return response()->json([
                'success' => true,
                'message' => 'Booking permanently deleted.',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to permanently delete booking.',
        ], 500);
    }

    /**
     * Bulk restore multiple trashed bookings.
     * 
     * POST /api/admin/bookings/restore-bulk
     * 
     * @param array $ids Array of booking IDs to restore
     */
    public function restoreBulk(): JsonResponse
    {
        $ids = request()->input('ids', []);

        if (empty($ids)) {
            return response()->json([
                'success' => false,
                'message' => 'No booking IDs provided.',
            ], 422);
        }

        $restored = 0;
        $failed = [];

        foreach ($ids as $id) {
            $booking = $this->bookingService->getTrashedBookingById($id);
            
            if (!$booking) {
                $failed[] = ['id' => $id, 'reason' => 'Not found'];
                continue;
            }

            // Check for overlapping bookings
            $hasOverlap = Booking::overlappingBookings(
                roomId: $booking->room_id,
                checkIn: $booking->check_in,
                checkOut: $booking->check_out,
                excludeBookingId: $booking->id
            )->exists();

            if ($hasOverlap) {
                $failed[] = ['id' => $id, 'reason' => 'Date conflict'];
                continue;
            }

            if ($this->bookingService->restore($booking)) {
                $restored++;
            } else {
                $failed[] = ['id' => $id, 'reason' => 'Restore failed'];
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$restored} booking(s) restored.",
            'data' => [
                'restored_count' => $restored,
                'failed' => $failed,
            ],
        ]);
    }
}
