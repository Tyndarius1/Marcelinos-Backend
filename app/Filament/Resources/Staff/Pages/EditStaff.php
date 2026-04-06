<?php

namespace App\Filament\Resources\Staff\Pages;

use App\Filament\Actions\TypedDeleteAction;
use App\Filament\Actions\TypedForceDeleteAction;
use App\Filament\Resources\Staff\StaffResource;
use App\Models\User;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditStaff extends EditRecord
{
    protected static string $resource = StaffResource::class;

    protected function getHeaderActions(): array
    {
        if ($this->record->trashed()) {
            return [
                RestoreAction::make(),
                TypedForceDeleteAction::make(fn (User $record): string => $record->email),
            ];
        }

        return [
            TypedDeleteAction::make(fn (User $record): string => $record->email),
        ];
    }
}
