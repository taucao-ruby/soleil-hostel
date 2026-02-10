<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\AdminBookingController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\LocationController;

/*
|--------------------------------------------------------------------------
| API v1 Routes
|--------------------------------------------------------------------------
|
| Current stable API version. All routes are prefixed with /api/v1.
| Controllers and services are reused from the main application.
|
*/

// ========== LOCATION ENDPOINTS (v1) ==========
// Public read-only
Route::get('/locations', [LocationController::class, 'index']);
Route::get('/locations/{slug}', [LocationController::class, 'show']);
Route::get('/locations/{slug}/availability', [LocationController::class, 'availability']);

// ========== ROOM ENDPOINTS (v1) ==========
// Public read-only (now supports ?location_id= filter)
Route::get('/rooms', [RoomController::class, 'index']);
Route::get('/rooms/{id}', [RoomController::class, 'show']);

// Protected room management (Admin only)
Route::middleware(['check_token_valid'])->group(function () {
    Route::post('/rooms', [RoomController::class, 'store']);
    Route::put('/rooms/{id}', [RoomController::class, 'update']);
    Route::patch('/rooms/{id}', [RoomController::class, 'update']);
    Route::delete('/rooms/{id}', [RoomController::class, 'destroy']);
});

// ========== BOOKING ENDPOINTS (v1) ==========
// All booking endpoints require authenticated + verified email
Route::middleware(['check_token_valid', 'verified'])->group(function () {
    Route::post('/bookings', [BookingController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::get('/bookings/{booking}', [BookingController::class, 'show']);
    Route::put('/bookings/{booking}', [BookingController::class, 'update'])->middleware('throttle:10,1');
    Route::patch('/bookings/{booking}', [BookingController::class, 'update'])->middleware('throttle:10,1');
    Route::delete('/bookings/{booking}', [BookingController::class, 'destroy'])->middleware('throttle:10,1');
    
    // Booking status change endpoints
    Route::post('/bookings/{booking}/confirm', [BookingController::class, 'confirm'])
        ->middleware(['role:admin', 'throttle:10,1']);
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])
        ->middleware('throttle:10,1');

    // ========== ADMIN BOOKING ENDPOINTS (v1) ==========
    Route::prefix('admin/bookings')->middleware('role:admin')->group(function () {
        Route::get('/', [AdminBookingController::class, 'index']);
        Route::get('/trashed', [AdminBookingController::class, 'trashed']);
        Route::get('/trashed/{id}', [AdminBookingController::class, 'showTrashed']);
        Route::post('/{id}/restore', [AdminBookingController::class, 'restore']);
        Route::post('/restore-bulk', [AdminBookingController::class, 'restoreBulk']);
        Route::delete('/{id}/force', [AdminBookingController::class, 'forceDelete']);
    });

    // ========== ADMIN CONTACT MESSAGE ENDPOINTS (v1) ==========
    Route::prefix('admin/contact-messages')->middleware('role:admin')->group(function () {
        Route::get('/', [ContactController::class, 'index']);
        Route::patch('/{id}/read', [ContactController::class, 'markAsRead']);
    });
});
