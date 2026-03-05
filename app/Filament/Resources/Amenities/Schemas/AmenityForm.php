<?php

namespace App\Filament\Resources\Amenities\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AmenityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Amenity details')
                    ->description('Add a perk or feature that you can assign to rooms and venues (e.g. Wi‑Fi, parking, air conditioning).')
                    ->icon('heroicon-o-sparkles')
                    ->schema([
                        TextInput::make('name')
                            ->label('Amenity name')
                            ->placeholder('e.g. Free Wi‑Fi, Parking, Air conditioning')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
