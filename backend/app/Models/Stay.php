<?php

namespace App\Models;

use App\Enums\StayStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Stay Model — operational occupancy lifecycle for a booking.
 *
 * One stay per booking (stays.booking_id is UNIQUE).
 * stay_status tracks the front-desk / operational state of the guest,
 * distinct from booking.status which tracks the commercial reservation state.
 *
 * In-house guest detection: stays.stay_status IN ('in_house', 'late_checkout').
 * Do NOT use a static `users.active` flag for in-house status.
 *
 * @property-read RoomAssignment|null $currentRoomAssignment
 */
class Stay extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'stay_status',
        'scheduled_check_in_at',
        'scheduled_check_out_at',
        'actual_check_in_at',
        'actual_check_out_at',
        'late_checkout_minutes',
        'late_checkout_fee_amount',
        'no_show_at',
        'checked_in_by',
        'checked_out_by',
    ];

    protected $casts = [
        'stay_status' => StayStatus::class,
        'scheduled_check_in_at' => 'datetime',
        'scheduled_check_out_at' => 'datetime',
        'actual_check_in_at' => 'datetime',
        'actual_check_out_at' => 'datetime',
        'no_show_at' => 'datetime',
        'late_checkout_minutes' => 'integer',
        'late_checkout_fee_amount' => 'integer',
    ];

    // ===== RELATIONSHIPS =====

    /**
     * The booking this stay belongs to.
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * All room assignments for this stay (assignment history).
     */
    public function roomAssignments(): HasMany
    {
        return $this->hasMany(RoomAssignment::class);
    }

    /**
     * The current active room assignment (assigned_until IS NULL).
     */
    public function currentRoomAssignment(): HasOne
    {
        return $this->hasOne(RoomAssignment::class)->whereNull('assigned_until');
    }

    /**
     * Service recovery cases linked to this stay.
     */
    public function serviceRecoveryCases(): HasMany
    {
        return $this->hasMany(ServiceRecoveryCase::class);
    }

    /**
     * Staff member who checked the guest in.
     */
    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    /**
     * Staff member who checked the guest out.
     */
    public function checkedOutBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_out_by');
    }

    // ===== SCOPES =====

    /**
     * Scope: guests physically present in the hostel.
     * Covers both normal in_house and late_checkout statuses.
     */
    public function scopeInHouse(Builder $query): Builder
    {
        return $query->whereIn('stay_status', [
            StayStatus::IN_HOUSE->value,
            StayStatus::LATE_CHECKOUT->value,
        ]);
    }

    /**
     * Scope: guests expected to arrive today (status=expected, scheduled check-in is today).
     */
    public function scopeExpectedToday(Builder $query): Builder
    {
        return $query
            ->where('stay_status', StayStatus::EXPECTED->value)
            ->whereDate('scheduled_check_in_at', today());
    }

    /**
     * Scope: guests due to check out today (in_house, scheduled check-out is today).
     */
    public function scopeDueOutToday(Builder $query): Builder
    {
        return $query
            ->where('stay_status', StayStatus::IN_HOUSE->value)
            ->whereDate('scheduled_check_out_at', today());
    }

    /**
     * Scope: guests in late checkout status.
     */
    public function scopeLateCheckout(Builder $query): Builder
    {
        return $query->where('stay_status', StayStatus::LATE_CHECKOUT->value);
    }
}
