<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
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
            'role' => UserRole::class,
        ];
    }

    // ===== ROLE HELPER METHODS (RBAC) =====

    /**
     * Check if the user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN;
    }

    /**
     * Check if the user is a moderator.
     */
    public function isModerator(): bool
    {
        return $this->role === UserRole::MODERATOR;
    }

    /**
     * Check if the user is a regular user.
     */
    public function isUser(): bool
    {
        return $this->role === UserRole::USER;
    }

    /**
     * Check if the user has at least the given role level.
     * 
     * Hierarchy: USER (1) < MODERATOR (2) < ADMIN (3)
     * 
     * Example: $user->isAtLeast(UserRole::MODERATOR) 
     *          returns true for MODERATOR and ADMIN
     */
    public function isAtLeast(UserRole $role): bool
    {
        $levels = [
            UserRole::USER->value => 1,
            UserRole::MODERATOR->value => 2,
            UserRole::ADMIN->value => 3,
        ];

        return $levels[$this->role->value] >= $levels[$role->value];
    }

    /**
     * Check if user has any of the given roles.
     * 
     * @param UserRole ...$roles
     */
    public function hasAnyRole(UserRole ...$roles): bool
    {
        return in_array($this->role, $roles, true);
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
