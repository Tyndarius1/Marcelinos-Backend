<?php

namespace App\Mail;

use App\Models\Booking;
use App\Support\CancellationPolicy;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RefundCompletedGuestMail extends Mailable
{
    use Queueable, SerializesModels;

    public Booking $booking;
    public string $billingToken;

    public function __construct(Booking $booking, string $billingToken)
    {
        $this->booking = $booking;
        $this->billingToken = $billingToken;
    }

    public function build(): self
    {
        $booking = $this->booking;
        $booking->loadMissing('guest');

        $totalPaid = (float) $booking->total_paid;
        $totalPrice = (float) $booking->total_price;
        $isCancelled = (string) $booking->booking_status === Booking::BOOKING_STATUS_CANCELLED;

        $cancellationBreakdown = null;
        $refundAmount = 0.0;
        $balanceDue = 0.0;

        if ($isCancelled) {
            $cancellationBreakdown = CancellationPolicy::breakdownForCancelledBooking($totalPrice, $totalPaid);
            $refundAmount = (float) $cancellationBreakdown['amount_to_refund'];
        } else {
            $refundAmount = max(0.0, round($totalPaid - $totalPrice, 2));
            $balanceDue = max(0.0, round($totalPrice - $totalPaid, 2));
        }

        $guestDisplayName = $booking->displayGuestName();
        if ($guestDisplayName === '—' || $guestDisplayName === '') {
            $guestDisplayName = 'Guest';
        }

        $preheader = $refundAmount > 0.009
            ? 'Your refund of PHP '.number_format($refundAmount, 2)." for booking {$booking->reference_number} is recorded."
            : "Your payment status for booking {$booking->reference_number} has been updated.";

        $subject = $refundAmount > 0.009
            ? "Marcelino's Resort Hotel - Refund completed ({$booking->reference_number})"
            : "Marcelino's Resort Hotel - Payment update ({$booking->reference_number})";

        return $this
            ->subject($subject)
            ->view('emails.refund-completed-guest', [
                'guestDisplayName' => $guestDisplayName,
                'preheader' => $preheader,
                'cancellationBreakdown' => $cancellationBreakdown,
                'refundAmount' => $refundAmount,
                'balanceDue' => $balanceDue,
                'totalPaid' => $totalPaid,
                'totalPrice' => $totalPrice,
                'isCancelled' => $isCancelled,
                'billingToken' => $this->billingToken,
            ]);
    }
}
