<?php

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
$afterCheckoutSendTestimonial = fn () => Artisan::call('testimonials:send-feedback');

/**
 * Complete check-outs: occupied → completed when check_out has passed.
 * Runs every 10 minutes 10:00–10:50 and once at 11:00. After each run, send testimonial
 * feedback for eligible completed bookings (see testimonials:send-feedback).
 */
Schedule::command('bookings:complete-checkouts')
    ->cron('0,10,20,30,40,50 10 * * *')
    ->timezone($manila)
    ->withoutOverlapping()
    ->after($afterCheckoutSendTestimonial);

Schedule::command('bookings:complete-checkouts')
    ->dailyAt('11:00')
    ->timezone($manila)
    ->withoutOverlapping()
    ->after($afterCheckoutSendTestimonial);

/*
|--------------------------------------------------------------------------
| Daily at 12:00 — Manila
|--------------------------------------------------------------------------
| bookings:cancel-unpaid      — unpaid, due to check in today → cancelled
| bookings:send-reminders     — reminder email one day before check-in
*/
foreach ([
    'bookings:activate-checkins' => true,
    'bookings:cancel-unpaid' => true,
    'bookings:send-reminders' => true,
] as $signature => $withoutOverlapping) {
    $event = Schedule::command($signature)
        ->dailyAt('12:00')
        ->timezone($manila);
    if ($withoutOverlapping) {
        $event->withoutOverlapping();
    }
}
