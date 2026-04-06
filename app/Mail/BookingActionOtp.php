<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingActionOtp extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public string $purposeLabel,
        public int $ttlMinutes,
        public string $guestName = 'Guest',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Marcelino\'s Resort and Hotel - '.$this->purposeLabel.' verification',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-action-otp',
            with: [
                'code' => $this->code,
                'purposeLabel' => $this->purposeLabel,
                'ttlMinutes' => $this->ttlMinutes,
                'guestName' => $this->guestName,
            ],
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
