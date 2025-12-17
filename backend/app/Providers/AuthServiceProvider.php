<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use App\Policies\BookingPolicy;
use App\Policies\RoomPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Booking::class => BookingPolicy::class,
        Room::class => RoomPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // ========== RBAC Gates ==========
        // These gates provide a centralized way to check permissions
        // without needing to reference specific policies.

        /**
         * Admin-only gate - highest privilege level
         * Use for: User management, system configuration, destructive operations
         */
        Gate::define('admin', function (User $user): bool {
            return $user->isAdmin();
        });

        /**
         * Moderator gate - includes moderators AND admins
         * Use for: Content moderation, viewing all resources, approving content
         */
        Gate::define('moderator', function (User $user): bool {
            return $user->isModerator();
        });

        /**
         * Manage users gate - admin only
         * Use for: CRUD operations on user accounts
         */
        Gate::define('manage-users', function (User $user): bool {
            return $user->isAdmin();
        });

        /**
         * Moderate content gate - moderator level
         * Use for: Approving reviews, managing bookings, content oversight
         */
        Gate::define('moderate-content', function (User $user): bool {
            return $user->isModerator();
        });

        /**
         * View all bookings gate - moderator level
         * Use for: Admin dashboard, reports, booking oversight
         */
        Gate::define('view-all-bookings', function (User $user): bool {
            return $user->isModerator();
        });

        /**
         * Manage rooms gate - admin only
         * Use for: Creating, updating, deleting rooms
         */
        Gate::define('manage-rooms', function (User $user): bool {
            return $user->isAdmin();
        });
    }
}
