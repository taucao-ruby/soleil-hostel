<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Traits\Purifiable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use HasFactory, Purifiable, SoftDeletes;

    protected $fillable = [
        'room_id',
        'location_id',
        'check_in',
        'check_out',
        'guest_name',
        'guest_email',
        'status',
        'user_id',
        'deleted_by',
        // Payment fields
        'payment_intent_id',
        'amount',
        // Deposit tracking (operational — NOT recognized revenue)
        'deposit_amount',
        'deposit_collected_at',
        // Refund fields
        'refund_id',
        'refund_status',
        'refund_amount',
        'refund_error',
        // Cancellation audit
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
    ];

    protected $casts = [
        'check_in' => 'date',
        'check_out' => 'date',
        'deleted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'status' => BookingStatus::class,
        'amount' => 'integer',
        'deposit_amount' => 'integer',
        'deposit_collected_at' => 'datetime',
        'refund_amount' => 'integer',
    ];

    /**
     * Auto-purify these fields when saving
     * Uses HTML Purifier whitelist, not regex blacklist
     * (Regex XSS = 99% bypass. HTML Purifier = 0% bypass)
     */
    public function getPurifiableFields(): array
    {
        return ['guest_name'];
    }

    /** Active booking statuses for query scopes. */
    public const ACTIVE_STATUSES = [BookingStatus::PENDING, BookingStatus::CONFIRMED];

    /**
     * Get the room that owns the booking.
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Get the location for this booking (denormalized for analytics).
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get the user that made the booking.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who cancelled the booking.
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * Get the operational stay for this booking (nullable — exists once stay tracking begins).
     */
    public function stay(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Stay::class, 'booking_id');
    }

    /**
     * Get the review for this booking.
     *
     * One-to-one: Each booking can have at most one review.
     * Used by ReviewPolicy for uniqueness check without DB query.
     */
    public function review(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Review::class, 'booking_id');
    }

    // ===== PAYMENT / REFUND METHODS =====

    /**
     * Check if booking has a refundable payment.
     */
    public function isRefundable(): bool
    {
        return $this->payment_intent_id !== null
            && $this->refund_id === null
            && $this->status->isCancellable();
    }

    /**
     * Calculate refund amount based on cancellation policy.
     *
     * @return int Amount in cents
     */
    public function calculateRefundAmount(): int
    {
        if (! $this->amount || $this->amount <= 0) {
            return 0;
        }

        $hoursUntilCheckIn = now()->diffInHours($this->check_in, false);
        $config = config('booking.cancellation');

        // Already past check-in
        if ($hoursUntilCheckIn < 0) {
            return 0;
        }

        // Full refund window
        if ($hoursUntilCheckIn >= $config['full_refund_hours']) {
            $refundPct = 100;
        }
        // Partial refund window
        elseif ($hoursUntilCheckIn >= $config['partial_refund_hours']) {
            $refundPct = $config['partial_refund_pct'];
        }
        // No refund zone
        else {
            return 0;
        }

        // Apply cancellation fee if enabled
        if ($config['allow_fee']) {
            $refundPct = max(0, $refundPct - $config['fee_pct']);
        }

        return (int) ($this->amount * $refundPct / 100);
    }

    /**
     * Get refund percentage based on current time.
     *
     * @return int Percentage (0-100)
     */
    public function getRefundPercentage(): int
    {
        $hoursUntilCheckIn = now()->diffInHours($this->check_in, false);
        $config = config('booking.cancellation');

        if ($hoursUntilCheckIn < 0) {
            return 0;
        }

        if ($hoursUntilCheckIn >= $config['full_refund_hours']) {
            return 100;
        }

        if ($hoursUntilCheckIn >= $config['partial_refund_hours']) {
            return $config['partial_refund_pct'];
        }

        return 0;
    }

    /**
     * Check if refund is in a failed state.
     */
    public function hasFailedRefund(): bool
    {
        return $this->status === BookingStatus::REFUND_FAILED
            || $this->refund_status === 'failed';
    }

    // ===== SCOPES =====

    /**
     * Scope: Load common relationships with column selection to prevent N+1
     *
     * This is the PRIMARY scope to use in controllers. Loads room + user with only needed columns.
     *
     * Usage: Booking::withCommonRelations()->get()
     */
    public function scopeWithCommonRelations(Builder $query): Builder
    {
        return $query
            ->with([
                'room' => fn ($q) => $q->selectColumns(),
                'user' => fn ($q) => $q->selectColumns(),
            ]);
    }

    /**
     * Scope: Select only commonly needed columns to reduce memory + bandwidth
     *
     * Usage: Booking::selectColumns()->get()
     */
    public function scopeSelectColumns(Builder $query): Builder
    {
        return $query->select([
            'bookings.id',
            'bookings.room_id',
            'bookings.user_id',
            'bookings.check_in',
            'bookings.check_out',
            'bookings.guest_name',
            'bookings.guest_email',
            'bookings.status',
            'bookings.amount',
            'bookings.payment_intent_id',
            'bookings.refund_amount',
            'bookings.refund_status',
            'bookings.cancelled_at',
            'bookings.cancelled_by',
            'bookings.cancellation_reason',
            'bookings.created_at',
            'bookings.updated_at',
        ]);
    }

    /**
     * Scope: Find overlapping bookings for a room within a date range
     *
     * Uses half-open interval [check_in, check_out):
     * - Allows book_old.check_out == book_new.check_in (same-day turnover)
     *
     * @param  int  $roomId  Room ID
     * @param  Carbon|\DateTime  $checkIn  New check-in date
     * @param  Carbon|\DateTime  $checkOut  New check-out date
     */
    public function scopeOverlappingBookings(
        Builder $query,
        int $roomId,
        $checkIn,
        $checkOut,
        ?int $excludeBookingId = null
    ): Builder {
        // Ensure parameters are Carbon instances
        $checkIn = $checkIn instanceof Carbon ? $checkIn : Carbon::parse($checkIn);
        $checkOut = $checkOut instanceof Carbon ? $checkOut : Carbon::parse($checkOut);

        // Overlap logic with half-open interval [a1, b1) and [a2, b2):
        // Overlap occurs when: a1 < b2 AND a2 < b1
        //
        // Trong SQL: check_in < check_out_new AND check_out > check_in_new
        return $query
            ->where('room_id', $roomId)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->where('check_in', '<', $checkOut) // Existing booking start < new end date
            ->where('check_out', '>', $checkIn) // Existing booking end > new start date
            ->when($excludeBookingId, fn ($q, $excludeId) => $q->where('id', '!=', $excludeId));
    }

    /**
     * Scope: Filter active bookings (not cancelled)
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    /**
     * Scope: Filter cancelled bookings
     */
    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', BookingStatus::CANCELLED);
    }

    /**
     * Scope: Filter bookings by status
     */
    public function scopeByStatus(Builder $query, BookingStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    // ===== ACCESSORS / MUTATORS =====

    /**
     * Accessor: Check if booking has expired (check_out is in the past)
     */
    public function isExpired(): bool
    {
        return $this->check_out->isPast();
    }

    /**
     * Accessor: Check if booking has started (check_in is past or today)
     */
    public function isStarted(): bool
    {
        return $this->check_in->isPast() || $this->check_in->isToday();
    }

    /**
     * Accessor: Number of nights (duration in nights)
     */
    public function getNightsAttribute(): int
    {
        return $this->check_out->diffInDays($this->check_in);
    }

    /**
     * Accessor: Check if date range is valid (check_out must not equal check_in)
     */
    public function isValidDateRange(): bool
    {
        return $this->check_in->lessThan($this->check_out);
    }

    /**
     * Scope: Acquire FOR UPDATE lock on overlapping bookings
     *
     * Uses pessimistic locking to ensure transaction safety
     * DB locks matching rows, preventing other transactions from modifying them
     */
    public function scopeWithLock(Builder $query): Builder
    {
        return $query->lockForUpdate();
    }

    // ===== SOFT DELETE METHODS =====

    /**
     * Get the user who deleted the booking.
     */
    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /**
     * Soft delete with audit trail - records who deleted and when.
     *
     * @param  int|null  $deletedByUserId  User ID who performed deletion
     */
    public function softDeleteWithAudit(?int $deletedByUserId = null): bool
    {
        $this->deleted_by = $deletedByUserId ?? auth()->id();
        $this->save();

        return $this->delete();
    }

    /**
     * Restore a soft deleted booking and clear audit columns.
     */
    public function restoreWithAudit(): bool
    {
        $this->deleted_by = null;

        return $this->restore();
    }

    /**
     * Scope: Filter overlapping bookings including soft deleted ones.
     * Use this for historical reports where deleted bookings matter.
     */
    public function scopeOverlappingBookingsIncludingTrashed(
        Builder $query,
        int $roomId,
        $checkIn,
        $checkOut,
        ?int $excludeBookingId = null
    ): Builder {
        $checkIn = $checkIn instanceof Carbon ? $checkIn : Carbon::parse($checkIn);
        $checkOut = $checkOut instanceof Carbon ? $checkOut : Carbon::parse($checkOut);

        return $query
            ->withTrashed()
            ->where('room_id', $roomId)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->where('check_in', '<', $checkOut)
            ->where('check_out', '>', $checkIn)
            ->when($excludeBookingId, fn ($q, $excludeId) => $q->where('id', '!=', $excludeId));
    }
}
