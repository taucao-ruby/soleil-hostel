<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v2 Routes (Skeleton)
|--------------------------------------------------------------------------
|
| API v2 is under development. All routes return 501 Not Implemented.
| This provides a clear signal to clients that v2 is not yet ready.
|
*/

// ========== V2 SKELETON - NOT IMPLEMENTED ==========

Route::any('/rooms', fn() => response()->json([
    'error' => 'NOT_IMPLEMENTED',
    'message' => 'API v2 under development',
    'useInstead' => '/api/v1/rooms'
], 501));

Route::any('/rooms/{id}', fn() => response()->json([
    'error' => 'NOT_IMPLEMENTED',
    'message' => 'API v2 under development',
    'useInstead' => '/api/v1/rooms/{id}'
], 501));

Route::any('/bookings', fn() => response()->json([
    'error' => 'NOT_IMPLEMENTED',
    'message' => 'API v2 under development',
    'useInstead' => '/api/v1/bookings'
], 501));

Route::any('/bookings/{id}', fn() => response()->json([
    'error' => 'NOT_IMPLEMENTED',
    'message' => 'API v2 under development',
    'useInstead' => '/api/v1/bookings/{id}'
], 501));

Route::any('/admin/bookings', fn() => response()->json([
    'error' => 'NOT_IMPLEMENTED',
    'message' => 'API v2 under development',
    'useInstead' => '/api/v1/admin/bookings'
], 501));

Route::any('/admin/bookings/{any}', fn() => response()->json([
    'error' => 'NOT_IMPLEMENTED',
    'message' => 'API v2 under development',
    'useInstead' => '/api/v1/admin/bookings'
], 501))->where('any', '.*');

// Catch-all for any other v2 routes
Route::fallback(fn() => response()->json([
    'error' => 'NOT_IMPLEMENTED',
    'message' => 'API v2 under development. This endpoint does not exist in v2.',
    'useInstead' => '/api/v1/'
], 501));
