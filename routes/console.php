<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Booking status scheduled tasks
|--------------------------------------------------------------------------
| All times are in Asia/Manila (UTC+8).
// Commands use the Booking model so model events run.
|
| Cron entry required on the server (run every minute):
|   * * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
*/

// Free rooms: mark occupied bookings that checked out today as complete.
Schedule::command('bookings:complete-checkouts')
    ->everyMinute()
    ->timezone('Asia/Manila');

// Send Testimonial Feedback: send testimonial feedback email to guests 1 day after their booking is completed.
Schedule::command('testimonials:send-feedback')
    ->dailyAt('12:00')
    ->timezone('Asia/Manila')
    ->withoutOverlapping();

// Activate stays: mark paid bookings that check in today as occupied.
Schedule::command('bookings:activate-checkins')
    ->dailyAt('12:00')
    ->timezone('Asia/Manila');

// Cancel no-shows: cancel unpaid bookings that were due to check in today.
Schedule::command('bookings:cancel-unpaid')
    ->dailyAt('12:00')
    ->timezone('Asia/Manila');
