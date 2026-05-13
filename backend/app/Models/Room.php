<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\RoomReadinessStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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
 * @property string|null $image_url
 * @property string $price
 * @property int $max_guests
 * @property string $status
 * @property RoomReadinessStatus $readiness_status
 * @property \Carbon\Carbon|null $readiness_updated_at
 * @property int|null $readiness_updated_by
 * @property string|null $room_type_code
 * @property int|null $room_tier
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
        'image_url',
        'price',
        'max_guests',
        'status',
        'readiness_status',
        'readiness_updated_at',
        'readiness_updated_by',
        'room_type_code',
        'room_tier',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'max_guests' => 'integer',
        'readiness_status' => RoomReadinessStatus::class,
        'readiness_updated_at' => 'datetime',
        'room_tier' => 'integer',
        'lock_version' => 'integer',
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
     * Get active bookings for the room (for availability checking).
     */
    public function activeBookings(): HasMany
    {
        return $this->bookings()
            ->whereIn('status', BookingStatus::ACTIVE_STATUSES);
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
            'rooms.image_url',
            'rooms.price',
            'rooms.max_guests',
            'rooms.room_type_code',
            'rooms.room_tier',
            'rooms.status',
            'rooms.readiness_status',
            'rooms.readiness_updated_at',
            'rooms.readiness_updated_by',
            'rooms.lock_version', // Include for optimistic locking
            'rooms.created_at',
            'rooms.updated_at',
        ]);
    }

    /**
     * Scope: physically active rooms.
     *
     * @deprecated Batch 4 / 3B — prefer scopeBookable() for full bookability or
     *             scopeReady() when you only need the physical readiness gate.
     *             This scope now intentionally references *only* readiness_status
     *             so callers stop coupling to the legacy rooms.status column.
     *             The architecture test in tests/Unit/Architecture/RoomsStatusUsageTest
     *             prevents new callers from being added.
     *
     * Usage: Room::active()->get()
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('readiness_status', RoomReadinessStatus::READY->value);
    }

    /**
     * Scope: rooms eligible to accept a new booking.
     */
    public function scopeBookable(Builder $query): Builder
    {
        return $query
            ->where('status', 'available')
            ->where('readiness_status', RoomReadinessStatus::READY->value)
            ->whereHas('location', fn (Builder $location) => $location->where('is_active', true));
    }

    /**
     * Scope: Filter rooms by location.
     *
     * Usage: Room::atLocation(1)->get()
     */
    public function scopeAtLocation(Builder $query, Location|int $location): Builder
    {
        $locationId = $location instanceof Location ? $location->id : $location;

        return $query->where('location_id', $locationId);
    }

    /**
     * Scope: rooms physically ready for immediate arrival.
     */
    public function scopeReady(Builder $query): Builder
    {
        return $query->where('readiness_status', RoomReadinessStatus::READY->value);
    }

    /**
     * Scope: rooms equivalent to the provided source room.
     */
    public function scopeEquivalentTo(Builder $query, Room $source): Builder
    {
        if ($source->room_type_code === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where('room_type_code', $source->room_type_code)
            ->where('max_guests', '>=', $source->max_guests)
            ->where('id', '!=', $source->id)
            ->where('readiness_status', '!=', RoomReadinessStatus::OUT_OF_SERVICE->value);
    }

    /**
     * Scope: rooms that qualify as an upgrade over the provided source room.
     */
    public function scopeUpgradeOver(Builder $query, Room $source): Builder
    {
        if ($source->room_tier === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->whereNotNull('room_tier')
            ->where('room_tier', '>', $source->room_tier)
            ->where('max_guests', '>=', $source->max_guests)
            ->where('id', '!=', $source->id)
            ->where('readiness_status', '!=', RoomReadinessStatus::OUT_OF_SERVICE->value);
    }

    /**
     * Scope: Available rooms between dates (no overlapping bookings).
     *
     * Uses half-open interval [check_in, check_out) for overlap detection.
     * Allows same-day checkout/checkin.
     *
     * Usage: Room::availableBetween('2026-03-01', '2026-03-05')->get()
     *
     * @param  Builder<Room>  $query
     * @return Builder<Room>
     */
    public function scopeAvailableBetween(Builder $query, string $checkIn, string $checkOut): Builder
    {
        // Normalize to datetime format for consistent comparison across SQLite and PostgreSQL
        $checkInDt = \Carbon\Carbon::parse($checkIn)->startOfDay()->toDateTimeString();
        $checkOutDt = \Carbon\Carbon::parse($checkOut)->startOfDay()->toDateTimeString();

        // Batch 4 / 3B: route through scopeBookable() so the bookability predicate
        // is owned by exactly one scope. scopeBookable already enforces
        // status='available' AND readiness_status='ready' AND location.is_active.
        return $query->bookable()
            ->whereDoesntHave('bookings', function (Builder $q) use ($checkInDt, $checkOutDt) {
                $q->whereIn('status', BookingStatus::ACTIVE_STATUSES)
                    ->where('check_in', '<', $checkOutDt)
                    ->where('check_out', '>', $checkInDt);
            });
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

    /**
     * Determine whether this room can serve as an equivalent replacement for the source room.
     */
    public function isEquivalentTo(Room $other): bool
    {
        return $this->id !== $other->id
            && $this->room_type_code !== null
            && $other->room_type_code !== null
            && $this->room_type_code === $other->room_type_code
            && $this->max_guests >= $other->max_guests;
    }

    /**
     * Total stay price for the given date range, in the same integer unit
     * used by `bookings.amount` (smallest currency unit — for VND that is
     * the đồng itself, matching CreateBookingService::calculateAmount).
     *
     * Returns 0 when check_out is not strictly after check_in. The caller
     * (proposal revalidation, booking creation) is responsible for date
     * sanity — this method is a pure pricing helper.
     */
    public function currentPriceForDates(string $checkIn, string $checkOut): int
    {
        $start = \Carbon\Carbon::parse($checkIn)->startOfDay();
        $end = \Carbon\Carbon::parse($checkOut)->startOfDay();

        $nights = (int) $start->diffInDays($end, false);
        if ($nights <= 0) {
            return 0;
        }

        return (int) round(((float) $this->price) * $nights);
    }

    /**
     * Determine whether this room is an operational upgrade over the source room.
     */
    public function isUpgradeOver(Room $other): bool
    {
        return $this->room_tier !== null
            && $other->room_tier !== null
            && $this->room_tier > $other->room_tier
            && $this->max_guests >= $other->max_guests;
    }

    /**
     * Cross-location equivalent candidates at the given location.
     *
     * @return EloquentCollection<int, self>
     */
    public function equivalentCandidatesAt(Location $location): EloquentCollection
    {
        return self::query()
            ->atLocation($location)
            ->where('location_id', '!=', $this->location_id)
            ->ready()
            ->equivalentTo($this)
            ->orderBy('max_guests')
            ->orderBy('room_tier')
            ->get();
    }

    /**
     * Cross-location upgrade candidates at the given location.
     *
     * @return EloquentCollection<int, self>
     */
    public function upgradeCandidatesAt(Location $location): EloquentCollection
    {
        return self::query()
            ->atLocation($location)
            ->where('location_id', '!=', $this->location_id)
            ->ready()
            ->upgradeOver($this)
            ->orderBy('room_tier')
            ->orderBy('max_guests')
            ->get();
    }
}
