<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | Advanced rate limiting system supporting multi-level limits,
    | sliding window + token bucket algorithms, and Redis/memory fallback.
    |
    */

    'default' => env('RATE_LIMIT_DRIVER', 'redis'),

    'fallback_to_memory' => env('RATE_LIMIT_FALLBACK', true),

    'memory_store_limit' => 10000,

    'redis_key_prefix' => 'rate:',

    /*
    |--------------------------------------------------------------------------
    | Per-Endpoint Configuration
    |--------------------------------------------------------------------------
    |
    | Define rate limit rules for specific endpoints.
    | Format: 'endpoint' => ['limits' => [...], 'description' => '...']
    |
    */

    'endpoints' => [
        // Authentication endpoints
        'login' => [
            'limits' => [
                [
                    'type' => 'sliding_window',
                    'window' => 60,  // 60 seconds
                    'max' => 5,
                    'by' => 'ip',
                    'description' => 'Prevent brute-force attacks',
                ],
                [
                    'type' => 'sliding_window',
                    'window' => 3600,  // 1 hour
                    'max' => 20,
                    'by' => 'email',
                    'description' => 'Per-email hourly limit',
                ],
            ],
        ],

        'register' => [
            'limits' => [
                [
                    'type' => 'sliding_window',
                    'window' => 60,
                    'max' => 3,
                    'by' => 'ip',
                    'description' => 'Prevent registration spam',
                ],
            ],
        ],

        // Booking operations
        'booking.create' => [
            'limits' => [
                [
                    'type' => 'sliding_window',
                    'window' => 60,
                    'max' => 3,
                    'by' => 'user',
                    'description' => 'Prevent rapid repeated bookings',
                ],
                [
                    'type' => 'token_bucket',
                    'capacity' => 20,
                    'refill_rate' => 1,
                    'by' => 'user',
                    'description' => 'Allow burst of 20 bookings, then 1/sec refill',
                ],
                [
                    'type' => 'sliding_window',
                    'window' => 86400,  // 24 hours
                    'max' => 100,
                    'by' => 'room',
                    'description' => 'Per-room daily spam check',
                ],
            ],
        ],

        'booking.update' => [
            'limits' => [
                [
                    'type' => 'sliding_window',
                    'window' => 60,
                    'max' => 5,
                    'by' => 'user',
                    'description' => 'Prevent update spam',
                ],
            ],
        ],

        'booking.delete' => [
            'limits' => [
                [
                    'type' => 'sliding_window',
                    'window' => 60,
                    'max' => 3,
                    'by' => 'user',
                    'description' => 'Prevent cancellation abuse',
                ],
            ],
        ],

        // Room queries (high-traffic read operations)
        'room.availability' => [
            'limits' => [
                [
                    'type' => 'token_bucket',
                    'capacity' => 100,
                    'refill_rate' => 10,
                    'by' => 'ip',
                    'description' => 'Allow high-throughput room availability checks',
                ],
            ],
        ],

        // Contact form
        'contact.store' => [
            'limits' => [
                [
                    'type' => 'sliding_window',
                    'window' => 60,
                    'max' => 3,
                    'by' => 'ip',
                    'description' => 'Prevent contact form spam',
                ],
                [
                    'type' => 'sliding_window',
                    'window' => 86400,
                    'max' => 10,
                    'by' => 'ip',
                    'description' => 'Daily contact form limit',
                ],
            ],
        ],

        // Global API endpoints
        'api.public' => [
            'limits' => [
                [
                    'type' => 'token_bucket',
                    'capacity' => 200,
                    'refill_rate' => 10,
                    'by' => 'ip',
                    'description' => 'General API endpoint limit',
                ],
            ],
        ],

        'api.authenticated' => [
            'limits' => [
                [
                    'type' => 'token_bucket',
                    'capacity' => 500,
                    'refill_rate' => 50,
                    'by' => 'user',
                    'description' => 'Authenticated user API limit',
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Tier Configuration
    |--------------------------------------------------------------------------
    |
    | Rate limit multipliers based on subscription tier.
    | Free tier = 1x, Premium = 3x, Enterprise = 10x
    |
    */

    'user_tiers' => [
        'free' => [
            'multiplier' => 1.0,
            'booking_per_minute' => 3,
            'booking_per_hour' => 30,
            'booking_per_day' => 100,
            'api_per_minute' => 100,
        ],

        'premium' => [
            'multiplier' => 3.0,
            'booking_per_minute' => 10,
            'booking_per_hour' => 100,
            'booking_per_day' => 500,
            'api_per_minute' => 500,
        ],

        'enterprise' => [
            'multiplier' => 10.0,
            'booking_per_minute' => 50,
            'booking_per_hour' => 500,
            'booking_per_day' => 5000,
            'api_per_minute' => 2000,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Whitelist Configuration
    |--------------------------------------------------------------------------
    |
    | IPs and users that bypass rate limiting
    |
    */

    'whitelist' => [
        'ips' => [
            // Internal network
            '127.0.0.1',
            '::1',
            // Add monitoring/health check IPs here
        ],

        'user_ids' => [
            // Add admin user IDs that should bypass limits
        ],

        'emails' => [
            // Add email addresses that bypass limits
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring & Alerting
    |--------------------------------------------------------------------------
    |
    | Configuration for observability
    |
    */

    'monitoring' => [
        'enabled' => env('RATE_LIMIT_MONITORING', true),

        'log_all_throttles' => env('RATE_LIMIT_LOG_ALL', false),

        'log_channel' => 'rate-limiting',

        'prometheus_enabled' => env('PROMETHEUS_ENABLED', false),

        'prometheus_namespace' => 'soleil_hostel_api',

        'alert_threshold' => [
            'throttle_percentage' => 5,  // Alert if > 5% throttled
            'redis_fallback_count' => 10,  // Alert after 10 Redis failures
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Response Configuration
    |--------------------------------------------------------------------------
    |
    | HTTP response details for throttled requests
    |
    */

    'responses' => [
        'http_status' => 429,

        'message' => 'Too many requests. Please try again later.',

        'include_retry_after' => true,

        'include_remaining' => true,

        'include_reset' => true,
    ],
];
