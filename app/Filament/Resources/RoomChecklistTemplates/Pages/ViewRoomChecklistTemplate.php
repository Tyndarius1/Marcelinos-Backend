<?php

namespace App\Filament\Resources\RoomChecklistTemplates\Pages;

use App\Filament\Resources\RoomChecklistTemplates\RoomChecklistTemplateResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRoomChecklistTemplate extends ViewRecord
{
    protected static string $resource = RoomChecklistTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
