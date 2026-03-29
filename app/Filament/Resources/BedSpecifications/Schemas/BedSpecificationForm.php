<?php

namespace App\Filament\Resources\BedSpecifications\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BedSpecificationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Bed specification')
                    ->description('Define a label you can assign to rooms (e.g. 1 Queen Bed, 2 Single Beds).')
                    ->icon('heroicon-o-moon')
                    ->schema([
                        TextInput::make('specification')
                            ->label('Specification')
                            ->placeholder('e.g. 1 Double Bed, 2 Single Beds')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
