<?php

use App\Http\Controllers\AiController;
use App\Http\Controllers\ProposalConfirmationController;
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
| AI Proposal Confirmation (Phase 4+)
|--------------------------------------------------------------------------
|
| User confirms or declines a BookingActionProposal.
| On confirm: harness dispatches to existing service layer.
| On decline: logs rejection, returns acknowledgement.
| No model invocation — this is enforcement, not inference.
|
| Rate limit (Lane 3 Batch 3.2):
|   throttle:5,1 — 5 requests per minute per authenticated user.
|
|   This is tighter than the main task endpoint (throttle:10,1) because
|   decide is a confirmed-action surface: each POST can trigger a real
|   booking create or cancellation via the service layer. A stricter
|   per-user ceiling reduces abuse in the event of a leaked session or
|   replayed hash, while still allowing legitimate burst patterns
|   (review multiple pending proposals, decline a few, confirm one).
|   Per-hash replay is already neutralised by Cache::forget() after the
|   first decide — the cache is cleared regardless of success, so
|   subsequent attempts against the same hash 404 immediately.
|
*/

Route::middleware([
    'check_token_valid',
    'verified',
    'throttle:5,1',
    'ai_harness_enabled',
])->group(function () {
    Route::post('/proposals/{hash}/decide', [ProposalConfirmationController::class, 'decide'])
        ->name('v1.ai.proposals.decide')
        ->where('hash', '[a-f0-9]{64}');
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
