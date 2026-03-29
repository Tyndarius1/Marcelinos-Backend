<?php

namespace App\Filament\Resources\BedModifiers;

use App\Filament\Resources\BedModifiers\Pages\CreateBedModifier;
use App\Filament\Resources\BedModifiers\Pages\EditBedModifier;
use App\Filament\Resources\BedModifiers\Pages\ListBedModifiers;
use App\Filament\Resources\BedModifiers\Pages\ViewBedModifier;
use App\Filament\Resources\BedModifiers\Schemas\BedModifierForm;
use App\Filament\Resources\BedModifiers\Tables\BedModifiersTable;
use App\Models\BedModifier;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BedModifierResource extends Resource
{
    protected static ?string $model = BedModifier::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-squares-plus';

    protected static string|\UnitEnum|null $navigationGroup = 'Bed specifications & modifiers';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Modifiers';

    protected static ?string $modelLabel = 'Bed modifier';

    protected static ?string $pluralModelLabel = 'Bed modifiers';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationBadge(): ?string
    {
        return (string) BedModifier::count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }

    public static function form(Schema $schema): Schema
    {
        return BedModifierForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BedModifiersTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('rooms');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBedModifiers::route('/'),
            'create' => CreateBedModifier::route('/create'),
            'edit' => EditBedModifier::route('/{record}/edit'),
            'view' => ViewBedModifier::route('/{record}'),
        ];
    }
}
