<?php

namespace App\Filament\Resources\RoomChecklistTemplates\Pages;

use App\Filament\Actions\TypedDeleteAction;
use App\Filament\Actions\TypedForceDeleteAction;
use App\Filament\Resources\RoomChecklistTemplates\RoomChecklistTemplateResource;
use App\Models\RoomChecklistTemplate;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditRoomChecklistTemplate extends EditRecord
{
    protected static string $resource = RoomChecklistTemplateResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $types = is_array($data['applicable_room_types'] ?? null)
            ? array_values(array_filter(
                array_map(fn ($value): string => strtolower(trim((string) $value)), $data['applicable_room_types']),
                fn (string $value): bool => $value !== ''
            ))
            : [];

        $data['applicable_room_types'] = array_values(array_unique($types));

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $types = is_array($data['applicable_room_types'] ?? null)
            ? array_values(array_filter(
                array_map(fn ($value): string => strtolower(trim((string) $value)), $data['applicable_room_types']),
                fn (string $value): bool => $value !== ''
            ))
            : [];

        $data['applicable_room_types'] = $types === [] ? null : array_values(array_unique($types));

        return $data;
    }

    protected function getHeaderActions(): array
    {
        if ($this->record->trashed()) {
            return [
                RestoreAction::make(),
                TypedForceDeleteAction::make(fn (RoomChecklistTemplate $record): string => $record->label),
            ];
        }

        return [
            TypedDeleteAction::make(fn (RoomChecklistTemplate $record): string => $record->label),
        ];
    }
}
