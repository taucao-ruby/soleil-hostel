<?php

namespace App\Services;

use App\Models\Room;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RoomService
{
    private const CACHE_TTL_ROOMS = 60;          // 1 minute
    private const CACHE_TTL_AVAILABILITY = 30;   // 30 seconds
    private const CACHE_TAG_ROOMS = 'rooms';
    private const CACHE_TAG_AVAILABILITY = 'availability';
    
    private static ?bool $cacheSupportsTagsCache = null;

    /**
     * Check if cache supports tagging
     * Array cache (used in tests) doesn't support tags
     */
    private function supportsTags(): bool
    {
        if (self::$cacheSupportsTagsCache !== null) {
            return self::$cacheSupportsTagsCache;
        }
        
        try {
            // Try to create a dummy tag to see if it's supported
            Cache::tags(['dummy-check'])->get('dummy-key');
            self::$cacheSupportsTagsCache = true;
        } catch (\BadMethodCallException $e) {
            self::$cacheSupportsTagsCache = false;
        } catch (\Exception $e) {
            // If any other exception occurs, return true to be safe
            self::$cacheSupportsTagsCache = true;
        }
        
        return self::$cacheSupportsTagsCache;
    }

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
        return Room::where('status', 'active')
            ->select(['id', 'name', 'description', 'price', 'max_guests', 'status', 'created_at'])
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
}
