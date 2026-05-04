<?php

use App\Models\ActivityLog;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Booking scheduled tasks (Asia/Manila)
|--------------------------------------------------------------------------
| Server cron (every minute): php artisan schedule:run
*/

$manila = 'Asia/Manila';

/*
|--------------------------------------------------------------------------
| Daily at 12:00 — Manila
|--------------------------------------------------------------------------
| bookings:send-reminders — reminder email one day before check-in
|
| bookings:cancel-unpaid is scheduled separately every 15 minutes (9:00 PM Manila check-in-day rule).
*/
Schedule::command('bookings:send-reminders')
    ->dailyAt('12:00')
    ->timezone($manila)
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Every 15 minutes — Manila
|--------------------------------------------------------------------------
| Enforce unpaid settlement deadline (9:00 PM on check-in day, Manila) so cancellations run soon after due.
*/
Schedule::command('bookings:cancel-unpaid')
    ->everyFifteenMinutes()
    ->timezone($manila)
    ->withoutOverlapping();

Schedule::command('bookings:prune-pending-verification')
    ->hourly()
    ->timezone($manila)
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Session pruning (database session driver)
|--------------------------------------------------------------------------
| Avoids request-time deadlocks from session GC lottery by pruning on schedule.
*/
Schedule::command('session:prune')
    ->hourly()
    ->timezone($manila)
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Hourly full Google Sheets mirror refresh
|--------------------------------------------------------------------------
| Rebuilds spreadsheet tabs from the DB to remove manual edits and guarantee
| all booking rows are present in Sheets.
*/
Schedule::command('bookings:sync-google-sheet')
    ->hourly()
    ->timezone($manila)
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Weekly activity-log retention cleanup
|--------------------------------------------------------------------------
| Runs every 7 days and keeps only the latest 7 days of audit records.
*/
Schedule::call(function (): void {
    ActivityLog::query()
        ->where('created_at', '<', now()->subDays(7))
        ->delete();
})
    ->name('activity-log-retention-cleanup')
    ->weekly()
    ->sundays()
    ->at('01:00')
    ->timezone($manila)
    ->withoutOverlapping();
