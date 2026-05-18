<?php

namespace App\Http\Controllers;

use App\Exceptions\BookingRestoreConflictException;
use App\Http\Requests\Admin\AdminBookingIndexRequest;
use App\Http\Requests\BulkRestoreBookingsRequest;
use App\Http\Resources\BookingResource;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Services\AdminAuditService;
use App\Services\BookingService;
use App\Traits\ApiResponse;
use Illuminate\Database\QueryException;
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

    private const POSTGRES_EXCLUSION_VIOLATION = '23P01';

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
     * Supports optional query filters (validated by AdminBookingIndexRequest):
     *   check_in_start, check_in_end   — filter by check_in date (inclusive)
     *   check_out_start, check_out_end — filter by check_out date (inclusive)
     *   status                         — exact status match (e.g., 'confirmed')
     *   location_id                    — filter by denormalized location_id
     *   search                         — guest_name, guest_email, or booking id
     *   per_page                       — page size, 1–100 (default 50)
     */
    public function index(AdminBookingIndexRequest $request): JsonResponse
    {
        Gate::authorize('view-all-bookings');

        $validated = $request->validated();

        $filters = array_filter([
            'check_in_start' => $validated['check_in_start'] ?? null,
            'check_in_end' => $validated['check_in_end'] ?? null,
            'check_out_start' => $validated['check_out_start'] ?? null,
            'check_out_end' => $validated['check_out_end'] ?? null,
            'status' => $validated['status'] ?? null,
            'location_id' => isset($validated['location_id']) ? (int) $validated['location_id'] : null,
            'search' => $validated['search'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        $perPage = (int) ($validated['per_page'] ?? 50);

        $bookings = $this->bookingRepository->getAdminPaginated($filters, [
            'room' => fn ($q) => $q->select(['id', 'name', 'price', 'created_at', 'updated_at']),
            'user' => fn ($q) => $q->select(['id', 'name', 'email', 'role', 'created_at', 'updated_at']),
            'deletedBy' => fn ($q) => $q->select(['id', 'name', 'email', 'role', 'created_at', 'updated_at']),
            'cancelledBy' => static fn ($q) => $q->select([
                'id',
                'name',
                'email',
                'role',
                'created_at',
                'updated_at',
            ]),
        ], $perPage);

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
     * POST /api/v1/admin/bookings/{id}/restore
     *
     * The overlap check and the restore execute atomically inside a DB transaction
     * with a pessimistic lock (see BookingService::restore). Two possible conflict
     * responses are mapped here:
     *  - 422: sequential overlap detected inside the transaction (before restore)
     *  - 409: concurrent overlap detected by the PostgreSQL exclusion constraint
     */
    public function restore(int $id): JsonResponse
    {
        Gate::authorize('admin');

        $booking = $this->bookingService->getTrashedBookingById($id);

        if (! $booking) {
            return $this->error(__('booking.trashed_not_found'), 404);
        }

        try {
            $result = $this->bookingService->restore($booking);
        } catch (BookingRestoreConflictException) {
            return $this->error(__('booking.restore_conflict'), 422);
        } catch (QueryException $e) {
            if ($this->isPgExclusionViolation($e)) {
                return $this->error(__('booking.restore_concurrent_conflict'), 409);
            }
            throw $e;
        }

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
     * POST /api/v1/admin/bookings/restore-bulk
     *
     * Atomicity: each booking is restored independently inside its own transaction.
     * A conflict on one item does not roll back successfully restored items.
     *
     * Response shape:
     *   success_count  int   — number of bookings successfully restored
     *   failure_count  int   — number of bookings not restored
     *   restored_count int   — alias for success_count (backward compat)
     *   failed[]       array — per-item failure details: { id, reason }
     */
    public function restoreBulk(BulkRestoreBookingsRequest $request): JsonResponse
    {
        Gate::authorize('admin');

        $ids = $request->validated('ids');

        $successCount = 0;
        $failed = [];

        foreach ($ids as $id) {
            $booking = $this->bookingService->getTrashedBookingById($id);

            if (! $booking) {
                $failed[] = ['id' => $id, 'reason' => __('booking.bulk_not_found')];

                continue;
            }

            try {
                $restored = $this->bookingService->restore($booking);

                if ($restored) {
                    $successCount++;
                    $this->auditService->log('booking.restore', 'booking', $id, [
                        'room_id' => $booking->room_id,
                        'bulk' => true,
                    ]);
                } else {
                    $failed[] = ['id' => $id, 'reason' => __('booking.bulk_restore_failed')];
                }
            } catch (BookingRestoreConflictException) {
                $failed[] = ['id' => $id, 'reason' => __('booking.bulk_date_conflict')];
            } catch (QueryException $e) {
                if ($this->isPgExclusionViolation($e)) {
                    $failed[] = ['id' => $id, 'reason' => __('booking.bulk_date_conflict')];
                } else {
                    $failed[] = ['id' => $id, 'reason' => __('booking.bulk_restore_failed')];
                }
            }
        }

        return $this->success([
            'success_count' => $successCount,
            'failure_count' => count($failed),
            'restored_count' => $successCount,  // backward compat alias
            'failed' => $failed,
        ], __('booking.bulk_restored', ['count' => $successCount]));
    }

    /**
     * Detects the PostgreSQL SQLSTATE 23P01 (exclusion_violation) that backstops
     * the booking-overlap invariant. PDO always surfaces the SQLSTATE in
     * errorInfo[0] for driver-level errors, making it the authoritative source.
     *
     * Used to distinguish the BL-1 empty-overlap-set race (concurrent restore
     * commits caught by no_overlapping_bookings → 409) from generic DB errors
     * (which must propagate as 500 rather than be silently swallowed).
     */
    private function isPgExclusionViolation(QueryException $e): bool
    {
        return ($e->errorInfo[0] ?? null) === self::POSTGRES_EXCLUSION_VIOLATION;
    }
}
