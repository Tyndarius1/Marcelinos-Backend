<?php

namespace App\Filament\Resources\BedModifiers\Pages;

use App\Filament\Resources\BedModifiers\BedModifierResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBedModifiers extends ListRecords
{
    protected static string $resource = BedModifierResource::class;

    protected static ?string $title = 'Bed modifiers';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New bed modifier'),
        ];
    }
}
