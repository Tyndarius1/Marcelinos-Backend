<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

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
            ->where('status', Booking::STATUS_OCCUPIED)
            ->get();

        $count = 0;
        foreach ($bookings as $booking) {
            $booking->update(['status' => Booking::STATUS_COMPLETED]);
            $count++;
        }

        if ($count > 0) {
            $this->info('Marked ' . $count . ' booking(s) as complete (check_out <= ' . $before->toDateTimeString() . ').');
        } else {
            $this->comment('No occupied bookings eligible for completion (check_out <= ' . $before->toDateTimeString() . ').');
        }

        return self::SUCCESS;
    }
}
