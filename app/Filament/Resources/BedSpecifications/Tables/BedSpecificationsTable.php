<?php

namespace App\Filament\Resources\BedSpecifications\Tables;

use App\Filament\Actions\TypedDeleteBulkAction;
use App\Filament\Actions\TypedForceDeleteBulkAction;
use App\Filament\Resources\BedSpecifications\BedSpecificationResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class BedSpecificationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordAction('view')
            ->recordUrl(fn ($record) => BedSpecificationResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('specification')
                    ->label('Specification')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-moon')
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
            ->defaultSort('specification')
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    TypedDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    TypedForceDeleteBulkAction::make(),
                ]),
            ]);
    }
}
