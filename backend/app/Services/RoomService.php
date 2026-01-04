<?php

namespace App\Services;

use App\Exceptions\OptimisticLockException;
use App\Models\Room;
use App\Traits\HasCacheTagSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RoomService
{
    use HasCacheTagSupport;

    private const CACHE_TTL_ROOMS = 60;          // 1 minute
    private const CACHE_TTL_AVAILABILITY = 30;   // 30 seconds
    private const CACHE_TAG_ROOMS = 'rooms';
    private const CACHE_TAG_AVAILABILITY = 'availability';

    /**
     * Get all active rooms with availability - CACHED
     * 
     * Cache Strategy:
     * - Tag-based: tags(['rooms']) → flush all on any room change
     * - TTL: 60s (rooms list changes rarely)
     * - Fallback: DB query if cache miss
     */
    public function getAllRoomsWithAvailability(): Collection
    {
        $cacheKey = 'rooms:list:all:active';

        if (!$this->supportsTags()) {
            return Cache::remember(
                $cacheKey,
                self::CACHE_TTL_ROOMS,
                fn() => $this->fetchRoomsFromDB()
            );
        }

        return Cache::tags([self::CACHE_TAG_ROOMS])
            ->remember(
                $cacheKey,
                self::CACHE_TTL_ROOMS,
                fn() => $this->fetchRoomsFromDB()
            );
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
        $cacheKey = "rooms:id:{$roomId}";

        if (!$this->supportsTags()) {
            return Cache::remember(
                $cacheKey,
                self::CACHE_TTL_ROOMS,
                fn() => Room::with('bookings')
                    ->select(['id', 'name', 'description', 'price', 'max_guests', 'status', 'created_at', 'updated_at'])
                    ->find($roomId)
            );
        }

        return Cache::tags([self::CACHE_TAG_ROOMS, "room-{$roomId}"])
            ->remember(
                $cacheKey,
                self::CACHE_TTL_ROOMS,
                fn() => Room::with('bookings')
                    ->select(['id', 'name', 'description', 'price', 'max_guests', 'status', 'created_at', 'updated_at'])
                    ->find($roomId)
            );
    }

    /**
     * Check room availability - CACHED with shorter TTL
     * 
     * Cache Strategy:
     * - Key: rooms:availability:{roomId}:{checkIn}:{checkOut}
     * - Tags: ['availability', 'availability-room-{roomId}']
     * - TTL: 30s (critical - bookings change frequently)
     * - Negative cache: cache "false" results too (prevent DB hammering)
     */
    public function isRoomAvailable(int $roomId, string $checkIn, string $checkOut): bool
    {
        $cacheKey = "rooms:availability:{$roomId}:{$checkIn}:{$checkOut}";

        try {
            if ($this->supportsTags()) {
                // Try to acquire lock to prevent thundering herd
                $lockKey = "rooms:availability:lock:{$roomId}";
                $lock = Cache::lock($lockKey, 5);

                if ($lock->get()) {
                    $result = Cache::tags([self::CACHE_TAG_AVAILABILITY, "availability-room-{$roomId}"])
                        ->remember(
                            $cacheKey,
                            self::CACHE_TTL_AVAILABILITY,
                            fn() => $this->checkOverlappingBookings($roomId, $checkIn, $checkOut)
                        );

                    $lock->release();
                    return $result;
                }

                // If lock acquisition fails, fallback to DB (prevent cascading failures)
                Log::warning("Cache lock failed for room availability", ['room_id' => $roomId]);
                return $this->checkOverlappingBookings($roomId, $checkIn, $checkOut);
            } else {
                // For array cache (tests), just use simple remember without tags
                return Cache::remember(
                    $cacheKey,
                    self::CACHE_TTL_AVAILABILITY,
                    fn() => $this->checkOverlappingBookings($roomId, $checkIn, $checkOut)
                );
            }

        } catch (\Exception $e) {
            Log::error("Cache availability check failed: {$e->getMessage()}");
            // Fallback to direct DB query
            return $this->checkOverlappingBookings($roomId, $checkIn, $checkOut);
        }
    }

    /**
     * Get room availability WITH booking list (for frontend)
     * 
     * Cache Strategy:
     * - Key: rooms:detail:{roomId}:bookings
     * - Includes active bookings list
     * - TTL: 30s (changes frequently)
     */
    public function getRoomDetailWithBookings(int $roomId): ?array
    {
        $cacheKey = "rooms:detail:{$roomId}:bookings";

        if (!$this->supportsTags()) {
            return Cache::remember(
                $cacheKey,
                self::CACHE_TTL_AVAILABILITY,
                function () use ($roomId) {
                    $room = Room::with(['bookings' => function ($q) {
                        $q->where('status', 'confirmed')
                          ->select(['id', 'room_id', 'check_in', 'check_out', 'status']);
                    }])->find($roomId);

                    if (!$room) return null;

                    return [
                        'room' => $room->only(['id', 'name', 'price', 'max_guests']),
                        'bookings' => $room->bookings->map(fn($b) => [
                            'check_in' => $b->check_in->format('Y-m-d'),
                            'check_out' => $b->check_out->format('Y-m-d'),
                        ]),
                    ];
                }
            );
        }

        return Cache::tags([self::CACHE_TAG_AVAILABILITY, "availability-room-{$roomId}"])
            ->remember(
                $cacheKey,
                self::CACHE_TTL_AVAILABILITY,
                function () use ($roomId) {
                    $room = Room::with(['bookings' => function ($q) {
                        $q->where('status', 'confirmed')
                          ->select(['id', 'room_id', 'check_in', 'check_out', 'status']);
                    }])->find($roomId);

                    if (!$room) return null;

                    return [
                        'room' => $room->only(['id', 'name', 'price', 'max_guests']),
                        'bookings' => $room->bookings->map(fn($b) => [
                            'check_in' => $b->check_in->format('Y-m-d'),
                            'check_out' => $b->check_out->format('Y-m-d'),
                        ]),
                    ];
                }
            );
    }

    /**
     * ===== INVALIDATION METHODS =====
     */

    public function invalidateRoom(int $roomId): void
    {
        if ($this->supportsTags()) {
            // Invalidate specific room + availability
            Cache::tags([self::CACHE_TAG_ROOMS, "room-{$roomId}"])->flush();
            Cache::tags(["availability-room-{$roomId}"])->flush();
        } else {
            Cache::forget("rooms:id:{$roomId}");
            Cache::forget('rooms:list:all:active');
        }
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
        Log::info("Cache invalidated for all rooms");
    }

    public function invalidateAvailability(int $roomId): void
    {
        if ($this->supportsTags()) {
            Cache::tags(["availability-room-{$roomId}"])->flush();
        } else {
            Cache::flush(); // Simple flush for array cache
        }
        Log::info("Cache invalidated for room {$roomId} availability");
    }

    /**
     * ===== INTERNAL DB METHODS =====
     */

    private function fetchRoomsFromDB(): Collection
    {
        return Room::select(['id', 'name', 'description', 'price', 'max_guests', 'status', 'created_at', 'updated_at'])
            ->orderBy('name')
            ->get();
    }

    private function checkOverlappingBookings(int $roomId, string $checkIn, string $checkOut): bool
    {
        return !Room::find($roomId)
            ->bookings()
            ->where('status', 'confirmed')
            ->where('check_in', '<', $checkOut)
            ->where('check_out', '>', $checkIn)
            ->exists();
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
        // Uses raw query to ensure atomicity (Eloquent's update() wouldn't work here)
        $rowsAffected = DB::table('rooms')
            ->where('id', $room->id)
            ->where('lock_version', $currentVersion)
            ->update(array_merge($updateData, [
                'lock_version' => DB::raw('lock_version + 1'),
                'updated_at' => now(),
            ]));

        // If no rows were updated, the version didn't match
        // This means another process updated the room between read and write
        if ($rowsAffected === 0) {
            // Refresh to get the actual current version for logging/debugging
            $room->refresh();
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
        $room->refresh();

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

        $room = Room::create($createData);

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

        $rowsAffected = DB::table('rooms')
            ->where('id', $room->id)
            ->where('lock_version', $currentVersion)
            ->delete();

        if ($rowsAffected === 0) {
            $room->refresh();
            
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
