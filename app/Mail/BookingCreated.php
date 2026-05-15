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
    public string $billingToken;

    public function __construct(Booking $booking, string $billingToken)
    {
        if (! Str::isUuid((string) $booking->receipt_token)) {
            $booking->forceFill([
                'receipt_token' => (string) Str::uuid(),
            ])->saveQuietly();
        }

        $this->booking = $booking;
        $this->billingToken = $billingToken;
    }

    public function build()
    {
        $this->booking->loadMissing('guest');

        $guestDisplayName = $this->booking->displayGuestName();
        if ($guestDisplayName === '—' || $guestDisplayName === '') {
            $guestDisplayName = 'Guest';
        }

        $bookingCcAddress = config('mail.booking_cc_address');

        if (filled($bookingCcAddress)) {
            $this->cc($bookingCcAddress);
        }

        return $this
            ->subject('Marcelino\'s Resort Hotel - Booking Confirmation')
            ->view('emails.booking-created', [
                'guestDisplayName' => $guestDisplayName,
                'billingToken' => $this->billingToken,
            ]);
    }
}
