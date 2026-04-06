<?php

namespace App\Filament\Resources\Amenities\Pages;

use App\Filament\Actions\TypedDeleteAction;
use App\Filament\Actions\TypedForceDeleteAction;
use App\Filament\Resources\Amenities\AmenityResource;
use App\Models\Amenity;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditAmenity extends EditRecord
{
    protected static string $resource = AmenityResource::class;

    protected function getHeaderActions(): array
    {
        if ($this->record->trashed()) {
            return [
                RestoreAction::make(),
                TypedForceDeleteAction::make(fn (Amenity $record): string => $record->name),
            ];
        }

        return [
            TypedDeleteAction::make(fn (Amenity $record): string => $record->name),
        ];
    }
}
