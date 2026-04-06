<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'booking_id',
        'total_amount',
        'partial_amount',
        'is_fullypaid',
    ];

    protected $casts = [
        'total_amount' => 'integer',
        'partial_amount' => 'integer',
        'is_fullypaid' => 'boolean',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
