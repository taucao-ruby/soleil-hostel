<?php

use Laravel\Octane\Events\RequestHandled;
use Laravel\Octane\Events\RequestReceived;
use Laravel\Octane\Events\TickReceived;
use Laravel\Octane\Octane;

/*
|--------------------------------------------------------------------------
| Octane Callbacks
|--------------------------------------------------------------------------
|
| This file is loaded by Octane and will be executed on each request
| allowing you to hook into Octane lifecycle events.
|
*/

/**
 * Handle request received - initialize N+1 tracking
 */
Octane::onRequestReceived(function (RequestReceived $event) {
    // Initialize query tracking for this request
    $queryCount = 0;

    \DB::listen(function ($query) use (&$queryCount) {
        $queryCount++;

        // Store in request context for later analysis
        if ($queryCount > 50) {
            \Log::warning('N+1 Query Threshold Exceeded', [
                'path' => request()->path(),
                'query_count' => $queryCount,
            ]);
        }
    });
});

/**
 * Handle request handled - log metrics
 */
Octane::onRequestHandled(function (RequestHandled $event) {
    // Log request metrics for monitoring
    // This is called after response is sent
});

/**
 * On tick - periodic cleanup
 */
Octane::onTick(function (TickReceived $event) {
    // Run background tasks periodically
    if ($event->index % 300 === 0) { // Every 5 minutes (60 ticks/sec * 300)
        // Cleanup old cache tags
        \Cache::tags(['room-availability'])->flush();
    }
});
