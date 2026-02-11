<?php

/**
 * N+1 Query Detector Configuration
 *
 * N+1 query = tự đẻ ra 1000 query trong 1 trang, khách đợi 5 giây là bỏ chạy
 * Config này phát hiện N+1 tự động trong testing + CI
 */

return [
    'enabled' => env('QUERY_DETECTOR_ENABLED', true),

    // Bật trong testing environment
    'in_test' => true,

    // Alert nếu query > threshold
    'threshold' => env('QUERY_DETECTOR_THRESHOLD', 50),

    // Models để track (nếu rỗng = tất cả)
    'models' => [
        'App\Models\Booking',
        'App\Models\Room',
        'App\Models\User',
    ],

    // Các query được phép (whitelist - không đếm)
    'whitelist' => [
        'select * from information_schema',
        'select * from sqlite_master',
    ],
];
