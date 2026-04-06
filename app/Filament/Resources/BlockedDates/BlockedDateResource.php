<?php

namespace App\Filament\Resources\BlockedDates;

use App\Filament\Resources\BlockedDates\Pages\CreateBlockedDate;
use App\Filament\Resources\BlockedDates\Pages\EditBlockedDate;
use App\Filament\Resources\BlockedDates\Pages\ListBlockedDates;
use App\Filament\Resources\BlockedDates\Schemas\BlockedDateForm;
use App\Filament\Resources\BlockedDates\Tables\BlockedDatesTable;
use App\Filament\Resources\Concerns\ResolvesTrashedRecordRoutes;
use App\Models\BlockedDate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BlockedDateResource extends Resource
{
    use ResolvesTrashedRecordRoutes;

    protected static ?string $model = BlockedDate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Blocked Dates';

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'date';

    public static function form(Schema $schema): Schema
    {
        return BlockedDateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BlockedDatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBlockedDates::route('/'),
            'create' => CreateBlockedDate::route('/create'),
            'edit' => EditBlockedDate::route('/{record}/edit'),
        ];
    }
}
