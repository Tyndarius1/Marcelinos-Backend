<?php

namespace App\Filament\Resources\Rooms;

use App\Filament\Resources\Concerns\ResolvesTrashedRecordRoutes;
use App\Filament\Resources\Rooms\Pages\CreateRoom;
use App\Filament\Resources\Rooms\Pages\EditRoom;
use App\Filament\Resources\Rooms\Pages\ListRooms;
use App\Filament\Resources\Rooms\Pages\ViewRoom;
use App\Filament\Resources\Rooms\RelationManagers\RoomBlockedDatesRelationManager;
use App\Filament\Resources\Rooms\Schemas\RoomForm;
use App\Filament\Resources\Rooms\Tables\RoomsTable;
use App\Models\Room;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class RoomResource extends Resource
{
    use ResolvesTrashedRecordRoutes;

    protected static ?string $model = Room::class;

    // Navigation icon in Filament sidebar
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static string|\UnitEnum|null $navigationGroup = 'Properties';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Rooms';

    // The attribute to display in the title when editing/viewing a record
    protected static ?string $recordTitleAttribute = 'name';

    // Form configuration
    public static function form(Schema $schema): Schema
    {
        return RoomForm::configure($schema);
    }

    // Table configuration
    public static function table(Table $table): Table
    {
        return RoomsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RoomBlockedDatesRelationManager::class,
        ];
    }

    // Define resource pages
    public static function getPages(): array
    {
        return [
            'index' => ListRooms::route('/'),
            'create' => CreateRoom::route('/create'),
            'edit' => EditRoom::route('/{record}/edit'),
            'view' => ViewRoom::route('/{record}'),
        ];
    }
}
