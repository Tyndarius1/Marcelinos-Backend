<?php

namespace App\Filament\Resources\Venues\Pages;

use App\Filament\Actions\TypedDeleteAction;
use App\Filament\Actions\TypedForceDeleteAction;
use App\Filament\Resources\Venues\VenuesResource;
use App\Models\Venue;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditVenues extends EditRecord
{
    protected static string $resource = VenuesResource::class;

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
                TypedForceDeleteAction::make(fn (Venue $record): string => $record->name),
            ];
        }

        return [
            TypedDeleteAction::make(fn (Venue $record): string => $record->name),
        ];
    }
}
