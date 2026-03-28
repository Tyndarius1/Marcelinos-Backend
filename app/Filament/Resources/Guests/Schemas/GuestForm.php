<?php

namespace App\Filament\Resources\Guests\Schemas;

use App\Filament\Forms\Components\PhAddressFields;
use App\Models\Guest;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class GuestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('first_name')->required(),
                TextInput::make('middle_name'),
                TextInput::make('last_name')->required(),
                TextInput::make('contact_num')->required(),
                TextInput::make('email')
                    ->required()
                    ->email()
                    ->unique(ignoreRecord: true),
                Select::make('gender')
                    ->options(Guest::genderOptions())
                    ->required(),
                Toggle::make('is_international')
                    ->label('International')
                    ->required()
                    ->default(false)
                    ->live()
                    ->afterStateUpdated(function (Set $set, $state): void {
                        if ($state) {
                            $set('ph_region_code', null);
                            $set('ph_province_code', null);
                            $set('ph_municipality_code', null);
                            $set('ph_barangay_code', null);
                            $set('region', null);
                            $set('province', null);
                            $set('municipality', null);
                            $set('barangay', null);
                        } else {
                            $set('country', 'Philippines');
                        }
                    }),
                TextInput::make('country')
                    ->default('Philippines')
                    ->required(fn (Get $get) => (bool) $get('is_international'))
                    ->visible(fn (Get $get) => (bool) $get('is_international')),
                ...PhAddressFields::make(),
            ]);
    }
}
