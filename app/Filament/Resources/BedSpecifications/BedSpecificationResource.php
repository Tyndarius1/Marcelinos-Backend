<?php

namespace App\Filament\Resources\BedSpecifications;

use App\Filament\Resources\BedSpecifications\Pages\CreateBedSpecification;
use App\Filament\Resources\BedSpecifications\Pages\EditBedSpecification;
use App\Filament\Resources\BedSpecifications\Pages\ListBedSpecifications;
use App\Filament\Resources\BedSpecifications\Pages\ViewBedSpecification;
use App\Filament\Resources\BedSpecifications\Schemas\BedSpecificationForm;
use App\Filament\Resources\BedSpecifications\Tables\BedSpecificationsTable;
use App\Models\BedSpecification;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BedSpecificationResource extends Resource
{
    protected static ?string $model = BedSpecification::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-moon';

    protected static string|\UnitEnum|null $navigationGroup = 'Bed specifications & modifiers';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Specifications';

    protected static ?string $modelLabel = 'Bed specification';

    protected static ?string $pluralModelLabel = 'Bed specifications';

    protected static ?string $recordTitleAttribute = 'specification';

    public static function getNavigationBadge(): ?string
    {
        return (string) BedSpecification::count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }

    public static function form(Schema $schema): Schema
    {
        return BedSpecificationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BedSpecificationsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('rooms');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBedSpecifications::route('/'),
            'create' => CreateBedSpecification::route('/create'),
            'edit' => EditBedSpecification::route('/{record}/edit'),
            'view' => ViewBedSpecification::route('/{record}'),
        ];
    }
}
