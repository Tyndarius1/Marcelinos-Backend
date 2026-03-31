<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ActivateCheckinBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:activate-checkins
                            {--date= : The date (Y-m-d) to process; defaults to today}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark bookings with check-in date today and status paid/partial as occupied';

    /**
     * Execute the console command.
     * Uses Eloquent so Booking model events run.
     */
    public function handle(): int
    {
        try {
            $date = $this->option('date')
                ? Carbon::parse($this->option('date'))->toDateString()
                : Carbon::today()->toDateString();
        } catch (\Throwable $e) {
            $this->error('Invalid --date value. Use format Y-m-d.');

            return self::FAILURE;
        }

        $bookings = Booking::query()
            ->whereDate('check_in', $date)
            ->whereIn('status', [Booking::STATUS_PAID, Booking::STATUS_PARTIAL])
            ->get();

        $count = 0;
        foreach ($bookings as $booking) {
            $booking->update(['status' => Booking::STATUS_OCCUPIED]);
            $count++;
        }

        if ($count > 0) {
            $this->info("Marked {$count} booking(s) as occupied for check-in date {$date}.");
        } else {
            $this->comment("No paid/partial bookings with check-in on {$date}.");
        }

        return self::SUCCESS;
    }
}
