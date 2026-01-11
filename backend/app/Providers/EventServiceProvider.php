<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Database\Events\QueryExecuted;
use App\Events\BookingCreated;
use App\Events\BookingUpdated;
use App\Events\BookingDeleted;
use App\Events\BookingCancelled;
use App\Listeners\InvalidateCacheOnBookingChange;
use App\Listeners\QueryDebuggerListener;
use App\Listeners\SendBookingConfirmation;
use App\Listeners\SendBookingCancellation;
use App\Listeners\SendBookingUpdateNotification;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
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
    ];

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
