<?php

namespace App\Filament\Resources\Amenities\Tables;

use App\Filament\Actions\TypedDeleteBulkAction;
use App\Filament\Actions\TypedForceDeleteBulkAction;
use App\Filament\Resources\Amenities\AmenityResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class AmenitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordAction('view')
            ->recordUrl(fn ($record) => AmenityResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('name')
                    ->label('Amenity')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-sparkles')
                    ->iconColor('primary')
                    ->weight('medium'),

                TextColumn::make('rooms_count')
                    ->label('Rooms')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => (string) ((int) ($state ?? 0)))
                    ->color(fn ($state): string => ((int) ($state ?? 0)) > 0 ? 'info' : 'gray')
                    ->sortable(),

                TextColumn::make('venues_count')
                    ->label('Venues')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => (string) ((int) ($state ?? 0)))
                    ->color(fn ($state): string => ((int) ($state ?? 0)) > 0 ? 'success' : 'gray')
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
