<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

/**
 * Room Model
 *
 * Represents a hostel room that can be booked by guests.
 * Implements optimistic locking via lock_version column to prevent
 * race conditions during concurrent updates.
 *
 * @property int         $id
 * @property string      $name
 * @property string      $description
 * @property string      $price
 * @property int         $max_guests
 * @property string      $status
 * @property int         $lock_version  Optimistic locking version (starts at 1)
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
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
        'name',
        'description',
        'price',
        'max_guests',
        'status',
    ];

    /**
     * The attributes that are guarded from mass assignment.
     * lock_version is internal concurrency control - never allow mass assignment.
     *
     * @var array<int, string>
     */
    protected $guarded = [
        'lock_version',
    ];
    
    protected $casts = [
        'price' => 'decimal:2',
        'lock_version' => 'integer', // Ensure version is always an integer
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
     * Get the bookings for the room.
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Get active bookings for the room (for availability checking).
     */
    public function activeBookings(): HasMany
    {
        return $this->bookings()
            ->whereIn('status', Booking::ACTIVE_STATUSES);
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
            'rooms.name',
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
     * Scope: Only active rooms
     * 
     * Usage: Room::active()->get()
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}

