<?php

namespace App\Jobs;

use App\Mail\BookingCreated;
use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendBookingConfirmation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Booking $booking
    ) {
    }

    public function handle(): void
    {
        $this->booking->loadMissing('guest');

        if ($this->booking->guest && $this->booking->guest->email) {
            Mail::to($this->booking->guest->email)->send(new BookingCreated($this->booking));
        }
    }
}
