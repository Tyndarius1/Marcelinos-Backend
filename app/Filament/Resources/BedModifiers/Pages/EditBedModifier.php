<?php

namespace App\Filament\Resources\BedModifiers\Pages;

use App\Filament\Resources\BedModifiers\BedModifierResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBedModifier extends EditRecord
{
    protected static string $resource = BedModifierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
