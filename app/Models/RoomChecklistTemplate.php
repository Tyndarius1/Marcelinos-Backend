<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RoomChecklistTemplate extends Model
{
    use SoftDeletes;

    protected $table = 'room_checklist_item_templates';

    protected $fillable = [
        'label',
        'default_charge',
        'applicable_room_types',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'applicable_room_types' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function appliesToRoomType(?string $roomType): bool
    {
        $allowed = $this->applicable_room_types;

        if (! is_array($allowed) || $allowed === []) {
            return true;
        }

        $needle = strtolower(trim((string) $roomType));
        if ($needle === '') {
            return false;
        }

        return collect($allowed)
            ->map(fn ($value): string => strtolower(trim((string) $value)))
            ->contains($needle);
    }
}

