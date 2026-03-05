<?php

namespace App\Filament\Resources\Amenities\Pages;

use App\Filament\Resources\Amenities\AmenityResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAmenity extends ViewRecord
{
    protected static string $resource = AmenityResource::class;

    public function getTitle(): string
    {
        return (string) $this->record?->name ?? 'View amenity';
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->icon('heroicon-o-pencil-square'),
        ];
    }
}
