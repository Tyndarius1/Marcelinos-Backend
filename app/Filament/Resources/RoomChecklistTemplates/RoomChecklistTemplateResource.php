<?php

namespace App\Filament\Resources\RoomChecklistTemplates;

use App\Filament\Resources\Concerns\ResolvesTrashedRecordRoutes;
use App\Filament\Resources\RoomChecklistTemplates\Pages\CreateRoomChecklistTemplate;
use App\Filament\Resources\RoomChecklistTemplates\Pages\EditRoomChecklistTemplate;
use App\Filament\Resources\RoomChecklistTemplates\Pages\ListRoomChecklistTemplates;
use App\Filament\Resources\RoomChecklistTemplates\Pages\ViewRoomChecklistTemplate;
use App\Filament\Resources\RoomChecklistTemplates\Schemas\RoomChecklistTemplateForm;
use App\Filament\Resources\RoomChecklistTemplates\Tables\RoomChecklistTemplatesTable;
use App\Models\RoomChecklistTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class RoomChecklistTemplateResource extends Resource
{
    use ResolvesTrashedRecordRoutes;

    protected static ?string $model = RoomChecklistTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Room Inventory Checklist';

    protected static ?string $recordTitleAttribute = 'label';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasPrivilege('manage_bookings')
            || $user->hasPrivilege('manage_rooms');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return RoomChecklistTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RoomChecklistTemplatesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoomChecklistTemplates::route('/'),
            'create' => CreateRoomChecklistTemplate::route('/create'),
            'edit' => EditRoomChecklistTemplate::route('/{record}/edit'),
            'view' => ViewRoomChecklistTemplate::route('/{record}'),
        ];
    }
}
