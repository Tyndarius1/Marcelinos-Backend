<?php

namespace App\Filament\Resources\Guests\Pages;

use App\Filament\Actions\TypedDeleteAction;
use App\Filament\Actions\TypedForceDeleteAction;
use App\Filament\Resources\Guests\GuestResource;
use App\Models\Guest;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditGuest extends EditRecord
{
    protected static string $resource = GuestResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        foreach (['ph_region_code', 'ph_province_code', 'ph_municipality_code', 'ph_barangay_code'] as $key) {
            unset($data[$key]);
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        if ($this->record->trashed()) {
            return [
                RestoreAction::make(),
                TypedForceDeleteAction::make(fn (Guest $record): string => filled($record->email) ? $record->email : $record->full_name),
            ];
        }

        return [
            TypedDeleteAction::make(fn (Guest $record): string => filled($record->email) ? $record->email : $record->full_name),
        ];
    }
}
