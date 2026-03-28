<?php

namespace App\Filament\Forms\Components;

use App\Support\PsgcApi;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * Cascading PSGC-driven selects (PSGC API) + hidden string fields stored on the guest record.
 */
final class PhAddressFields
{
    /**
     * @return list<Field>
     */
    public static function make(): array
    {
        return [
            Select::make('ph_region_code')
                ->label('Region')
                ->placeholder('Select region')
                ->options(fn (): array => PsgcApi::regionOptions())
                ->searchable()
                ->preload()
                ->live()
                ->dehydrated(false)
                ->required(fn (Get $get): bool => ! (bool) $get('is_international'))
                ->visible(fn (Get $get): bool => ! (bool) $get('is_international'))
                ->afterStateUpdated(function (Set $set, ?string $state): void {
                    $set('ph_province_code', null);
                    $set('ph_municipality_code', null);
                    $set('ph_barangay_code', null);
                    if (! $state) {
                        $set('region', null);
                        $set('province', null);
                        $set('municipality', null);
                        $set('barangay', null);

                        return;
                    }
                    $set('region', PsgcApi::regionLabel($state));
                    $set('province', null);
                    $set('municipality', null);
                    $set('barangay', null);
                }),

            Select::make('ph_province_code')
                ->label('Province')
                ->placeholder('Select province')
                ->options(fn (Get $get): array => PsgcApi::provinceOptions((string) ($get('ph_region_code') ?? '')))
                ->searchable()
                ->preload()
                ->live()
                ->dehydrated(false)
                ->visible(function (Get $get): bool {
                    if ((bool) $get('is_international')) {
                        return false;
                    }

                    $region = (string) ($get('ph_region_code') ?? '');

                    return $region !== '' && ! PsgcApi::isNcr($region);
                })
                ->required(fn (Get $get): bool => ! (bool) $get('is_international') && ! PsgcApi::isNcr((string) ($get('ph_region_code') ?? '')))
                ->disabled(fn (Get $get): bool => (string) ($get('ph_region_code') ?? '') === '')
                ->afterStateUpdated(function (Set $set, ?string $state): void {
                    $set('ph_municipality_code', null);
                    $set('ph_barangay_code', null);
                    $set('province', $state ? PsgcApi::provinceLabel($state) : null);
                    $set('municipality', null);
                    $set('barangay', null);
                }),

            Select::make('ph_municipality_code')
                ->label('Municipality / City')
                ->placeholder('Select municipality or city')
                ->options(fn (Get $get): array => PsgcApi::municipalityOptions(
                    $get('ph_region_code'),
                    $get('ph_province_code'),
                ))
                ->searchable()
                ->preload()
                ->live()
                ->dehydrated(false)
                ->required(fn (Get $get): bool => ! (bool) $get('is_international'))
                ->visible(fn (Get $get): bool => ! (bool) $get('is_international'))
                ->disabled(function (Get $get): bool {
                    $region = (string) ($get('ph_region_code') ?? '');
                    if ($region === '') {
                        return true;
                    }
                    if (PsgcApi::isNcr($region)) {
                        return false;
                    }

                    return (string) ($get('ph_province_code') ?? '') === '';
                })
                ->afterStateUpdated(function (Set $set, ?string $state): void {
                    $set('ph_barangay_code', null);
                    $set('municipality', $state ? PsgcApi::municipalityLabel($state) : null);
                    $set('barangay', null);
                }),

            Select::make('ph_barangay_code')
                ->label('Barangay')
                ->placeholder('Select barangay')
                ->options(fn (Get $get): array => PsgcApi::barangayOptions((string) ($get('ph_municipality_code') ?? '')))
                ->searchable()
                ->preload()
                ->dehydrated(false)
                ->required(fn (Get $get): bool => ! (bool) $get('is_international'))
                ->visible(fn (Get $get): bool => ! (bool) $get('is_international'))
                ->disabled(fn (Get $get): bool => (string) ($get('ph_municipality_code') ?? '') === '')
                ->afterStateUpdated(function (Set $set, ?string $state): void {
                    $set('barangay', $state ? PsgcApi::barangayLabel($state) : null);
                }),

            Hidden::make('region'),
            Hidden::make('province'),
            Hidden::make('municipality'),
            Hidden::make('barangay'),
        ];
    }
}
