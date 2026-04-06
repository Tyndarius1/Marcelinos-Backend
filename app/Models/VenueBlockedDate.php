<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VenueBlockedDate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'venue_id',
        'blocked_on',
        'reason',
    ];

    protected $casts = [
        'blocked_on' => 'date',
    ];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * Blocked calendar day overlaps a booking window [checkIn, checkOut] (same semantics as booking overlap).
     */
    public function scopeOverlappingBookingRange($query, $checkIn, $checkOut): void
    {
        $query->whereRaw('? < DATE_ADD(venue_blocked_dates.blocked_on, INTERVAL 1 DAY)', [$checkIn])
            ->whereRaw('? > venue_blocked_dates.blocked_on', [$checkOut]);
    }
}
