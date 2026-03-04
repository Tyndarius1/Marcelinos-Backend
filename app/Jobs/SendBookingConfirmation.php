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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

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

        // Generate QR code asynchronously if it doesn't exist
        if (empty($this->booking->qr_code)) {
            $qrData = json_encode([
                'booking_id' => $this->booking->id,
                'reference' => $this->booking->reference_number,
                'guest_id' => $this->booking->guest_id,
            ]);

            $path = 'qr/bookings/' . Str::uuid() . '.svg';

            Storage::disk('public')->put(
                $path,
                QrCode::size(300)->generate($qrData)
            );

            // Prevent infinite event loop if any other listeners track updates
            $this->booking->updateQuietly([
                'qr_code' => $path,
            ]);
        }

        if ($this->booking->guest && $this->booking->guest->email) {
            Mail::to($this->booking->guest->email)->send(new BookingCreated($this->booking));
        }
    }
}
