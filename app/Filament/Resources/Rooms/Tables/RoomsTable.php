<?php

namespace App\Filament\Resources\Rooms\Tables;

use App\Filament\Actions\TypedDeleteBulkAction;
use App\Filament\Actions\TypedForceDeleteBulkAction;
use App\Filament\Resources\Rooms\RoomResource;
use App\Models\Room;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class RoomsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordAction('view')
            ->recordUrl(fn ($record) => RoomResource::getUrl('view', ['record' => $record]))
            ->columns([
                // ✅ Featured Image
                SpatieMediaLibraryImageColumn::make('featured_image')
                    ->label('Featured')
                    ->circular()
                    ->collection('featured'),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('capacity')
                    ->numeric()
                    ->sortable(),

                ViewColumn::make('type')
                    ->view('filament.tables.columns.room-type-badge-column'),

                TextColumn::make('price')
                    ->money('PHP', true)
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->colors(Room::statusColors()),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
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
                ])
                    ->visible(fn () => Auth::user() && Auth::user()->role === 'admin'),
            ]);
    }
}
