<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Booking;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a booking is cancelled.
 *
 * Listeners:
 * - SendBookingCancellation: Queues cancellation email notification
 * - InvalidateCacheOnBookingChange: Clears booking/room caches
 */
class BookingCancelled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Booking $booking,
    ) {}
}
