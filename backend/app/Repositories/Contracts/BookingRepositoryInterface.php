<?php

namespace App\Repositories\Contracts;

use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;

/**
 * BookingRepositoryInterface - Data access abstraction for Booking domain
 * 
 * PURPOSE:
 * Defines the contract for Booking data access operations, decoupling services,
 * controllers, and jobs from direct Eloquent usage. This enables:
 * - Improved testability (easy mocking in unit tests)
 * - Future flexibility (alternate implementations, caching decorators)
 * - Clear separation of data access from business logic
 * 
 * DESIGN PRINCIPLES:
 * - Contains ONLY pure data access logic (no business rules, no validation)
 * - Methods mirror existing Booking model usage patterns exactly
 * - Returns Eloquent models/collections (no DTOs or transformations)
 * - Respects all existing global scopes and soft delete behavior
 * - Throws same exceptions as direct Eloquent calls (e.g., ModelNotFoundException)
 * 
 * IMPORTANT:
 * - No transactions or cross-entity coordination in repository
 * - Business logic remains in services (CreateBookingService, BookingService)
 * - Repository is a thin wrapper around current Eloquent patterns
 */
interface BookingRepositoryInterface
{
    // ========== BASIC CRUD OPERATIONS ==========

    /**
     * Find a booking by ID.
     * 
     * @param int $id Booking ID
     * @return Booking|null Null if not found
     */
    public function findById(int $id): ?Booking;

    /**
     * Find a booking by ID or throw exception.
     * 
     * @param int $id Booking ID
     * @return Booking
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findByIdOrFail(int $id): Booking;

    /**
     * Find a booking by ID with specified relations.
     * 
     * Derived from: BookingService::getBookingById()
     *   Booking::with(['room', 'user'])->find($bookingId)
     * 
     * @param int $id Booking ID
     * @param array $relations Relations to eager load (e.g., ['room', 'user'])
     * @return Booking|null
     */
    public function findByIdWithRelations(int $id, array $relations): ?Booking;

    /**
     * Create a new booking.
     * 
     * @param array $data Booking attributes (room_id, check_in, check_out, guest_name, etc.)
     * @return Booking The created booking
     */
    public function create(array $data): Booking;

    /**
     * Update an existing booking.
     * 
     * @param Booking $booking The booking to update
     * @param array $data Attributes to update
     * @return bool Success status
     */
    public function update(Booking $booking, array $data): bool;

    /**
     * Delete a booking (soft delete).
     * 
     * @param Booking $booking The booking to delete
     * @return bool Success status
     */
    public function delete(Booking $booking): bool;

    // ========== USER BOOKINGS QUERIES ==========

    /**
     * Get bookings for a specific user.
     * 
     * NOTE: Currently all observed usages include orderBy('check_in', 'desc').
     * This base method kept for flexibility; prefer getByUserIdOrderedByCheckIn()
     * for consistency with existing service patterns.
     * 
     * @param int $userId User ID
     * @param array $columns Columns to select
     * @param array $relations Relations to eager load
     * @return Collection
     */
    public function getByUserId(
        int $userId,
        array $columns = ['*'],
        array $relations = []
    ): Collection;

    /**
     * Get bookings for a user ordered by check-in date descending.
     * 
     * Derived from: BookingService::getUserBookings()
     *   Booking::where('user_id', $userId)->with([...])->orderBy('check_in', 'desc')->get()
     * 
     * @param int $userId User ID
     * @param array $columns Columns to select
     * @param array $relations Relations to eager load
     * @return Collection
     */
    public function getByUserIdOrderedByCheckIn(
        int $userId,
        array $columns = ['*'],
        array $relations = []
    ): Collection;

    // ========== OVERLAP/CONFLICT DETECTION QUERIES ==========

    /**
     * Find overlapping bookings for a room in given date range.
     * Uses half-open interval logic [check_in, check_out).
     * 
     * This is CRITICAL for double-booking prevention.
     * 
     * @param int $roomId Room ID
     * @param Carbon|\DateTimeInterface|string $checkIn Check-in date
     * @param Carbon|\DateTimeInterface|string $checkOut Check-out date
     * @param int|null $excludeBookingId Booking ID to exclude (for updates)
     * @return Collection Overlapping bookings
     */
    public function findOverlappingBookings(
        int $roomId,
        $checkIn,
        $checkOut,
        ?int $excludeBookingId = null
    ): Collection;

    /**
     * Check if overlapping bookings exist (faster than fetching all).
     * 
     * Derived from: AdminBookingController::restore(), restoreBulk()
     *   Booking::overlappingBookings(...)->exists()
     * 
     * @param int $roomId Room ID
     * @param Carbon|\DateTimeInterface|string $checkIn Check-in date
     * @param Carbon|\DateTimeInterface|string $checkOut Check-out date
     * @param int|null $excludeBookingId Booking ID to exclude (for updates)
     * @return bool True if overlap exists
     */
    public function hasOverlappingBookings(
        int $roomId,
        $checkIn,
        $checkOut,
        ?int $excludeBookingId = null
    ): bool;

    /**
     * Get overlapping bookings with pessimistic lock (FOR UPDATE).
     * 
     * CRITICAL for race condition prevention in high-concurrency scenarios.
     * Must be used within a database transaction.
     * 
     * Derived from: CreateBookingService::createBookingWithLocking()
     *   Booking::query()->overlappingBookings()->withLock()->get()
     * 
     * @param int $roomId Room ID
     * @param Carbon|\DateTimeInterface|string $checkIn Check-in date
     * @param Carbon|\DateTimeInterface|string $checkOut Check-out date
     * @param int|null $excludeBookingId Booking ID to exclude
     * @return Collection Locked overlapping bookings
     */
    public function findOverlappingBookingsWithLock(
        int $roomId,
        $checkIn,
        $checkOut,
        ?int $excludeBookingId = null
    ): Collection;

    /**
     * Check if overlapping bookings exist with pessimistic lock (FOR UPDATE).
     * 
     * CRITICAL for race condition prevention in high-concurrency scenarios.
     * Must be used within a database transaction.
     * 
     * Derived from: CreateBookingService::update()
     *   Booking::query()->overlappingBookings()->withLock()->exists()
     * 
     * @param int $roomId Room ID
     * @param Carbon|\DateTimeInterface|string $checkIn Check-in date
     * @param Carbon|\DateTimeInterface|string $checkOut Check-out date
     * @param int|null $excludeBookingId Booking ID to exclude
     * @return bool True if overlap exists
     */
    public function hasOverlappingBookingsWithLock(
        int $roomId,
        $checkIn,
        $checkOut,
        ?int $excludeBookingId = null
    ): bool;

    // ========== SOFT DELETE QUERIES ==========

    /**
     * Get only trashed (soft deleted) bookings.
     * 
     * @param array $relations Relations to eager load
     * @return Collection
     */
    public function getTrashed(array $relations = []): Collection;

    /**
     * Find a trashed booking by ID.
     * 
     * @param int $id Booking ID
     * @param array $relations Relations to eager load
     * @return Booking|null
     */
    public function findTrashedById(int $id, array $relations = []): ?Booking;

    /**
     * Restore a soft deleted booking.
     * 
     * @param Booking $booking The trashed booking to restore
     * @return bool Success status
     */
    public function restore(Booking $booking): bool;

    /**
     * Permanently delete a booking (force delete).
     * 
     * @param Booking $booking The booking to permanently delete
     * @return bool Success status
     */
    public function forceDelete(Booking $booking): bool;

    /**
     * Get trashed bookings older than specified date.
     * Used for retention policy enforcement.
     * 
     * Derived from: PruneOldSoftDeletedBookings command
     *   Booking::onlyTrashed()->where('deleted_at', '<', $cutoffDate)
     * 
     * @param Carbon $cutoffDate Delete records older than this date
     * @return Collection
     */
    public function getTrashedOlderThan(Carbon $cutoffDate): Collection;

    // ========== ADMIN/LISTING QUERIES ==========

    /**
     * Get all bookings including trashed (for admin view).
     * 
     * Derived from: AdminBookingController::index()
     *   Booking::withTrashed()->with([...])->get()
     * 
     * @param array $relations Relations to eager load
     * @return Collection
     */
    public function getAllWithTrashed(array $relations = []): Collection;

    /**
     * Get bookings with common relations loaded (room, user).
     * Uses optimized column selection to prevent N+1.
     * 
     * NOTE: Relies on existing Booking::withCommonRelations() scope.
     * Currently not actively used in services/controllers, but kept for
     * consistency with documented model scope usage patterns.
     * 
     * @return Collection
     */
    public function getWithCommonRelations(): Collection;

    // ========== QUERY BUILDER ACCESS ==========

    /**
     * Get a fresh query builder for Booking.
     * Allows callers to build custom queries when needed.
     * 
     * @return Builder
     */
    public function query(): Builder;
}
