<?php

namespace App\Filament\Resources\RoomChecklistTemplates\Pages;

use App\Filament\Resources\RoomChecklistTemplates\RoomChecklistTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRoomChecklistTemplates extends ListRecords
{
    protected static string $resource = RoomChecklistTemplateResource::class;

    protected static ?string $title = 'Checklist';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New checklist item template')
                ->slideOver()
                ->modalHeading('New checklist item template')
                ->modalSubmitActionLabel('Create template'),
        ];
    }
}
