<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class InspectionItemPhoto extends Model
{
    protected $fillable = [
        'inspection_item_id',
        'file_path',
    ];

    public function inspectionItem(): BelongsTo
    {
        return $this->belongsTo(InspectionItem::class, 'inspection_item_id');
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }
}
