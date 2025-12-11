<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Auth\AuthController as TokenAuthController;
use App\Http\Controllers\Auth\HttpOnlyTokenController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\CspViolationReportController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// ========== PUBLIC ROUTES (No authentication) ==========

// ========== HEALTH CHECK ==========
Route::get('/health', [HealthCheckController::class, 'check']);
Route::get('/health/detailed', [HealthCheckController::class, 'detailed']);

// Health check
Route::get('ping', function() {
    return response()->json([
        'ok' => true, 
        'message' => 'API is working!',
        'timestamp' => now()
    ]);
});

// Auth routes (public - with rate limiting)
// Legacy endpoints
Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

// NEW: Token expiration endpoints (Bearer token)
Route::post('/auth/login-v2', [TokenAuthController::class, 'login'])->middleware('throttle:5,1');

// NEW: httpOnly cookie authentication (XSS-safe)
// Token stored in httpOnly Cookie, NOT localStorage
Route::post('/auth/login-httponly', [HttpOnlyTokenController::class, 'login'])->middleware('throttle:5,1');
Route::get('/auth/csrf-token', function(Request $request) {
    // Generate a random CSRF token (not session-based for API)
    return response()->json(['csrf_token' => \Illuminate\Support\Str::random(64)]);
});

// Security: CSP violation reporting
Route::post('/csp-violation-report', [CspViolationReportController::class, 'report'])->withoutMiddleware(['api']);

// Room read-only (public)
Route::get('/rooms', [RoomController::class, 'index']);
Route::get('/rooms/{id}', [RoomController::class, 'show']);

// Contact form (public - with rate limiting)
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:3,1');

// ========== PROTECTED ROUTES (Require valid token) ==========
// 
// Our custom middleware:
// 1. check_token_valid - Check token exists, not expired + not revoked
//
// Nếu token invalid (expired/revoked) → 401 Unauthorized
// Frontend sẽ nhận 401 → tự động gọi refresh endpoint
//

Route::middleware(['check_httponly_token'])->group(function () {
    // NEW: httpOnly cookie authentication endpoints (with custom middleware)
    Route::post('/auth/refresh-httponly', [HttpOnlyTokenController::class, 'refresh']);
    Route::post('/auth/logout-httponly', [HttpOnlyTokenController::class, 'logout']);
    Route::get('/auth/me-httponly', [HttpOnlyTokenController::class, 'me']);
});

Route::middleware(['check_token_valid'])->group(function () {
    // ========== AUTH ENDPOINTS (Legacy) ==========
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // ========== AUTH ENDPOINTS (New - Token Expiration) ==========
    Route::post('/auth/refresh-v2', [TokenAuthController::class, 'refresh']);
    Route::post('/auth/logout-v2', [TokenAuthController::class, 'logout']);
    Route::post('/auth/logout-all-v2', [TokenAuthController::class, 'logoutAll']);
    Route::get('/auth/me-v2', [TokenAuthController::class, 'me']);

    // ========== ROOM MANAGEMENT (Admin only) ==========
    Route::post('/rooms', [RoomController::class, 'store']);
    Route::put('/rooms/{id}', [RoomController::class, 'update']);
    Route::patch('/rooms/{id}', [RoomController::class, 'update']);
    Route::delete('/rooms/{id}', [RoomController::class, 'destroy']);

    // ========== BOOKING ENDPOINTS ==========
    Route::post('/bookings', [BookingController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::get('/bookings/{booking}', [BookingController::class, 'show']);
    Route::put('/bookings/{booking}', [BookingController::class, 'update'])->middleware('throttle:10,1');
    Route::patch('/bookings/{booking}', [BookingController::class, 'update'])->middleware('throttle:10,1');
    Route::delete('/bookings/{booking}', [BookingController::class, 'destroy'])->middleware('throttle:10,1');
});


