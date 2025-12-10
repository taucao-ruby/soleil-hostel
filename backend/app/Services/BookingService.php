<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BookingService
{
    private const CACHE_TTL_USER_BOOKINGS = 300;  // 5 minutes
    private const CACHE_TTL_BOOKING = 600;        // 10 minutes
    private const CACHE_TAG_BOOKINGS = 'bookings';
    private const CACHE_TAG_USER = 'user-bookings';
    
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
     * Get user's bookings - CACHED
     * 
     * Cache Strategy:
     * - Key: bookings:user:{userId}:page-{page}
     * - Tags: ['user-bookings', 'user-bookings-{userId}']
     * - TTL: 5m (user bookings don't change often)
     * - Per-user cache: user can't see other user's bookings
     */
    public function getUserBookings(int $userId, int $page = 1): Collection
    {
        $cacheKey = "bookings:user:{$userId}:page-{$page}";

        // If cache doesn't support tags (e.g., array driver in tests), skip tagging
        if (!$this->supportsTags()) {
            return Cache::remember(
                $cacheKey,
                self::CACHE_TTL_USER_BOOKINGS,
                fn() => Booking::where('user_id', $userId)
                    ->with(['room' => function ($q) {
                        $q->select(['id', 'name', 'description', 'price', 'max_guests', 'status', 'created_at', 'updated_at']);
                    }])
                    ->select(['id', 'room_id', 'user_id', 'check_in', 'check_out', 'status', 'guest_name', 'guest_email', 'nights', 'created_at', 'updated_at'])
                    ->orderBy('check_in', 'desc')
                    ->get()
            );
        }

        return Cache::tags([self::CACHE_TAG_USER, "user-bookings-{$userId}"])
            ->remember(
                $cacheKey,
                self::CACHE_TTL_USER_BOOKINGS,
                fn() => Booking::where('user_id', $userId)
                    ->with(['room' => function ($q) {
                        $q->select(['id', 'name', 'description', 'price', 'max_guests', 'status', 'created_at', 'updated_at']);
                    }])
                    ->select(['id', 'room_id', 'user_id', 'check_in', 'check_out', 'status', 'guest_name', 'guest_email', 'nights', 'created_at', 'updated_at'])
                    ->orderBy('check_in', 'desc')
                    ->get()
            );
    }

    /**
     * Get single booking - CACHED
     * 
     * Cache Strategy:
     * - Key: bookings:id:{bookingId}
     * - Tags: ['bookings', 'booking-{bookingId}']
     * - TTL: 10m
     */
    public function getBookingById(int $bookingId): ?Booking
    {
        $cacheKey = "bookings:id:{$bookingId}";

        // If cache doesn't support tags, skip tagging
        if (!$this->supportsTags()) {
            return Cache::remember(
                $cacheKey,
                self::CACHE_TTL_BOOKING,
                fn() => Booking::with(['room', 'user'])
                    ->select(['id', 'room_id', 'user_id', 'check_in', 'check_out', 'status', 'guest_name', 'guest_email', 'nights', 'created_at', 'updated_at'])
                    ->find($bookingId)
            );
        }

        return Cache::tags([self::CACHE_TAG_BOOKINGS, "booking-{$bookingId}"])
            ->remember(
                $cacheKey,
                self::CACHE_TTL_BOOKING,
                fn() => Booking::with(['room', 'user'])
                    ->select(['id', 'room_id', 'user_id', 'check_in', 'check_out', 'status', 'guest_name', 'guest_email', 'nights', 'created_at', 'updated_at'])
                    ->find($bookingId)
            );
    }

    /**
     * ===== INVALIDATION METHODS =====
     */

    public function invalidateUserBookings(int $userId): void
    {
        if ($this->supportsTags()) {
            Cache::tags(["user-bookings-{$userId}"])->flush();
        } else {
            Cache::forget("bookings:user:{$userId}:page-1");
        }
        Log::info("Cache invalidated for user {$userId} bookings");
    }

    public function invalidateBooking(int $bookingId, int $userId): void
    {
        if ($this->supportsTags()) {
            Cache::tags(["booking-{$bookingId}"])->flush();
        } else {
            Cache::forget("bookings:id:{$bookingId}");
        }
        $this->invalidateUserBookings($userId);
        Log::info("Cache invalidated for booking {$bookingId}");
    }

    public function invalidateAllBookings(): void
    {
        if ($this->supportsTags()) {
            Cache::tags([self::CACHE_TAG_BOOKINGS])->flush();
        } else {
            // For array cache, just clear all bookings keys
            Cache::flush();
        }
        Log::info("Cache invalidated for all bookings");
    }
}
