<?php

namespace App\Filament\Resources\Venues;

use App\Filament\Resources\Concerns\ResolvesTrashedRecordRoutes;
use App\Filament\Resources\Venues\Pages\CreateVenues;
use App\Filament\Resources\Venues\Pages\EditVenues;
use App\Filament\Resources\Venues\Pages\ListVenues;
use App\Filament\Resources\Venues\Pages\ViewVenues;
use App\Filament\Resources\Venues\RelationManagers\VenueBlockedDatesRelationManager;
use App\Filament\Resources\Venues\Schemas\VenuesForm;
use App\Filament\Resources\Venues\Tables\VenuesTable;
use App\Models\Venue;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class VenuesResource extends Resource
{
    use ResolvesTrashedRecordRoutes;

    protected static ?string $model = Venue::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static string|\UnitEnum|null $navigationGroup = 'Properties';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Venues';

    public static function form(Schema $schema): Schema
    {
        return VenuesForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VenuesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            VenueBlockedDatesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVenues::route('/'),
            'create' => CreateVenues::route('/create'),
            'edit' => EditVenues::route('/{record}/edit'),
            'view' => ViewVenues::route('/{record}'),
        ];
    }
}
