<?php

use App\Jobs\ReconcileRefundsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
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
Schedule::job(new ReconcileRefundsJob())
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->onOneServer() // For multi-server deployments
    ->name('reconcile-refunds');
