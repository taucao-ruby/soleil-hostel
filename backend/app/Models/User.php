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
    // 
    // SECURITY: All role checks MUST use these methods.
    // NEVER compare $user->role to strings directly.
    // The role attribute is cast to UserRole enum for type safety.

    /**
     * Check if the user has the ADMIN role.
     * 
     * Use for: Full administrative access, user management, system config.
     */
    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN;
    }

    /**
     * Check if the user has moderator-level access or higher.
     * 
     * Returns true for: MODERATOR, ADMIN
     * Use for: Content moderation, viewing all bookings, approving reviews.
     */
    public function isModerator(): bool
    {
        return $this->isAtLeast(UserRole::MODERATOR);
    }

    /**
     * Check if the user is a regular user (lowest privilege level).
     * 
     * Returns true ONLY for: USER
     * Use for: Checking if user has no elevated privileges.
     */
    public function isUser(): bool
    {
        return $this->role === UserRole::USER;
    }

    /**
     * Check if the user has the exact specified role.
     * 
     * Type-safe single role check using the UserRole enum.
     * 
     * @param UserRole $role The role to check against
     */
    public function hasRole(UserRole $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if the user has any of the specified roles.
     * 
     * Type-safe multi-role check. Useful for policies that allow
     * multiple roles to perform an action.
     * 
     * @param array<UserRole> $roles Array of UserRole enum cases
     * 
     * @example $user->hasAnyRole([UserRole::ADMIN, UserRole::MODERATOR])
     */
    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /**
     * Check if the user has at least the given role level.
     * 
     * Role Hierarchy (lowest to highest):
     *   USER (1) < MODERATOR (2) < ADMIN (3)
     * 
     * This enables "role inheritance" - higher roles inherit lower role permissions.
     * 
     * @param UserRole $role The minimum role level required
     * 
     * @example $user->isAtLeast(UserRole::MODERATOR) // true for MODERATOR and ADMIN
     */
    public function isAtLeast(UserRole $role): bool
    {
        static $levels = null;
        
        // Cache level mapping for performance
        $levels ??= [
            UserRole::USER->value => 1,
            UserRole::MODERATOR->value => 2,
            UserRole::ADMIN->value => 3,
        ];

        return $levels[$this->role->value] >= $levels[$role->value];
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
