<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'booking_id',
        'payment_type',
        'total_amount',
        'partial_amount',
        'is_fullypaid',
        'provider',
        'provider_ref',
        'provider_status',
        'notes',
    ];

    protected $casts = [
        'total_amount' => 'integer',
        'partial_amount' => 'integer',
        'is_fullypaid' => 'boolean',
    ];

    const TYPE_BOOKING = 'booking';
    const TYPE_DAMAGE = 'damage';

    protected static function booted(): void
    {
        // Recompute booking payment status from amounts any time payments change
        $recompute = function (Payment $payment): void {
            $payment->loadMissing('booking');
            $booking = $payment->booking;
            if (! $booking) {
                return;
            }

            // Refresh to have accurate totals
            $booking->refresh();

            $nextStatus = Booking::paymentStatusFromAmounts(
                (float) $booking->total_price,
                (float) $booking->total_paid,
            );

            if ($nextStatus !== $booking->payment_status) {
                $booking->update(['payment_status' => $nextStatus]);
            }
        };

        static::deleted($recompute);
        static::forceDeleted($recompute);
        static::restored($recompute);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
