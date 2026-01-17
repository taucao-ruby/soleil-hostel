<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\AdminBookingController;

/*
|--------------------------------------------------------------------------
| Legacy API Routes (Deprecated)
|--------------------------------------------------------------------------
|
| These routes maintain backward compatibility for clients not yet migrated
| to versioned endpoints. All routes proxy to v1 with deprecation headers.
|
| Sunset Date: July 1, 2026
| Successor: /api/v1/*
|
| RFC 8594 Headers Added:
| - Deprecation: <current-date>
| - Sunset: Sat, 01 Jul 2026 00:00:00 GMT
| - Link: </api/v1/...>; rel="successor-version"
| - X-Deprecation-Notice: Human-readable message
|
*/

// ========== LEGACY ROOM ENDPOINTS ==========
// Public read-only (with deprecation headers)
Route::get('/rooms', [RoomController::class, 'index'])
    ->middleware('deprecated:2026-07-01,/api/v1/rooms');
Route::get('/rooms/{id}', [RoomController::class, 'show'])
    ->middleware('deprecated:2026-07-01,/api/v1/rooms/{id}');

// Protected room management (Admin only)
Route::middleware(['check_token_valid'])->group(function () {
    Route::post('/rooms', [RoomController::class, 'store'])
        ->middleware('deprecated:2026-07-01,/api/v1/rooms');
    Route::put('/rooms/{id}', [RoomController::class, 'update'])
        ->middleware('deprecated:2026-07-01,/api/v1/rooms/{id}');
    Route::patch('/rooms/{id}', [RoomController::class, 'update'])
        ->middleware('deprecated:2026-07-01,/api/v1/rooms/{id}');
    Route::delete('/rooms/{id}', [RoomController::class, 'destroy'])
        ->middleware('deprecated:2026-07-01,/api/v1/rooms/{id}');
});

// ========== LEGACY BOOKING ENDPOINTS ==========
// All booking endpoints require authenticated + verified email
Route::middleware(['check_token_valid', 'verified'])->group(function () {
    Route::post('/bookings', [BookingController::class, 'store'])
        ->middleware(['throttle:10,1', 'deprecated:2026-07-01,/api/v1/bookings']);
    Route::get('/bookings', [BookingController::class, 'index'])
        ->middleware('deprecated:2026-07-01,/api/v1/bookings');
    Route::get('/bookings/{booking}', [BookingController::class, 'show'])
        ->middleware('deprecated:2026-07-01,/api/v1/bookings/{booking}');
    Route::put('/bookings/{booking}', [BookingController::class, 'update'])
        ->middleware(['throttle:10,1', 'deprecated:2026-07-01,/api/v1/bookings/{booking}']);
    Route::patch('/bookings/{booking}', [BookingController::class, 'update'])
        ->middleware(['throttle:10,1', 'deprecated:2026-07-01,/api/v1/bookings/{booking}']);
    Route::delete('/bookings/{booking}', [BookingController::class, 'destroy'])
        ->middleware(['throttle:10,1', 'deprecated:2026-07-01,/api/v1/bookings/{booking}']);
    
    // Booking status change endpoints
    Route::post('/bookings/{booking}/confirm', [BookingController::class, 'confirm'])
        ->middleware(['role:admin', 'throttle:10,1', 'deprecated:2026-07-01,/api/v1/bookings/{booking}/confirm']);
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])
        ->middleware(['throttle:10,1', 'deprecated:2026-07-01,/api/v1/bookings/{booking}/cancel']);

    // ========== LEGACY ADMIN BOOKING ENDPOINTS ==========
    Route::prefix('admin/bookings')->middleware('role:admin')->group(function () {
        Route::get('/', [AdminBookingController::class, 'index'])
            ->middleware('deprecated:2026-07-01,/api/v1/admin/bookings');
        Route::get('/trashed', [AdminBookingController::class, 'trashed'])
            ->middleware('deprecated:2026-07-01,/api/v1/admin/bookings/trashed');
        Route::get('/trashed/{id}', [AdminBookingController::class, 'showTrashed'])
            ->middleware('deprecated:2026-07-01,/api/v1/admin/bookings/trashed/{id}');
        Route::post('/{id}/restore', [AdminBookingController::class, 'restore'])
            ->middleware('deprecated:2026-07-01,/api/v1/admin/bookings/{id}/restore');
        Route::post('/restore-bulk', [AdminBookingController::class, 'restoreBulk'])
            ->middleware('deprecated:2026-07-01,/api/v1/admin/bookings/restore-bulk');
        Route::delete('/{id}/force', [AdminBookingController::class, 'forceDelete'])
            ->middleware('deprecated:2026-07-01,/api/v1/admin/bookings/{id}/force');
    });
});
