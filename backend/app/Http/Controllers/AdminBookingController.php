<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkRestoreBookingsRequest;
use App\Http\Resources\BookingResource;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Services\AdminAuditService;
use App\Services\BookingService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * AdminBookingController - Backoffice booking management
 *
 * Read operations (index, trashed, showTrashed): moderator+ via route + gate
 * Write operations (restore, forceDelete, restoreBulk): admin-only via route + gate
 */
class AdminBookingController extends Controller
{
    use ApiResponse;

    public function __construct(
        private BookingService $bookingService,
        private BookingRepositoryInterface $bookingRepository,
        private AdminAuditService $auditService
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
        Gate::authorize('view-all-bookings');

        $bookings = $this->bookingRepository->getAllWithTrashedPaginated([
            'room' => fn ($q) => $q->select(['id', 'name', 'price', 'created_at', 'updated_at']),
            'user' => fn ($q) => $q->select(['id', 'name', 'email', 'role', 'created_at', 'updated_at']),
            'deletedBy' => fn ($q) => $q->select(['id', 'name', 'email', 'role', 'created_at', 'updated_at']),
        ]);

        return $this->success([
            'bookings' => BookingResource::collection($bookings),
            'meta' => [
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
                'per_page' => $bookings->perPage(),
                'total' => $bookings->total(),
            ],
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
        Gate::authorize('view-all-bookings');

        $bookings = $this->bookingService->getTrashedBookings();

        return $this->success([
            'bookings' => BookingResource::collection($bookings),
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
        Gate::authorize('view-all-bookings');

        $booking = $this->bookingService->getTrashedBookingById($id);

        if (! $booking) {
            return $this->error(__('booking.trashed_not_found'), 404);
        }

        return $this->success(new BookingResource($booking));
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
        Gate::authorize('admin');

        $booking = $this->bookingService->getTrashedBookingById($id);

        if (! $booking) {
            return $this->error(__('booking.trashed_not_found'), 404);
        }

        // Check for overlapping active bookings before restoring
        $hasOverlap = $this->bookingRepository->hasOverlappingBookings(
            roomId: $booking->room_id,
            checkIn: $booking->check_in,
            checkOut: $booking->check_out,
            excludeBookingId: $booking->id
        );

        if ($hasOverlap) {
            return $this->error(__('booking.restore_conflict'), 422);
        }

        $result = $this->bookingService->restore($booking);

        if ($result) {
            $this->auditService->log('booking.restore', 'booking', $id, [
                'room_id' => $booking->room_id,
                'check_in' => $booking->check_in->toDateString(),
                'check_out' => $booking->check_out->toDateString(),
            ]);

            return $this->success(
                new BookingResource($booking->fresh(['room', 'user'])),
                __('booking.restored')
            );
        }

        return $this->error(__('booking.restore_failed'), 500);
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
    public function forceDelete(Request $request, int $id): JsonResponse
    {
        Gate::authorize('admin');

        $booking = $this->bookingService->getTrashedBookingById($id);

        if (! $booking) {
            return $this->error(__('booking.trashed_not_found_force'), 404);
        }

        // Capture audit snapshot before permanent deletion destroys the record
        $auditSnapshot = [
            'user_id' => $booking->user_id,
            'room_id' => $booking->room_id,
            'check_in' => $booking->check_in->toDateString(),
            'check_out' => $booking->check_out->toDateString(),
            'status' => $booking->status->value ?? $booking->status,
            'guest_name' => $booking->guest_name,
            'reason' => $request->input('reason'),
        ];

        $result = $this->bookingService->forceDelete($booking);

        if ($result) {
            $this->auditService->log('booking.force_delete', 'booking', $id, $auditSnapshot);

            return $this->success(null, __('booking.permanently_deleted'));
        }

        return $this->error(__('booking.force_delete_failed'), 500);
    }

    /**
     * Bulk restore multiple trashed bookings.
     *
     * POST /api/admin/bookings/restore-bulk
     *
     * @param  array  $ids  Array of booking IDs to restore
     */
    public function restoreBulk(BulkRestoreBookingsRequest $request): JsonResponse
    {
        Gate::authorize('admin');

        $ids = $request->validated('ids');

        $restored = 0;
        $failed = [];

        foreach ($ids as $id) {
            $booking = $this->bookingService->getTrashedBookingById($id);

            if (! $booking) {
                $failed[] = ['id' => $id, 'reason' => __('booking.bulk_not_found')];

                continue;
            }

            // Check for overlapping bookings
            $hasOverlap = $this->bookingRepository->hasOverlappingBookings(
                roomId: $booking->room_id,
                checkIn: $booking->check_in,
                checkOut: $booking->check_out,
                excludeBookingId: $booking->id
            );

            if ($hasOverlap) {
                $failed[] = ['id' => $id, 'reason' => __('booking.bulk_date_conflict')];

                continue;
            }

            if ($this->bookingService->restore($booking)) {
                $restored++;
                $this->auditService->log('booking.restore', 'booking', $id, [
                    'room_id' => $booking->room_id,
                    'bulk' => true,
                ]);
            } else {
                $failed[] = ['id' => $id, 'reason' => __('booking.bulk_restore_failed')];
            }
        }

        return $this->success([
            'restored_count' => $restored,
            'failed' => $failed,
        ], __('booking.bulk_restored', ['count' => $restored]));
    }
}
