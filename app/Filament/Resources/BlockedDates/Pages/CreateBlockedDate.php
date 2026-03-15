<?php

namespace App\Filament\Resources\BlockedDates\Pages;

use App\Filament\Resources\BlockedDates\BlockedDateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBlockedDate extends CreateRecord
{
    protected static string $resource = BlockedDateResource::class;

    /**
     * Remove form-only field so it is not sent to the model (no DB column).
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['confirm_contacted']);
        return $data;
    }
}
