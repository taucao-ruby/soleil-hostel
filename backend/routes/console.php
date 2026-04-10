<?php

use App\Jobs\ReconcileRefundsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', static function (): void {
    echo Inspiring::quote().PHP_EOL;
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
|
| Define the application's command schedule here. These tasks will be
| executed by the Laravel scheduler when `schedule:run` is invoked.
|
*/

// Reconcile orphaned refunds every 5 minutes
// Fixes bookings stuck in refund_pending or refund_failed states
Schedule::job(new ReconcileRefundsJob)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer() // For multi-server deployments
    ->name('reconcile-refunds');

// Horizon monitoring: Persist queue metrics for dashboard (every 5 minutes)
Schedule::command('horizon:snapshot')
    ->everyFiveMinutes()
    ->onOneServer()
    ->name('horizon-snapshot');

// Horizon cleanup: Trim old monitoring data (daily at 2 AM)
Schedule::command('horizon:clear', ['--verbose'])
    ->dailyAt('02:00')
    ->onOneServer()
    ->name('horizon-clear');

// AI Harness: Nightly regression gate eval across all phases (daily at 3 AM)
// Auto-blocks deploy if any phase fails thresholds
Schedule::command('ai:eval', ['--all-phases'])
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->name('ai-regression-gate')
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::channel('ai')->error(
            'AI Regression Gate NIGHTLY: BLOCKED — deploy should be held'
        );
    });
