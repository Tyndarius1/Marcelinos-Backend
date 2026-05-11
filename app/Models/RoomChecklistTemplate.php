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

    /**
     * @return array<int>
     */
    public function applicableRoomIds(): array
    {
        $raw = $this->applicable_room_types;
        if (! is_array($raw) || $raw === []) {
            return [];
        }

        return collect($raw)
            ->map(fn ($value): int => (int) $value)
            ->filter(fn (int $value): bool => $value > 0)
            ->unique()
            ->values()
            ->all();
    }

    public function appliesToRoom(?int $roomId): bool
    {
        $allowed = $this->applicableRoomIds();

        if ($allowed === []) {
            return true;
        }

        return $roomId !== null && in_array((int) $roomId, $allowed, true);
    }

    public function appliesToRoomType(?string $roomType): bool
    {
        // Backward compatibility for legacy templates that stored room types.
        $allowed = $this->applicable_room_types;

        if (! is_array($allowed) || $allowed === []) {
            return true;
        }

        $allowedLooksLikeRoomIds = collect($allowed)
            ->contains(fn ($value): bool => is_numeric($value) && (int) $value > 0);

        if ($allowedLooksLikeRoomIds) {
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

