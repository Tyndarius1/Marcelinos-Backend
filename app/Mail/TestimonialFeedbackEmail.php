<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class TestimonialFeedbackEmail extends Mailable
{
    use Queueable, SerializesModels;

    public Booking $booking;

    /** Dynamic link to the testimonial form (signed, expires in 14 days). */
    public string $feedbackUrl;

    public function __construct(Booking $booking)
    {
        $this->booking = $booking;

        // Signed URL so the link is tamper-proof and expires (one-time use enforced when you build the form).
        $this->feedbackUrl = URL::temporarySignedRoute(
            'testimonial.feedback.redirect',
            now()->addDays(14),
            ['token' => $booking->receipt_token]
        );
    }

    public function build()
    {
        return $this
            ->subject('How was your stay? Share your experience')
            ->view('emails.testimonial-feedback');
    }
}
