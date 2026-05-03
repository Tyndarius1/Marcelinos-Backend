<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InspectionItem extends Model
{
    public const STATUS_OK = 'ok';

    public const STATUS_DAMAGED = 'damaged';

    public const STATUS_MISSING = 'missing';

    protected $fillable = [
        'inspection_id',
        'inventory_item_id',
        'status',
        'remarks',
    ];

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(BookingInspection::class, 'inspection_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(RoomInventoryItem::class, 'inventory_item_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(InspectionItemPhoto::class, 'inspection_item_id');
    }
}
