<?php

namespace App\Repositories;

use App\Models\Room;
use App\Repositories\Contracts\RoomRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * EloquentRoomRepository
 *
 * Pure data access implementation for the Room domain.
 * Each method reproduces the exact query sequence previously present in RoomService.
 *
 * Constraints:
 * - NO business rules, pricing logic, or availability calculations
 * - NO transactions or cross-entity coordination
 * - NO side effects beyond current Eloquent behavior
 * - Returns same Eloquent model instances or collections as direct calls
 * - Respects existing global scopes, soft delete behavior, and eager loading
 */
class EloquentRoomRepository implements RoomRepositoryInterface
{
    /**
     * Find a room by ID with bookings relationship eager loaded.
     *
     * Reproduces: Room::with('bookings')
     *     ->select(['id', 'name', 'description', 'price', 'max_guests', 'status', 'created_at', 'updated_at'])
     *     ->find($roomId)
     *
     * @param int $roomId
     * @return Room|null
     */
    public function findByIdWithBookings(int $roomId): ?Room
    {
        return Room::with('bookings')
            ->select(['id', 'name', 'description', 'price', 'max_guests', 'status', 'created_at', 'updated_at'])
            ->find($roomId);
    }

    /**
     * Find a room by ID with confirmed bookings filtered.
     *
     * Reproduces: Room::with(['bookings' => function ($q) {
     *     $q->where('status', 'confirmed')
     *       ->select(['id', 'room_id', 'check_in', 'check_out', 'status']);
     * }])->find($roomId)
     *
     * @param int $roomId
     * @return Room|null
     */
    public function findByIdWithConfirmedBookings(int $roomId): ?Room
    {
        return Room::with(['bookings' => function ($q) {
            $q->where('status', 'confirmed')
              ->select(['id', 'room_id', 'check_in', 'check_out', 'status']);
        }])->find($roomId);
    }

    /**
     * Find a room by ID (simple find).
     *
     * Reproduces: Room::find($roomId)
     *
     * @param int $roomId
     * @return Room|null
     */
    public function findById(int $roomId): ?Room
    {
        return Room::find($roomId);
    }

    /**
     * Get all rooms ordered by name.
     *
     * Reproduces: Room::select(['id', 'name', 'description', 'price', 'max_guests', 'status', 'created_at', 'updated_at'])
     *     ->orderBy('name')
     *     ->get()
     *
     * @return Collection<int, Room>
     */
    public function getAllOrderedByName(): Collection
    {
        return Room::select(['id', 'name', 'description', 'price', 'max_guests', 'status', 'created_at', 'updated_at'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Check if overlapping confirmed bookings exist for a room.
     *
     * Reproduces: Room::find($roomId)
     *     ->bookings()
     *     ->where('status', 'confirmed')
     *     ->where('check_in', '<', $checkOut)
     *     ->where('check_out', '>', $checkIn)
     *     ->exists()
     *
     * @param int $roomId
     * @param string $checkIn
     * @param string $checkOut
     * @return bool True if overlapping bookings exist
     */
    public function hasOverlappingConfirmedBookings(int $roomId, string $checkIn, string $checkOut): bool
    {
        $room = Room::find($roomId);

        if ($room === null) {
            return false;
        }

        return $room->bookings()
            ->where('status', 'confirmed')
            ->where('check_in', '<', $checkOut)
            ->where('check_out', '>', $checkIn)
            ->exists();
    }

    /**
     * Create a new room.
     *
     * Reproduces: Room::create($data)
     *
     * @param array $data Validated room data (lock_version excluded)
     * @return Room
     */
    public function create(array $data): Room
    {
        return Room::create($data);
    }

    /**
     * Atomic update with optimistic lock version check.
     *
     * Reproduces: DB::table('rooms')
     *     ->where('id', $room->id)
     *     ->where('lock_version', $currentVersion)
     *     ->update(array_merge($updateData, [
     *         'lock_version' => DB::raw('lock_version + 1'),
     *         'updated_at' => now(),
     *     ]))
     *
     * @param int $roomId
     * @param int $expectedVersion
     * @param array $data Data to update (lock_version excluded, managed internally)
     * @return int Number of affected rows (0 = version mismatch, 1 = success)
     */
    public function updateWithVersionCheck(int $roomId, int $expectedVersion, array $data): int
    {
        return DB::table('rooms')
            ->where('id', $roomId)
            ->where('lock_version', $expectedVersion)
            ->update(array_merge($data, [
                'lock_version' => DB::raw('lock_version + 1'),
                'updated_at' => now(),
            ]));
    }

    /**
     * Atomic delete with optimistic lock version check.
     *
     * Reproduces: DB::table('rooms')
     *     ->where('id', $room->id)
     *     ->where('lock_version', $currentVersion)
     *     ->delete()
     *
     * @param int $roomId
     * @param int $expectedVersion
     * @return int Number of affected rows (0 = version mismatch, 1 = success)
     */
    public function deleteWithVersionCheck(int $roomId, int $expectedVersion): int
    {
        return DB::table('rooms')
            ->where('id', $roomId)
            ->where('lock_version', $expectedVersion)
            ->delete();
    }

    /**
     * Refresh a room model instance from database.
     *
     * Reproduces: $room->refresh()
     *
     * @param Room $room
     * @return Room
     */
    public function refresh(Room $room): Room
    {
        $room->refresh();
        return $room;
    }
}
