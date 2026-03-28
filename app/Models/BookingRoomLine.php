<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Guest-requested room type + bed-spec line (matches billing statement).
 */
class BookingRoomLine extends Model
{
    protected $fillable = [
        'booking_id',
        'room_type',
        'inventory_group_key',
        'quantity',
        'unit_price_per_night',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price_per_night' => 'decimal:2',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Same label style as the guest billing statement: "Standard - 1 Double Bed".
     */
    public function displayLabel(): string
    {
        $type = Room::typeOptions()[$this->room_type] ?? ucfirst((string) $this->room_type);
        $key = (string) $this->inventory_group_key;
        $detail = str_starts_with($key, 'spec:')
            ? substr($key, 5)
            : (str_starts_with($key, 'desc:') ? substr($key, 5) : $key);

        return $type.' - '.$detail;
    }
}
