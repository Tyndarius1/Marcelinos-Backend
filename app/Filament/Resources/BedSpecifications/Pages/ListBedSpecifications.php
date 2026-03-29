<?php

namespace App\Filament\Resources\BedSpecifications\Pages;

use App\Filament\Resources\BedSpecifications\BedSpecificationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBedSpecifications extends ListRecords
{
    protected static string $resource = BedSpecificationResource::class;

    protected static ?string $title = 'Bed specifications';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New bed specification'),
        ];
    }
}
