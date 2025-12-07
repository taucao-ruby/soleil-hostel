<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * RateLimiterDegraded Event
 * 
 * Fired when rate limiter falls back from Redis to in-memory storage.
 * Used for alerting and monitoring distributed system health.
 */
class RateLimiterDegraded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public array $data
    ) {
    }
}
