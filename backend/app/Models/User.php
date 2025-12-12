<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Override createToken to handle abilities JSON encoding properly
     * for SQLite compatibility
     */
    public function createToken($name, $abilities = ['*'], $expiresAt = null)
    {
        // Generate token
        $token = Str::random(80);
        
        // Prepare abilities as JSON string
        $abilitiesJson = is_array($abilities) 
            ? json_encode($abilities)
            : $abilities;
        
        // Use DB directly to avoid double-encoding
        $tokenId = \Illuminate\Support\Facades\DB::table('personal_access_tokens')->insertGetId([
            'tokenable_type' => static::class,
            'tokenable_id' => $this->getKey(),
            'name' => $name,
            'token' => hash('sha256', $token),
            'abilities' => $abilitiesJson,
            'expires_at' => $expiresAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Load the created model
        $tokenModel = PersonalAccessToken::find($tokenId);
        
        // Return token with plain text for API response
        return new \Laravel\Sanctum\NewAccessToken($tokenModel, $token);
    }

    /**
     * Get the bookings for the user.
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    // ===== SCOPES =====

    /**
     * Scope: Select only commonly needed columns
     * 
     * Usage: User::selectColumns()->get()
     */
    public function scopeSelectColumns(Builder $query): Builder
    {
        return $query->select([
            'users.id',
            'users.name',
            'users.email',
            'users.role',
            'users.created_at',
            'users.updated_at',
        ]);
    }
}
