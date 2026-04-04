<?php

namespace App\Providers;

use App\Events\BookingCancelled;
use App\Events\BookingCreated;
use App\Events\BookingDeleted;
use App\Events\BookingRestored;
use App\Events\BookingUpdated;
use App\Listeners\InvalidateCacheOnBookingChange;
use App\Listeners\QueryDebuggerListener;
use App\Listeners\SendBookingCancellation;
use App\Listeners\SendBookingConfirmation;
use App\Listeners\SendBookingUpdateNotification;
use App\Listeners\SendEmailVerificationCode;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationCode::class,
        ],

        // ========== QUERY DEBUGGING ==========
        QueryExecuted::class => [
            QueryDebuggerListener::class,  // ← Track N+1 queries
        ],

        // ========== BOOKING EVENTS + CACHE INVALIDATION + NOTIFICATIONS ==========
        BookingCreated::class => [
            InvalidateCacheOnBookingChange::class,
            SendBookingConfirmation::class,  // ← Send confirmation email
        ],

        BookingUpdated::class => [
            InvalidateCacheOnBookingChange::class,
            SendBookingUpdateNotification::class,  // ← Send update email
        ],

        BookingDeleted::class => [
            InvalidateCacheOnBookingChange::class,
            SendBookingCancellation::class,  // ← Send cancellation email (soft delete)
        ],

        // Cancellation with refund flow
        BookingCancelled::class => [
            InvalidateCacheOnBookingChange::class,
            SendBookingCancellation::class,  // ← Send cancellation email with refund info
        ],

        // Restore: room availability must be re-blocked (booking is active again)
        BookingRestored::class => [
            InvalidateCacheOnBookingChange::class,
        ],
    ];

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
