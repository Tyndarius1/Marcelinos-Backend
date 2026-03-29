<?php

namespace App\Filament\Resources\BedSpecifications\Pages;

use App\Filament\Resources\BedSpecifications\BedSpecificationResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewBedSpecification extends ViewRecord
{
    protected static string $resource = BedSpecificationResource::class;

    public function getTitle(): string
    {
        return (string) $this->record?->specification ?? 'View bed specification';
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->icon('heroicon-o-pencil-square'),
        ];
    }
}
