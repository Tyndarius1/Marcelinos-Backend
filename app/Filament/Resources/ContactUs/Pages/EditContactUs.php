<?php

namespace App\Filament\Resources\ContactUs\Pages;

use App\Filament\Actions\TypedDeleteAction;
use App\Filament\Actions\TypedForceDeleteAction;
use App\Filament\Resources\ContactUs\ContactUsResource;
use App\Models\ContactUs;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;

class EditContactUs extends EditRecord
{
    protected static string $resource = ContactUsResource::class;

    public function form(Schema $schema): Schema
    {
        return ContactUsResource::form($schema);
    }

    protected function getHeaderActions(): array
    {
        if ($this->record->trashed()) {
            return [
                RestoreAction::make(),
                TypedForceDeleteAction::make(fn (ContactUs $record): string => filled($record->email) ? $record->email : $record->full_name),
            ];
        }

        return [
            TypedDeleteAction::make(fn (ContactUs $record): string => filled($record->email) ? $record->email : $record->full_name),
        ];
    }
}
