<?php

use App\Http\Controllers\AdminBookingController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\RoomController;
use Illuminate\Support\Facades\Route;

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
Route::get('/locations', [LocationController::class, 'index'])->name('v1.locations.index');
Route::get('/locations/{slug}', [LocationController::class, 'show'])->name('v1.locations.show');
Route::get('/locations/{slug}/availability', [LocationController::class, 'availability'])->name('v1.locations.availability');

// ========== ROOM ENDPOINTS (v1) ==========
// Public read-only (now supports ?location_id= filter)
Route::get('/rooms', [RoomController::class, 'index'])->name('v1.rooms.index');
Route::get('/rooms/{room}', [RoomController::class, 'show'])->name('v1.rooms.show');

// Protected room management (Admin only — defense-in-depth: middleware + policy)
Route::middleware(['check_token_valid', 'role:admin'])->group(function () {
    Route::post('/rooms', [RoomController::class, 'store'])->name('v1.rooms.store');
    Route::put('/rooms/{room}', [RoomController::class, 'update'])->name('v1.rooms.update');
    Route::patch('/rooms/{room}', [RoomController::class, 'update'])->name('v1.rooms.patch');
    Route::delete('/rooms/{room}', [RoomController::class, 'destroy'])->name('v1.rooms.destroy');
});

// ========== BOOKING ENDPOINTS (v1) ==========
// All booking endpoints require authenticated + verified email
Route::middleware(['check_token_valid', 'verified'])->group(function () {
    Route::post('/bookings', [BookingController::class, 'store'])->name('v1.bookings.store')->middleware('throttle:10,1');
    Route::get('/bookings', [BookingController::class, 'index'])->name('v1.bookings.index');
    Route::get('/bookings/{booking}', [BookingController::class, 'show'])->name('v1.bookings.show');
    Route::put('/bookings/{booking}', [BookingController::class, 'update'])->name('v1.bookings.update')->middleware('throttle:10,1');
    Route::patch('/bookings/{booking}', [BookingController::class, 'update'])->name('v1.bookings.patch')->middleware('throttle:10,1');
    Route::delete('/bookings/{booking}', [BookingController::class, 'destroy'])->name('v1.bookings.destroy')->middleware('throttle:10,1');

    // Booking status change endpoints
    Route::post('/bookings/{booking}/confirm', [BookingController::class, 'confirm'])
        ->name('v1.bookings.confirm')->middleware(['role:admin', 'throttle:10,1']);
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])
        ->name('v1.bookings.cancel')->middleware('throttle:10,1');

    // ========== ADMIN BOOKING ENDPOINTS (v1) ==========
    // Read-only endpoints: moderator+ (view bookings, view trashed)
    Route::prefix('admin/bookings')->middleware('role:moderator')->group(function () {
        Route::get('/', [AdminBookingController::class, 'index'])->name('v1.admin.bookings.index');
        Route::get('/trashed', [AdminBookingController::class, 'trashed'])->name('v1.admin.bookings.trashed');
        Route::get('/trashed/{id}', [AdminBookingController::class, 'showTrashed'])->name('v1.admin.bookings.showTrashed');
    });

    // Destructive endpoints: admin only (restore, force-delete)
    Route::prefix('admin/bookings')->middleware('role:admin')->group(function () {
        Route::post('/{id}/restore', [AdminBookingController::class, 'restore'])->name('v1.admin.bookings.restore');
        Route::post('/restore-bulk', [AdminBookingController::class, 'restoreBulk'])->name('v1.admin.bookings.restoreBulk');
        Route::delete('/{id}/force', [AdminBookingController::class, 'forceDelete'])->name('v1.admin.bookings.forceDelete');
    });

    // ========== ADMIN CONTACT MESSAGE ENDPOINTS (v1) ==========
    // Moderator+ can view and manage contact messages
    Route::prefix('admin/contact-messages')->middleware('role:moderator')->group(function () {
        Route::get('/', [ContactController::class, 'index'])->name('v1.admin.contactMessages.index');
        Route::patch('/{id}/read', [ContactController::class, 'markAsRead'])->name('v1.admin.contactMessages.markAsRead');
    });

    // ========== REVIEW ENDPOINTS (v1) ==========
    Route::post('/reviews', [ReviewController::class, 'store'])->name('v1.reviews.store');
    Route::put('/reviews/{review}', [ReviewController::class, 'update'])->name('v1.reviews.update');
    Route::patch('/reviews/{review}', [ReviewController::class, 'update'])->name('v1.reviews.patch');
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])->name('v1.reviews.destroy');
});
