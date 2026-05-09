<?php

namespace App\Support;

use App\Models\Booking;

final class BookingRevenueCalculator
{
    /**
     * Calculate the booking revenue considering payment status.
     * For partial payments, returns the actual amount paid.
     * For paid/completed bookings, returns the total booking price.
     * For unpaid bookings, returns 0.
     */
    public static function forBooking(Booking $booking): float
    {
        $paymentStatus = (string) ($booking->payment_status ?? '');
        
        // Unpaid bookings contribute no revenue
        if ($paymentStatus === Booking::PAYMENT_STATUS_UNPAID) {
            return 0.0;
        }
        
        // Paid and completed bookings contribute their full total price
        if ($paymentStatus === Booking::PAYMENT_STATUS_PAID) {
            return max(0.0, (float) ($booking->total_price ?? 0));
        }
        
        // Partial payments contribute their actual paid amount
        if ($paymentStatus === Booking::PAYMENT_STATUS_PARTIAL) {
            $booking->loadMissing('payments');
            $totalPaid = (float) $booking->payments
                ->sum('partial_amount');
            return max(0.0, $totalPaid);
        }
        
        // Refund-related statuses don't contribute to revenue
        if (in_array($paymentStatus, [
            Booking::PAYMENT_STATUS_REFUND_PENDING,
            Booking::PAYMENT_STATUS_NON_REFUNDABLE,
            Booking::PAYMENT_STATUS_REFUNDED,
        ], true)) {
            return 0.0;
        }
        
        // Default to total price for unknown statuses
        return max(0.0, (float) ($booking->total_price ?? 0));
    }

    /**
     * @param  iterable<Booking>  $bookings
     */
    public static function forBookings(iterable $bookings): float
    {
        $total = 0.0;

        foreach ($bookings as $booking) {
            if (! $booking instanceof Booking) {
                continue;
            }

            $total += self::forBooking($booking);
        }

        return $total;
    }
}
