<?php

namespace App\Filament\Resources\Guests\Tables;

use App\Models\Guest;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class GuestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordAction('view')
            ->recordUrl(fn($record) => \App\Filament\Resources\Guests\GuestResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('full_name')
                    ->label('Name')
                    ->formatStateUsing(fn($record) => $record->full_name)
                    ->searchable(['first_name', 'middle_name', 'last_name'])
                    ->sortable(),
                TextColumn::make('contact_num')->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),

                // ✅ Gender Badge
                TextColumn::make('gender')
                    ->badge()
                    ->colors([
                        'primary' => Guest::GENDER_MALE,
                        'warning' => Guest::GENDER_FEMALE,
                        'secondary' => Guest::GENDER_OTHER,
                    ])
                    ->formatStateUsing(fn(string $state): string => Guest::genderOptions()[$state] ?? ucfirst($state)),

                // ✅ International Guest Icon
                IconColumn::make('is_international')
                    ->boolean()
                    ->label('International'),

                TextColumn::make('country')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('region')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('province')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('municipality')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('barangay')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),


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
                SelectFilter::make('gender')
                    ->options(Guest::genderOptions()),
                TernaryFilter::make('is_international')
                    ->label('International')
                    ->trueLabel('International')
                    ->falseLabel('Local'),
            ])
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
