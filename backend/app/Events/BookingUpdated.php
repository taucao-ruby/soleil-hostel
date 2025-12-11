<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;

class BookingUpdated
{
    use Dispatchable, InteractsWithSockets;

    /**
     * Create a new event instance.
     * Accepts both Booking models and stdClass objects to avoid serialization issues
     */
    public function __construct(
        public mixed $booking,
        public mixed $originalBooking
    ) {}
}
