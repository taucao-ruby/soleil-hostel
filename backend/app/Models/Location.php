<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Location Model
 *
 * Represents a physical Soleil brand property/location.
 * Each location has multiple rooms and tracks bookings for analytics.
 *
 * @property int         $id
 * @property string      $name
 * @property string      $slug
 * @property string      $address
 * @property string      $city
 * @property string|null $district
 * @property string|null $ward
 * @property string|null $postal_code
 * @property float|null  $latitude
 * @property float|null  $longitude
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $description
 * @property array|null  $amenities
 * @property array|null  $images
 * @property bool        $is_active
 * @property int         $total_rooms
 * @property int         $lock_version
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'address',
        'city',
        'district',
        'ward',
        'postal_code',
        'latitude',
        'longitude',
        'phone',
        'email',
        'description',
        'amenities',
        'images',
        'is_active',
        'total_rooms',
    ];

    protected $guarded = [
        'lock_version',
    ];

    protected $casts = [
        'amenities' => 'array',
        'images' => 'array',
        'is_active' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'total_rooms' => 'integer',
        'lock_version' => 'integer',
    ];

    // ===== RELATIONSHIPS =====

    /**
     * Get all rooms at this location.
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /**
     * Get active (available) rooms at this location.
     */
    public function activeRooms(): HasMany
    {
        return $this->rooms()->where('status', 'available');
    }

    /**
     * Get all bookings at this location (denormalized for analytics).
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    // ===== SCOPES =====

    /**
     * Scope: Only active locations.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Include room counts for listing pages.
     */
    public function scopeWithRoomCounts(Builder $query): Builder
    {
        return $query->withCount([
            'rooms',
            'activeRooms as available_rooms_count',
        ]);
    }

    /**
     * Scope: Select commonly needed columns for listings.
     */
    public function scopeSelectColumns(Builder $query): Builder
    {
        return $query->select([
            'locations.id',
            'locations.name',
            'locations.slug',
            'locations.address',
            'locations.city',
            'locations.district',
            'locations.ward',
            'locations.postal_code',
            'locations.latitude',
            'locations.longitude',
            'locations.phone',
            'locations.email',
            'locations.description',
            'locations.amenities',
            'locations.images',
            'locations.is_active',
            'locations.total_rooms',
            'locations.lock_version',
            'locations.created_at',
            'locations.updated_at',
        ]);
    }

    // ===== ACCESSORS =====

    /**
     * Get full formatted address.
     */
    protected function fullAddress(): Attribute
    {
        return Attribute::make(
            get: fn () => implode(', ', array_filter([
                $this->address,
                $this->ward,
                $this->district,
                $this->city,
                $this->postal_code,
            ]))
        );
    }

    /**
     * Get coordinates as array (for map integration).
     */
    protected function coordinates(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->latitude && $this->longitude
                ? ['lat' => (float) $this->latitude, 'lng' => (float) $this->longitude]
                : null
        );
    }

    /**
     * Get lock_version with fallback for safety.
     */
    public function getLockVersionAttribute(?int $value): int
    {
        return $value ?? 1;
    }
}
