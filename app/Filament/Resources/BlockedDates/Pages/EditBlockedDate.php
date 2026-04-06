<?php

namespace App\Filament\Resources\BlockedDates\Pages;

use App\Filament\Actions\TypedDeleteAction;
use App\Filament\Actions\TypedForceDeleteAction;
use App\Filament\Resources\BlockedDates\BlockedDateResource;
use App\Models\BlockedDate;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditBlockedDate extends EditRecord
{
    protected static string $resource = BlockedDateResource::class;

    protected function getHeaderActions(): array
    {
        if ($this->record->trashed()) {
            return [
                RestoreAction::make(),
                TypedForceDeleteAction::make(fn (BlockedDate $record): string => $record->date?->format('Y-m-d') ?? ''),
            ];
        }

        return [
            TypedDeleteAction::make(fn (BlockedDate $record): string => $record->date?->format('Y-m-d') ?? ''),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['confirm_contacted']);

        return $data;
    }
}
