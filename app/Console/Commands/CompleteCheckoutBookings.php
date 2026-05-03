<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Support\BookingInspectionService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Console command to automatically mark occupied bookings as completed once their check-out
 * time has passed. This should be scheduled to run regularly (e.g. via cron) to ensure
 * that bookings with status 'occupied' are transitioned to 'completed' when appropriate.
 *
 * Options:
 *   --date   Legacy. If provided, process bookings whose check_out is on or before the end of this day (Y-m-d).
 *   --before If provided, process bookings whose check_out is before or on this datetime (Y-m-d H:i:s),
 *            defaults to now.
 *
 * Use this command to keep the status of bookings up-to-date and automate completion of stays.
 * Eloquent model events will fire for each status update.
 */
class CompleteCheckoutBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:complete-checkouts
                            {--date= : Legacy. The date (Y-m-d) to process; defaults to today}
                            {--before= : Process bookings whose check_out is <= this datetime (defaults to now)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark occupied bookings as complete when their check-out time has passed';

    /**
     * Execute the console command.
     * Uses Eloquent so Booking model events run.
     */
    public function handle(): int
    {
        $before = $this->option('before')
            ? Carbon::parse($this->option('before'))
            : now();

        // Backward compatible: if --date is provided, interpret it as "end of that day".
        if ($this->option('date')) {
            $before = Carbon::parse($this->option('date'))->endOfDay();
        }

        $bookings = Booking::query()
            ->where('check_out', '<=', $before)
            ->where('booking_status', Booking::BOOKING_STATUS_OCCUPIED)
            ->get();

        $count = 0;
        $skipped = 0;
        foreach ($bookings as $booking) {
            $booking->loadMissing('rooms');
            if (BookingInspectionService::bookingNeedsInventoryInspection($booking)) {
                $skipped++;

                continue;
            }

            $booking->update(['booking_status' => Booking::BOOKING_STATUS_COMPLETED]);
            $count++;
        }

        if ($skipped > 0) {
            $this->warn('Skipped '.$skipped.' booking(s) that require a staff checkout inspection (room inventory configured).');
        }

        if ($count > 0) {
            $this->info('Marked '.$count.' booking(s) as complete (check_out <= '.$before->toDateTimeString().').');
        } elseif ($skipped === 0) {
            $this->comment('No occupied bookings eligible for completion (check_out <= '.$before->toDateTimeString().').');
        }

        return self::SUCCESS;
    }
}
