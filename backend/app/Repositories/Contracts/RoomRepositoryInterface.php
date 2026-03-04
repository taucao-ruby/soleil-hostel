<?php

namespace App\Repositories\Contracts;

use App\Models\Room;
use Illuminate\Support\Collection;

/**
 * RoomRepositoryInterface
 *
 * Pure data access contract for the Room domain.
 * Methods are derived directly from observable RoomService usage.
 *
 * Constraints:
 * - NO business rules, pricing logic, or availability calculations
 * - NO transactions or cross-entity coordination
 * - Returns Eloquent model instances or collections exactly as current direct calls
 * - Preserves existing global scopes, soft delete behavior, and eager loading
 */
interface RoomRepositoryInterface
{
    /**
     * Find a room by ID with bookings relationship eager loaded.
     *
     * Mirrors: Room::with('bookings')->select([...])->find($roomId)
     */
    public function findByIdWithBookings(int $roomId): ?Room;

    /**
     * Find a room by ID with confirmed bookings filtered by callback.
     *
     * Mirrors: Room::with(['bookings' => function ($q) { ... }])->find($roomId)
     */
    public function findByIdWithConfirmedBookings(int $roomId): ?Room;

    /**
     * Get all rooms ordered by name.
     *
     * Mirrors: Room::select([...])->orderBy('name')->get()
     *
     * @return Collection<int, Room>
     */
    public function getAllOrderedByName(): Collection;

    /**
     * Check if overlapping confirmed bookings exist for a room.
     *
     * @return bool True if overlapping bookings exist
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If room does not exist
     */
    public function hasOverlappingConfirmedBookings(int $roomId, string $checkIn, string $checkOut): bool;

    /**
     * Create a new room.
     *
     * Mirrors: Room::create($data)
     *
     * @param  array  $data  Validated room data (lock_version excluded)
     */
    public function create(array $data): Room;

    /**
     * Atomic update with optimistic lock version check.
     *
     * Mirrors: DB::table('rooms')->where('id', ...)->where('lock_version', ...)->update(...)
     *
     * @param  array  $data  Data to update (lock_version excluded, managed internally)
     * @return int Number of affected rows (0 = version mismatch, 1 = success)
     */
    public function updateWithVersionCheck(int $roomId, int $expectedVersion, array $data): int;

    /**
     * Atomic delete with optimistic lock version check.
     *
     * Mirrors: DB::table('rooms')->where('id', ...)->where('lock_version', ...)->delete()
     *
     * @return int Number of affected rows (0 = version mismatch, 1 = success)
     */
    public function deleteWithVersionCheck(int $roomId, int $expectedVersion): int;

    /**
     * Refresh a room model instance from database.
     *
     * Mirrors: $room->refresh()
     */
    public function refresh(Room $room): Room;
}
