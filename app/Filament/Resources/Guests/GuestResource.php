<?php

namespace App\Filament\Resources\Guests;

use App\Filament\Resources\Concerns\ResolvesTrashedRecordRoutes;
use App\Filament\Resources\Guests\Pages\CreateGuest;
use App\Filament\Resources\Guests\Pages\EditGuest;
use App\Filament\Resources\Guests\Pages\ListGuests;
use App\Filament\Resources\Guests\Pages\ViewGuest;
use App\Filament\Resources\Guests\RelationManagers\ReviewsRelationManager;
use App\Filament\Resources\Guests\Schemas\GuestForm;
use App\Filament\Resources\Guests\Tables\GuestsTable;
use App\Models\Guest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class GuestResource extends Resource
{
    use ResolvesTrashedRecordRoutes;

    protected static ?string $model = Guest::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|\UnitEnum|null $navigationGroup = 'People';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Guests';

    protected static ?string $recordTitleAttribute = 'first_name';

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'first_name',
            'middle_name',
            'last_name',
            'email',
            'contact_num',
        ];
    }

    public static function getGlobalSearchResultTitle($record): string
    {
        return $record->full_name;
    }

    // Form configuration
    public static function form(Schema $schema): Schema
    {
        return GuestForm::configure($schema);
    }

    // Table configuration
    public static function table(Table $table): Table
    {
        return GuestsTable::configure($table);
    }

    // Define relations (if you want to show bookings for a guest)
    public static function getRelations(): array
    {
        return [
            ReviewsRelationManager::class,
        ];
    }

    // Define resource pages
    public static function getPages(): array
    {
        return [
            'index' => ListGuests::route('/'),
            'create' => CreateGuest::route('/create'),
            'edit' => EditGuest::route('/{record}/edit'),
            'view' => ViewGuest::route('/{record}'),
        ];
    }
}
