<?php

namespace App\Repositories;

use App\Models\Booking;
use App\Repositories\Contracts\BookingRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * EloquentBookingRepository - Eloquent implementation of BookingRepositoryInterface
 * 
 * PURPOSE:
 * Provides concrete Eloquent-based data access for the Booking domain.
 * Acts as a thin wrapper around existing Booking model queries.
 * 
 * DESIGN PRINCIPLES:
 * - Pure data access only (no business logic, no validation)
 * - Returns exact same Eloquent models/collections as direct calls
 * - Preserves all existing behavior (global scopes, soft deletes)
 * - No side effects beyond what Eloquent already does
 * - Throws same exceptions as direct Eloquent calls
 * 
 * USAGE:
 * Inject BookingRepositoryInterface in services/controllers.
 * Laravel container will resolve to this implementation.
 * 
 * @see BookingRepositoryInterface
 */
class EloquentBookingRepository implements BookingRepositoryInterface
{
    // ========== BASIC CRUD OPERATIONS ==========

    /**
     * {@inheritDoc}
     */
    public function findById(int $id): ?Booking
    {
        return Booking::find($id);
    }

    /**
     * {@inheritDoc}
     */
    public function findByIdOrFail(int $id): Booking
    {
        return Booking::findOrFail($id);
    }

    /**
     * {@inheritDoc}
     */
    public function findByIdWithRelations(int $id, array $relations): ?Booking
    {
        return Booking::with($relations)->find($id);
    }

    /**
     * {@inheritDoc}
     */
    public function create(array $data): Booking
    {
        return Booking::create($data);
    }

    /**
     * {@inheritDoc}
     */
    public function update(Booking $booking, array $data): bool
    {
        return $booking->update($data);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(Booking $booking): bool
    {
        return $booking->delete();
    }

    // ========== USER BOOKINGS QUERIES ==========

    /**
     * {@inheritDoc}
     */
    public function getByUserId(
        int $userId,
        array $columns = ['*'],
        array $relations = []
    ): Collection {
        $query = Booking::where('user_id', $userId);

        if (!empty($relations)) {
            $query->with($relations);
        }

        if ($columns !== ['*']) {
            $query->select($columns);
        }

        return $query->get();
    }

    /**
     * {@inheritDoc}
     */
    public function getByUserIdOrderedByCheckIn(
        int $userId,
        array $columns = ['*'],
        array $relations = []
    ): Collection {
        $query = Booking::where('user_id', $userId);

        if (!empty($relations)) {
            $query->with($relations);
        }

        if ($columns !== ['*']) {
            $query->select($columns);
        }

        return $query->orderBy('check_in', 'desc')->get();
    }

    // ========== OVERLAP/CONFLICT DETECTION QUERIES ==========

    /**
     * {@inheritDoc}
     */
    public function findOverlappingBookings(
        int $roomId,
        $checkIn,
        $checkOut,
        ?int $excludeBookingId = null
    ): Collection {
        return Booking::overlappingBookings($roomId, $checkIn, $checkOut, $excludeBookingId)->get();
    }

    /**
     * {@inheritDoc}
     */
    public function hasOverlappingBookings(
        int $roomId,
        $checkIn,
        $checkOut,
        ?int $excludeBookingId = null
    ): bool {
        return Booking::overlappingBookings($roomId, $checkIn, $checkOut, $excludeBookingId)->exists();
    }

    /**
     * {@inheritDoc}
     */
    public function findOverlappingBookingsWithLock(
        int $roomId,
        $checkIn,
        $checkOut,
        ?int $excludeBookingId = null
    ): Collection {
        return Booking::query()
            ->overlappingBookings($roomId, $checkIn, $checkOut, $excludeBookingId)
            ->withLock()
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function hasOverlappingBookingsWithLock(
        int $roomId,
        $checkIn,
        $checkOut,
        ?int $excludeBookingId = null
    ): bool {
        return Booking::query()
            ->overlappingBookings($roomId, $checkIn, $checkOut, $excludeBookingId)
            ->withLock()
            ->exists();
    }

    // ========== SOFT DELETE QUERIES ==========

    /**
     * {@inheritDoc}
     */
    public function getTrashed(array $relations = []): Collection
    {
        $query = Booking::onlyTrashed();

        if (!empty($relations)) {
            $query->with($relations);
        }

        return $query->get();
    }

    /**
     * {@inheritDoc}
     */
    public function findTrashedById(int $id, array $relations = []): ?Booking
    {
        $query = Booking::onlyTrashed();

        if (!empty($relations)) {
            $query->with($relations);
        }

        return $query->find($id);
    }

    /**
     * {@inheritDoc}
     */
    public function restore(Booking $booking): bool
    {
        return $booking->restore();
    }

    /**
     * {@inheritDoc}
     */
    public function forceDelete(Booking $booking): bool
    {
        return $booking->forceDelete();
    }

    /**
     * {@inheritDoc}
     */
    public function getTrashedOlderThan(Carbon $cutoffDate): Collection
    {
        return Booking::onlyTrashed()
            ->where('deleted_at', '<', $cutoffDate)
            ->get();
    }

    // ========== ADMIN/LISTING QUERIES ==========

    /**
     * {@inheritDoc}
     */
    public function getAllWithTrashed(array $relations = []): Collection
    {
        $query = Booking::withTrashed();

        if (!empty($relations)) {
            $query->with($relations);
        }

        return $query->get();
    }

    /**
     * {@inheritDoc}
     * 
     * NOTE: Relies on existing Booking::withCommonRelations() scope
     * defined in App\Models\Booking (lines 71-82).
     */
    public function getWithCommonRelations(): Collection
    {
        return Booking::withCommonRelations()->get();
    }

    // ========== QUERY BUILDER ACCESS ==========

    /**
     * {@inheritDoc}
     */
    public function query(): Builder
    {
        return Booking::query();
    }
}
