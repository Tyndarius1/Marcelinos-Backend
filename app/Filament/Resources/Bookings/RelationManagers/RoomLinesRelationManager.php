<?php

namespace App\Filament\Resources\Bookings\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Guest-selected room type + bed-spec lines (physical rooms assigned separately).
 */
class RoomLinesRelationManager extends RelationManager
{
    protected static string $relationship = 'roomLines';

    protected static ?string $title = 'Requested room types';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('room_type')
                    ->label('Type')
                    ->badge()
                    ->sortable(),
                TextColumn::make('inventory_group_key')
                    ->label('Bed / layout group')
                    ->wrap()
                    ->limit(80),
                TextColumn::make('quantity')
                    ->label('Qty')
                    ->sortable(),
                TextColumn::make('unit_price_per_night')
                    ->label('Rate / night')
                    ->money('PHP')
                    ->sortable(),
            ])
            ->paginated(false);
    }
}
