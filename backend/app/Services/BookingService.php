<?php

namespace App\Services;

use App\Models\Booking;
use App\Traits\HasCacheTagSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BookingService
{
    use HasCacheTagSupport;

    private const CACHE_TTL_USER_BOOKINGS = 300;  // 5 minutes
    private const CACHE_TTL_BOOKING = 600;        // 10 minutes
    private const CACHE_TTL_TRASHED = 180;        // 3 minutes (shorter for admin views)
    private const CACHE_TAG_BOOKINGS = 'bookings';
    private const CACHE_TAG_USER = 'user-bookings';
    private const CACHE_TAG_TRASHED = 'trashed-bookings';

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
                    ->select(['id', 'room_id', 'user_id', 'check_in', 'check_out', 'status', 'guest_name', 'guest_email', 'created_at', 'updated_at'])
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
                    ->select(['id', 'room_id', 'user_id', 'check_in', 'check_out', 'status', 'guest_name', 'guest_email', 'created_at', 'updated_at'])
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
                    ->select(['id', 'room_id', 'user_id', 'check_in', 'check_out', 'status', 'guest_name', 'guest_email', 'created_at', 'updated_at'])
                    ->find($bookingId)
            );
        }

        return Cache::tags([self::CACHE_TAG_BOOKINGS, "booking-{$bookingId}"])
            ->remember(
                $cacheKey,
                self::CACHE_TTL_BOOKING,
                fn() => Booking::with(['room', 'user'])
                    ->select(['id', 'room_id', 'user_id', 'check_in', 'check_out', 'status', 'guest_name', 'guest_email', 'created_at', 'updated_at'])
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

    // ===== SOFT DELETE METHODS =====

    /**
     * Soft delete a booking with audit trail.
     * 
     * Records who deleted the booking for compliance and audit purposes.
     * Does NOT permanently remove the record from database.
     * 
     * @param Booking $booking The booking to soft delete
     * @param int|null $deletedByUserId User ID who is deleting (defaults to auth user)
     * @return bool Success status
     */
    public function softDelete(Booking $booking, ?int $deletedByUserId = null): bool
    {
        $userId = $booking->user_id;
        $bookingId = $booking->id;
        
        $result = $booking->softDeleteWithAudit($deletedByUserId);
        
        if ($result) {
            $this->invalidateBooking($bookingId, $userId);
            $this->invalidateTrashedBookings();
            Log::info("Booking {$bookingId} soft deleted by user " . ($deletedByUserId ?? auth()->id()));
        }
        
        return $result;
    }

    /**
     * Restore a soft deleted booking.
     * 
     * Clears the audit trail (deleted_by, deleted_at) and makes booking active again.
     * Only authorized admins should call this method.
     * 
     * @param Booking $booking The trashed booking to restore
     * @return bool Success status
     */
    public function restore(Booking $booking): bool
    {
        if (!$booking->trashed()) {
            return false;
        }
        
        $result = $booking->restoreWithAudit();
        
        if ($result) {
            $this->invalidateBooking($booking->id, $booking->user_id);
            $this->invalidateTrashedBookings();
            Log::info("Booking {$booking->id} restored by user " . auth()->id());
        }
        
        return $result;
    }

    /**
     * Permanently delete a soft deleted booking (force delete).
     * 
     * ⚠️ WARNING: This PERMANENTLY removes the record from database.
     * Only use for compliance requirements (e.g., GDPR "right to be forgotten").
     * Should be restricted to super admins only.
     * 
     * @param Booking $booking The trashed booking to permanently delete
     * @return bool Success status
     */
    public function forceDelete(Booking $booking): bool
    {
        if (!$booking->trashed()) {
            Log::warning("Attempted to force delete non-trashed booking {$booking->id}");
            return false;
        }
        
        $bookingId = $booking->id;
        $userId = $booking->user_id;
        
        $result = $booking->forceDelete();
        
        if ($result) {
            $this->invalidateBooking($bookingId, $userId);
            $this->invalidateTrashedBookings();
            Log::warning("Booking {$bookingId} PERMANENTLY deleted by user " . auth()->id());
        }
        
        return $result;
    }

    /**
     * Get all trashed bookings for admin view.
     * 
     * Cache Strategy:
     * - Key: bookings:trashed:page-{page}
     * - Tags: ['trashed-bookings']
     * - TTL: 3m (shorter because admin actions may change frequently)
     * 
     * @param int $page Page number
     * @return Collection
     */
    public function getTrashedBookings(int $page = 1): Collection
    {
        $cacheKey = "bookings:trashed:page-{$page}";

        if (!$this->supportsTags()) {
            return Cache::remember(
                $cacheKey,
                self::CACHE_TTL_TRASHED,
                fn() => Booking::onlyTrashed()
                    ->with([
                        'room' => fn($q) => $q->select(['id', 'name', 'price', 'created_at', 'updated_at']),
                        'user' => fn($q) => $q->select(['id', 'name', 'email', 'role', 'created_at', 'updated_at']),
                        'deletedBy' => fn($q) => $q->select(['id', 'name', 'email', 'role', 'created_at', 'updated_at']),
                    ])
                    ->orderBy('deleted_at', 'desc')
                    ->get()
            );
        }

        return Cache::tags([self::CACHE_TAG_TRASHED])
            ->remember(
                $cacheKey,
                self::CACHE_TTL_TRASHED,
                fn() => Booking::onlyTrashed()
                    ->with([
                        'room' => fn($q) => $q->select(['id', 'name', 'price', 'created_at', 'updated_at']),
                        'user' => fn($q) => $q->select(['id', 'name', 'email', 'role', 'created_at', 'updated_at']),
                        'deletedBy' => fn($q) => $q->select(['id', 'name', 'email', 'role', 'created_at', 'updated_at']),
                    ])
                    ->orderBy('deleted_at', 'desc')
                    ->get()
            );
    }

    /**
     * Get a single trashed booking by ID (for admin restore/purge).
     * 
     * @param int $bookingId
     * @return Booking|null
     */
    public function getTrashedBookingById(int $bookingId): ?Booking
    {
        return Booking::onlyTrashed()
            ->with(['room', 'user', 'deletedBy'])
            ->find($bookingId);
    }

    /**
     * Invalidate trashed bookings cache.
     */
    public function invalidateTrashedBookings(): void
    {
        if ($this->supportsTags()) {
            Cache::tags([self::CACHE_TAG_TRASHED])->flush();
        } else {
            Cache::forget("bookings:trashed:page-1");
        }
        Log::info("Cache invalidated for trashed bookings");
    }
}
