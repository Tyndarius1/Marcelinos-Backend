<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomChecklistItem extends Model
{
    const STATUS_GOOD = 'good';
    const STATUS_BROKEN = 'broken';
    const STATUS_MISSING = 'missing';
    const STATUS_NOT_APPLICABLE = 'not_applicable';

    protected $fillable = [
        'room_checklist_id',
        'label',
        'charge',
        'quantity',
        'status',
        'notes',
        'evidence_photo_path',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function roomChecklist(): BelongsTo
    {
        return $this->belongsTo(RoomChecklist::class);
    }
}

