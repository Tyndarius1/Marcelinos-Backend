<?php

namespace App\Filament\Resources\BedModifiers\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BedModifierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Bed modifier')
                    ->description('Optional add-on shown with bed specs (e.g. w/Living Room, w/Balcony).')
                    ->icon('heroicon-o-squares-plus')
                    ->schema([
                        TextInput::make('name')
                            ->label('Name')
                            ->placeholder('e.g. w/Living Room, w/Balcony')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
