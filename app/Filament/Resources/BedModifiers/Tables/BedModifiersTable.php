<?php

namespace App\Filament\Resources\BedModifiers\Tables;

use App\Filament\Resources\BedModifiers\BedModifierResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BedModifiersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordAction('view')
            ->recordUrl(fn ($record) => BedModifierResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('name')
                    ->label('Modifier')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-squares-plus')
                    ->iconColor('primary')
                    ->weight('medium'),

                TextColumn::make('rooms_count')
                    ->label('Rooms')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => (string) ((int) ($state ?? 0)))
                    ->color(fn ($state): string => ((int) ($state ?? 0)) > 0 ? 'info' : 'gray')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
