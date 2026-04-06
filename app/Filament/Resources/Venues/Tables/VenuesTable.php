<?php

namespace App\Filament\Resources\Venues\Tables;

use App\Filament\Actions\TypedDeleteBulkAction;
use App\Filament\Actions\TypedForceDeleteBulkAction;
use App\Filament\Resources\Venues\VenuesResource;
use App\Models\Venue;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class VenuesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordAction('view')
            ->recordUrl(fn ($record) => VenuesResource::getUrl('view', ['record' => $record]))
            ->columns([
                // ✅ Featured Image (Uses Spatie Media Library)
                SpatieMediaLibraryImageColumn::make('featured_image')
                    ->label('Featured')
                    ->collection('featured')
                    ->circular(),

                TextColumn::make('name')
                    ->label('Venue Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('capacity')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('wedding_price')
                    ->label('Wedding')
                    ->money('PHP', true)
                    ->sortable(),

                TextColumn::make('birthday_price')
                    ->label('Birthday')
                    ->money('PHP', true)
                    ->sortable(),

                TextColumn::make('meeting_staff_price')
                    ->label('Meeting/Seminar')
                    ->money('PHP', true)
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->colors(Venue::statusColors())
                    ->formatStateUsing(fn (string $state): string => Venue::statusOptions()[$state] ?? ucfirst($state)),

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
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    TypedDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    TypedForceDeleteBulkAction::make(),
                ])
                    ->visible(fn () => Auth::user() && Auth::user()->role === 'admin'),
            ]);
    }
}
