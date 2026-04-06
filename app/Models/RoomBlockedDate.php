<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RoomBlockedDate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'room_id',
        'blocked_on',
        'reason',
    ];

    protected $casts = [
        'blocked_on' => 'date',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Blocked calendar day overlaps a booking window [checkIn, checkOut] (same semantics as booking_room overlap).
     */
    public function scopeOverlappingBookingRange($query, $checkIn, $checkOut): void
    {
        $query->whereRaw('? < DATE_ADD(room_blocked_dates.blocked_on, INTERVAL 1 DAY)', [$checkIn])
            ->whereRaw('? > room_blocked_dates.blocked_on', [$checkOut]);
    }
}
