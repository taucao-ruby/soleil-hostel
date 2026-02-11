<?php

namespace App\Services;

use App\Exceptions\OptimisticLockException;
use App\Models\Room;
use App\Repositories\Contracts\RoomRepositoryInterface;
use App\Traits\HasCacheTagSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RoomService
{
    use HasCacheTagSupport;

    private const CACHE_TTL_ROOMS = 60;          // 1 minute
    private const CACHE_TAG_ROOMS = 'rooms';

    public function __construct(
        private readonly RoomRepositoryInterface $roomRepository,
        private readonly RoomAvailabilityService $roomAvailabilityService
    ) {
    }

    /**
     * Get room by ID with availability info - CACHED
     * 
     * Cache Strategy:
     * - Key: rooms:id:{id}
     * - Tags: ['rooms', 'room-{id}']
     * - TTL: 60s
     * - Allows granular invalidation: can flush room-1 without flushing all rooms
     */
    public function getRoomById(int $roomId): ?Room
    {
        // Canonical availability/read path lives in RoomAvailabilityService.
        return $this->roomAvailabilityService->getRoomAvailability($roomId);
    }

    /**
     * Find a room by ID (uncached, for write operations).
     *
     * Used by controller for update/delete operations where
     * we need the fresh model instance for authorization and mutation.
     *
     * @param int $roomId
     * @return Room|null
     */
    public function findById(int $roomId): ?Room
    {
        return $this->roomRepository->findByIdWithBookings($roomId);
    }

    /**
     * ===== INVALIDATION METHODS =====
     */

    public function invalidateRoom(int $roomId): void
    {
        if ($this->supportsTags()) {
            Cache::tags([self::CACHE_TAG_ROOMS, "room-{$roomId}"])->flush();
        } else {
            Cache::forget("rooms:id:{$roomId}");
            Cache::forget('rooms:list:all:active');
        }
        $this->roomAvailabilityService->invalidateRoomCache($roomId);
        Log::info("Cache invalidated for room {$roomId}");
    }

    public function invalidateAllRooms(): void
    {
        if ($this->supportsTags()) {
            Cache::tags([self::CACHE_TAG_ROOMS])->flush();
        } else {
            Cache::forget('rooms:list:all:active');
            Cache::flush();
        }
        $this->roomAvailabilityService->invalidateAllCache();
        Log::info("Cache invalidated for all rooms");
    }

    /**
     * ===== INTERNAL DB METHODS =====
     */

    private function fetchRoomsFromDB(): Collection
    {
        return $this->roomRepository->getAllOrderedByName();
    }

    /**
     * ===== OPTIMISTIC LOCKING UPDATE =====
     */

    /**
     * Update a room with optimistic concurrency control.
     *
     * This method implements the "compare-and-swap" pattern:
     * 1. Attempt UPDATE with WHERE lock_version = expectedVersion
     * 2. If rowsAffected = 0 → version mismatch → throw exception
     * 3. If rowsAffected = 1 → success → version was incremented
     *
     * Why atomic update instead of SELECT + compare + UPDATE?
     * - Avoids TOCTOU (time-of-check-time-of-use) race condition
     * - Single query = no window for concurrent modification
     * - No pessimistic locks needed
     *
     * @param Room     $room            The Room model to update
     * @param array    $data            The data to update (validated from request)
     * @param int|null $currentVersion  The lock_version the client expects (from their last read)
     *                                  If null, backward compatible mode: use current DB version
     *
     * @return Room The updated Room model with new lock_version
     *
     * @throws OptimisticLockException If version mismatch detected (concurrent modification)
     */
    public function updateWithOptimisticLock(Room $room, array $data, ?int $currentVersion = null): Room
    {
        // Backward compatibility: if no version provided, fetch current version
        // This allows legacy code paths to continue working temporarily
        // In production, you should eventually require lock_version
        if ($currentVersion === null) {
            $currentVersion = $room->lock_version;
            Log::warning('Room update called without lock_version - using current DB version', [
                'room_id' => $room->id,
                'current_version' => $currentVersion,
            ]);
        }

        // Prepare the data for update, excluding lock_version from $data
        // lock_version is managed by this method, not by the caller
        $updateData = collect($data)
            ->except(['lock_version', 'id'])
            ->toArray();

        // Atomic update with version check and increment
        // This is the key to optimistic locking - single atomic operation
        // Repository handles the raw query to ensure atomicity
        $rowsAffected = $this->roomRepository->updateWithVersionCheck(
            $room->id,
            $currentVersion,
            $updateData
        );

        // If no rows were updated, the version didn't match
        // This means another process updated the room between read and write
        if ($rowsAffected === 0) {
            // Refresh to get the actual current version for logging/debugging
            $this->roomRepository->refresh($room);
            $actualVersion = $room->lock_version;

            Log::warning('Optimistic lock conflict detected', [
                'room_id' => $room->id,
                'expected_version' => $currentVersion,
                'actual_version' => $actualVersion,
            ]);

            throw OptimisticLockException::forRoom(
                $room,
                $currentVersion,
                $actualVersion
            );
        }

        // Success! Refresh the model to get the updated data including new version
        $this->roomRepository->refresh($room);

        // Invalidate cache for this room (important for consistency)
        $this->invalidateRoom($room->id);

        Log::info('Room updated successfully with optimistic locking', [
            'room_id' => $room->id,
            'old_version' => $currentVersion,
            'new_version' => $room->lock_version,
        ]);

        return $room;
    }

    /**
     * Create a new room.
     *
     * New rooms automatically get lock_version = 1 (from DB default).
     *
     * @param array $data Validated room data
     * @return Room The newly created room
     */
    public function createRoom(array $data): Room
    {
        // Ensure lock_version is not set by caller (DB default handles it)
        $createData = collect($data)->except(['lock_version'])->toArray();

        $room = $this->roomRepository->create($createData);

        // Invalidate room list cache
        $this->invalidateAllRooms();

        Log::info('Room created', [
            'room_id' => $room->id,
            'lock_version' => $room->lock_version,
        ]);

        return $room;
    }

    /**
     * Delete a room with optimistic locking check.
     *
     * While deletion conflicts are less common, we still check version
     * to prevent deleting a room that was modified after the user
     * decided to delete it.
     *
     * @param Room     $room            The room to delete
     * @param int|null $currentVersion  Expected version (optional for backward compatibility)
     *
     * @return bool True if deleted successfully
     *
     * @throws OptimisticLockException If version mismatch detected
     */
    public function deleteWithOptimisticLock(Room $room, ?int $currentVersion = null): bool
    {
        // Backward compatibility
        if ($currentVersion === null) {
            $currentVersion = $room->lock_version;
        }

        $rowsAffected = $this->roomRepository->deleteWithVersionCheck(
            $room->id,
            $currentVersion
        );

        if ($rowsAffected === 0) {
            $this->roomRepository->refresh($room);
            
            throw OptimisticLockException::forRoom(
                $room,
                $currentVersion,
                $room->lock_version
            );
        }

        $this->invalidateRoom($room->id);
        $this->invalidateAllRooms();

        Log::info('Room deleted', ['room_id' => $room->id]);

        return true;
    }
}
