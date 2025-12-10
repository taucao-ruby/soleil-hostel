<?php

namespace App\Services;

use App\Models\Room;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class RoomAvailabilityService
{
    /**
     * Cache TTL in seconds (1 hour)
     */
    private const CACHE_TTL = 3600;

    /**
     * Cache tag for room availability
     */
    private const CACHE_TAG = 'room-availability';
    
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
     * Get all active rooms with availability info (cached)
     * 
     * Cache key: room-availability:all
     * Cache time: 1 hour
     * Invalidated by: BookingCreated, BookingUpdated, BookingDeleted events
     */
    public function getAllRoomsWithAvailability(): Collection
    {
        if (!$this->supportsTags()) {
            return Cache::remember(
                'all-rooms-with-availability',
                self::CACHE_TTL,
                fn() => Room::withCommonRelations()
                    ->active()
                    ->orderBy('created_at', 'desc')
                    ->get()
            );
        }
        
        return Cache::tags([self::CACHE_TAG])
            ->remember(
                'all-rooms-with-availability',
                self::CACHE_TTL,
                fn() => Room::withCommonRelations()
                    ->active()
                    ->orderBy('created_at', 'desc')
                    ->get()
            );
    }

    /**
     * Get specific room availability (cached)
     * 
     * Cache key: room-availability:room:{id}
     * Cache time: 1 hour
     * Invalidated by: BookingCreated, BookingUpdated, BookingDeleted events for that room
     */
    public function getRoomAvailability(int $roomId): ?Room
    {
        if (!$this->supportsTags()) {
            return Cache::remember(
                "room-availability:room:{$roomId}",
                self::CACHE_TTL,
                fn() => Room::withCommonRelations()
                    ->find($roomId)
            );
        }
        
        return Cache::tags([self::CACHE_TAG])
            ->remember(
                "room-availability:room:{$roomId}",
                self::CACHE_TTL,
                fn() => Room::withCommonRelations()
                    ->find($roomId)
            );
    }

    /**
     * Check if room is available for date range (cached with specific key per room+dates)
     * 
     * Cache key: room-availability:available:{roomId}:{checkInDate}:{checkOutDate}
     * Cache time: 1 hour
     */
    public function isRoomAvailable(int $roomId, string $checkInDate, string $checkOutDate): bool
    {
        $cacheKey = "room-availability:available:{$roomId}:{$checkInDate}:{$checkOutDate}";
        
        if (!$this->supportsTags()) {
            return Cache::remember(
                $cacheKey,
                self::CACHE_TTL,
                fn() => !Room::find($roomId)
                    ->activeBookings()
                    ->overlappingBookings(
                        roomId: $roomId,
                        checkIn: $checkInDate,
                        checkOut: $checkOutDate
                    )
                    ->exists()
            );
        }
        
        return Cache::tags([self::CACHE_TAG])
            ->remember(
                $cacheKey,
                self::CACHE_TTL,
                fn() => !Room::find($roomId)
                    ->activeBookings()
                    ->overlappingBookings(
                        roomId: $roomId,
                        checkIn: $checkInDate,
                        checkOut: $checkOutDate
                    )
                    ->exists()
            );
    }

    /**
     * Invalidate ALL room availability cache
     * Called when a booking is created/updated/deleted
     */
    public function invalidateAllCache(): void
    {
        if ($this->supportsTags()) {
            Cache::tags([self::CACHE_TAG])->flush();
        } else {
            Cache::forget('all-rooms-with-availability');
            Cache::flush(); // For simplicity, flush all in array cache
        }
    }

    /**
     * Invalidate cache for specific room
     * Called when booking for specific room changes
     */
    public function invalidateRoomCache(int $roomId): void
    {
        if ($this->supportsTags()) {
            Cache::tags([self::CACHE_TAG])
                ->forget("room-availability:room:{$roomId}");
            
            // Also invalidate all rooms cache since count changed
            Cache::tags([self::CACHE_TAG])
                ->forget('all-rooms-with-availability');
        } else {
            Cache::forget("room-availability:room:{$roomId}");
            Cache::forget('all-rooms-with-availability');
        }
    }
}
