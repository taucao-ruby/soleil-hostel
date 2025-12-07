<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * RequestThrottled Event
 * 
 * Fired when a request exceeds rate limits.
 * Used for logging, monitoring, and alerting.
 */
class RequestThrottled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public array $data
    ) {
    }
}
