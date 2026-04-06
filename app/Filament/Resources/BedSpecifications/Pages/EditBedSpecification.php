<?php

namespace App\Filament\Resources\BedSpecifications\Pages;

use App\Filament\Actions\TypedDeleteAction;
use App\Filament\Actions\TypedForceDeleteAction;
use App\Filament\Resources\BedSpecifications\BedSpecificationResource;
use App\Models\BedSpecification;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditBedSpecification extends EditRecord
{
    protected static string $resource = BedSpecificationResource::class;

    protected function getHeaderActions(): array
    {
        if ($this->record->trashed()) {
            return [
                RestoreAction::make(),
                TypedForceDeleteAction::make(fn (BedSpecification $record): string => $record->specification),
            ];
        }

        return [
            TypedDeleteAction::make(fn (BedSpecification $record): string => $record->specification),
        ];
    }
}
