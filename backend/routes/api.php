<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Auth\AuthController as TokenAuthController;
use App\Http\Controllers\Auth\HttpOnlyTokenController;
use App\Http\Controllers\Auth\UnifiedAuthController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\AdminBookingController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\HealthController;
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

// ========== KUBERNETES/DOCKER HEALTH PROBES ==========
// Failure Semantics: DB=CRITICAL (503), Cache/Queue=DEGRADED (200 with warning)
Route::prefix('health')->group(function () {
    // Liveness: Is the app process alive? (shallow check)
    Route::get('/live', [HealthController::class, 'liveness'])->name('health.liveness');
    
    // Readiness: Can the app accept traffic? (checks critical deps)
    Route::get('/ready', [HealthController::class, 'readiness'])->name('health.readiness');
    
    // Full: Detailed health for monitoring dashboards
    Route::get('/full', [HealthController::class, 'detailed'])->name('health.full');
    
    // Individual component checks for granular monitoring
    Route::get('/db', [HealthController::class, 'database'])->name('health.database');
    Route::get('/cache', [HealthController::class, 'cache'])->name('health.cache');
    Route::get('/queue', [HealthController::class, 'queue'])->name('health.queue');
});

// Health check
Route::get('ping', function() {
    return response()->json([
        'ok' => true, 
        'message' => 'API is working!',
        'timestamp' => now()
    ]);
});

// Auth routes (public - with rate limiting)
// ========== LEGACY ENDPOINTS (Deprecated - Sunset July 2026) ==========
// These endpoints are maintained for backward compatibility.
// New clients SHOULD use -v2 or -httponly variants.
Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware(['throttle:5,1', 'deprecated:2026-07-01,/api/auth/login-v2']);

// ========== BEARER TOKEN ENDPOINTS (Current - v2) ==========
Route::post('/auth/login-v2', [TokenAuthController::class, 'login'])->middleware('throttle:5,1');

// ========== HTTPONLY COOKIE ENDPOINTS (Current) ==========
// Token stored in httpOnly Cookie, NOT localStorage - XSS-safe
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

// ========== EMAIL VERIFICATION ROUTES ==========
// These routes require authentication but NOT verified email
// The verification link itself uses signed URLs for security

// Verification notice (required by Laravel - named route)
Route::get('/email/verify', [EmailVerificationController::class, 'notice'])
    ->middleware(['check_token_valid'])
    ->name('verification.notice');

// Verify email (signed URL from verification email)
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['check_token_valid', 'signed'])
    ->name('verification.verify');

// Resend verification email
Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
    ->middleware(['check_token_valid', 'throttle:email-verification'])
    ->name('verification.send');

// Check verification status
Route::get('/email/verification-status', [EmailVerificationController::class, 'status'])
    ->middleware(['check_token_valid'])
    ->name('verification.status');

// ========== PROTECTED ROUTES (Require valid token) ==========
// 
// Our custom middleware:
// 1. check_token_valid - Check token exists, not expired + not revoked
//
// Nếu token invalid (expired/revoked) → 401 Unauthorized
// Frontend sẽ nhận 401 → tự động gọi refresh endpoint
//

Route::middleware(['check_httponly_token'])->group(function () {
    // ========== HTTPONLY COOKIE AUTH ENDPOINTS ==========
    Route::post('/auth/refresh-httponly', [HttpOnlyTokenController::class, 'refresh']);
    Route::post('/auth/logout-httponly', [HttpOnlyTokenController::class, 'logout']);
    Route::get('/auth/me-httponly', [HttpOnlyTokenController::class, 'me']);
});

Route::middleware(['check_token_valid'])->group(function () {
    // ========== LEGACY AUTH ENDPOINTS (Deprecated - Sunset July 2026) ==========
    Route::post('/auth/logout', [AuthController::class, 'logout'])
        ->middleware('deprecated:2026-07-01,/api/auth/logout-v2');
    Route::post('/auth/refresh', [AuthController::class, 'refresh'])
        ->middleware('deprecated:2026-07-01,/api/auth/refresh-v2');
    Route::get('/auth/me', [AuthController::class, 'me'])
        ->middleware('deprecated:2026-07-01,/api/auth/me-v2');

    // ========== BEARER TOKEN AUTH ENDPOINTS (Current - v2) ==========
    Route::post('/auth/refresh-v2', [TokenAuthController::class, 'refresh']);
    Route::post('/auth/logout-v2', [TokenAuthController::class, 'logout']);
    Route::post('/auth/logout-all-v2', [TokenAuthController::class, 'logoutAll']);
    Route::get('/auth/me-v2', [TokenAuthController::class, 'me']);

    // ========== ROOM MANAGEMENT (Admin only) ==========
    Route::post('/rooms', [RoomController::class, 'store']);
    Route::put('/rooms/{id}', [RoomController::class, 'update']);
    Route::patch('/rooms/{id}', [RoomController::class, 'update']);
    Route::delete('/rooms/{id}', [RoomController::class, 'destroy']);
});

// ========== UNIFIED AUTH ENDPOINTS (NEW - Mode-agnostic) ==========
// These endpoints detect auth mode (Bearer/Cookie) and delegate appropriately.
// Use these for new clients that want mode-agnostic auth handling.
Route::prefix('auth/unified')->group(function () {
    Route::get('/me', [UnifiedAuthController::class, 'me']);
    Route::post('/logout', [UnifiedAuthController::class, 'logout']);
    Route::post('/logout-all', [UnifiedAuthController::class, 'logoutAll']);
});

// ========== PROTECTED ROUTES (Require valid token + verified email) ==========
// SECURITY: These routes require email verification before access
// Users with unverified email will receive 403 Forbidden

Route::middleware(['check_token_valid', 'verified'])->group(function () {
    // ========== BOOKING ENDPOINTS ==========
    Route::post('/bookings', [BookingController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::get('/bookings/{booking}', [BookingController::class, 'show']);
    Route::put('/bookings/{booking}', [BookingController::class, 'update'])->middleware('throttle:10,1');
    Route::patch('/bookings/{booking}', [BookingController::class, 'update'])->middleware('throttle:10,1');
    Route::delete('/bookings/{booking}', [BookingController::class, 'destroy'])->middleware('throttle:10,1');
    
    // Booking status change endpoints (with notification triggers)
    Route::post('/bookings/{booking}/confirm', [BookingController::class, 'confirm'])
        ->middleware(['role:admin', 'throttle:10,1']);
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])
        ->middleware('throttle:10,1');

    // ========== ADMIN BOOKING ENDPOINTS (Soft Delete Management) ==========
    // All routes require admin role via middleware
    Route::prefix('admin/bookings')->middleware('role:admin')->group(function () {
        // View all bookings including trashed
        Route::get('/', [AdminBookingController::class, 'index']);
        
        // View only trashed bookings (Trash view)
        Route::get('/trashed', [AdminBookingController::class, 'trashed']);
        
        // View specific trashed booking
        Route::get('/trashed/{id}', [AdminBookingController::class, 'showTrashed']);
        
        // Restore a soft deleted booking
        Route::post('/{id}/restore', [AdminBookingController::class, 'restore']);
        
        // Bulk restore multiple bookings
        Route::post('/restore-bulk', [AdminBookingController::class, 'restoreBulk']);
        
        // Permanently delete (force delete) - GDPR "right to be forgotten"
        Route::delete('/{id}/force', [AdminBookingController::class, 'forceDelete']);
    });
});


