<?php

namespace App\Services;

use App\Models\Room;
use App\Repositories\Contracts\RoomRepositoryInterface;
use App\Traits\HasCacheTagSupport;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Single source of truth for all room availability logic.
 *
 * Consolidated from RoomService (availability methods) + RoomAvailabilityService.
 * Unified cache tags: 'room-availability' and 'room-availability-{id}'.
 * Unified TTL: 300 seconds (5 minutes).
 */
class RoomAvailabilityService
{
    use HasCacheTagSupport;

    /**
     * Cache TTL in seconds (5 minutes — balanced between freshness and performance)
     */
    private const CACHE_TTL = 300;

    /**
     * Cache tag for room availability
     */
    private const CACHE_TAG = 'room-availability';

    public function __construct(
        private readonly RoomRepositoryInterface $roomRepository
    ) {
    }

    /**
     * Get all active rooms with availability info (cached)
     * 
     * Cache key: room-availability:all
     * Cache time: 5 minutes
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
     * Cache time: 5 minutes
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
        
        return Cache::tags([self::CACHE_TAG, "room-availability-{$roomId}"])
            ->remember(
                "room-availability:room:{$roomId}",
                self::CACHE_TTL,
                fn() => Room::withCommonRelations()
                    ->find($roomId)
            );
    }

    /**
     * Get room availability WITH booking list (for frontend)
     * Moved from RoomService for consolidation.
     * 
     * Cache Strategy:
     * - Key: room-availability:detail:{roomId}:bookings
     * - Includes active bookings list
     * - TTL: 300s (5 minutes)
     */
    public function getRoomDetailWithBookings(int $roomId): ?array
    {
        $cacheKey = "room-availability:detail:{$roomId}:bookings";

        $fetchData = function () use ($roomId) {
            $room = $this->roomRepository->findByIdWithConfirmedBookings($roomId);

            if (!$room) return null;

            return [
                'room' => $room->only(['id', 'name', 'price', 'max_guests']),
                'bookings' => $room->bookings->map(fn($b) => [
                    'check_in' => $b->check_in->format('Y-m-d'),
                    'check_out' => $b->check_out->format('Y-m-d'),
                ]),
            ];
        };

        if (!$this->supportsTags()) {
            return Cache::remember($cacheKey, self::CACHE_TTL, $fetchData);
        }

        return Cache::tags([self::CACHE_TAG, "room-availability-{$roomId}"])
            ->remember($cacheKey, self::CACHE_TTL, $fetchData);
    }

    /**
     * Check if room is available for date range (cached with specific key per room+dates)
     * 
     * Cache key: room-availability:available:{roomId}:{checkInDate}:{checkOutDate}
     * Cache time: 5 minutes
     */
    public function isRoomAvailable(int $roomId, string $checkInDate, string $checkOutDate): bool
    {
        $cacheKey = "room-availability:available:{$roomId}:{$checkInDate}:{$checkOutDate}";
        
        $checkAvailability = function () use ($roomId, $checkInDate, $checkOutDate) {
            $room = Room::find($roomId);
            if (!$room) {
                return false;
            }
            return !$room->activeBookings()
                ->overlappingBookings(
                    roomId: $roomId,
                    checkIn: $checkInDate,
                    checkOut: $checkOutDate
                )
                ->exists();
        };

        if (!$this->supportsTags()) {
            return Cache::remember(
                $cacheKey,
                self::CACHE_TTL,
                $checkAvailability
            );
        }
        
        return Cache::tags([self::CACHE_TAG, "room-availability-{$roomId}"])
            ->remember(
                $cacheKey,
                self::CACHE_TTL,
                $checkAvailability
            );
    }

    /**
     * Check for overlapping bookings (moved from RoomService).
     * Returns true if room is available (no overlapping confirmed bookings).
     */
    public function checkOverlappingBookings(int $roomId, string $checkIn, string $checkOut): bool
    {
        return !$this->roomRepository->hasOverlappingConfirmedBookings($roomId, $checkIn, $checkOut);
    }

    /**
     * Invalidate availability cache for a specific room.
     * Single entry point for all booking-related cache invalidation.
     * Called by InvalidateCacheOnBookingChange listener.
     */
    public function invalidateAvailability(int $roomId): void
    {
        if ($this->supportsTags()) {
            // Flush room-specific availability cache
            Cache::tags(["room-availability-{$roomId}"])->flush();
            // Also flush the global all-rooms cache
            Cache::tags([self::CACHE_TAG])->flush();
        } else {
            Cache::forget("room-availability:room:{$roomId}");
            Cache::forget("room-availability:detail:{$roomId}:bookings");
            Cache::forget('all-rooms-with-availability');
        }
        Log::info("Availability cache invalidated for room {$roomId}");
    }

    /**
     * Invalidate ALL room availability cache.
     * Called when a booking is created/updated/deleted.
     */
    public function invalidateAllCache(): void
    {
        if ($this->supportsTags()) {
            Cache::tags([self::CACHE_TAG])->flush();
        } else {
            Cache::forget('all-rooms-with-availability');
            Cache::flush();
        }
        Log::info("All room availability cache invalidated");
    }

    /**
     * Invalidate cache for specific room.
     * Called when booking for specific room changes.
     */
    public function invalidateRoomCache(int $roomId): void
    {
        $this->invalidateAvailability($roomId);
    }
}
