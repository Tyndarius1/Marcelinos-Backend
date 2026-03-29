<?php

namespace App\Filament\Resources\BedModifiers\Pages;

use App\Filament\Resources\BedModifiers\BedModifierResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewBedModifier extends ViewRecord
{
    protected static string $resource = BedModifierResource::class;

    public function getTitle(): string
    {
        return (string) $this->record?->name ?? 'View bed modifier';
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->icon('heroicon-o-pencil-square'),
        ];
    }
}
