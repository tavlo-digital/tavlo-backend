<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('table-sessions:close-stale')->everyMinute();
Schedule::command('kitchen-orders:release-scheduled')->everyMinute()->withoutOverlapping();
Schedule::command('payments:reconcile-stale')->everyMinute();
Schedule::command('payments:release-stale-checkout-holds')->everyMinute();
Schedule::command('subscriptions:reconcile-stale')->everyMinute();
Schedule::command('fiskaly:retry-failed')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('notifications:prune-silent')->daily();
Schedule::command('session-activities:prune')->daily();
// 2026-09-08 audit finding: an uploaded financial-expense receipt that's
// never attached to a saved expense was never cleaned up.
Schedule::command('financials:cleanup-orphaned-attachments')->daily();
