<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\StayStatus;
use App\Events\BookingRestored;
use App\Exceptions\BookingRestoreConflictException;
use App\Models\Booking;
use App\Models\Stay;
use App\Notifications\BookingConfirmed;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Traits\HasCacheTagSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class BookingService
{
    use HasCacheTagSupport;

    private const CACHE_TTL_ADMIN_BOOKINGS = 120;  // 2 minutes (admin views)

    private const CACHE_TTL_USER_BOOKINGS = 300;  // 5 minutes

    private const CACHE_TTL_BOOKING = 600;        // 10 minutes

    private const CACHE_TTL_TRASHED = 180;        // 3 minutes (shorter for admin views)

    private const CACHE_TAG_BOOKINGS = 'bookings';

    private const CACHE_TAG_USER = 'user-bookings';

    private const CACHE_TAG_TRASHED = 'trashed-bookings';

    // Rate limiting: max 5 confirmation emails per user per minute
    private const RATE_LIMIT_CONFIRMATIONS_PER_MINUTE = 5;

    // Column list for booking queries (consistent across all queries)
    private const BOOKING_COLUMNS = [
        'id',
        'room_id',
        'user_id',
        'check_in',
        'check_out',
        'status',
        'guest_name',
        'guest_email',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
        'created_at',
        'updated_at',
    ];

    // Payment/refund projection required by booking serializers and cancellation flow
    private const BOOKING_PAYMENT_REFUND_COLUMNS = [
        'amount',
        'payment_intent_id',
        'refund_id',
        'refund_status',
        'refund_amount',
        'refund_error',
    ];

    private static function bookingSelectColumns(): array
    {
        return [...self::BOOKING_COLUMNS, ...self::BOOKING_PAYMENT_REFUND_COLUMNS];
    }

    public function __construct(
        private readonly BookingRepositoryInterface $bookingRepository,
        private readonly RoomAvailabilityService $roomAvailabilityService
    ) {}

    /**
     * Confirm a pending booking and trigger confirmation email notification.
     *
     * Architecture:
     * - Updates booking status to confirmed within a transaction
     * - Dispatches BookingConfirmed notification (queued, afterCommit)
     * - Rate limits per-user to prevent email abuse
     * - Notification is non-blocking: booking is confirmed even if email fails
     *
     * @param  Booking  $booking  The pending booking to confirm
     * @return Booking The confirmed booking
     *
     * @throws \RuntimeException If booking is not in pending status
     *
     * @see docs/backend/BOOKING_CONFIRMATION_NOTIFICATION_ARCHITECTURE.md
     */
    public function confirmBooking(Booking $booking): Booking
    {
        if ($booking->status !== BookingStatus::PENDING) {
            throw new \RuntimeException(
                "Cannot confirm booking: current status is '{$booking->status->value}', expected 'pending'"
            );
        }

        return DB::transaction(function () use ($booking) {
            $booking->update(['status' => BookingStatus::CONFIRMED]);

            // Create the operational stay record (idempotent — skip if already exists).
            // Canonical business hours: 14:00 check-in, 12:00 check-out.
            // Actual timestamps are intentionally left null (filled at front-desk check-in/out).
            Stay::firstOrCreate(
                ['booking_id' => $booking->id],
                [
                    'stay_status' => StayStatus::EXPECTED,
                    'scheduled_check_in_at' => $booking->check_in->copy()->setTime(14, 0, 0),
                    'scheduled_check_out_at' => $booking->check_out->copy()->setTime(12, 0, 0),
                    'actual_check_in_at' => null,
                    'actual_check_out_at' => null,
                ]
            );

            // Invalidate cache
            $this->invalidateBooking($booking->id, $booking->user_id);

            // Rate limit confirmation emails per user
            $rateLimitKey = 'booking-confirm-email:'.$booking->user_id;

            if (RateLimiter::tooManyAttempts($rateLimitKey, self::RATE_LIMIT_CONFIRMATIONS_PER_MINUTE)) {
                Log::warning('Rate limit hit for booking confirmation email', [
                    'user_id' => $booking->user_id,
                    'booking_id' => $booking->id,
                ]);
                // Still confirm booking, just skip notification (business decision: email is non-critical)
            } else {
                RateLimiter::hit($rateLimitKey, decaySeconds: 60);

                // Dispatch notification to user (afterCommit ensures it waits for transaction)
                $booking->user->notify(new BookingConfirmed($booking));

                Log::info('Booking confirmed and notification queued', [
                    'booking_id' => $booking->id,
                    'user_id' => $booking->user_id,
                ]);
            }

            return $booking->fresh();
        });
    }

    /**
     * Cancel a booking with refund processing.
     *
     * Delegates to CancellationService which handles the complete cancellation flow
     * including refund processing, idempotency, and state machine transitions.
     *
     * @param  Booking  $booking  The booking to cancel
     * @param  int|null  $cancelledByUserId  User performing the cancellation
     * @return Booking The cancelled booking
     *
     * @throws \RuntimeException If booking is already cancelled
     */
    public function cancelBooking(Booking $booking, ?int $cancelledByUserId = null): Booking
    {
        if ($booking->status === BookingStatus::CANCELLED) {
            throw new \RuntimeException('Booking is already cancelled');
        }

        $actor = $cancelledByUserId
            ? \App\Models\User::findOrFail($cancelledByUserId)
            : auth()->user();

        return app(CancellationService::class)->cancel($booking, $actor);
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
        if (! $this->supportsTags()) {
            return Cache::remember(
                $cacheKey,
                self::CACHE_TTL_USER_BOOKINGS,
                fn () => Booking::where('user_id', $userId)
                    ->with(['room' => function ($q) {
                        $q->select(['id', 'name', 'description', 'price', 'max_guests', 'status', 'created_at', 'updated_at']);
                    }])
                    ->select(self::bookingSelectColumns())
                    ->orderBy('check_in', 'desc')
                    ->get()
            );
        }

        return Cache::tags([self::CACHE_TAG_USER, "user-bookings-{$userId}"])
            ->remember(
                $cacheKey,
                self::CACHE_TTL_USER_BOOKINGS,
                fn () => Booking::where('user_id', $userId)
                    ->with(['room' => function ($q) {
                        $q->select(['id', 'name', 'description', 'price', 'max_guests', 'status', 'created_at', 'updated_at']);
                    }])
                    ->select(self::bookingSelectColumns())
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
        if (! $this->supportsTags()) {
            return Cache::remember(
                $cacheKey,
                self::CACHE_TTL_BOOKING,
                fn () => Booking::with(['room', 'user'])
                    ->select(self::bookingSelectColumns())
                    ->find($bookingId)
            );
        }

        return Cache::tags([self::CACHE_TAG_BOOKINGS, "booking-{$bookingId}"])
            ->remember(
                $cacheKey,
                self::CACHE_TTL_BOOKING,
                fn () => Booking::with(['room', 'user'])
                    ->select(self::bookingSelectColumns())
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
        Log::info('Cache invalidated for all bookings');
    }

    // ===== SOFT DELETE METHODS =====

    /**
     * Soft delete a booking with audit trail.
     *
     * Records who deleted the booking for compliance and audit purposes.
     * Does NOT permanently remove the record from database.
     *
     * @param  Booking  $booking  The booking to soft delete
     * @param  int|null  $deletedByUserId  User ID who is deleting (defaults to auth user)
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
            Log::info("Booking {$bookingId} soft deleted by user ".($deletedByUserId ?? auth()->id()));
        }

        return $result;
    }

    /**
     * Restore a soft deleted booking.
     *
     * Executes the overlap check and the restore inside a single DB transaction
     * using a pessimistic lock on any overlapping rows. This eliminates the
     * TOCTOU window where two concurrent restores could both pass the pre-lock
     * check and then race to the exclusion constraint.
     *
     * The PostgreSQL exclusion constraint (no_overlapping_bookings) remains the
     * final backstop. If a concurrent restore slips through the lock window
     * (e.g. both started before any overlapping row existed), a QueryException
     * with SQLSTATE 23P01 will propagate to the controller which maps it to 409.
     *
     * @param  Booking  $booking  The trashed booking to restore
     * @return bool Success status (false only if booking was not trashed)
     *
     * @throws BookingRestoreConflictException When an overlapping active booking is detected
     * @throws \Illuminate\Database\QueryException If the DB exclusion constraint fires (23P01)
     */
    public function restore(Booking $booking): bool
    {
        if (! $booking->trashed()) {
            return false;
        }

        $restored = DB::transaction(function () use ($booking) {
            // Lock-aware overlap check: acquires FOR UPDATE on any conflicting rows,
            // preventing concurrent transactions from restoring into the same slot.
            $hasOverlap = $this->bookingRepository->hasOverlappingBookingsWithLock(
                roomId: $booking->room_id,
                checkIn: $booking->check_in,
                checkOut: $booking->check_out,
                excludeBookingId: $booking->id
            );

            if ($hasOverlap) {
                throw new BookingRestoreConflictException($booking);
            }

            return $booking->restoreWithAudit();
        });

        if ($restored) {
            // Invalidate booking-specific caches synchronously
            $this->invalidateBooking($booking->id, $booking->user_id);
            $this->invalidateTrashedBookings();
            // Invalidate room availability cache synchronously so the
            // restored booking immediately re-blocks the room slot
            $this->roomAvailabilityService->invalidateAvailability($booking->room_id);
            // Fire event for queued listener (notification hooks etc.)
            event(new BookingRestored($booking));
            Log::info("Booking {$booking->id} restored by user ".auth()->id());
        }

        return $restored;
    }

    /**
     * Permanently delete a soft deleted booking (force delete).
     *
     * ⚠️ WARNING: This PERMANENTLY removes the record from database.
     * Only use for compliance requirements (e.g., GDPR "right to be forgotten").
     * Should be restricted to super admins only.
     *
     * @param  Booking  $booking  The trashed booking to permanently delete
     * @return bool Success status
     */
    public function forceDelete(Booking $booking): bool
    {
        if (! $booking->trashed()) {
            Log::warning("Attempted to force delete non-trashed booking {$booking->id}");

            return false;
        }

        $bookingId = $booking->id;
        $userId = $booking->user_id;

        $result = $booking->forceDelete();

        if ($result) {
            $this->invalidateBooking($bookingId, $userId);
            $this->invalidateTrashedBookings();
            Log::warning("Booking {$bookingId} PERMANENTLY deleted by user ".auth()->id());
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
     * @param  int  $page  Page number
     */
    public function getTrashedBookings(int $page = 1): Collection
    {
        $cacheKey = "bookings:trashed:page-{$page}";

        if (! $this->supportsTags()) {
            return Cache::remember(
                $cacheKey,
                self::CACHE_TTL_TRASHED,
                fn () => Booking::onlyTrashed()
                    ->with([
                        'room' => fn ($q) => $q->select(['id', 'name', 'price', 'created_at', 'updated_at']),
                        'user' => fn ($q) => $q->select(['id', 'name', 'email', 'role', 'created_at', 'updated_at']),
                        'deletedBy' => fn ($q) => $q->select(['id', 'name', 'email', 'role', 'created_at', 'updated_at']),
                    ])
                    ->orderBy('deleted_at', 'desc')
                    ->get()
            );
        }

        return Cache::tags([self::CACHE_TAG_TRASHED])
            ->remember(
                $cacheKey,
                self::CACHE_TTL_TRASHED,
                fn () => Booking::onlyTrashed()
                    ->with([
                        'room' => fn ($q) => $q->select(['id', 'name', 'price', 'created_at', 'updated_at']),
                        'user' => fn ($q) => $q->select(['id', 'name', 'email', 'role', 'created_at', 'updated_at']),
                        'deletedBy' => fn ($q) => $q->select(['id', 'name', 'email', 'role', 'created_at', 'updated_at']),
                    ])
                    ->orderBy('deleted_at', 'desc')
                    ->get()
            );
    }

    /**
     * Get a single trashed booking by ID (for admin restore/purge).
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
            Cache::forget('bookings:trashed:page-1');
        }
        Log::info('Cache invalidated for trashed bookings');
    }
}
