<?php

namespace App\Filament\Resources\DamagesAndLosses\Pages;

use App\Filament\Resources\DamagesAndLosses\DamagesAndLossesResource;
use Filament\Resources\Pages\ListRecords;

class ListDamagesAndLosses extends ListRecords
{
    protected static string $resource = DamagesAndLossesResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
