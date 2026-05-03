<?php

namespace App\Filament\Resources\Rooms\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class RoomInventoryItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'roomInventoryItems';

    protected static ?string $title = 'Room inventory (checkout inspection)';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->roomInventoryItems()->count();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('item_name')
                ->label(__('Item name'))
                ->required()
                ->maxLength(255),
            TextInput::make('quantity')
                ->label(__('Quantity'))
                ->numeric()
                ->minValue(1)
                ->default(1)
                ->required(),
            TextInput::make('price')
                ->label(__('Price'))
                ->numeric()
                ->prefix('₱')
                ->minValue(0)
                ->default(0)
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('item_name')
            ->columns([
                TextColumn::make('item_name')
                    ->label(__('Item'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('quantity')
                    ->label(__('Qty'))
                    ->sortable(),
                TextColumn::make('price')
                    ->label(__('Price'))
                    ->money('PHP')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
