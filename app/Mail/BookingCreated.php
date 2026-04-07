<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class BookingCreated extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Booking $booking;

    public function __construct(Booking $booking)
    {
        if (! Str::isUuid((string) $booking->receipt_token)) {
            $booking->forceFill([
                'receipt_token' => (string) Str::uuid(),
            ])->saveQuietly();
        }

        $this->booking = $booking;
    }

    public function build()
    {
        return $this
            ->subject('Marcelino\'s Resort and Hotel - Booking Confirmation')
            ->view('emails.booking-created');
    }
}
