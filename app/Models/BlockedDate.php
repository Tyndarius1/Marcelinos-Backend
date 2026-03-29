<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class BlockedDate extends Model
{
    protected $fillable = ['date', 'reason'];

    protected $casts = [
        'date' => 'date',
    ];

    /**
     * True when any resort-wide blocked calendar day falls within the booking window
     * [checkIn, checkOut) (same overlap semantics as room/venue blocked dates).
     */
    public static function overlapsRange(Carbon $checkIn, Carbon $checkOut): bool
    {
        return static::query()
            ->whereRaw('? < DATE_ADD(blocked_dates.date, INTERVAL 1 DAY)', [$checkIn])
            ->whereRaw('? > blocked_dates.date', [$checkOut])
            ->exists();
    }
}
