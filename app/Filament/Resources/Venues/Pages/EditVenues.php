<?php

namespace App\Filament\Resources\Venues\Pages;

use App\Filament\Resources\Venues\VenuesResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVenues extends EditRecord
{
    protected static string $resource = VenuesResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['amenities'] = $this->record->amenities->pluck('id')->all();
        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->amenities()->sync($this->form->getState()['amenities'] ?? []);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
