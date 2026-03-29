<?php

namespace App\Filament\Resources\BedSpecifications\Pages;

use App\Filament\Resources\BedSpecifications\BedSpecificationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBedSpecification extends EditRecord
{
    protected static string $resource = BedSpecificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
