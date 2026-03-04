<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v2 Routes (Skeleton)
|--------------------------------------------------------------------------
|
| API v2 is under development. All routes return 501 Not Implemented.
| In production, these routes are not registered (returns 404 via fallback).
| In non-production environments, they provide a clear signal to clients.
|
*/

// Only register v2 skeleton routes in non-production environments
if (! app()->isProduction()) {

    // ========== V2 SKELETON - NOT IMPLEMENTED ==========

    Route::any('/rooms', fn () => response()->json([
        'error' => 'NOT_IMPLEMENTED',
        'message' => 'API v2 under development',
        'useInstead' => '/api/v1/rooms',
    ], 501))->name('v2.rooms');

    Route::any('/rooms/{id}', fn () => response()->json([
        'error' => 'NOT_IMPLEMENTED',
        'message' => 'API v2 under development',
        'useInstead' => '/api/v1/rooms/{id}',
    ], 501))->name('v2.rooms.show');

    Route::any('/bookings', fn () => response()->json([
        'error' => 'NOT_IMPLEMENTED',
        'message' => 'API v2 under development',
        'useInstead' => '/api/v1/bookings',
    ], 501))->name('v2.bookings');

    Route::any('/bookings/{id}', fn () => response()->json([
        'error' => 'NOT_IMPLEMENTED',
        'message' => 'API v2 under development',
        'useInstead' => '/api/v1/bookings/{id}',
    ], 501))->name('v2.bookings.show');

    Route::any('/admin/bookings', fn () => response()->json([
        'error' => 'NOT_IMPLEMENTED',
        'message' => 'API v2 under development',
        'useInstead' => '/api/v1/admin/bookings',
    ], 501))->name('v2.admin.bookings');

    Route::any('/admin/bookings/{any}', fn () => response()->json([
        'error' => 'NOT_IMPLEMENTED',
        'message' => 'API v2 under development',
        'useInstead' => '/api/v1/admin/bookings',
    ], 501))->where('any', '.*')->name('v2.admin.bookings.any');

    // Catch-all for any other v2 routes
    Route::fallback(fn () => response()->json([
        'error' => 'NOT_IMPLEMENTED',
        'message' => 'API v2 under development. This endpoint does not exist in v2.',
        'useInstead' => '/api/v1/',
    ], 501));
}
