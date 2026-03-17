<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    /** @use HasFactory<\Database\Factories\PaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'amount',
        'reference_number',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    protected static function booted()
    {
        static::saved(function ($payment) {
            if ($payment->booking) {
                $payment->booking->updateStatusBasedOnPayments();
            }
        });

        static::deleted(function ($payment) {
            if ($payment->booking) {
                $payment->booking->updateStatusBasedOnPayments();
            }
        });
    }
}
