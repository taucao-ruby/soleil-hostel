<?php

use App\Http\Controllers\AiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| AI Harness Routes (v1)
|--------------------------------------------------------------------------
|
| All AI endpoints are gated by:
|   1. check_token_valid — authenticated user
|   2. verified — email verified
|   3. throttle:10,1 — rate limit per user
|   4. ai_harness_enabled — feature flag kill switch
|   5. ai_request_normalizer — builds HarnessRequest DTO
|
| POST /api/v1/ai/{task_type}
|   task_type: faq_lookup | room_discovery | booking_status | admin_draft
|
*/

Route::middleware([
    'check_token_valid',
    'verified',
    'throttle:10,1',
    'ai_harness_enabled',
    'ai_canary_router',
    'ai_request_normalizer',
])->group(function () {
    Route::post('/{task_type}', [AiController::class, 'handle'])
        ->name('v1.ai.handle')
        ->where('task_type', '[a-z_]+');
});

/*
|--------------------------------------------------------------------------
| AI Health Check (kill switch verification)
|--------------------------------------------------------------------------
|
| Returns 200 when AI_HARNESS_ENABLED=true, 404 otherwise.
| Used by kill switch procedure to verify harness is down.
| Only gated by ai_harness_enabled — no auth required.
|
*/

Route::middleware(['ai_harness_enabled'])
    ->get('/health', fn () => response()->json(['status' => 'ok', 'enabled' => true]))
    ->name('v1.ai.health');
