<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingAssignmentAudit extends Model
{
    protected $table = 'booking_assignment_audits';

    protected $fillable = [
        'booking_id',
        'user_id',
        'previous_rooms',
        'new_rooms',
        'reason',
    ];

    protected $casts = [
        'previous_rooms' => 'array',
        'new_rooms' => 'array',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
