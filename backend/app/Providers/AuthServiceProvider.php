<?php

namespace App\Providers;

use App\Models\Booking;
use App\Models\Review;
use App\Models\Room;
use App\Models\User;
use App\Policies\BookingPolicy;
use App\Policies\ReviewPolicy;
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
        Review::class => ReviewPolicy::class,
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
         * Moderator gate - includes moderators AND admins (hierarchy-based)
         * Use for: Route middleware defense-in-depth on moderator+ endpoints
         */
        Gate::define('moderator', fn (User $user): bool => $user->isModerator());

        /**
         * View all bookings gate - moderator+ (CURRENT)
         * Use for: Admin booking index, trashed list, trashed show (read-only)
         */
        Gate::define('view-all-bookings', fn (User $user): bool => $user->isModerator());

        /**
         * Moderate content gate - moderator+ (CURRENT)
         * Use for: Contact message viewing and management
         */
        Gate::define('moderate-content', fn (User $user): bool => $user->isModerator());

        // ========== RESERVED GATES (not yet invoked) ==========
        Gate::define('manage-users', fn (User $user): bool => $user->isAdmin());
        Gate::define('manage-rooms', fn (User $user): bool => $user->isAdmin());

        /**
         * View queue monitoring gate - admin only
         * Use for: Accessing Horizon dashboard, viewing failed jobs, retry operations
         */
        Gate::define('view-queue-monitoring', function (User $user): bool {
            return $user->isAdmin();
        });
    }
}
