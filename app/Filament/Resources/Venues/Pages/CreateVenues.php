<?php

namespace App\Filament\Resources\Venues\Pages;

use App\Filament\Resources\Venues\VenuesResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVenues extends CreateRecord
{
    protected static string $resource = VenuesResource::class;

    protected function afterCreate(): void
    {
        $this->record->amenities()->sync($this->form->getState()['amenities'] ?? []);
    }
}
