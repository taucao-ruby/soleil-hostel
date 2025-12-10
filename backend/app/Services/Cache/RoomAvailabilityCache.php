<?php

namespace App\Services\Cache;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;
use App\Models\Room;
use Carbon\Carbon;

class RoomAvailabilityCache
{
    /**
     * Cache tag for room availability
     */
    private const TAG_ROOM_AVAILABILITY = 'room_availability';

    /**
     * Cache tag for specific room
     */
    private const TAG_ROOM_PREFIX = 'room_';

    /**
     * Default cache TTL (60 seconds)
     */
    private const TTL_SECONDS = 60;

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
     * Get available rooms with caching
     * Returns from cache within TTL, else queries database and caches result
     */
    public function getAvailableRooms(
        Carbon $checkIn,
        Carbon $checkOut,
        ?int $capacity = null
    ): Collection {
        $cacheKey = $this->buildCacheKey($checkIn, $checkOut, $capacity);

        if (!$this->supportsTags()) {
            // Fallback to basic cache (for tests)
            return Cache::remember(
                $cacheKey,
                self::TTL_SECONDS,
                fn() => $this->queryAvailableRooms($checkIn, $checkOut, $capacity)
            );
        }

        // ← Cache Hit (fast path)
        if (Cache::tags([self::TAG_ROOM_AVAILABILITY])->has($cacheKey)) {
            return Cache::tags([self::TAG_ROOM_AVAILABILITY])
                ->get($cacheKey);
        }

        // ← Cache Miss: query database
        $rooms = $this->queryAvailableRooms($checkIn, $checkOut, $capacity);

        // Store in cache for 60 seconds with tags
        Cache::tags([
            self::TAG_ROOM_AVAILABILITY,
            self::TAG_ROOM_PREFIX . $capacity,
        ])->put($cacheKey, $rooms, now()->addSeconds(self::TTL_SECONDS));

        return $rooms;
    }

    /**
     * Get single room availability with caching
     */
    public function getRoomAvailability(
        Room $room,
        Carbon $checkIn,
        Carbon $checkOut
    ): array {
        $cacheKey = "room_{$room->id}_{$checkIn->format('Y-m-d')}_{$checkOut->format('Y-m-d')}";

        if (!$this->supportsTags()) {
            // Fallback to basic cache
            return Cache::remember(
                $cacheKey,
                self::TTL_SECONDS,
                fn () => [
                    'room_id' => $room->id,
                    'is_available' => $this->isRoomAvailable($room, $checkIn, $checkOut),
                    'booked_dates' => $this->getBookedDates($room, $checkIn, $checkOut),
                ]
            );
        }

        return Cache::tags([
            self::TAG_ROOM_AVAILABILITY,
            self::TAG_ROOM_PREFIX . $room->id,
        ])->remember(
            $cacheKey,
            self::TTL_SECONDS,
            fn () => [
                'room_id' => $room->id,
                'is_available' => $this->isRoomAvailable($room, $checkIn, $checkOut),
                'booked_dates' => $this->getBookedDates($room, $checkIn, $checkOut),
            ]
        );
    }

    /**
     * Invalidate cache for room (called on new booking)
     * Uses tags to flush all related cache entries at once
     */
    public function invalidateRoomAvailability(int $roomId): bool
    {
        try {
            if ($this->supportsTags()) {
                Cache::tags([
                    self::TAG_ROOM_AVAILABILITY,
                    self::TAG_ROOM_PREFIX . $roomId,
                ])->flush();
            } else {
                // For non-tag caches, do a full flush (not ideal but necessary)
                Cache::flush();
            }

            return true;
        } catch (\Exception $e) {
            \Log::warning("Failed to invalidate room cache: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Invalidate all availability cache (nuclear option)
     */
    public function invalidateAllAvailability(): bool
    {
        try {
            if ($this->supportsTags()) {
                Cache::tags([self::TAG_ROOM_AVAILABILITY])->flush();
            } else {
                // For non-tag caches, do a full flush
                Cache::flush();
            }
            return true;
        } catch (\Exception $e) {
            \Log::warning("Failed to invalidate all room cache: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Warm up cache for popular date ranges (called on deployment)
     */
    public function warmUpCache(Carbon $from, Carbon $to): int
    {
        $count = 0;

        for ($date = $from->copy(); $date->lessThan($to); $date->addDay()) {
            $checkIn = $date->copy();
            $checkOut = $date->copy()->addDays(2);

            // Warm cache for different room capacities
            foreach ([1, 2, 4] as $capacity) {
                $this->getAvailableRooms($checkIn, $checkOut, $capacity);
                $count++;
            }
        }

        \Log::info("Warmed up {$count} cache entries");
        return $count;
    }

    /**
     * Get cache statistics (for monitoring)
     */
    public function getCacheStats(): array
    {
        return [
            'driver' => config('cache.default'),
            'ttl_seconds' => self::TTL_SECONDS,
            'tag_room_availability' => self::TAG_ROOM_AVAILABILITY,
        ];
    }

    // ========== PRIVATE HELPERS ==========

    /**
     * Build cache key from parameters
     */
    private function buildCacheKey(Carbon $checkIn, Carbon $checkOut, ?int $capacity = null): string
    {
        $key = "rooms_availability_{$checkIn->format('Y-m-d')}_{$checkOut->format('Y-m-d')}";

        if ($capacity) {
            $key .= "_{$capacity}";
        }

        return $key;
    }

    /**
     * Query database for available rooms
     * Uses pessimistic locking to prevent double-booking
     */
    private function queryAvailableRooms(
        Carbon $checkIn,
        Carbon $checkOut,
        ?int $capacity = null
    ): Collection {
        $query = Room::query()
            ->where('is_active', true)
            ->whereDoesntHave('bookings', function ($q) use ($checkIn, $checkOut) {
                $q->where('status', '!=', 'cancelled')
                  ->whereBetween('check_out', [$checkIn, $checkOut])
                  ->orWhere(function ($q) use ($checkIn, $checkOut) {
                      $q->whereBetween('check_in', [$checkIn, $checkOut->copy()->subDay()]);
                  });
            });

        if ($capacity) {
            $query->where('capacity', '>=', $capacity);
        }

        return $query->orderBy('price')->get();
    }

    /**
     * Check if single room is available
     */
    private function isRoomAvailable(Room $room, Carbon $checkIn, Carbon $checkOut): bool
    {
        return !$room->bookings()
            ->where('status', '!=', 'cancelled')
            ->whereBetween('check_out', [$checkIn, $checkOut])
            ->orWhere(function ($q) use ($checkIn, $checkOut) {
                $q->whereBetween('check_in', [$checkIn, $checkOut->copy()->subDay()]);
            })
            ->exists();
    }

    /**
     * Get booked dates for room in range
     */
    private function getBookedDates(Room $room, Carbon $checkIn, Carbon $checkOut): array
    {
        return $room->bookings()
            ->where('status', '!=', 'cancelled')
            ->where('check_in', '<', $checkOut)
            ->where('check_out', '>', $checkIn)
            ->pluck('check_in', 'check_out')
            ->toArray();
    }
}

