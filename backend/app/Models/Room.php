<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\RoomReadinessStatus;
use App\Enums\RoomTypeCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Room Model
 *
 * Represents a hostel room that can be booked by guests.
 * Implements optimistic locking via lock_version column to prevent
 * race conditions during concurrent updates.
 *
 * @property int $id
 * @property int $location_id
 * @property string $name
 * @property string|null $room_number
 * @property string $description
 * @property string $price
 * @property int $max_guests
 * @property string $status
 * @property RoomTypeCode $room_type_code
 * @property int $room_tier
 * @property RoomReadinessStatus $readiness_status
 * @property int $lock_version Optimistic locking version (starts at 1)
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Location $location
 */
class Room extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     * Note: lock_version is NOT fillable - it's managed internally via query builder.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'location_id',
        'name',
        'room_number',
        'description',
        'price',
        'max_guests',
        'status',
        'room_type_code',
        'room_tier',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'max_guests' => 'integer',
        'lock_version' => 'integer',
        'room_type_code' => RoomTypeCode::class,
        'room_tier' => 'integer',
        'readiness_status' => RoomReadinessStatus::class,
        'readiness_changed_at' => 'datetime',
        'readiness_changed_by' => 'integer',
    ];

    /**
     * The attributes that should be hidden for serialization.
     * Note: lock_version is intentionally NOT hidden - clients need it for updates.
     *
     * @var array<int, string>
     */
    protected $hidden = [];

    /**
     * Get the lock_version attribute with fallback for legacy data.
     *
     * If lock_version is null (legacy data before migration), treat as version 1.
     * This ensures backward compatibility with existing records.
     */
    public function getLockVersionAttribute(?int $value): int
    {
        return $value ?? 1;
    }

    /**
     * Get the location this room belongs to.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get the bookings for the room.
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Get the reviews for the room.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Get all room assignments for this room.
     */
    public function roomAssignments(): HasMany
    {
        return $this->hasMany(RoomAssignment::class);
    }

    /**
     * Get room readiness transition history.
     */
    public function readinessLogs(): HasMany
    {
        return $this->hasMany(RoomReadinessLog::class);
    }

    /**
     * Staff member who last changed readiness.
     */
    public function readinessChangedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'readiness_changed_by');
    }

    /**
     * Get active bookings for the room (for availability checking).
     */
    public function activeBookings(): HasMany
    {
        return $this->bookings()
            ->whereIn('status', [BookingStatus::PENDING, BookingStatus::CONFIRMED]);
    }

    // ===== SCOPES =====

    /**
     * Scope: Load common relationships to prevent N+1
     *
     * Loads bookings count + latest booking for availability display
     * Usage: Room::withCommonRelations()->get()
     */
    public function scopeWithCommonRelations(Builder $query): Builder
    {
        return $query
            ->selectColumns()
            ->withCount('activeBookings');
    }

    /**
     * Scope: Select only commonly needed columns
     *
     * Usage: Room::selectColumns()->get()
     */
    public function scopeSelectColumns(Builder $query): Builder
    {
        return $query->select([
            'rooms.id',
            'rooms.location_id',
            'rooms.name',
            'rooms.room_number',
            'rooms.description',
            'rooms.price',
            'rooms.max_guests',
            'rooms.status',
            'rooms.lock_version', // Include for optimistic locking
            'rooms.created_at',
            'rooms.updated_at',
        ]);
    }

    /**
     * Scope: Only active (available) rooms
     *
     * Usage: Room::active()->get()
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('status', 'available')
            ->where('readiness_status', '!=', RoomReadinessStatus::OUT_OF_SERVICE->value);
    }

    /**
     * Scope: Filter rooms by location.
     *
     * Usage: Room::atLocation(1)->get()
     */
    public function scopeAtLocation(Builder $query, int $locationId): Builder
    {
        return $query->where('location_id', $locationId);
    }

    /**
     * Scope: Available rooms between dates (no overlapping bookings).
     *
     * Uses half-open interval [check_in, check_out) for overlap detection.
     * Allows same-day checkout/checkin.
     *
     * Usage: Room::availableBetween('2026-03-01', '2026-03-05')->get()
     */
    public function scopeAvailableBetween(Builder $query, string $checkIn, string $checkOut): Builder
    {
        // Normalize to datetime format for consistent comparison across SQLite and PostgreSQL
        $checkInDt = \Carbon\Carbon::parse($checkIn)->startOfDay()->toDateTimeString();
        $checkOutDt = \Carbon\Carbon::parse($checkOut)->startOfDay()->toDateTimeString();

        return $query->where('status', 'available')
            ->where('readiness_status', '!=', RoomReadinessStatus::OUT_OF_SERVICE->value)
            ->whereDoesntHave('bookings', function (Builder $q) use ($checkInDt, $checkOutDt) {
                $q->whereIn('status', [BookingStatus::PENDING, BookingStatus::CONFIRMED])
                    ->where('check_in', '<', $checkOutDt)
                    ->where('check_out', '>', $checkInDt);
            });
    }

    // ===== ROOM CLASSIFICATION COMPARISON =====

    /**
     * Check if this room is equivalent to another (same type and tier).
     */
    public function isEquivalentTo(Room $other): bool
    {
        return $this->room_type_code === $other->room_type_code
            && $this->room_tier === $other->room_tier;
    }

    /**
     * Check if this room is a strict upgrade over another (higher tier).
     * Never returns true for equal or lower tier.
     */
    public function isUpgradeOver(Room $other): bool
    {
        return $this->room_tier > $other->room_tier;
    }

    /**
     * Check if this room is equivalent to another regardless of location.
     * Same as isEquivalentTo — location is irrelevant for this check.
     */
    public function isCrossLocationEquivalentTo(Room $other): bool
    {
        return $this->room_type_code === $other->room_type_code
            && $this->room_tier === $other->room_tier;
    }

    // ===== ACCESSORS =====

    /**
     * Display name with room number if available.
     */
    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->room_number
                ? "{$this->name} (#{$this->room_number})"
                : $this->name
        );
    }
}
