<?php

namespace App\Filament\Resources\RoomChecklistTemplates\Pages;

use App\Filament\Resources\RoomChecklistTemplates\RoomChecklistTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRoomChecklistTemplate extends CreateRecord
{
    protected static string $resource = RoomChecklistTemplateResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $roomIds = is_array($data['applicable_room_types'] ?? null)
            ? array_values(array_filter(
                array_map(fn ($value): int => (int) $value, $data['applicable_room_types']),
                fn (int $value): bool => $value > 0
            ))
            : [];

        $data['applicable_room_types'] = $roomIds === [] ? null : array_values(array_unique($roomIds));

        return $data;
    }
}
