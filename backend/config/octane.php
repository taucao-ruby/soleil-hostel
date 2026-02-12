<?php

/**
 * Laravel Octane Configuration for Soleil Hostel
 *
 * Octane = Swoole/RoadRunner + persistent app state
 * Performance: 40ms → 5-10ms latency (4-8x faster)
 *
 * Installation:
 * composer require laravel/octane
 * php artisan octane:install
 *
 * Production deployment:
 * - Use Swoole (better performance) on Linux
 * - Octane watches for file changes (enable in production with care)
 * - Workers pool = CPU cores (auto-detect)
 * - Max connections = 50k (tunable)
 */

use Laravel\Octane\Events\RequestHandled;
use Laravel\Octane\Events\RequestReceived;
use Laravel\Octane\Events\TaskReceived;
use Laravel\Octane\Events\TickReceived;
use Laravel\Octane\Octane;

return [

    /*
    |--------------------------------------------------------------------------
    | Octane Server
    |--------------------------------------------------------------------------
    |
    | This value determines which server will be used to run your Laravel
    | application. By default, we will use the Swoole server. However, you
    | are free to change this to use RoadRunner or another server.
    |
    | Supported: "swoole", "roadrunner"
    |
    */

    'server' => env('OCTANE_SERVER', 'swoole'),

    /*
    |--------------------------------------------------------------------------
    | Host
    |--------------------------------------------------------------------------
    |
    | This is the host which Octane will bind to when serving the
    | application. This host will be used to construct Octane's URL
    | when serving the application in the local environment.
    |
    */

    'host' => env('OCTANE_HOST', '0.0.0.0'),

    /*
    |--------------------------------------------------------------------------
    | Port
    |--------------------------------------------------------------------------
    |
    | This is the port at which Octane will listen for requests. This host
    | will be used to construct Octane's URL when serving the application
    | in the local environment. This may also be set to "auto".
    |
    */

    'port' => env('OCTANE_PORT', 8000),

    /*
    |--------------------------------------------------------------------------
    | Worker Type
    |--------------------------------------------------------------------------
    |
    | This value determines the type of workers that Octane will spawn to
    | handle incoming requests. Swoole supports "sync" and "thread" workers.
    | RoadRunner supports "sync" and "tcp" workers.
    |
    */

    'workers' => env('OCTANE_WORKERS', 'auto'),

    /*
    |--------------------------------------------------------------------------
    | Max Requests
    |--------------------------------------------------------------------------
    |
    | The number of requests an Octane worker will handle before being
    | recycled. This value helps prevent memory leaks.
    |
    */

    'max_requests' => env('OCTANE_MAX_REQUESTS', 500),

    /*
    |--------------------------------------------------------------------------
    | Swoole Settings
    |--------------------------------------------------------------------------
    |
    | These are the "settings" for Swoole's HTTP server. These settings
    | will be merged into Swoole's server configuration. You are free to
    | modify these values as needed for your application.
    |
    | https://www.swoole.co.uk/docs/modules/swoole-server/configuration
    |
    */

    'swoole' => [
        'max_conn' => 50000,                    // Max simultaneous connections
        'max_coroutines' => 30000,              // Max concurrent coroutines
        'task_worker_num' => env('OCTANE_TASK_WORKERS', 4),  // Background job workers
        'task_max_request' => 500,              // Recycle task workers
        'task_enable_coroutine' => true,        // Use coroutines in tasks
        'worker_num' => env('OCTANE_WORKERS', -1),  // -1 = auto (CPU cores)
        'max_request' => 500,                   // Recycle workers
        'enable_coroutine' => true,
        'http_compression' => true,             // Gzip responses
        'http_compression_level' => 6,
        'package_max_length' => 100 * 1024 * 1024,  // 100MB max payload
        'buffer_output_size' => 32 * 1024 * 1024,   // 32MB buffer
        'socket_buffer_size' => 32 * 1024 * 1024,   // 32MB socket buffer
        'client_buffer_size' => 32 * 1024 * 1024,   // 32MB client buffer
        'max_wait_time' => 30,                  // Max request time (seconds)
        'reload_async' => true,                 // Async worker reload
    ],

    /*
    |--------------------------------------------------------------------------
    | RoadRunner Settings
    |--------------------------------------------------------------------------
    |
    | These are the settings for RoadRunner. These settings are used
    | to configure RoadRunner's behavior. You are free to modify these
    | values as needed for your application.
    |
    */

    'roadrunner' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Warm On Boot
    |--------------------------------------------------------------------------
    |
    | If the application should be "warmed up" on server boot. A warmed
    | application will pre-load all of your application's service
    | providers and bootstrap files, improving performance.
    |
    */

    'warm' => env('OCTANE_WARM', true),

    /*
    |--------------------------------------------------------------------------
    | Watch Directories
    |--------------------------------------------------------------------------
    |
    | Octane can automatically reload your application if any files in
    | these directories change. However, this feature is very slow and
    | should be disabled in production.
    |
    */

    'watch' => explode(
        ',',
        (string) env('OCTANE_WATCH', 'app,bootstrap,config,database,resources,routes,storage')
    ),

    /*
    |--------------------------------------------------------------------------
    | Listeners
    |--------------------------------------------------------------------------
    |
    | These event listeners are registered with Octane and will be called
    | when specific events occur. You can use these listeners to optimize
    | your application's performance.
    |
    */

    'listeners' => [
        RequestReceived::class => [],
        RequestHandled::class => [],
        TaskReceived::class => [],
        TickReceived::class => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Flush Log Views Cache On Request
    |--------------------------------------------------------------------------
    |
    | Setting this value to true will flush the log views cache for every
    | request to ensure that the latest logs are displayed. This is useful
    | for development but should be disabled in production.
    |
    */

    'flush_views_cache_on_request' => env('OCTANE_FLUSH_VIEWS_CACHE_ON_REQUEST', false),

    /*
    |--------------------------------------------------------------------------
    | Redis Client
    |--------------------------------------------------------------------------
    |
    | This is the Redis client that will be used by Octane's table memory
    | storage. The value below references one of the "connections" defined
    | in the config/database.php configuration file.
    |
    */

    'redis' => 'default',

];
