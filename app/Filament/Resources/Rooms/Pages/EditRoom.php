<?php

namespace App\Filament\Resources\Rooms\Pages;

use App\Filament\Actions\TypedDeleteAction;
use App\Filament\Actions\TypedForceDeleteAction;
use App\Filament\Resources\Rooms\RoomResource;
use App\Models\Room;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditRoom extends EditRecord
{
    protected static string $resource = RoomResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['amenities'] = $this->record->amenities->pluck('id')->all();

        return $data;
    }

    protected function getHeaderActions(): array
    {
        if ($this->record->trashed()) {
            return [
                RestoreAction::make(),
                TypedForceDeleteAction::make(fn (Room $record): string => $record->name),
            ];
        }

        return [
            TypedDeleteAction::make(fn (Room $record): string => $record->name),
        ];
    }
}
