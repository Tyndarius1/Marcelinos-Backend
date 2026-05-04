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

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
